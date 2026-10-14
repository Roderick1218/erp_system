@extends('layouts.app')
@section('title', __('Payroll'))
@section('content')
<x-page-header :eyebrow="__('HR').' · '.__('Payroll')" :title="__('Payroll')" :count="$runs->count()" :sub="__('Monthly payroll runs: generate as draft, adjust overtime / commission, then finalize to lock.')">
    <x-slot:actions><button class="btn btn-primary" data-dialog="dlg-run"><x-icon name="plus" class="ico ico-sm" />{{ __('Run payroll') }}</button></x-slot:actions>
</x-page-header>

<div class="alert alert-warning"><x-icon name="exclamation" /><div><b>{{ __('Simplified statutory calculation.') }}</b> {{ __($notice) }}</div></div>

<div class="panel">
    @if($runs->count())
        <div class="table-wrap"><table class="table">
            <thead><tr><th>{{ __('Pay month') }}</th><th>{{ __('Status') }}</th><th class="num">{{ __('Employees') }}</th><th class="num">{{ __('Gross') }}</th><th class="num">{{ __('Net pay') }}</th><th class="num hide-sm">{{ __('Employer EPF+SOCSO+EIS') }}</th><th class="hide-sm">{{ __('Created') }}</th><th class="chev-cell"></th></tr></thead>
            <tbody>@foreach($runs as $r)
                <tr data-href="{{ route('payroll.show', $r) }}">
                    <td class="primary">{{ \Illuminate\Support\Carbon::parse($r->period.'-01')->translatedFormat(app()->getLocale() === 'zh_CN' ? 'Y年n月' : 'F Y') }}<span class="sub mono">{{ $r->period }}</span></td>
                    <td><x-badge :status="$r->status" /></td>
                    <td class="num">{{ $r->payslips_count }}</td>
                    <td class="num">{{ money($r->payslips_sum_gross) }}</td>
                    <td class="num"><b>{{ money($r->payslips_sum_net_pay) }}</b></td>
                    <td class="num hide-sm">{{ money($r->payslips_sum_epf_employer + $r->payslips_sum_socso_employer + $r->payslips_sum_eis_employer) }}</td>
                    <td class="hide-sm small muted">{{ $r->creator?->name }} · {{ d($r->created_at) }}@if($r->finalized_at)<br>{{ __('Finalized :date', ['date' => d($r->finalized_at)]) }}@endif</td>
                    <td class="chev-cell"><x-icon name="chevron-right" class="ico ico-sm" /></td>
                </tr>
            @endforeach</tbody>
        </table></div>
    @else
        <x-empty icon="banknotes" :title="__('No payroll runs yet')">{{ __('Run payroll for a month to generate draft payslips for every active employee.') }}<div style="margin-top:10px"><button class="btn btn-primary btn-sm" data-dialog="dlg-run">{{ __('Run payroll') }}</button></div></x-empty>
    @endif
</div>

<dialog id="dlg-run">
    <form method="post" action="{{ route('payroll.store') }}">
        @csrf
        <div class="dlg-head"><h3>{{ __('Run payroll') }}</h3><button type="button" class="icon-btn" data-close><x-icon name="x" /></button></div>
        <div class="dlg-body">
            <x-field name="period" :label="__('Month')" type="month" :value="now()->format('Y-m')" required />
            <p class="small muted" style="margin:10px 0 0">{{ __('Creates a draft payslip for every active employee from their current basic salary and fixed allowance, deducts approved unpaid leave (basic ÷ 26 per day), and estimates EPF / SOCSO / EIS. Nothing is paid or locked until you finalize.') }}</p>
        </div>
        <div class="dlg-foot"><button type="button" class="btn btn-ghost" data-close>{{ __('Cancel') }}</button><button class="btn btn-primary">{{ __('Generate draft') }}</button></div>
    </form>
</dialog>
@if($errors->has('period'))<span data-reopen-dialog="dlg-run"></span>@endif
@endsection
