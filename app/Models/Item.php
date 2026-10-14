<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use Audited, SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'unit_cost' => 'decimal:4',
        'selling_price' => 'decimal:2',
        'reorder_level' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function balances(): HasMany
    {
        return $this->hasMany(StockBalance::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function supplierPrices(): HasMany
    {
        return $this->hasMany(SupplierPrice::class);
    }

    /** Total on hand across all warehouses (eager-load with withSum('balances', 'qty')). */
    public function getOnHandAttribute(): float
    {
        return (float) ($this->balances_sum_qty ?? $this->balances()->sum('qty'));
    }

    public function getIsLowStockAttribute(): bool
    {
        return $this->reorder_level > 0 && $this->on_hand <= (float) $this->reorder_level;
    }
}
