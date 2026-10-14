@extends('layouts.app')
@section('title', __('Payments Received'))
@section('content')
<x-page-header :eyebrow="__('Sales').' · '.__('Payments')" :title="__('Payments Received')" :count="$rows->total()" :sub="__('Every receipt recorded against an invoice. Record new payments from the invoice page.')" />

@if($byMethod->count())
    <div class="kpis" style="margin-bottom:16px">
        <x-stat :label="__('Total received (filtered)')" :value="money($sum)" icon="banknotes" tone="success" />
        @foreach($byMethod->sortByDesc('s')->take(3) as $m)
            <x-stat :label="__($m->method)" :value="money($m->s)" icon="receipt" tone="info" :hint="__(':n payment(s)', ['n' => $m->n])" />
        @endforeach
    </div>
@endif

<div class="panel">
    <form method="get" class="filters">
        <div class="filters-title"><x-icon name="funnel" class="ico ico-sm" />{{ __('Filters') }}</div>
        <div class="filter-row">
            <div class="field grow"><label>{{ __('Search') }}</label><input name="q" value="{{ request('q') }}" placeholder="{{ __('Reference, invoice no, customer…') }}"></div>
            <div class="field"><label>{{ __('From') }}</label><input type="date" name="from" value="{{ request('from') }}"></div>
            <div class="field"><label>{{ __('To') }}</label><input type="date" name="to" value="{{ request('to') }}"></div>
            <div class="field"><label>{{ __('Method') }}</label><select name="method"><option value="">{{ __('All') }}</option>@foreach($methods as $m)<option value="{{ $m }}" @selected(request('method') === $m)>{{ __($m) }}</option>@endforeach</select></div>
            <div class="actions">
                @if(request()->except('page'))<a class="btn btn-ghost" href="{{ route('payments.index') }}">{{ __('Reset') }}</a>@endif
                <button class="btn btn-primary"><x-icon name="search" class="ico ico-sm" />{{ __('Search') }}</button>
            </div>
        </div>
    </form>
    @if($rows->count())
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Invoice') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Method') }}</th><th class="hide-sm">{{ __('Reference') }}</th><th class="hide-sm">{{ __('Recorded by') }}</th><th class="num">{{ __('Amount') }}</th></tr></thead>
                <tbody>
                @foreach($rows as $p)
                    <tr data-href="{{ route('invoices.show', $p->invoice) }}">
                        <td class="nowrap">{{ d($p->paid_on) }}</td>
                        <td class="code">{{ $p->invoice->doc_no }}</td>
                        <td class="primary">{{ $p->invoice->customer->name }}</td>
                        <td><span class="tag">{{ __($p->method) }}</span></td>
                        <td class="hide-sm mono small">{{ $p->reference ?? '—' }}</td>
                        <td class="hide-sm">{{ $p->creator?->name ?? '—' }}</td>
                        <td class="num"><b>{{ money($p->amount) }}</b></td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot><tr><td colspan="6" class="right">{{ __('Total (all filtered)') }}</td><td class="num">{{ money($sum) }}</td></tr></tfoot>
            </table>
        </div>
        <div class="panel-foot">{{ $rows->links() }}</div>
    @else
        <x-empty icon="banknotes" :title="__('No payments found')" />
    @endif
</div>
@endsection
