<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Services\AttendanceService;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AttendanceController extends Controller
{
    /** My attendance history (self-service). */
    public function index(Request $request)
    {
        $employee = $request->user()->employee;
        $month = $request->input('month', now()->format('Y-m'));
        $start = Carbon::parse($month.'-01');
        $rows = $employee
            ? Attendance::where('employee_id', $employee->id)->whereDate('work_date', '>=', $start->toDateString())
                ->whereDate('work_date', '<=', $start->copy()->endOfMonth()->toDateString())->orderByDesc('work_date')->get()
            : collect();

        return view('attendance.index', compact('employee', 'rows', 'month', 'start'));
    }

    public function clock(Request $request, AttendanceService $service)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 422, __('Your login is not linked to an employee profile.'));
        $row = $service->clock($employee);

        return back()->with('ok', $row->clock_out ? __('Clocked out at :time', ['time' => $row->clock_out->format('H:i:s')]) : __($row->is_late ? 'Clocked in at :time (late)' : 'Clocked in at :time', ['time' => $row->clock_in->format('H:i:s')]));
    }

    /** HR: everyone's status for one day. */
    public function board(Request $request)
    {
        $date = Carbon::parse($request->input('date', today()->toDateString()))->startOfDay();
        $employees = $this->employeeQuery($request)->get();

        $records = Attendance::whereIn('employee_id', $employees->pluck('id'))->whereDate('work_date', $date->toDateString())->get()->keyBy('employee_id');
        $leaves = LeaveRequest::with('leaveType')->whereIn('employee_id', $employees->pluck('id'))->where('status', 'approved')
            ->whereDate('start_date', '<=', $date->toDateString())->whereDate('end_date', '>=', $date->toDateString())->get()->keyBy('employee_id');
        $holiday = Holiday::whereDate('date', $date->toDateString())->whereNull('branch_id')->first();

        $rows = $employees->map(function (Employee $e) use ($records, $leaves, $date, $holiday) {
            $att = $records[$e->id] ?? null;
            $leave = $leaves[$e->id] ?? null;
            $status = match (true) {
                $att && $att->clock_in && $att->is_late => 'late',
                $att && $att->clock_in => 'present',
                (bool) $leave => 'on_leave',
                $date->isSunday() || (bool) $holiday => 'off',
                $date->isFuture() => 'upcoming',
                default => 'absent',
            };

            return compact('e', 'att', 'leave', 'status');
        });

        $counts = $rows->countBy('status');

        return view('attendance.board', [
            'date' => $date,
            'rows' => $rows,
            'counts' => $counts,
            'holiday' => $holiday,
            'branches' => Branch::orderBy('name')->pluck('legal_name', 'id'),
            'departments' => Department::orderBy('name')->pluck('name', 'id'),
            'statusFilter' => $request->input('status'),
        ]);
    }

    /** HR: month matrix employee × day, with totals; ?export=csv downloads it. */
    public function report(Request $request)
    {
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->input('month')) ? $request->input('month') : now()->format('Y-m');
        $start = Carbon::parse($month.'-01')->startOfDay();
        $end = $start->copy()->endOfMonth()->startOfDay();
        $days = collect(CarbonPeriod::create($start, $end))->map(fn ($d) => Carbon::instance($d));
        $employees = $this->employeeQuery($request)->get();
        $ids = $employees->pluck('id');

        $att = Attendance::whereIn('employee_id', $ids)->whereDate('work_date', '>=', $start->toDateString())->whereDate('work_date', '<=', $end->toDateString())->get()
            ->groupBy('employee_id')->map(fn ($g) => $g->keyBy(fn ($a) => $a->work_date->toDateString()));
        $leaves = LeaveRequest::whereIn('employee_id', $ids)->where('status', 'approved')
            ->whereDate('start_date', '<=', $end->toDateString())->whereDate('end_date', '>=', $start->toDateString())->get()->groupBy('employee_id');
        $holidays = Holiday::whereDate('date', '>=', $start->toDateString())->whereDate('date', '<=', $end->toDateString())->get();

        $today = today();
        $matrix = $employees->map(function (Employee $e) use ($days, $att, $leaves, $holidays, $today) {
            $cells = [];
            $t = ['P' => 0, 'L' => 0, 'A' => 0, 'LV' => 0, 'hours' => 0.0];
            foreach ($days as $d) {
                $key = $d->toDateString();
                $a = $att[$e->id][$key] ?? null;
                $isHoliday = $holidays->contains(fn ($h) => $h->date->toDateString() === $key && (! $h->branch_id || $h->branch_id === $e->branch_id));
                $onLeave = ($leaves[$e->id] ?? collect())->contains(fn ($l) => $d->between($l->start_date, $l->end_date));
                $code = match (true) {
                    $a && $a->clock_in => $a->is_late ? 'L' : 'P',
                    $onLeave => 'LV',
                    $isHoliday => 'H',
                    $d->isSunday() => 'S',
                    $d->gt($today) => '',
                    ($e->hire_date && $d->lt($e->hire_date)) => '',
                    default => 'A',
                };
                if (isset($t[$code])) {
                    $t[$code]++;
                }
                if ($a && $a->hours) {
                    $t['hours'] += $a->hours;
                }
                $cells[$key] = ['code' => $code, 'a' => $a];
            }

            return ['e' => $e, 'cells' => $cells, 'tot' => $t];
        });

        if ($request->input('export') === 'csv') {
            $headers = array_merge([__('Emp no'), __('Name')], $days->map(fn ($d) => $d->format('d'))->all(), [__('Present'), __('Late'), __('Absent'), __('Leave'), __('Hours')]);
            $rows = $matrix->map(fn ($r) => array_merge([$r['e']->emp_no, $r['e']->display_name], array_column($r['cells'], 'code'),
                [$r['tot']['P'] + $r['tot']['L'], $r['tot']['L'], $r['tot']['A'], $r['tot']['LV'], round($r['tot']['hours'], 2)]));

            return csv_download("attendance-$month.csv", $headers, $rows);
        }

        return view('attendance.report', [
            'month' => $month, 'start' => $start, 'days' => $days, 'matrix' => $matrix,
            'branches' => Branch::orderBy('name')->pluck('legal_name', 'id'),
            'departments' => Department::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    /** HR: correct a record (forgot to clock out, wrong time…). */
    public function update(Request $request, Attendance $attendance, AttendanceService $service)
    {
        $data = $request->validate([
            'clock_in' => 'required|date_format:H:i',
            'clock_out' => 'nullable|date_format:H:i|after:clock_in',
            'remarks' => 'nullable|string|max:255',
        ]);
        $day = $attendance->work_date->toDateString();
        $in = Carbon::parse("$day {$data['clock_in']}");
        $attendance->update([
            'clock_in' => $in,
            'clock_out' => $data['clock_out'] ? Carbon::parse("$day {$data['clock_out']}") : null,
            'is_late' => $service->isLate($in),
            'remarks' => $data['remarks'] ?? $attendance->remarks,
        ]);

        return back()->with('ok', __('Attendance for :name updated.', ['name' => $attendance->employee->display_name]));
    }

    /** HR: add a missing record for an employee on a day. */
    public function manual(Request $request, AttendanceService $service)
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'work_date' => 'required|date|before_or_equal:today',
            'clock_in' => 'required|date_format:H:i',
            'clock_out' => 'nullable|date_format:H:i|after:clock_in',
            'remarks' => 'nullable|string|max:255',
        ]);
        $day = Carbon::parse($data['work_date'])->toDateString();
        if (Attendance::where('employee_id', $data['employee_id'])->whereDate('work_date', $day)->exists()) {
            return back()->with('error', __('A record already exists for that day — edit it instead.'));
        }
        $in = Carbon::parse("$day {$data['clock_in']}");
        $row = Attendance::create([
            'employee_id' => $data['employee_id'], 'work_date' => $day, 'clock_in' => $in,
            'clock_out' => $data['clock_out'] ? Carbon::parse("$day {$data['clock_out']}") : null,
            'is_late' => $service->isLate($in), 'source' => 'manual', 'remarks' => $data['remarks'] ?? 'Manual entry by HR',
        ]);

        return back()->with('ok', __('Manual attendance added for :name.', ['name' => $row->employee->display_name]));
    }

    private function employeeQuery(Request $request)
    {
        return Employee::active()->with(['branch', 'department'])
            ->when($request->input('branch'), fn ($w, $v) => $w->where('branch_id', $v))
            ->when($request->input('department'), fn ($w, $v) => $w->where('department_id', $v))
            ->when($request->input('q'), fn ($w, $v) => $w->where(fn ($x) => $x->where('name', 'like', "%$v%")->orWhere('nickname', 'like', "%$v%")->orWhere('emp_no', 'like', "%$v%")))
            ->orderBy('emp_no');
    }
}
