<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $tab = in_array($request->input('tab'), ['active', 'resigned', 'all'], true) ? $request->input('tab') : 'active';

        $q = Employee::query()->with(['branch', 'department']);
        if ($s = trim((string) $request->input('q'))) {
            $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('nickname', 'like', "%$s%")->orWhere('emp_no', 'like', "%$s%")
                ->orWhere('phone', 'like', "%$s%")->orWhere('ic_no', 'like', "%$s%")->orWhere('passport_no', 'like', "%$s%"));
        }
        $q->when($request->input('branch'), fn ($w, $v) => $w->where('branch_id', $v))
            ->when($request->input('department'), fn ($w, $v) => $w->where('department_id', $v))
            ->when($request->input('nationality'), fn ($w, $v) => $w->where('nationality', $v))
            ->when($request->input('employment_type'), fn ($w, $v) => $w->where('employment_type', $v))
            ->when($request->boolean('expiring'), function (Builder $w) {
                $soon = now()->addDays(30)->toDateString();
                $w->where(fn ($x) => $x->whereDate('passport_expiry', '<=', $soon)->orWhereDate('work_permit_expiry', '<=', $soon)
                    ->orWhereDate('cidb_expiry', '<=', $soon)->orWhereDate('gdl_expiry', '<=', $soon));
            });
        $counts = [
            'active' => (clone $q)->where('status', 'active')->count(),
            'resigned' => (clone $q)->where('status', '!=', 'active')->count(),
            'all' => (clone $q)->count(),
        ];
        if ($tab === 'active') {
            $q->where('status', 'active');
        } elseif ($tab === 'resigned') {
            $q->where('status', '!=', 'active');
        }

        return view('employees.index', [
            'rows' => $q->orderBy('emp_no')->paginate(25)->withQueryString(),
            'tab' => $tab,
            'counts' => $counts,
            'branches' => Branch::orderBy('name')->pluck('legal_name', 'id'),
            'departments' => Department::orderBy('name')->pluck('name', 'id'),
            'nationalities' => Employee::query()->distinct()->orderBy('nationality')->pluck('nationality'),
        ]);
    }

    public function show(Request $request, Employee $employee)
    {
        $employee->load(['branch', 'department', 'user.roles']);
        $year = now()->year;
        $monthStart = now()->startOfMonth();

        $balances = LeaveType::orderBy('id')->get()->map(fn (LeaveType $t) => [
            'type' => $t,
            'entitled' => (float) $t->days_per_year,
            'taken' => LeaveRequest::takenDays($employee->id, $t->id, $year),
        ]);

        return view('employees.show', [
            'e' => $employee,
            'balances' => $balances,
            'leaves' => $employee->leaveRequests()->with('leaveType', 'reviewer')->latest('start_date')->limit(30)->get(),
            'attendance' => Attendance::where('employee_id', $employee->id)->whereDate('work_date', '>=', $monthStart->toDateString())->orderByDesc('work_date')->get(),
            'payslips' => $employee->payslips()->with('run')->latest('id')->get(),
        ]);
    }

    public function create()
    {
        return view('employees.form', ['e' => new Employee([
            'status' => 'active', 'employment_type' => 'permanent', 'nationality' => 'MALAYSIA', 'hire_date' => now(),
            'emp_no' => $this->nextNo(), 'branch_id' => auth()->user()->branch_id,
        ]), ...$this->lookups()]);
    }

    public function store(Request $request)
    {
        $employee = Employee::create($this->validated($request));

        return redirect()->route('employees.show', $employee)->with('ok', __('Employee :no created.', ['no' => $employee->emp_no]));
    }

    public function edit(Employee $employee)
    {
        return view('employees.form', ['e' => $employee, ...$this->lookups()]);
    }

    public function update(Request $request, Employee $employee)
    {
        $employee->update($this->validated($request, $employee));

        return redirect()->route('employees.show', $employee)->with('ok', __('Employee updated.'));
    }

    public function destroy(Employee $employee)
    {
        if ($employee->payslips()->exists()) {
            return back()->with('error', __('This employee has payslips — set the status to Resigned instead of deleting.'));
        }
        $employee->delete();

        return redirect()->route('employees.index')->with('ok', __('Employee :no deleted.', ['no' => $employee->emp_no]));
    }

    // ------------------------------------------------------------------

    private function validated(Request $request, ?Employee $employee = null): array
    {
        $data = $request->validate([
            'emp_no' => ['required', 'string', 'max:32', Rule::unique('employees', 'emp_no')->ignore($employee?->id)],
            'name' => 'required|string|max:255',
            'nickname' => 'nullable|string|max:64',
            'gender' => 'nullable|in:M,F',
            'dob' => 'nullable|date|before:today',
            'nationality' => 'required|string|max:64',
            'ic_no' => 'nullable|string|max:32',
            'phone' => 'nullable|string|max:32',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:1000',
            'emergency_contact' => 'nullable|string|max:255',
            'branch_id' => 'nullable|exists:branches,id',
            'department_id' => 'nullable|exists:departments,id',
            'position' => 'nullable|string|max:100',
            'employment_type' => 'required|in:'.implode(',', array_keys(Employee::EMPLOYMENT_TYPES)),
            'hire_date' => 'nullable|date',
            'resign_date' => 'nullable|date|after_or_equal:hire_date',
            'status' => 'required|in:active,resigned,suspended',
            'passport_no' => 'nullable|string|max:32',
            'passport_expiry' => 'nullable|date',
            'work_permit_no' => 'nullable|string|max:64',
            'work_permit_expiry' => 'nullable|date',
            'cidb_no' => 'nullable|string|max:64',
            'cidb_expiry' => 'nullable|date',
            'gdl_no' => 'nullable|string|max:64',
            'gdl_expiry' => 'nullable|date',
            'basic_salary' => 'required|numeric|min:0|max:999999',
            'fixed_allowance' => 'nullable|numeric|min:0|max:999999',
            'epf_no' => 'nullable|string|max:32',
            'socso_no' => 'nullable|string|max:32',
            'tax_no' => 'nullable|string|max:32',
            'bank_name' => 'nullable|string|max:64',
            'bank_account' => 'nullable|string|max:64',
        ]);
        $data['name'] = mb_strtoupper($data['name']);
        $data['nickname'] = isset($data['nickname']) ? mb_strtoupper($data['nickname']) : null;
        $data['nationality'] = mb_strtoupper($data['nationality']);
        $data['fixed_allowance'] ??= 0;
        if ($data['status'] === 'resigned' && empty($data['resign_date'])) {
            $data['resign_date'] = now()->toDateString();
        }

        return $data;
    }

    private function lookups(): array
    {
        return [
            'branches' => Branch::orderBy('name')->pluck('legal_name', 'id')->all(),
            'departments' => Department::orderBy('name')->pluck('name', 'id')->all(),
            'nationalities' => array_values(array_unique(array_merge(['MALAYSIA', 'BANGLADESH', 'MYANMAR', 'NEPAL', 'INDONESIA', 'INDIA'], Employee::query()->distinct()->pluck('nationality')->all()))),
        ];
    }

    private function nextNo(): string
    {
        $last = Employee::withTrashed()->where('emp_no', 'like', 'E%')->orderByDesc('emp_no')->value('emp_no');

        return sprintf('E%04d', $last ? ((int) substr($last, 1)) + 1 : 1);
    }
}
