<?php

namespace App\Models\Concerns;

use App\Models\Item;

/**
 * Shared by Quotation and Invoice: rewrite the line items and recompute every
 * total on the server (client-side totals are only a preview, never trusted).
 */
trait HasDocumentLines
{
    /**
     * @param  array<int, array{item_id?:mixed, description?:?string, qty:mixed, uom?:?string, unit_price:mixed}>  $rows
     */
    public function syncLines(array $rows, float $discount = 0, float $taxRate = 0): void
    {
        $this->lines()->delete();
        $subtotal = 0.0;
        $sort = 0;
        $itemNames = Item::whereIn('id', array_filter(array_column($rows, 'item_id')))->pluck('name', 'id');

        foreach ($rows as $row) {
            $description = trim((string) ($row['description'] ?? ''));
            $itemId = ($row['item_id'] ?? null) ?: null;
            if ($description === '' && $itemId) {
                $description = (string) ($itemNames[$itemId] ?? '');
            }
            if ($description === '') {
                continue;
            }
            $qty = round((float) $row['qty'], 2);
            $price = round((float) $row['unit_price'], 2);
            $amount = round($qty * $price, 2);
            $subtotal += $amount;
            $this->lines()->create([
                'item_id' => $itemId,
                'description' => $description,
                'qty' => $qty,
                'uom' => strtoupper(trim((string) ($row['uom'] ?? '')) ?: 'LOT'),
                'unit_price' => $price,
                'amount' => $amount,
                'sort' => $sort++,
            ]);
        }

        $subtotal = round($subtotal, 2);
        $discount = round(min(max($discount, 0), $subtotal), 2);
        $taxable = $subtotal - $discount;
        $tax = round($taxable * max($taxRate, 0) / 100, 2);

        $this->forceFill([
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax_rate' => max($taxRate, 0),
            'tax' => $tax,
            'total' => round($taxable + $tax, 2),
        ])->save();
    }

    /** Validation rules for the lines[] array posted by the data-lines editor. */
    public static function lineRules(): array
    {
        return [
            'lines' => 'required|array|min:1',
            'lines.*.item_id' => 'nullable|exists:items,id',
            'lines.*.description' => 'nullable|string|max:255',
            'lines.*.qty' => 'required|numeric|min:0.01|max:9999999',
            'lines.*.uom' => 'nullable|string|max:16',
            'lines.*.unit_price' => 'required|numeric|min:0|max:99999999',
            'discount' => 'nullable|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
        ];
    }

    /** True when at least one posted line has a description or a stock item. */
    public static function hasUsableLine(array $rows): bool
    {
        foreach ($rows as $r) {
            if (trim((string) ($r['description'] ?? '')) !== '' || ! empty($r['item_id'])) {
                return true;
            }
        }

        return false;
    }
}
