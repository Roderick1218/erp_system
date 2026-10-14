<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierPrice;
use App\Models\Warehouse;
use App\Services\StockService;
use App\Support\DocNo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PurchaseOrderController extends Controller
{
    public const STATUSES = ['draft' => 'Draft', 'ordered' => 'Ordered', 'partial' => 'Partially received', 'received' => 'Received', 'cancelled' => 'Cancelled'];

    public function index(Request $request)
    {
        $rows = PurchaseOrder::query()->with(['supplier', 'warehouse', 'project'])->withCount('lines')
            ->when($request->input('status'), fn ($q, $v) => $v === 'open' ? $q->whereIn('status', ['ordered', 'partial']) : $q->where('status', $v))
            ->when($request->input('supplier'), fn ($q, $v) => $q->where('supplier_id', $v))
            ->when($request->input('warehouse'), fn ($q, $v) => $q->where('warehouse_id', $v))
            ->when($request->input('from'), fn ($q, $v) => $q->whereDate('order_date', '>=', $v))
            ->when($request->input('to'), fn ($q, $v) => $q->whereDate('order_date', '<=', $v))
            ->when(trim((string) $request->input('q')), fn ($q, $s) => $q->where(fn ($w) => $w->where('doc_no', 'like', "%$s%")->orWhere('notes', 'like', "%$s%")
                ->orWhereHas('supplier', fn ($x) => $x->where('name', 'like', "%$s%"))))
            ->latest('order_date')->latest('id')
            ->paginate(25)->withQueryString();

        return view('purchase_orders.index', [
            'rows' => $rows,
            'statusCounts' => PurchaseOrder::selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status'),
            'openValue' => PurchaseOrder::whereIn('status', ['ordered', 'partial'])->sum('total'),
            'overdue' => PurchaseOrder::whereIn('status', ['ordered', 'partial'])->whereNotNull('expected_date')->whereDate('expected_date', '<', today())->count(),
            'suppliers' => Supplier::orderBy('name')->pluck('name', 'id'),
            'warehouses' => Warehouse::orderBy('code')->pluck('code', 'id'),
        ]);
    }

    public function create(Request $request)
    {
        $po = new PurchaseOrder([
            'supplier_id' => $request->input('supplier_id'),
            'project_id' => $request->input('project_id'),
            'warehouse_id' => Warehouse::where('branch_id', $request->user()->branch_id)->value('id') ?? Warehouse::value('id'),
            'order_date' => today(),
            'expected_date' => today()->addDays(7),
        ]);
        $lines = [];
        if ($itemId = $request->input('item_id')) {
            $lines[] = ['item_id' => $itemId, 'qty' => 1, 'unit_price' => ''];
        }

        return view('purchase_orders.form', ['po' => $po, 'lines' => $lines] + $this->lookups());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $po = DB::transaction(function () use ($data, $request) {
            $po = PurchaseOrder::create([
                'doc_no' => DocNo::next('purchase_orders', 'PO'),
                'status' => $request->input('submit') === 'order' ? 'ordered' : 'draft',
                'created_by' => $request->user()->id,
            ] + $data['header']);
            $this->syncLines($po, $data['lines']);

            return $po;
        });

        return redirect()->route('purchase-orders.show', $po)->with('ok', $po->status === 'ordered' ? __(':doc created and marked as ordered.', ['doc' => $po->doc_no]) : __(':doc saved as draft.', ['doc' => $po->doc_no]));
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load(['supplier', 'warehouse', 'project', 'creator', 'lines.item']);
        $receipts = StockMovement::with(['item', 'user'])->where('reference', $purchaseOrder->doc_no)->where('type', 'in')->orderByDesc('moved_at')->get();

        return view('purchase_orders.show', ['po' => $purchaseOrder, 'receipts' => $receipts, 'statuses' => self::STATUSES]);
    }

    public function edit(PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->status !== 'draft') {
            return redirect()->route('purchase-orders.show', $purchaseOrder)->with('error', __('Only draft purchase orders can be edited.'));
        }
        $purchaseOrder->load('lines');

        return view('purchase_orders.form', [
            'po' => $purchaseOrder,
            'lines' => $purchaseOrder->lines->map(fn ($l) => ['item_id' => $l->item_id, 'qty' => (float) $l->qty, 'unit_price' => (float) $l->unit_price])->all(),
        ] + $this->lookups());
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        abort_unless($purchaseOrder->status === 'draft', 422, __('Only draft purchase orders can be edited.'));
        $data = $this->validated($request);
        DB::transaction(function () use ($purchaseOrder, $data, $request) {
            $purchaseOrder->update($data['header'] + ($request->input('submit') === 'order' ? ['status' => 'ordered'] : []));
            $purchaseOrder->lines()->delete();
            $this->syncLines($purchaseOrder, $data['lines']);
        });

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('ok', __(':doc updated.', ['doc' => $purchaseOrder->doc_no]));
    }

    public function destroy(PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->status !== 'draft') {
            return back()->with('error', __('Only draft purchase orders can be deleted — cancel it instead.'));
        }
        $purchaseOrder->delete();

        return redirect()->route('purchase-orders.index')->with('ok', __(':doc deleted.', ['doc' => $purchaseOrder->doc_no]));
    }

    /** Mark ordered (sent to supplier) or cancel. */
    public function status(Request $request, PurchaseOrder $purchaseOrder)
    {
        $to = $request->validate(['status' => 'required|in:ordered,cancelled'])['status'];
        $allowed = ['ordered' => ['draft'], 'cancelled' => ['draft', 'ordered']];
        if (! in_array($purchaseOrder->status, $allowed[$to], true)) {
            return back()->with('error', __('A :from purchase order cannot be changed to :to.', ['from' => __(self::STATUSES[$purchaseOrder->status] ?? $purchaseOrder->status), 'to' => __(self::STATUSES[$to] ?? $to)]));
        }
        if ($to === 'cancelled' && $purchaseOrder->lines()->where('received_qty', '>', 0)->exists()) {
            return back()->with('error', __('Goods have already been received on this PO — it cannot be cancelled.'));
        }
        $purchaseOrder->update(['status' => $to]);

        return back()->with('ok', __(':doc marked as :status.', ['doc' => $purchaseOrder->doc_no, 'status' => __(self::STATUSES[$to])]));
    }

    /** Goods receipt: each received quantity becomes a stock-in at the PO price. */
    public function receive(Request $request, PurchaseOrder $purchaseOrder, StockService $stock)
    {
        if (! in_array($purchaseOrder->status, ['ordered', 'partial'], true)) {
            return back()->with('error', __('Mark the purchase order as ordered before receiving goods.'));
        }
        $purchaseOrder->load('lines.item');
        $v = Validator::make($request->all(), [
            'receive' => 'required|array',
            'receive.*' => 'nullable|numeric|min:0',
            'received_at' => 'nullable|date',
            'reference' => 'nullable|string|max:64',
        ]);
        if ($v->fails()) {
            return back()->withErrors($v)->withInput()->with('reopen', 'dlg-receive');
        }
        $qtys = collect($request->input('receive'))->map(fn ($q) => (float) $q)->filter(fn ($q) => $q > 0);
        if ($qtys->isEmpty()) {
            return back()->withErrors(['receive' => __('Enter the quantity received for at least one line.')])->withInput()->with('reopen', 'dlg-receive');
        }
        $errors = [];
        foreach ($qtys as $lineId => $q) {
            $line = $purchaseOrder->lines->firstWhere('id', (int) $lineId);
            if (! $line) {
                $errors["receive.$lineId"] = __('Unknown PO line.');
            } elseif (round($q, 2) > round((float) $line->qty - (float) $line->received_qty, 2)) {
                $errors["receive.$lineId"] = __(':item: only :qty outstanding.', ['item' => $line->item->name, 'qty' => qty((float) $line->qty - (float) $line->received_qty)]);
            }
        }
        if ($errors) {
            return back()->withErrors($errors)->withInput()->with('reopen', 'dlg-receive');
        }

        try {
            DB::transaction(function () use ($purchaseOrder, $qtys, $request, $stock) {
                $at = $request->input('received_at') ? Carbon::parse($request->input('received_at')) : now();
                $ref = $request->input('reference');
                foreach ($qtys as $lineId => $q) {
                    $line = $purchaseOrder->lines->firstWhere('id', (int) $lineId);
                    $stock->move($line->item_id, $purchaseOrder->warehouse_id, $q, 'in', [
                        'unit_cost' => (float) $line->unit_price,
                        'supplier_id' => $purchaseOrder->supplier_id,
                        'reference' => $purchaseOrder->doc_no,
                        'remarks' => trim('Goods receipt'.($ref ? ' · DO '.$ref : '')),
                        'moved_at' => $at,
                    ]);
                    $line->increment('received_qty', $q);
                    $this->rememberPrice($purchaseOrder->supplier_id, $line->item_id, (float) $line->unit_price, $at);
                }
                $purchaseOrder->load('lines');
                $done = $purchaseOrder->lines->every(fn ($l) => round((float) $l->received_qty, 2) >= round((float) $l->qty, 2));
                $purchaseOrder->update(['status' => $done ? 'received' : 'partial']);
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput()->with('reopen', 'dlg-receive');
        }

        return back()->with('ok', __('Goods received into :wh — :doc is now :status.', ['wh' => $purchaseOrder->warehouse->code, 'doc' => $purchaseOrder->doc_no, 'status' => mb_strtolower(__(self::STATUSES[$purchaseOrder->status]))]));
    }

    // ------------------------------------------------------------------

    private function validated(Request $request): array
    {
        // Drop completely blank editor rows before validating.
        $request->merge(['lines' => collect($request->input('lines', []))->filter(fn ($l) => ! empty($l['item_id']))->values()->all()]);
        $data = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'project_id' => 'nullable|exists:projects,id',
            'order_date' => 'required|date',
            'expected_date' => 'nullable|date|after_or_equal:order_date',
            'notes' => 'nullable|string|max:2000',
            'lines' => 'required|array|min:1',
            'lines.*.item_id' => 'required|exists:items,id',
            'lines.*.qty' => 'required|numeric|gt:0',
            'lines.*.unit_price' => 'required|numeric|min:0',
        ], ['lines.required' => __('Add at least one item line.'), 'lines.*.item_id.required' => __('Choose an item on every line.')]);

        return ['header' => collect($data)->except('lines')->all(), 'lines' => $data['lines']];
    }

    private function syncLines(PurchaseOrder $po, array $lines): void
    {
        $total = 0;
        foreach ($lines as $l) {
            $po->lines()->create(['item_id' => $l['item_id'], 'qty' => $l['qty'], 'unit_price' => $l['unit_price']]);
            $total += round((float) $l['qty'] * (float) $l['unit_price'], 2);
        }
        $po->update(['total' => $total]);
    }

    /** Record a new supplier price only when it differs from the latest one on file. */
    private function rememberPrice(int $supplierId, int $itemId, float $price, $date): void
    {
        $latest = SupplierPrice::where('supplier_id', $supplierId)->where('item_id', $itemId)->orderByDesc('effective_date')->orderByDesc('id')->first();
        if (! $latest || round((float) $latest->unit_price, 4) !== round($price, 4)) {
            SupplierPrice::create(['supplier_id' => $supplierId, 'item_id' => $itemId, 'unit_price' => $price, 'effective_date' => $date->toDateString()]);
        }
    }

    private function lookups(): array
    {
        // supplier_id => [item_id => latest price] for pre-filling line prices in the browser.
        $priceMap = [];
        foreach (SupplierPrice::orderBy('effective_date')->orderBy('id')->get(['supplier_id', 'item_id', 'unit_price']) as $p) {
            $priceMap[$p->supplier_id][$p->item_id] = (float) $p->unit_price;
        }

        return [
            'suppliers' => Supplier::where('status', 'active')->orderBy('name')->pluck('name', 'id')->all(),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('code')->get()->mapWithKeys(fn ($w) => [$w->id => $w->code.' · '.$w->name])->all(),
            'projects' => Project::whereNotIn('status', ['completed', 'cancelled'])->orderByDesc('id')->get()->mapWithKeys(fn ($p) => [$p->id => $p->code.' · '.$p->name])->all(),
            'items' => Item::where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name', 'uom', 'unit_cost']),
            'priceMap' => $priceMap,
        ];
    }
}
