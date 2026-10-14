@extends('layouts.app')
@section('title', __('Payroll').' '.$run->period)
@section('content')
@php
    $sum = fn ($k) => $slips->sum(fn ($s) => (float) $s->{$k});
    $draft = $run->status === 'draft';
    $periodLabel = \Illuminate\Support\Carbon::parse($run->period.'-01')->translatedFormat(app()->getLocale() === 'zh_CN' ? 'Y年n月' : 'F Y');
    $sub = __(':n payslips · created by :name', ['n' => $slips->count(), 'name' => $run->creator?->name ?? '—'])
        .($run->finalized_at ? ' · '.__('finalized :date', ['date' => d($run->finalized_at, 'd M Y H:i')]) : '');
@endphp
<x-page-header :eyebrow="__('HR').' · '.__('Payroll').' · '.$run->period" :title="__('Payroll — :period', ['period' => $periodLabel])" :sub="$sub">
    <x-slot:badge><x-badge :status="$run->status" /></x-slot:badge>
    <x-slot:actions>
        <a class="btn btn-ghost" href="{{ route('payroll.export', $run) }}"><x-icon name="download" class="ico ico-sm" />{{ __('Export CSV') }}</a>
        @if($draft)
            <form method="post" action="{{ route('payroll.regenerate', $run) }}" data-confirm="{{ __('Regenerate all payslips from current employee salaries? Overtime, commission and deductions you entered are kept.') }}">@csrf<button class="btn btn-ghost"><x-icon name="refresh" class="ico ico-sm" />{{ __('Regenerate') }}</button></form>
            <form method="post" action="{{ route('payroll.finalize', $run) }}" data-confirm="{{ __('Finalize payroll :period? Payslips will be locked and can no longer be edited.', ['period' => $run->period]) }}">@csrf<button class="btn btn-primary"><x-icon name="lock" class="ico ico-sm" />{{ __('Finalize') }}</button></form>
        @endif
    </x-slot:actions>
</x-page-header>

<div class="alert alert-warning"><x-icon name="exclamation" /><div><b>{{ __('Simplified statutory calculation.') }}</b> {{ __($notice) }}</div></div>

<div class="kpis" style="margin-bottom:16px">
    <x-stat :label="__('Total gross')" :value="money($sum('gross'))" icon="banknotes" tone="info" />
    <x-stat :label="__('Total net pay')" :value="money($sum('net_pay'))" icon="check-badge" tone="success" :hint="__('Bank transfer total')" />
    <x-stat :label="__('Employee contributions')" :value="money($sum('epf_employee') + $sum('socso_employee') + $sum('eis_employee'))" icon="user" tone="neutral" :hint="__('EPF + SOCSO + EIS (deducted)')" />
    <x-stat :label="__('Employer contributions')" :value="money($sum('epf_employer') + $sum('socso_employer') + $sum('eis_employer'))" icon="building" tone="warning" :hint="__('Company cost on top of gross')" />
</div>

