<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class LeaveController extends Controller
{
    /** My leave: balances, apply form, history. */
    public function index(Request $request)
    {
        $employee = $request->user()->employee;
        $year = now()->year;
        $types = LeaveType::orderBy('id')->get();

        return view('leave.index', [
            'employee' => $employee,
            'types' => $types,
            'balances' => $employee ? $this->balances($employee->id, $year, $types) : collect(),
            'history' => $employee ? $employee->leaveRequests()->with('leaveType', 'reviewer')->latest('start_date')->get() : collect(),
            'year' => $year,
        ]);
    }

    public function store(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 422, __('Your login is not linked to an employee profile.'));

        $data = $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'half_day' => 'nullable|boolean',
            'reason' => 'required|string|max:1000',
        ]);
        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = Carbon::parse($data['end_date'])->startOfDay();
        $halfDay = $request->boolean('half_day');
        if ($halfDay && ! $start->equalTo($end)) {
            throw ValidationException::withMessages(['half_day' => __('Half-day leave must start and end on the same day.')]);
        }

        $days = LeaveRequest::countDays($start, $end, $employee->branch_id, $halfDay);
        if ($days <= 0) {
            throw ValidationException::withMessages(['start_date' => __('The selected dates are all Sundays or public holidays — no leave needed.')]);
        }
        if (LeaveRequest::overlaps($employee->id, $start, $end)) {
            throw ValidationException::withMessages(['start_date' => __('You already have pending or approved leave overlapping these dates.')]);
        }

        $type = LeaveType::find($data['leave_type_id']);
        $warn = null;
        if ((float) $type->days_per_year > 0) {
            $left = (float) $type->days_per_year - LeaveRequest::takenDays($employee->id, $type->id, $start->year);
            if ($days > $left) {
                $warn = ' '.__('Note: this exceeds your remaining :type balance (:n day(s)).', ['type' => __($type->name), 'n' => qty($left)]);
            }
        }

        LeaveRequest::create([
            'employee_id' => $employee->id, 'leave_type_id' => $type->id,
            'start_date' => $start->toDateString(), 'end_date' => $end->toDateString(),
            'days' => $days, 'reason' => $data['reason'], 'status' => 'pending',
        ]);

        return redirect()->route('leave.index')->with('ok', __('Leave request submitted for :n day(s) — waiting for approval.', ['n' => qty($days)]).$warn);
    }

    public function cancel(Request $request, LeaveRequest $leaveRequest)
    {
        abort_unless($leaveRequest->employee_id === $request->user()->employee_id, 403);
        if ($leaveRequest->status !== 'pending') {
            return back()->with('error', __('Only pending requests can be cancelled.'));
        }
        $leaveRequest->update(['status' => 'cancelled']);

        return back()->with('ok', __('Leave request cancelled.'));
    }

    /** HR: approve / reject queue. */
    public function approvals(Request $request)
    {
        $tab = in_array($request->input('tab'), ['pending', 'approved', 'rejected'], true) ? $request->input('tab') : 'pending';
        $rows = LeaveRequest::with(['employee.department', 'leaveType', 'reviewer'])->where('status', $tab)
            ->when($tab === 'pending', fn ($q) => $q->orderBy('start_date'), fn ($q) => $q->orderByDesc('reviewed_at'))
            ->paginate(20)->withQueryString();

        $year = now()->year;
        $rows->getCollection()->each(function (LeaveRequest $l) use ($year) {
            $entitled = (float) $l->leaveType->days_per_year;
            $l->balance_left = $entitled > 0 ? $entitled - LeaveRequest::takenDays($l->employee_id, $l->leave_type_id, $year) : null;
        });

        return view('leave.approvals', [
            'rows' => $rows,
            'tab' => $tab,
            'counts' => LeaveRequest::selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status'),
            'onLeaveToday' => LeaveRequest::with('employee', 'leaveType')->where('status', 'approved')
                ->whereDate('start_date', '<=', today()->toDateString())->whereDate('end_date', '>=', today()->toDateString())->get(),
        ]);
    }

    public function approve(Request $request, LeaveRequest $leaveRequest)
    {
        if ($leaveRequest->status !== 'pending') {
            return back()->with('error', __('This request has already been reviewed.'));
        }
        if ($leaveRequest->employee_id === $request->user()->employee_id && ! $request->user()->isAdmin()) {
            return back()->with('error', __('You cannot approve your own leave.'));
        }
        $leaveRequest->update(['status' => 'approved', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_note' => $request->input('review_note')]);

        return back()->with('ok', __('Leave approved for :name.', ['name' => $leaveRequest->employee->display_name]));
    }

    public function reject(Request $request, LeaveRequest $leaveRequest)
    {
        $data = $request->validate(['review_note' => 'required|string|max:255'], ['review_note.required' => __('Please give a reason for rejecting.')]);
        if ($leaveRequest->status !== 'pending') {
            return back()->with('error', __('This request has already been reviewed.'));
        }
        $leaveRequest->update(['status' => 'rejected', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_note' => $data['review_note']]);

        return back()->with('ok', __('Leave rejected for :name.', ['name' => $leaveRequest->employee->display_name]));
    }

    private function balances(int $employeeId, int $year, $types)
    {
        $pending = LeaveRequest::where('employee_id', $employeeId)->where('status', 'pending')->whereYear('start_date', $year)
            ->selectRaw('leave_type_id, SUM(days) d')->groupBy('leave_type_id')->pluck('d', 'leave_type_id');

        return $types->map(fn (LeaveType $t) => [
            'type' => $t,
            'entitled' => (float) $t->days_per_year,
            'taken' => LeaveRequest::takenDays($employeeId, $t->id, $year),
            'pending' => (float) ($pending[$t->id] ?? 0),
        ]);
    }
}
