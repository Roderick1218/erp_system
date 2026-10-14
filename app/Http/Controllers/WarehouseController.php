<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class WarehouseController extends Controller
{
    public function index()
    {
        $warehouses = Warehouse::with('branch')->orderBy('code')->get();
        $stats = StockBalance::query()->join('items', 'items.id', '=', 'stock_balances.item_id')
            ->where('stock_balances.qty', '!=', 0)
            ->groupBy('warehouse_id')
            ->selectRaw('warehouse_id, COUNT(*) as items, COALESCE(SUM(stock_balances.qty * items.unit_cost), 0) as value')
            ->get()->keyBy('warehouse_id');

        return view('warehouses.index', [
            'warehouses' => $warehouses,
            'stats' => $stats,
            'branches' => Branch::orderBy('name')->pluck('legal_name', 'id')->all(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        if ($data instanceof RedirectResponse) {
            return $data;
        }
        $w = Warehouse::create($data);

        return back()->with('ok', __('Warehouse :code added.', ['code' => $w->code]));
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $data = $this->validated($request, $warehouse);
        if ($data instanceof RedirectResponse) {
            return $data;
        }
        $warehouse->update($data);

        return back()->with('ok', __('Warehouse :code updated.', ['code' => $warehouse->code]));
    }

    public function destroy(Warehouse $warehouse)
    {
        if (StockBalance::where('warehouse_id', $warehouse->id)->where('qty', '!=', 0)->exists()) {
            return back()->with('error', __(':code still holds stock — transfer it out first, or mark the warehouse inactive.', ['code' => $warehouse->code]));
        }
        if ($warehouse->balances()->exists() || StockMovement::where('warehouse_id', $warehouse->id)->exists()) {
            $warehouse->update(['is_active' => false]);

            return back()->with('ok', __(':code has history, so it was deactivated instead of deleted.', ['code' => $warehouse->code]));
        }
        $warehouse->delete();

        return back()->with('ok', __('Warehouse :code deleted.', ['code' => $warehouse->code]));
    }

    private function validated(Request $request, ?Warehouse $warehouse = null)
    {
        $v = Validator::make($request->all(), [
            'code' => ['required', 'string', 'max:16', Rule::unique('warehouses', 'code')->ignore($warehouse?->id)],
            'name' => 'required|string|max:255',
            'branch_id' => 'nullable|exists:branches,id',
            'address' => 'nullable|string|max:1000',
        ]);
        if ($v->fails()) {
            return back()->withErrors($v)->withInput()->with('reopen', 'dlg-warehouse');
        }

        return $v->validated() + ['code' => strtoupper($request->input('code')), 'is_active' => $request->boolean('is_active')];
    }
}
