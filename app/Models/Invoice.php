<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use App\Models\Concerns\HasDocumentLines;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use Audited, HasDocumentLines, SoftDeletes;

    protected $guarded = ['id'];

    /** Re-derive paid_amount and status from the payments table (never from client input). */
    public function recalcPaid(): void
    {
        $paid = round((float) $this->payments()->sum('amount'), 2);
        $status = $this->status;
        if (! in_array($status, ['draft', 'void'], true)) {
            $status = $paid <= 0 ? 'unpaid' : ($paid + 0.004 >= (float) $this->total ? 'paid' : 'partial');
        }
        $this->forceFill(['paid_amount' => $paid, 'status' => $status])->save();
    }

    public function isEditable(): bool
    {
        return $this->status === 'draft' || ($this->status === 'unpaid' && (float) $this->paid_amount == 0.0);
    }

    public function getDisplayStatusAttribute(): string
    {
        return $this->is_overdue ? 'overdue' : $this->status;
    }

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('sort');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getBalanceAttribute(): float
    {
        return round((float) $this->total - (float) $this->paid_amount, 2);
    }

    public function getIsOverdueAttribute(): bool
    {
        return in_array($this->status, ['unpaid', 'partial'], true) && $this->due_date && $this->due_date->isPast() && ! $this->due_date->isToday();
    }
}
