<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'work_date' => 'date',
        'clock_in' => 'datetime',
        'clock_out' => 'datetime',
        'is_late' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** Worked hours, or null while still clocked in. */
    public function getHoursAttribute(): ?float
    {
        if (! $this->clock_in || ! $this->clock_out) {
            return null;
        }

        return round($this->clock_in->diffInMinutes($this->clock_out) / 60, 2);
    }
}
