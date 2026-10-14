<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierPrice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $rows = Supplier::query()
            ->withCount([
                'purchaseOrders as open_po_count' => fn ($q) => $q->whereIn('status', ['draft', 'ordered', 'partial']),
            ])
            ->withSum(['purchaseOrders as open_po_value' => fn ($q) => $q->whereIn('status', ['ordered', 'partial'])], 'total')
            ->when(trim((string) $request->input('q')), fn ($q, $s) => $q->where(fn ($w) => $w->where('code', 'like', "%$s%")->orWhere('name', 'like', "%$s%")
                ->orWhere('contact_person', 'like', "%$s%")->orWhere('phone', 'like', "%$s%")))
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->input('terms'), fn ($q, $v) => $q->where('payment_terms', $v))
            ->orderBy('name')
            ->paginate(25)->withQueryString();

        return view('suppliers.index', [
            'rows' => $rows,
            'itemCounts' => SupplierPrice::selectRaw('supplier_id, COUNT(DISTINCT item_id) as n')->groupBy('supplier_id')->pluck('n', 'supplier_id'),
            'terms' => Supplier::whereNotNull('payment_terms')->distinct()->orderBy('payment_terms')->pluck('payment_terms'),
            'statusCounts' => Supplier::selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function show(Supplier $supplier)
    {
        $prices = $supplier->prices()->with('item')->orderByDesc('effective_date')->orderByDesc('id')->get();
        // Latest unit price per stock item ("Latest unit price by supplier, per stock item").
        $latest = $prices->groupBy('item_id')->map(function ($g) {
            $now = $g->first();
            $prev = $g->skip(1)->first();

            return ['price' => $now, 'previous' => $prev, 'change' => $prev && (float) $prev->unit_price > 0 ? ((float) $now->unit_price - (float) $prev->unit_price) / (float) $prev->unit_price * 100 : null];
        })->sortBy(fn ($r) => $r['price']->item->name)->values();

        return view('suppliers.show', [
            's' => $supplier,
            'latest' => $latest,
            'prices' => $prices,
            'orders' => $supplier->purchaseOrders()->with('warehouse')->latest('order_date')->latest('id')->get(),
            'items' => Item::where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name', 'uom']),
            'received' => StockMovement::where('supplier_id', $supplier->id)->where('type', 'in')->selectRaw('COALESCE(SUM(qty * unit_cost),0) v')->value('v'),
        ]);
    }

    public function create()
    {
        return view('suppliers.form', ['s' => new Supplier(['status' => 'active', 'code' => $this->nextCode()])]);
    }

    public function store(Request $request)
    {
        $s = Supplier::create($this->validated($request));

        return redirect()->route('suppliers.show', $s)->with('ok', __('Supplier :code created.', ['code' => $s->code]));
    }

    public function edit(Supplier $supplier)
    {
        return view('suppliers.form', ['s' => $supplier]);
    }

    public function update(Request $request, Supplier $supplier)
    {
        $supplier->update($this->validated($request, $supplier));

        return redirect()->route('suppliers.show', $supplier)->with('ok', __('Supplier updated.'));
    }

    public function destroy(Supplier $supplier)
    {
        if ($supplier->purchaseOrders()->exists() || StockMovement::where('supplier_id', $supplier->id)->exists()) {
            return back()->with('error', __('This supplier has purchase orders or receipts — set the status to Inactive instead.'));
        }
        $supplier->prices()->delete();
        $supplier->delete();

        return redirect()->route('suppliers.index')->with('ok', __('Supplier :code deleted.', ['code' => $supplier->code]));
    }

    public function addPrice(Request $request, Supplier $supplier)
    {
        $v = Validator::make($request->all(), [
            'item_id' => 'required|exists:items,id',
            'unit_price' => 'required|numeric|min:0',
            'effective_date' => 'required|date',
        ]);
        if ($v->fails()) {
            return back()->withErrors($v)->withInput()->with('reopen', 'dlg-price');
        }
        $p = $supplier->prices()->create($v->validated());

        return back()->with('ok', __('Price :price recorded for :item.', ['price' => money($p->unit_price), 'item' => $p->item->name]));
    }

    private function validated(Request $request, ?Supplier $supplier = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', Rule::unique('suppliers', 'code')->ignore($supplier?->id)],
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:32',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:1000',
            'payment_terms' => 'nullable|string|max:32',
            'status' => 'required|in:active,inactive',
        ]);
        $data['code'] = strtoupper($data['code']);

        return $data;
    }

    private function nextCode(): string
    {
        $last = Supplier::withTrashed()->where('code', 'like', 'SUP-%')->orderByDesc('code')->value('code');

        return sprintf('SUP-%03d', $last ? ((int) substr($last, 4)) + 1 : 1);
    }
}
