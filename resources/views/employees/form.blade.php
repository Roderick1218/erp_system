@extends('layouts.app')
@section('title', $e->exists ? __('Edit').' '.$e->emp_no : __('New employee'))
@section('content')
<x-page-header :eyebrow="__('HR').' · '.__('Employees').' · '.($e->exists ? $e->emp_no : __('New'))" :title="$e->exists ? __('Edit employee') : __('New employee')" :sub="__('Fields marked * are required. Ctrl+S saves.')">
    <x-slot:actions><a class="btn btn-ghost" href="{{ $e->exists ? route('employees.show', $e) : route('employees.index') }}">{{ __('Cancel') }}</a></x-slot:actions>
</x-page-header>

<form method="post" action="{{ $e->exists ? route('employees.update', $e) : route('employees.store') }}" class="panel" data-main-form>
    @csrf @if($e->exists) @method('PUT') @endif
    <div class="form-section">
        <h4><span class="step">1</span>{{ __('Personal') }}</h4>
        <div class="form-grid cols-4">
            <x-field name="name" :label="__('Full name (as per IC / passport)')" :value="$e->name" required class="span-2" />
            <x-field name="nickname" :label="__('Nickname')" :value="$e->nickname" />
            <x-field name="gender" :label="__('Gender')" type="select" :options="['M' => __('Male'), 'F' => __('Female')]" placeholder="—" :value="$e->gender" />
            <x-field name="dob" :label="__('Date of birth')" type="date" :value="$e->dob" />
            <x-field name="nationality" :label="__('Nationality')" type="select" :options="$nationalities" :value="$e->nationality" required />
            <x-field name="ic_no" :label="__('IC no. (MyKad)')" :value="$e->ic_no" placeholder="900101-10-1234" />
            <x-field name="phone" :label="__('Phone')" :value="$e->phone" />
            <x-field name="email" :label="__('Email')" type="email" :value="$e->email" class="span-2" />
            <x-field name="emergency_contact" :label="__('Emergency contact')" :value="$e->emergency_contact" :placeholder="__('Name · relationship · phone')" class="span-2" />
            <x-field name="address" :label="__('Address')" type="textarea" :value="$e->address" class="span-all" />
        </div>
    </div>
    <div class="form-section">
        <h4><span class="step">2</span>{{ __('Employment') }}</h4>
        <div class="form-grid cols-4">
            <x-field name="emp_no" :label="__('Employee no.')" :value="$e->emp_no" required />
            <x-field name="position" :label="__('Position')" :value="$e->position" />
            <x-field name="department_id" :label="__('Department')" type="select" :options="collect($departments)->map(fn ($n) => __($n))->all()" placeholder="—" :value="$e->department_id" />
            <x-field name="branch_id" :label="__('Branch')" type="select" :options="$branches" placeholder="—" :value="$e->branch_id" />
            <x-field name="employment_type" :label="__('Employment type')" type="select" :options="collect(\App\Models\Employee::EMPLOYMENT_TYPES)->map(fn ($v) => __($v))->all()" :value="$e->employment_type" required />
            <x-field name="hire_date" :label="__('Hire date')" type="date" :value="$e->hire_date" />
            <x-field name="status" :label="__('Status')" type="select" :options="['active' => __('Active'), 'resigned' => __('Resigned'), 'suspended' => __('Suspended')]" :value="$e->status" required />
            <x-field name="resign_date" :label="__('Resign date')" type="date" :value="$e->resign_date" :hint="__('Auto-set to today when status is Resigned.')" />
        </div>
    </div>
    <div class="form-section">
        <h4><span class="step">3</span>{{ __('Documents & expiry') }}</h4>
        <div class="form-grid cols-4">
            <x-field name="passport_no" :label="__('Passport no.')" :value="$e->passport_no" />
            <x-field name="passport_expiry" :label="__('Passport expiry')" type="date" :value="$e->passport_expiry" />
            <x-field name="work_permit_no" :label="__('Work permit (PLKS) no.')" :value="$e->work_permit_no" />
            <x-field name="work_permit_expiry" :label="__('Work permit expiry')" type="date" :value="$e->work_permit_expiry" />
            <x-field name="cidb_no" :label="__('CIDB card no.')" :value="$e->cidb_no" />
            <x-field name="cidb_expiry" :label="__('CIDB expiry')" type="date" :value="$e->cidb_expiry" />
            <x-field name="gdl_no" :label="__('GDL licence no.')" :value="$e->gdl_no" />
            <x-field name="gdl_expiry" :label="__('GDL expiry')" type="date" :value="$e->gdl_expiry" />
        </div>
        <div class="hint small muted" style="margin-top:8px">{{ __('Documents expiring within 30 days appear on the dashboard and in the notification bell.') }}</div>
    </div>
    <div class="form-section">
        <h4><span class="step">4</span>{{ __('Payroll & statutory') }}</h4>
        <div class="form-grid cols-4">
            <x-field name="basic_salary" :label="__('Basic salary (RM / month)')" type="number" step="0.01" min="0" :value="$e->basic_salary" required />
            <x-field name="fixed_allowance" :label="__('Fixed allowance (RM / month)')" type="number" step="0.01" min="0" :value="$e->fixed_allowance" />
            <x-field name="epf_no" :label="__('EPF (KWSP) no.')" :value="$e->epf_no" />
            <x-field name="socso_no" :label="__('SOCSO (PERKESO) no.')" :value="$e->socso_no" />
            <x-field name="tax_no" :label="__('Income tax no.')" :value="$e->tax_no" />
            <x-field name="bank_name" :label="__('Bank')" :value="$e->bank_name" />
            <x-field name="bank_account" :label="__('Bank account no.')" :value="$e->bank_account" class="span-2" />
        </div>
    </div>
    <div class="form-actions">
        <a class="btn btn-ghost" href="{{ $e->exists ? route('employees.show', $e) : route('employees.index') }}">{{ __('Cancel') }}</a>
        <button class="btn btn-primary"><x-icon name="check" class="ico ico-sm" />{{ $e->exists ? __('Save changes') : __('Create employee') }}</button>
    </div>
</form>
@endsection
