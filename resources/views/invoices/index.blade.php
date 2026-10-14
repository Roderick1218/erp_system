@extends('layouts.app')
@section('title', __('Invoices'))
@section('content')
<x-page-header :eyebrow="__('Sales').' · '.__('Invoices')" :title="__('Invoices & Proforma')" :count="$counts['all']" :sub="__('Bill customers, record payments and chase overdue balances.')">
    <x-slot:actions>
        <a class="btn btn-ghost" href="{{ route('invoices.create', ['type' => 'proforma']) }}"><x-icon name="document" class="ico ico-sm" />{{ __('New proforma') }}</a>
        <a class="btn btn-primary" href="{{ route('invoices.create') }}"><x-icon name="plus" class="ico ico-sm" />{{ __('New invoice') }}</a>
    </x-slot:actions>
</x-page-header>

<div class="kpis" style="margin-bottom:16px">
    <x-stat :label="__('Not yet due')" :value="money($aging['current'])" icon="clock" tone="info" :hint="__('Tax invoices, within terms')" :href="route('invoices.index', ['status' => 'outstanding'])" />
    <x-stat :label="__('1–30 days overdue')" :value="money($aging['1-30'])" icon="exclamation" tone="warning" :href="route('invoices.index', ['status' => 'overdue'])" />
    <x-stat :label="__('31–60 days overdue')" :value="money($aging['31-60'])" icon="exclamation" tone="danger" :href="route('invoices.index', ['status' => 'overdue'])" />
    <x-stat :label="__('Over 60 days')" :value="money($aging['60+'])" icon="flag" tone="danger" :hint="__('Consider a reminder letter')" :href="route('invoices.index', ['status' => 'overdue'])" />
</div>

<div class="panel">
    <div class="tabs">
        @foreach(['' => 'All', 'draft' => 'Draft', 'outstanding' => 'Outstanding', 'overdue' => 'Overdue', 'paid' => 'Paid', 'void' => 'Void'] as $k => $label)
            <a href="{{ request()->fullUrlWithQuery(['status' => $k ?: null, 'page' => null]) }}" class="{{ (string) request('status') === $k ? 'active' : '' }}">{{ __($label) }} <span class="n">{{ $counts[$k ?: 'all'] }}</span></a>
        @endforeach
    </div>
    <form method="get" class="filters">
        <input type="hidden" name="status" value="{{ request('status') }}">
        <div class="filter-row">
            <div class="field grow"><label>{{ __('Search') }}</label><input name="q" value="{{ request('q') }}" placeholder="{{ __('Doc no, customer, project code…') }}"></div>
            <div class="field"><label>{{ __('Type') }}</label><select name="type"><option value="">{{ __('All') }}</option><option value="invoice" @selected(request('type') === 'invoice')>{{ __('Tax invoice') }}</option><option value="proforma" @selected(request('type') === 'proforma')>{{ __('Proforma') }}</option></select></div>
            <div class="field"><label>{{ __('From') }}</label><input type="date" name="from" value="{{ request('from') }}"></div>
            <div class="field"><label>{{ __('To') }}</label><input type="date" name="to" value="{{ request('to') }}"></div>
            <div class="actions">
                @if(request()->except('page', 'status'))<a class="btn btn-ghost" href="{{ route('invoices.index', ['status' => request('status')]) }}">{{ __('Reset') }}</a>@endif
                <button class="btn btn-primary"><x-icon name="search" class="ico ico-sm" />{{ __('Search') }}</button>
            </div>
        </div>
    </form>
    @if($rows->count())
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>{{ __('Doc no') }}</th><th>{{ __('Date') }}</th><th>{{ __('Customer') }}</th><th class="hide-sm">{{ __('Project') }}</th><th class="hide-sm">{{ __('Due') }}</th><th>{{ __('Status') }}</th><th class="num">{{ __('Total') }}</th><th class="num hide-sm">{{ __('Paid') }}</th><th class="num">{{ __('Balance') }}</th><th class="chev-cell"></th></tr></thead>
                <tbody>
                @foreach($rows as $inv)
                    <tr data-href="{{ route('invoices.show', $inv) }}">
                        <td class="code">{{ $inv->doc_no }}@if($inv->type === 'proforma')<span class="sub">{{ __('Proforma') }}</span>@endif</td>
                        <td class="nowrap">{{ d($inv->invoice_date) }}</td>
                        <td class="primary">{{ $inv->customer->name }}<span class="sub">{{ $inv->customer->code }}</span></td>
                        <td class="hide-sm mono small">{{ $inv->project?->code ?? '—' }}</td>
                        <td class="hide-sm nowrap {{ $inv->is_overdue ? 'text-danger' : '' }}">{{ d($inv->due_date) }}@if($inv->is_overdue)<span class="sub">{{ __(':n days late', ['n' => (int) $inv->due_date->diffInDays(today())]) }}</span>@endif</td>
                        <td><x-badge :status="$inv->display_status" /></td>
                        <td class="num">{{ money($inv->total) }}</td>
                        <td class="num hide-sm">{{ money($inv->paid_amount) }}</td>
                        <td class="num"><b>{{ in_array($inv->status, ['void', 'draft'], true) ? '—' : money($inv->balance) }}</b></td>
                        <td class="chev-cell"><x-icon name="chevron-right" class="ico ico-sm" /></td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot><tr><td colspan="6" class="right">{{ __(':n filtered document(s)', ['n' => number_format($rows->total())]) }}</td><td class="num">{{ money($sumTotal) }}</td><td class="num hide-sm">{{ money($sumPaid) }}</td><td class="num">{{ money($sumTotal - $sumPaid) }}</td><td></td></tr></tfoot>
            </table>
        </div>
        <div class="panel-foot">{{ $rows->links() }}</div>
    @else
        <x-empty icon="receipt" :title="__('No invoices match these filters')">
            <a class="btn btn-ghost btn-sm" href="{{ route('invoices.index') }}">{{ __('Clear filters') }}</a>
        </x-empty>
    @endif
</div>
@endsection
