<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * One attendance row per employee per day. First press = clock in, second = clock out.
 * Late = clock-in after work_start + grace (Company Settings).
 */
class AttendanceService
{
    public function clock(Employee $employee, string $source = 'web'): Attendance
    {
        $now = now();
        $row = Attendance::where('employee_id', $employee->id)->whereDate('work_date', $now->toDateString())->first();

        if (! $row) {
            return Attendance::create([
                'employee_id' => $employee->id, 'work_date' => $now->toDateString(),
                'clock_in' => $now, 'source' => $source, 'is_late' => $this->isLate($now),
            ]);
        }
        if ($row->clock_out) {
            throw ValidationException::withMessages(['clock' => __('You have already clocked out today.')]);
        }
        $row->update(['clock_out' => $now]);

        return $row;
    }

    public function isLate(Carbon $clockIn): bool
    {
        [$h, $m] = array_map('intval', explode(':', Setting::get('work_start', '08:30')));
        $limit = $clockIn->copy()->setTime($h, $m)->addMinutes((int) Setting::get('late_grace_minutes', '10'));

        return $clockIn->gt($limit);
    }
}
