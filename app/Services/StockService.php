<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ProjectCost;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Support\DocNo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only place stock quantities change. Every call locks the balance row,
 * refuses to go negative, keeps a moving-average unit cost on receipts and
 * writes an immutable stock_movements row. Issuing stock to a project also
 * books a Material cost line on that project.
 */
class StockService
{
    public const TYPES = [
        'in' => 'Stock In',
        'out' => 'Stock Out',
        'project' => 'Issue to Project',
        'adjust' => 'Adjustment',
        'transfer_in' => 'Transfer In',
        'transfer_out' => 'Transfer Out',
    ];

    /**
     * @param  float  $qty  signed quantity: positive adds stock, negative removes it
     * @param  array  $attrs  project_id, supplier_id, reference, remarks, unit_cost, moved_at, doc_no
     */
    public function move(int $itemId, int $warehouseId, float $qty, string $type, array $attrs = []): StockMovement
    {
        if ($qty == 0.0) {
            throw ValidationException::withMessages(['qty' => __('Quantity cannot be zero.')]);
        }

        return DB::transaction(function () use ($itemId, $warehouseId, $qty, $type, $attrs) {
            $item = Item::query()->lockForUpdate()->findOrFail($itemId);
            $balance = StockBalance::query()->where('item_id', $itemId)->where('warehouse_id', $warehouseId)->lockForUpdate()->first()
                ?? StockBalance::create(['item_id' => $itemId, 'warehouse_id' => $warehouseId, 'qty' => 0]);

            $newQty = round((float) $balance->qty + $qty, 2);
            if ($newQty < 0) {
                throw ValidationException::withMessages([
                    'qty' => __('Not enough stock for :item: only :qty :uom available in this warehouse.', ['item' => $item->name, 'qty' => qty($balance->qty), 'uom' => $item->uom]),
                ]);
            }

            $unitCost = isset($attrs['unit_cost']) && $attrs['unit_cost'] !== null ? (float) $attrs['unit_cost'] : (float) $item->unit_cost;

            // Moving average cost on receipts from outside (not transfers).
            if ($qty > 0 && $type === 'in') {
                $onHand = (float) StockBalance::where('item_id', $itemId)->sum('qty');
                $total = $onHand + $qty;
                if ($total > 0) {
                    $item->unit_cost = round((($onHand * (float) $item->unit_cost) + ($qty * $unitCost)) / $total, 4);
                    $item->save();
                }
            }

            $balance->qty = $newQty;
            $balance->save();

            $movement = StockMovement::create([
                'doc_no' => $attrs['doc_no'] ?? DocNo::next('stock_movements', 'STK'),
                'item_id' => $itemId,
                'warehouse_id' => $warehouseId,
                'type' => $type,
                'qty' => $qty,
                'unit_cost' => $unitCost,
                'balance_after' => $newQty,
                'project_id' => $attrs['project_id'] ?? null,
                'supplier_id' => $attrs['supplier_id'] ?? null,
                'reference' => $attrs['reference'] ?? null,
                'remarks' => $attrs['remarks'] ?? null,
                'user_id' => auth()->id(),
                'moved_at' => $attrs['moved_at'] ?? now(),
            ]);

            if ($type === 'project' && $movement->project_id) {
                ProjectCost::create([
                    'project_id' => $movement->project_id,
                    'cost_date' => $movement->moved_at->toDateString(),
                    'category' => 'Material',
                    'description' => $item->name.' × '.rtrim(rtrim(number_format(abs($qty), 2), '0'), '.').' '.$item->uom,
                    'amount' => round(abs($qty) * $unitCost, 2),
                    'reference' => $movement->doc_no,
                    'stock_movement_id' => $movement->id,
                    'created_by' => auth()->id(),
                ]);
            }

            return $movement;
        });
    }

    /** Move stock between warehouses as one paired transaction. */
    public function transfer(int $itemId, int $fromWarehouseId, int $toWarehouseId, float $qty, array $attrs = []): void
    {
        DB::transaction(function () use ($itemId, $fromWarehouseId, $toWarehouseId, $qty, $attrs) {
            $docNo = DocNo::next('stock_movements', 'STK');
            $this->move($itemId, $fromWarehouseId, -abs($qty), 'transfer_out', $attrs + ['doc_no' => $docNo]);
            $this->move($itemId, $toWarehouseId, abs($qty), 'transfer_in', $attrs + ['doc_no' => $docNo]);
        });
    }
}
