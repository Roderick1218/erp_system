<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class ItemController extends Controller
{
    public function index(Request $request)
    {
        $warehouses = Warehouse::orderBy('code')->get();
        $q = $this->filtered($request);
        $all = (clone $q)->get();
        $low = $all->filter(fn (Item $i) => $i->is_low_stock && $i->on_hand > 0);
        $out = $all->filter(fn (Item $i) => $i->on_hand <= 0);

        // Low/out filters depend on the summed balance, so they're applied on the collection.
        $stock = $request->input('stock');
        $filtered = match ($stock) {
            'low' => $all->filter(fn (Item $i) => $i->reorder_level > 0 && $i->on_hand <= (float) $i->reorder_level),
            'out' => $out,
            default => $all,
        };
        $page = max(1, (int) $request->input('page', 1));
        $rows = new LengthAwarePaginator($filtered->forPage($page, 25)->values(), $filtered->count(), 25, $page, ['path' => $request->url(), 'query' => $request->query()]);

        return view('items.index', [
            'rows' => $rows,
            'warehouses' => $warehouses,
            'categories' => Item::whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'summary' => [
                'count' => $all->count(),
                'value' => $all->sum(fn (Item $i) => $i->on_hand * (float) $i->unit_cost),
                'low' => $low->count(),
                'out' => $out->count(),
            ],
            'filteredValue' => $filtered->sum(fn (Item $i) => $i->on_hand * (float) $i->unit_cost),
            'cards' => [
                'warehouses' => $warehouses->count(),
                'movements' => StockMovement::where('moved_at', '>=', now()->subDays(30))->count(),
                'suppliers' => Supplier::where('status', 'active')->count(),
                'openPo' => PurchaseOrder::whereIn('status', ['draft', 'ordered', 'partial'])->count(),
            ],
        ] + StockController::lookups());
    }

    public function show(Item $item)
    {
        $item->load(['balances.warehouse']);
        $movements = $item->movements()->with(['warehouse', 'project', 'supplier', 'user'])->orderByDesc('moved_at')->orderByDesc('id')->paginate(20);
        $prices = $item->supplierPrices()->with('supplier')->orderByDesc('effective_date')->orderByDesc('id')->get();

        return view('items.show', [
            'item' => $item,
            'movements' => $movements,
            'prices' => $prices,
            'latestPrices' => $prices->unique('supplier_id')->sortBy('unit_price')->values(),
            'issued30' => abs((float) $item->movements()->where('type', 'project')->where('moved_at', '>=', now()->subDays(30))->sum('qty')),
        ] + StockController::lookups());
    }

    public function create()
    {
        return view('items.form', ['item' => new Item(['uom' => 'PCS', 'is_active' => true]), ...$this->lookups()]);
    }

    public function store(Request $request)
    {
        $item = Item::create($this->validated($request));

        return redirect()->route('items.show', $item)->with('ok', __('Item :sku created. Use “Stock in” to record the opening quantity.', ['sku' => $item->sku]));
    }

    public function edit(Item $item)
    {
        return view('items.form', ['item' => $item, ...$this->lookups()]);
    }

    public function update(Request $request, Item $item)
    {
        $item->update($this->validated($request, $item));

        return redirect()->route('items.show', $item)->with('ok', __('Item updated.'));
    }

    public function destroy(Item $item)
    {
        if ($item->movements()->exists()) {
            return back()->with('error', __('This item has stock history — mark it inactive instead of deleting.'));
        }
        StockBalance::where('item_id', $item->id)->where('qty', 0)->delete();
        $item->delete();

        return redirect()->route('items.index')->with('ok', __('Item :sku deleted.', ['sku' => $item->sku]));
    }

    // ------------------------------------------------------------------

    private function filtered(Request $request): Builder
    {
        $wh = $request->input('warehouse');

        return Item::query()
            ->with(['balances' => fn ($b) => $b->with('warehouse')])
            ->when($wh, fn ($q) => $q->withSum(['balances' => fn ($b) => $b->where('warehouse_id', $wh)], 'qty'), fn ($q) => $q->withSum('balances', 'qty'))
            ->when(trim((string) $request->input('q')), fn ($q, $s) => $q->where(fn ($w) => $w->where('sku', 'like', "%$s%")->orWhere('name', 'like', "%$s%")))
            ->when($request->input('category'), fn ($q, $v) => $q->where('category', $v))
            ->when($request->input('status') === 'inactive', fn ($q) => $q->where('is_active', false), fn ($q) => $q->when($request->input('status') !== 'all', fn ($w) => $w->where('is_active', true)))
            ->orderBy($request->input('sort') === 'sku' ? 'sku' : 'name');
    }

    private function validated(Request $request, ?Item $item = null): array
    {
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:64', Rule::unique('items', 'sku')->ignore($item?->id)],
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:64',
            'uom' => 'required|string|max:16',
            'selling_price' => 'nullable|numeric|min:0',
            'reorder_level' => 'nullable|numeric|min:0',
            'unit_cost' => $item ? 'prohibited' : 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ], ['unit_cost.prohibited' => __('Average cost is maintained by stock receipts and cannot be edited.')]);
        $data['sku'] = strtoupper($data['sku']);
        $data['selling_price'] = $data['selling_price'] ?? 0;
        $data['reorder_level'] = $data['reorder_level'] ?? 0;
        $data['is_active'] = $request->boolean('is_active');
        if (! $item) {
            $data['unit_cost'] = $data['unit_cost'] ?? 0;
        }

        return $data;
    }

    private function lookups(): array
    {
        return [
            'categories' => array_values(array_unique(array_merge(config('erp.item_categories'), Item::whereNotNull('category')->distinct()->pluck('category')->all()))),
            'uoms' => config('erp.uoms'),
        ];
    }
}
