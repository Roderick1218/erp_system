<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Services\StatutoryCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PayrollController extends Controller
{
    public function __construct(private StatutoryCalculator $calc) {}

    public function index()
    {
        $runs = PayrollRun::with('creator')->withCount('payslips')
            ->withSum('payslips', 'gross')->withSum('payslips', 'net_pay')
            ->withSum('payslips', 'epf_employer')->withSum('payslips', 'socso_employer')->withSum('payslips', 'eis_employer')
            ->orderByDesc('period')->get();

        return view('payroll.index', ['runs' => $runs, 'notice' => StatutoryCalculator::NOTICE]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['period' => 'required|date_format:Y-m|unique:payroll_runs,period'], ['period.unique' => __('A payroll run for that month already exists.')]);
        $run = DB::transaction(function () use ($data, $request) {
            $run = PayrollRun::create(['period' => $data['period'], 'status' => 'draft', 'created_by' => $request->user()->id]);
            $this->generate($run);

            return $run;
        });

        return redirect()->route('payroll.show', $run)->with('ok', __('Payroll :period generated for :n employees (draft).', ['period' => $run->period, 'n' => $run->payslips()->count()]));
    }

    public function show(PayrollRun $payrollRun)
    {
        $payrollRun->load('creator');

        return view('payroll.show', [
            'run' => $payrollRun,
            'slips' => $payrollRun->payslips()->with('employee.department', 'employee.branch')->get()->sortBy('employee.emp_no')->values(),
            'notice' => StatutoryCalculator::NOTICE,
        ]);
    }

    public function export(PayrollRun $payrollRun)
    {
        $rows = $payrollRun->payslips()->with('employee')->get()->sortBy('employee.emp_no')->map(fn (Payslip $p) => [
            $p->employee->emp_no, $p->employee->name, $p->employee->bank_name, $p->employee->bank_account,
            $p->basic, $p->allowance, $p->overtime, $p->commission, $p->unpaid_leave, $p->gross,
            $p->epf_employee, $p->socso_employee, $p->eis_employee, $p->other_deduction, $p->net_pay,
            $p->epf_employer, $p->socso_employer, $p->eis_employer, $p->note,
        ]);

        return csv_download("payroll-{$payrollRun->period}.csv", [
            __('Emp no'), __('Name'), __('Bank'), __('Account'), __('Basic'), __('Allowance'), __('Overtime'), __('Commission'), __('Unpaid leave'), __('Gross'),
            __('EPF (EE)'), __('SOCSO (EE)'), __('EIS (EE)'), __('Other deduction'), __('Net pay'), __('EPF (ER)'), __('SOCSO (ER)'), __('EIS (ER)'), __('Note'),
        ], $rows);
    }

    public function regenerate(PayrollRun $payrollRun)
    {
        if ($payrollRun->status !== 'draft') {
            return back()->with('error', __('Finalized payroll cannot be regenerated.'));
        }
        DB::transaction(fn () => $this->generate($payrollRun));

        return back()->with('ok', __('Payslips regenerated from current employee salaries (overtime / commission / deductions kept).'));
    }

    public function finalize(Request $request, PayrollRun $payrollRun)
    {
        if ($payrollRun->status !== 'draft') {
            return back()->with('error', __('Already finalized.'));
        }
        $payrollRun->update(['status' => 'finalized', 'finalized_at' => now()]);

        return back()->with('ok', __('Payroll :period finalized and locked.', ['period' => $payrollRun->period]));
    }

    public function updatePayslip(Request $request, Payslip $payslip)
    {
        abort_if($payslip->run->status !== 'draft', 422, __('This payroll run is finalized.'));
        $data = $request->validate([
            'overtime' => 'nullable|numeric|min:0|max:999999',
            'commission' => 'nullable|numeric|min:0|max:999999',
            'allowance' => 'nullable|numeric|min:0|max:999999',
            'other_deduction' => 'nullable|numeric|min:0|max:999999',
            'note' => 'nullable|string|max:255',
        ]);
        $payslip->fill(array_map(fn ($v) => $v ?? 0, array_diff_key($data, ['note' => 1])) + ['note' => $data['note'] ?? null]);
        $this->recompute($payslip, $payslip->employee, $this->periodEnd($payslip->run));
        $payslip->save();

        return back()->with('ok', __('Payslip for :name updated.', ['name' => $payslip->employee->display_name]));
    }

    /** Printable payslip — the employee's own, or anyone's for payroll managers. */
    public function payslip(Request $request, Payslip $payslip)
    {
        $user = $request->user();
        abort_unless($user->can('payroll.manage') || ($user->employee_id && $user->employee_id === $payslip->employee_id), 403);
        $payslip->load('run', 'employee.department', 'employee.branch');

        return view('payroll.payslip', ['p' => $payslip, 'notice' => StatutoryCalculator::NOTICE]);
    }

    // ------------------------------------------------------------------

    private function periodEnd(PayrollRun $run): Carbon
    {
        return Carbon::parse($run->period.'-01')->endOfMonth()->startOfDay();
    }

    /** (Re)build payslips for every active employee employed during the period. */
    private function generate(PayrollRun $run): void
    {
        $start = Carbon::parse($run->period.'-01')->startOfDay();
        $end = $this->periodEnd($run);
        $existing = $run->payslips()->get()->keyBy('employee_id');

        $employees = Employee::active()->where(fn ($q) => $q->whereNull('hire_date')->orWhereDate('hire_date', '<=', $end->toDateString()))->get();
        $keep = [];
        foreach ($employees as $e) {
            $slip = $existing[$e->id] ?? new Payslip(['payroll_run_id' => $run->id, 'employee_id' => $e->id, 'overtime' => 0, 'commission' => 0, 'other_deduction' => 0]);
            $slip->basic = $e->basic_salary;
            $slip->allowance = $e->fixed_allowance;
            $unpaidDays = $this->unpaidLeaveDays($e, $start, $end);
            $slip->unpaid_leave = round((float) $e->basic_salary / 26 * $unpaidDays, 2);
            $slip->note = $slip->note ?: ($unpaidDays ? qty($unpaidDays).' day(s) unpaid leave' : null);
            $this->recompute($slip, $e, $end);
            $slip->save();
            $keep[] = $slip->id;
        }
        $run->payslips()->whereNotIn('id', $keep)->delete();
    }

    private function unpaidLeaveDays(Employee $e, Carbon $start, Carbon $end): float
    {
        $days = 0;
        $leaves = LeaveRequest::with('leaveType')->where('employee_id', $e->id)->where('status', 'approved')
            ->whereHas('leaveType', fn ($q) => $q->where('is_paid', false))
            ->whereDate('start_date', '<=', $end->toDateString())->whereDate('end_date', '>=', $start->toDateString())->get();
        foreach ($leaves as $l) {
            $from = $l->start_date->max($start);
            $to = $l->end_date->min($end);
            // Only the part of the leave inside this month; min() keeps half-day requests at 0.5.
            $days += min((float) $l->days, LeaveRequest::countDays($from, $to, $e->branch_id));
        }

        return $days;
    }

    private function recompute(Payslip $slip, Employee $e, Carbon $asOf): void
    {
        $gross = (float) $slip->basic + (float) $slip->allowance + (float) $slip->overtime + (float) $slip->commission - (float) $slip->unpaid_leave;
        $gross = round(max(0, $gross), 2);
        $s = $this->calc->calculate($e, $gross, $asOf);
        $slip->fill($s);
        $slip->gross = $gross;
        $slip->net_pay = round($gross - $s['epf_employee'] - $s['socso_employee'] - $s['eis_employee'] - (float) $slip->other_deduction, 2);
    }
}
