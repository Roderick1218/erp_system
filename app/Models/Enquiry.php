<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Enquiry extends Model
{
    use Audited, SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'enquiry_date' => 'date',
        'inspection_date' => 'date',
        'estimate_amount' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function salesman(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salesman_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public const STAGES = ['new' => 'New', 'inspection' => 'Inspection', 'quotation' => 'Quotation', 'negotiation' => 'Negotiation', 'won' => 'Won', 'lost' => 'Lost'];
}
