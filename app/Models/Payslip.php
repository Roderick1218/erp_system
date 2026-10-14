<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payslip extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'basic' => 'decimal:2',
        'allowance' => 'decimal:2',
        'overtime' => 'decimal:2',
        'commission' => 'decimal:2',
        'unpaid_leave' => 'decimal:2',
        'other_deduction' => 'decimal:2',
        'gross' => 'decimal:2',
        'epf_employee' => 'decimal:2',
        'epf_employer' => 'decimal:2',
        'socso_employee' => 'decimal:2',
        'socso_employer' => 'decimal:2',
        'eis_employee' => 'decimal:2',
        'eis_employer' => 'decimal:2',
        'net_pay' => 'decimal:2',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
