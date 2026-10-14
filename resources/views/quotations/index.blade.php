@extends('layouts.app')
@section('title', __('Quotations'))
@section('content')
<x-page-header :eyebrow="__('Sales').' · '.__('Quotations')" :title="__('Quotations')" :count="$counts->sum()" :sub="__('Prepare, send and track quotations — accepted ones convert to invoices and projects.')">
    <x-slot:actions>
        <a class="btn btn-primary" href="{{ route('quotations.create') }}"><x-icon name="plus" class="ico ico-sm" />{{ __('New quotation') }}</a>
    </x-slot:actions>
</x-page-header>

<div class="panel">
    <div class="tabs">
        <a href="{{ request()->fullUrlWithQuery(['status' => null, 'page' => null]) }}" class="{{ request('status') ? '' : 'active' }}">{{ __('All') }} <span class="n">{{ $counts->sum() }}</span></a>
        @foreach(\App\Models\Quotation::STATUSES as $k => $label)
            @continue($k === 'expired')
            <a href="{{ request()->fullUrlWithQuery(['status' => $k, 'page' => null]) }}" class="{{ request('status') === $k ? 'active' : '' }}">{{ __($label) }} <span class="n">{{ $counts[$k] ?? 0 }}</span></a>
        @endforeach
        <a href="{{ request()->fullUrlWithQuery(['status' => 'expired', 'page' => null]) }}" class="{{ request('status') === 'expired' ? 'active' : '' }}">{{ __('Expired') }}</a>
    </div>
    <form method="get" class="filters">
        <input type="hidden" name="status" value="{{ request('status') }}">
        <div class="filter-row">
            <div class="field grow"><label>{{ __('Search') }}</label><input name="q" value="{{ request('q') }}" placeholder="{{ __('Doc no, customer, phone…') }}"></div>
            <div class="field"><label>{{ __('From') }}</label><input type="date" name="from" value="{{ request('from') }}"></div>
            <div class="field"><label>{{ __('To') }}</label><input type="date" name="to" value="{{ request('to') }}"></div>
            <div class="field"><label>{{ __('Salesman') }}</label><select name="salesman"><option value="">{{ __('All') }}</option>@foreach($salesmen as $id => $name)<option value="{{ $id }}" @selected(request('salesman') == $id)>{{ $name }}</option>@endforeach</select></div>
            <div class="actions">
                @if(request()->except('page', 'status'))<a class="btn btn-ghost" href="{{ route('quotations.index', ['status' => request('status')]) }}">{{ __('Reset') }}</a>@endif
                <button class="btn btn-primary"><x-icon name="search" class="ico ico-sm" />{{ __('Search') }}</button>
            </div>
        </div>
    </form>
    @if($rows->count())
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>{{ __('Doc no') }}</th><th>{{ __('Date') }}</th><th>{{ __('Customer') }}</th><th class="hide-sm">{{ __('Enquiry') }}</th><th class="hide-sm">{{ __('Salesman') }}</th><th class="hide-sm">{{ __('Valid until') }}</th><th>{{ __('Status') }}</th><th class="num">{{ __('Total') }}</th><th class="chev-cell"></th></tr></thead>
                <tbody>
                @foreach($rows as $q)
                    <tr data-href="{{ route('quotations.show', $q) }}">
                        <td class="code">{{ $q->doc_no }}</td>
                        <td class="nowrap">{{ d($q->quote_date) }}</td>
                        <td class="primary">{{ $q->customer->name }}<span class="sub">{{ $q->customer->code }}</span></td>
                        <td class="hide-sm mono small">{{ $q->enquiry?->doc_no ?? '—' }}</td>
                        <td class="hide-sm">{{ $q->salesman?->name ?? '—' }}</td>
                        <td class="hide-sm nowrap">{{ d($q->valid_until) }}</td>
                        <td><x-badge :status="$q->display_status" /></td>
                        <td class="num"><b>{{ money($q->total) }}</b></td>
                        <td class="chev-cell"><x-icon name="chevron-right" class="ico ico-sm" /></td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot><tr><td colspan="7" class="right">{{ __('Total of :n filtered quotation(s)', ['n' => number_format($rows->total())]) }}</td><td class="num">{{ money($sum) }}</td><td></td></tr></tfoot>
            </table>
        </div>
        <div class="panel-foot">{{ $rows->links() }}</div>
    @else
        <x-empty icon="document" :title="__('No quotations match these filters')">
            <a class="btn btn-ghost btn-sm" href="{{ route('quotations.index') }}">{{ __('Clear filters') }}</a>
        </x-empty>
    @endif
</div>
@endsection