<div class="panel">
    <div class="table-wrap">
        <table class="table compact">
            <thead><tr>
                <th>{{ __('Employee') }}</th><th class="num">{{ __('Basic') }}</th><th class="num">{{ __('Allow.') }}</th><th class="num">{{ __('OT') }}</th><th class="num">{{ __('Comm.') }}</th><th class="num">{{ __('Unpaid lv') }}</th><th class="num">{{ __('Gross') }}</th>
                <th class="num">{{ __('EPF EE') }}</th><th class="num">{{ __('SOCSO EE') }}</th><th class="num">{{ __('EIS EE') }}</th><th class="num">{{ __('Other ded.') }}</th><th class="num">{{ __('Net pay') }}</th>
                <th class="num hide-sm">{{ __('EPF ER') }}</th><th class="num hide-sm">{{ __('SOCSO ER') }}</th><th class="num hide-sm">{{ __('EIS ER') }}</th><th class="actions"></th>
            </tr></thead>
            <tbody>
            @foreach($slips as $s)
                <tr>
                    <td class="primary" style="white-space:nowrap">{{ $s->employee->display_name }}<span class="sub">{{ $s->employee->emp_no }} · {{ $s->employee->nationality }}@if($s->note) · {{ $s->note }}@endif</span></td>
                    <td class="num">{{ money($s->basic, false) }}</td>
                    <td class="num">{{ money($s->allowance, false) }}</td>
                    <td class="num">{{ money($s->overtime, false) }}</td>
                    <td class="num">{{ money($s->commission, false) }}</td>
                    <td class="num {{ $s->unpaid_leave > 0 ? 'text-danger' : '' }}">{{ $s->unpaid_leave > 0 ? '-'.money($s->unpaid_leave, false) : '0.00' }}</td>
                    <td class="num"><b>{{ money($s->gross, false) }}</b></td>
                    <td class="num">{{ money($s->epf_employee, false) }}</td>
                    <td class="num">{{ money($s->socso_employee, false) }}</td>
                    <td class="num">{{ money($s->eis_employee, false) }}</td>
                    <td class="num">{{ money($s->other_deduction, false) }}</td>
                    <td class="num"><b>{{ money($s->net_pay, false) }}</b></td>
                    <td class="num hide-sm muted">{{ money($s->epf_employer, false) }}</td>
                    <td class="num hide-sm muted">{{ money($s->socso_employer, false) }}</td>
                    <td class="num hide-sm muted">{{ money($s->eis_employer, false) }}</td>
                    <td class="actions">
                        @if($draft)
                            <button class="btn btn-ghost btn-sm" data-dialog="dlg-slip" data-action="{{ route('payslips.update', $s) }}" data-title="{{ __('Adjust — :name', ['name' => $s->employee->display_name]) }}"
                                    data-fill="{{ json_encode(['allowance' => $s->allowance, 'overtime' => $s->overtime, 'commission' => $s->commission, 'other_deduction' => $s->other_deduction, 'note' => $s->note]) }}"><x-icon name="pencil" class="ico ico-sm" /></button>
                        @endif
                        <a class="btn btn-ghost btn-sm" href="{{ route('payslips.show', $s) }}" title="{{ __('Payslip') }}"><x-icon name="printer" class="ico ico-sm" /></a>
                    </td>
                </tr>
            @endforeach
            </tbody>
            <tfoot><tr>
                <td>{{ __('Total (:n)', ['n' => $slips->count()]) }}</td>
                @foreach(['basic', 'allowance', 'overtime', 'commission', 'unpaid_leave', 'gross', 'epf_employee', 'socso_employee', 'eis_employee', 'other_deduction', 'net_pay'] as $k)<td class="num">{{ money($sum($k), false) }}</td>@endforeach
                @foreach(['epf_employer', 'socso_employer', 'eis_employer'] as $k)<td class="num hide-sm">{{ money($sum($k), false) }}</td>@endforeach
                <td></td>
            </tr></tfoot>
        </table>
    </div>
</div>

<dialog id="dlg-slip">
    <form method="post" action="">
        @csrf @method('PUT')
        <div class="dlg-head"><h3>{{ __('Adjust payslip') }}</h3><button type="button" class="icon-btn" data-close><x-icon name="x" /></button></div>
        <div class="dlg-body form-grid">
            <x-field name="allowance" :label="__('Allowance (RM)')" type="number" step="0.01" min="0" />
            <x-field name="overtime" :label="__('Overtime (RM)')" type="number" step="0.01" min="0" />
            <x-field name="commission" :label="__('Commission (RM)')" type="number" step="0.01" min="0" />
            <x-field name="other_deduction" :label="__('Other deduction (RM)')" type="number" step="0.01" min="0" :hint="__('Advance, loan repayment, etc.')" />
            <x-field name="note" :label="__('Note (printed on payslip)')" class="span-all" />
            <div class="hint small muted span-all">{{ __('EPF / SOCSO / EIS and net pay are recalculated when you save.') }}</div>
        </div>
        <div class="dlg-foot"><button type="button" class="btn btn-ghost" data-close>{{ __('Cancel') }}</button><button class="btn btn-primary">{{ __('Save & recalculate') }}</button></div>
    </form>
</dialog>
@endsection
