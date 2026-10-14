<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'is_paid' => 'boolean',
        'days_per_year' => 'decimal:1',
    ];

    public function requests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
