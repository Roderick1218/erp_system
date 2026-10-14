<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkerRate extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
