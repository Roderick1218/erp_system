<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Department;
use App\Models\LeaveType;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SettingController extends Controller
{
    private const LABELS = [
        'company_name' => ['Company name', 'text', 'Printed on documents and the footer.'],
        'company_reg_no' => ['Company registration no.', 'text', null],
        'work_start' => ['Work start time', 'time', 'Clock-ins after this time + grace are marked late.'],
        'late_grace_minutes' => ['Late grace (minutes)', 'number', null],
        'sst_rate' => ['Default SST rate (%)', 'number', 'Pre-filled on new quotations / invoices.'],
        'quotation_validity_days' => ['Quotation validity (days)', 'number', null],
        'invoice_due_days' => ['Invoice due (days)', 'number', null],
        'quotation_terms' => ['Default quotation terms', 'textarea', null],
    ];

    public function index()
    {
        return view('settings.index', [
            'fields' => collect(self::LABELS)->map(fn ($meta, $key) => ['label' => $meta[0], 'type' => $meta[1], 'hint' => $meta[2], 'value' => Setting::get($key)]),
            'branches' => Branch::withCount(['employees', 'customers'])->orderBy('code')->get(),
            'departments' => Department::withCount('employees')->orderBy('code')->get(),
            'leaveTypes' => LeaveType::withCount('requests')->orderBy('id')->get(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'company_name' => 'required|string|max:255',
            'company_reg_no' => 'nullable|string|max:64',
            'work_start' => 'required|date_format:H:i',
            'late_grace_minutes' => 'required|integer|min:0|max:240',
            'sst_rate' => 'required|numeric|min:0|max:100',
            'quotation_validity_days' => 'required|integer|min:1|max:365',
            'invoice_due_days' => 'required|integer|min:0|max:365',
            'quotation_terms' => 'nullable|string|max:5000',
        ]);
        foreach ($data as $k => $v) {
            Setting::put($k, $v === null ? null : (string) $v);
        }

        return back()->with('ok', __('Company settings saved.'));
    }

    public function storeLookup(Request $request, string $kind)
    {
        $id = $request->input('id');
        [$model, $rules] = match ($kind) {
            'branches' => [Branch::class, [
                'code' => ['required', 'string', 'max:16', Rule::unique('branches', 'code')->ignore($id)],
                'name' => 'required|string|max:255', 'legal_name' => 'nullable|string|max:255', 'reg_no' => 'nullable|string|max:64',
                'phone' => 'nullable|string|max:32', 'address' => 'nullable|string|max:1000',
            ]],
            'departments' => [Department::class, [
                'code' => ['required', 'string', 'max:16', Rule::unique('departments', 'code')->ignore($id)],
                'name' => 'required|string|max:255',
            ]],
            'leave-types' => [LeaveType::class, [
                'code' => ['required', 'string', 'max:16', Rule::unique('leave_types', 'code')->ignore($id)],
                'name' => 'required|string|max:64', 'days_per_year' => 'required|numeric|min:0|max:365',
                'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            ]],
        };
        $data = $request->validate($rules);
        $data['code'] = strtoupper($data['code']);
        if ($kind === 'leave-types') {
            $data['is_paid'] = $request->boolean('is_paid');
        }
        $id ? $model::findOrFail($id)->update($data) : $model::create($data);

        return redirect()->to(route('settings.index').'#'.$kind)->with('ok', $id ? __('Updated :name.', ['name' => $data['name']]) : __('Added :name.', ['name' => $data['name']]));
    }

    public function destroyLookup(string $kind, int $id)
    {
        $usage = match ($kind) {
            'branches' => collect(['employees', 'customers', 'users', 'warehouses', 'projects', 'enquiries'])->sum(fn ($t) => DB::table($t)->where('branch_id', $id)->count()),
            'departments' => DB::table('employees')->where('department_id', $id)->count(),
            'leave-types' => DB::table('leave_requests')->where('leave_type_id', $id)->count(),
        };
        if ($usage) {
            return back()->with('error', __('Cannot delete — it is used by :n record(s).', ['n' => $usage]));
        }
        $model = ['branches' => Branch::class, 'departments' => Department::class, 'leave-types' => LeaveType::class][$kind];
        $model::findOrFail($id)->delete();

        return back()->with('ok', __('Deleted.'));
    }
}
