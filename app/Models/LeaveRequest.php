<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class LeaveRequest extends Model
{
    use Audited;

    protected $guarded = ['id'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'days' => 'decimal:1',
        'reviewed_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Working days between two dates, skipping Sundays and public holidays
     * (company-wide holidays, plus the employee's branch holidays).
     */
    public static function countDays(Carbon $start, Carbon $end, ?int $branchId = null, bool $halfDay = false): float
    {
        if ($end->lt($start)) {
            return 0;
        }
        $holidays = Holiday::whereDate('date', '>=', $start->toDateString())->whereDate('date', '<=', $end->toDateString())
            ->where(fn ($q) => $q->whereNull('branch_id')->when($branchId, fn ($w) => $w->orWhere('branch_id', $branchId)))
            ->get()->map(fn ($h) => $h->date->toDateString())->all();
        $days = 0;
        foreach (CarbonPeriod::create($start, $end) as $day) {
            if (! $day->isSunday() && ! in_array($day->toDateString(), $holidays, true)) {
                $days++;
            }
        }
        if ($halfDay && $days > 0) {
            $days -= 0.5;
        }

        return $days;
    }

    /** Approved days of a leave type taken by an employee in a calendar year. */
    public static function takenDays(int $employeeId, int $leaveTypeId, int $year): float
    {
        return (float) static::where('employee_id', $employeeId)->where('leave_type_id', $leaveTypeId)
            ->where('status', 'approved')->whereYear('start_date', $year)->sum('days');
    }

    /** Does this employee already have pending/approved leave overlapping the range? */
    public static function overlaps(int $employeeId, Carbon $start, Carbon $end, ?int $ignoreId = null): bool
    {
        return static::where('employee_id', $employeeId)->whereIn('status', ['pending', 'approved'])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->whereDate('start_date', '<=', $end->toDateString())->whereDate('end_date', '>=', $start->toDateString())
            ->exists();
    }
}
