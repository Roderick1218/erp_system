@extends('layouts.app')
@section('title', __('Payslip').' '.$p->run->period)
@section('content')
@php
    $e = $p->employee;
    $period = \Illuminate\Support\Carbon::parse($p->run->period.'-01');
    $zh = app()->getLocale() === 'zh_CN';
    $deductions = (float) $p->epf_employee + (float) $p->socso_employee + (float) $p->eis_employee + (float) $p->other_deduction;
@endphp
<div class="print-bar no-print">
    @can('payroll.manage')<a class="btn btn-ghost" href="{{ route('payroll.show', $p->run) }}"><x-icon name="arrow-left" class="ico ico-sm" />{{ __('Back to payroll') }}</a>@endcan
    <button class="btn btn-primary" onclick="window.print()"><x-icon name="printer" class="ico ico-sm" />{{ __('Print / Save PDF') }}</button>
</div>
<div class="doc">
    <div class="doc-head">
        <div>
            <div class="co">{{ $e->branch?->legal_name ?? \App\Models\Setting::get('company_name') }}</div>
            <div class="small" style="color:#555">{{ $e->branch?->reg_no ?? \App\Models\Setting::get('company_reg_no') }}</div>
        </div>
        <div>
            <div class="doc-title">{{ __('PAYSLIP') }}</div>
            <div class="small" style="text-align:right">{{ $period->translatedFormat($zh ? 'Y年n月' : 'F Y') }} @if($p->run->status === 'draft')<b style="color:#c97a06">({{ __('DRAFT') }})</b>@endif</div>
        </div>
    </div>
    <table style="width:100%;font-size:12.5px;margin-bottom:10px">
        <tr><td style="width:18%;color:#666">{{ __('Name') }}</td><td><b>{{ $e->name }}</b></td><td style="width:18%;color:#666">{{ __('Employee no.') }}</td><td>{{ $e->emp_no }}</td></tr>
        <tr><td style="color:#666">{{ __('Position') }}</td><td>{{ $e->position }}</td><td style="color:#666">{{ __('Department') }}</td><td>{{ $e->department ? __($e->department->name) : '' }}</td></tr>
        <tr><td style="color:#666">{{ $e->ic_no ? __('IC no.') : __('Passport no.') }}</td><td>{{ $e->ic_no ?? $e->passport_no }}</td><td style="color:#666">{{ __('EPF / SOCSO') }}</td><td>{{ $e->epf_no ?? '—' }} / {{ $e->socso_no ?? '—' }}</td></tr>
        <tr><td style="color:#666">{{ __('Bank') }}</td><td>{{ $e->bank_name }} {{ $e->bank_account }}</td><td style="color:#666">{{ __('Pay period') }}</td><td>{{ d($period, 'd M') }} – {{ d($period->copy()->endOfMonth()) }}</td></tr>
    </table>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px">
        <table class="items">
            <thead><tr><th>{{ __('Earnings') }}</th><th style="text-align:right">RM</th></tr></thead>
            <tbody>
                <tr><td>{{ __('Basic salary') }}</td><td style="text-align:right">{{ money($p->basic, false) }}</td></tr>
                <tr><td>{{ __('Allowance') }}</td><td style="text-align:right">{{ money($p->allowance, false) }}</td></tr>
                @if($p->overtime > 0)<tr><td>{{ __('Overtime') }}</td><td style="text-align:right">{{ money($p->overtime, false) }}</td></tr>@endif
                @if($p->commission > 0)<tr><td>{{ __('Commission') }}</td><td style="text-align:right">{{ money($p->commission, false) }}</td></tr>@endif
                @if($p->unpaid_leave > 0)<tr><td>{{ __('Unpaid Leave') }}</td><td style="text-align:right">-{{ money($p->unpaid_leave, false) }}</td></tr>@endif
                <tr><td><b>{{ __('Gross pay') }}</b></td><td style="text-align:right"><b>{{ money($p->gross, false) }}</b></td></tr>
            </tbody>
        </table>
        <table class="items">
            <thead><tr><th>{{ __('Deductions') }}</th><th style="text-align:right">RM</th></tr></thead>
            <tbody>
                <tr><td>{{ __('EPF (employee)') }}</td><td style="text-align:right">{{ money($p->epf_employee, false) }}</td></tr>
                <tr><td>{{ __('SOCSO (employee)') }}</td><td style="text-align:right">{{ money($p->socso_employee, false) }}</td></tr>
                <tr><td>{{ __('EIS (employee)') }}</td><td style="text-align:right">{{ money($p->eis_employee, false) }}</td></tr>
                @if($p->other_deduction > 0)<tr><td>{{ __('Other deduction') }}</td><td style="text-align:right">{{ money($p->other_deduction, false) }}</td></tr>@endif
                <tr><td><b>{{ __('Total deductions') }}</b></td><td style="text-align:right"><b>{{ money($deductions, false) }}</b></td></tr>
            </tbody>
        </table>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:center;background:#f2f6fb;border:1px solid #d6e0ee;border-radius:6px;padding:12px 16px;margin-top:6px">
        <span style="font-weight:600">{{ __('NET PAY') }}</span><span style="font-size:20px;font-weight:700;color:var(--navy)">RM {{ money($p->net_pay, false) }}</span>
    </div>
    <div class="small" style="margin-top:12px;color:#555">
        {{ __('Employer contributions:') }} EPF {{ money($p->epf_employer, false) }} · SOCSO {{ money($p->socso_employer, false) }} · EIS {{ money($p->eis_employer, false) }}
        @if($p->note)<br>{{ __('Note:') }} {{ $p->note }}@endif
    </div>
    <div class="small" style="margin-top:14px;color:#8a6d1a;border-top:1px dashed #ddd;padding-top:8px">{{ __($notice) }}</div>
    <div class="small" style="margin-top:6px;color:#888">{{ __('This is a computer-generated payslip. No signature is required.') }}</div>
</div>
@endsection
