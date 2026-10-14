<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use App\Models\Concerns\HasDocumentLines;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quotation extends Model
{
    use Audited, HasDocumentLines, SoftDeletes;

    public const STATUSES = ['draft' => 'Draft', 'sent' => 'Sent', 'accepted' => 'Accepted', 'rejected' => 'Rejected', 'expired' => 'Expired'];

    protected $guarded = ['id'];

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'sent'], true);
    }

    /** Sent quotations past their validity date read as expired without a cron job. */
    public function getDisplayStatusAttribute(): string
    {
        return $this->status === 'sent' && $this->valid_until && $this->valid_until->lt(today()) ? 'expired' : $this->status;
    }

    protected $casts = [
        'quote_date' => 'date',
        'valid_until' => 'date',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function salesman(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salesman_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(QuotationLine::class)->orderBy('sort');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
