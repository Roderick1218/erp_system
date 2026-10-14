<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Project;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class StockController extends Controller
{
    public function index(Request $request)
    {
        $rows = $this->filtered($request)->with(['item', 'warehouse', 'project', 'supplier', 'user'])
            ->paginate(30)->withQueryString();

        $period = $this->filtered($request)->reorder();
        $totals = [
            'in' => (float) (clone $period)->where('qty', '>', 0)->sum('qty'),
            'out' => (float) abs((clone $period)->where('qty', '<', 0)->sum('qty')),
            'value_in' => (float) (clone $period)->where('qty', '>', 0)->selectRaw('COALESCE(SUM(qty * unit_cost),0) v')->value('v'),
            'value_out' => (float) abs((clone $period)->where('qty', '<', 0)->selectRaw('COALESCE(SUM(qty * unit_cost),0) v')->value('v')),
        ];

        return view('stock.index', ['rows' => $rows, 'totals' => $totals] + self::lookups());
    }

    public function export(Request $request)
    {
        $rows = $this->filtered($request)->with(['item', 'warehouse', 'project', 'supplier', 'user'])->cursor()->map(fn (StockMovement $m) => [
            $m->moved_at->format('Y-m-d H:i'), $m->doc_no, __(StockService::TYPES[$m->type] ?? $m->type), $m->item?->sku, $m->item?->name,
            $m->warehouse?->code, (float) $m->qty, $m->item?->uom, (float) $m->unit_cost, round((float) $m->qty * (float) $m->unit_cost, 2),
            (float) $m->balance_after, $m->project?->code, $m->supplier?->name, $m->reference, $m->remarks, $m->user?->name,
        ]);

        return csv_download('stock-transactions-'.now()->format('Ymd-His').'.csv',
            array_map(fn ($h) => __($h), ['Date', 'Doc no', 'Type', 'SKU', 'Item', 'Warehouse', 'Qty', 'UOM', 'Unit cost', 'Value', 'Balance after', 'Project', 'Supplier', 'Reference', 'Remarks', 'By']), $rows);
    }

    /** One dialog for stock in / out / issue to project / adjustment. */
    public function store(Request $request, StockService $stock)
    {
        $v = Validator::make($request->all(), [
            'type' => 'required|in:in,out,project,adjust',
            'item_id' => 'required|exists:items,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'qty' => 'required|numeric|not_in:0',
            'unit_cost' => 'nullable|numeric|min:0',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'project_id' => 'nullable|required_if:type,project|exists:projects,id',
            'reference' => 'nullable|string|max:64',
            'remarks' => 'nullable|string|max:255',
            'moved_at' => 'nullable|date',
        ], ['project_id.required_if' => __('Choose the project the stock is issued to.'), 'qty.not_in' => __('Quantity cannot be zero.')]);
        if ($v->fails()) {
            return $this->reopen($v->errors()->toArray(), 'dlg-stock');
        }
        $d = $v->validated();
        if ($d['type'] !== 'adjust' && $d['qty'] < 0) {
            return $this->reopen(['qty' => [__('Enter a positive quantity — the type decides whether stock goes in or out.')]], 'dlg-stock');
        }
        $qty = in_array($d['type'], ['out', 'project'], true) ? -abs($d['qty']) : (float) $d['qty'];

        try {
            $m = $stock->move((int) $d['item_id'], (int) $d['warehouse_id'], $qty, $d['type'], [
                'unit_cost' => $d['type'] === 'in' ? ($d['unit_cost'] ?? null) : null,
                'supplier_id' => $d['type'] === 'in' ? ($d['supplier_id'] ?? null) : null,
                'project_id' => $d['type'] === 'project' ? $d['project_id'] : null,
                'reference' => $d['reference'] ?? null,
                'remarks' => $d['remarks'] ?? null,
                'moved_at' => ! empty($d['moved_at']) ? Carbon::parse($d['moved_at']) : null,
            ]);
        } catch (ValidationException $e) {
            return $this->reopen($e->errors(), 'dlg-stock');
        }

        return back()->with('ok', __(':type recorded: :qty :uom :item (:doc). Balance now :balance.', ['type' => __(StockService::TYPES[$m->type]), 'qty' => qty(abs((float) $m->qty)), 'uom' => $m->item->uom, 'item' => $m->item->name, 'doc' => $m->doc_no, 'balance' => qty($m->balance_after)]));
    }

    public function transfer(Request $request, StockService $stock)
    {
        $v = Validator::make($request->all(), [
            'item_id' => 'required|exists:items,id',
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse_id' => 'required|exists:warehouses,id|different:from_warehouse_id',
            'qty' => 'required|numeric|gt:0',
            'reference' => 'nullable|string|max:64',
            'remarks' => 'nullable|string|max:255',
        ], ['to_warehouse_id.different' => __('Choose two different warehouses.')]);
        if ($v->fails()) {
            return $this->reopen($v->errors()->toArray(), 'dlg-transfer');
        }
        $d = $v->validated();
        try {
            $stock->transfer((int) $d['item_id'], (int) $d['from_warehouse_id'], (int) $d['to_warehouse_id'], (float) $d['qty'],
                ['reference' => $d['reference'] ?? null, 'remarks' => $d['remarks'] ?? null]);
        } catch (ValidationException $e) {
            return $this->reopen($e->errors(), 'dlg-transfer');
        }
        $item = Item::find($d['item_id']);
        $wh = Warehouse::whereIn('id', [$d['from_warehouse_id'], $d['to_warehouse_id']])->pluck('code', 'id');

        return back()->with('ok', __('Transferred :qty :uom :item from :from to :to.', ['qty' => qty($d['qty']), 'uom' => $item->uom, 'item' => $item->name, 'from' => $wh[$d['from_warehouse_id']], 'to' => $wh[$d['to_warehouse_id']]]));
    }

    /** Dropdown data shared by the stock dialogs (used by item + stock pages). */
    public static function lookups(): array
    {
        return [
            'dlgItems' => Item::where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name', 'uom', 'unit_cost']),
            'dlgWarehouses' => Warehouse::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'dlgProjects' => Project::whereNotIn('status', ['completed', 'cancelled'])->orderByDesc('id')->get(['id', 'code', 'name']),
            'dlgSuppliers' => Supplier::where('status', 'active')->orderBy('name')->get(['id', 'code', 'name']),
        ];
    }

    private function reopen(array $errors, string $dialog)
    {
        return back()->withErrors($errors)->withInput()->with('reopen', $dialog);
    }

    private function filtered(Request $request): Builder
    {
        return StockMovement::query()
            ->when($request->input('from'), fn ($q, $v) => $q->whereDate('moved_at', '>=', $v))
            ->when($request->input('to'), fn ($q, $v) => $q->whereDate('moved_at', '<=', $v))
            ->when($request->input('type'), fn ($q, $v) => $q->where('type', $v))
            ->when($request->input('item'), fn ($q, $v) => $q->where('item_id', $v))
            ->when($request->input('warehouse'), fn ($q, $v) => $q->where('warehouse_id', $v))
            ->when($request->input('project'), fn ($q, $v) => $q->where('project_id', $v))
            ->when(trim((string) $request->input('q')), fn ($q, $v) => $q->where(fn ($w) => $w->where('doc_no', 'like', "%$v%")
                ->orWhere('reference', 'like', "%$v%")->orWhere('remarks', 'like', "%$v%")))
            ->orderByDesc('moved_at')->orderByDesc('id');
    }
}
