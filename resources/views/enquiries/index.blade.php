@extends('layouts.app')
@section('title', __('Enquiries'))
@section('content')
@php
    $total = $stageCounts->sum();
    $open = $total - ($stageCounts['won'] ?? 0) - ($stageCounts['lost'] ?? 0);
@endphp
<x-page-header :eyebrow="__('Customers').' · '.__('Pipeline')" :title="__('Enquiries')" :count="$total" :sub="__('Every lead from first contact → inspection → quotation → won / lost.')">
    <x-slot:actions>
        <x-view-switch :current="$view" :views="['list' => [__('List'), 'table'], 'board' => [__('Board'), 'columns']]" />
        <a class="btn btn-ghost" href="{{ route('enquiries.export', request()->except('view', 'page')) }}"><x-icon name="download" class="ico ico-sm" />{{ __('Export CSV') }}</a>
        @can('customers.manage')<a class="btn btn-primary" href="{{ route('enquiries.create') }}"><x-icon name="plus" class="ico ico-sm" />{{ __('New enquiry') }}</a>@endcan
    </x-slot:actions>
</x-page-header>

<div class="panel" style="margin-bottom:16px">
    <form method="get" class="filters" style="border-bottom:0;border-radius:var(--radius)">
        <input type="hidden" name="view" value="{{ $view }}">
        @if($view === 'list')<input type="hidden" name="stage" value="{{ request('stage') }}">@endif
        <div class="filters-title"><x-icon name="funnel" class="ico ico-sm" />{{ __('Filters') }}</div>
        <div class="filter-row">
            <div class="field grow"><label>{{ __('Search') }}</label><input name="q" value="{{ request('q') }}" placeholder="{{ __('Doc no, customer, phone, site address…') }}"></div>
            <div class="field"><label>{{ __('From') }}</label><input type="date" name="from" value="{{ request('from') }}"></div>
            <div class="field"><label>{{ __('To') }}</label><input type="date" name="to" value="{{ request('to') }}"></div>
            <div class="field"><label>{{ __('Salesman') }}</label><select name="salesman"><option value="">{{ __('All') }}</option>@foreach($salesmen as $id => $name)<option value="{{ $id }}" @selected(request('salesman') == $id)>{{ $name }}</option>@endforeach</select></div>
            <div class="field"><label>{{ __('Service') }}</label><select name="service"><option value="">{{ __('All') }}</option>@foreach(config('erp.services') as $s)<option value="{{ $s }}" @selected(request('service') === $s)>{{ __($s) }}</option>@endforeach</select></div>
            <div class="field"><label>{{ __('Branch') }}</label><select name="branch"><option value="">{{ __('All') }}</option>@foreach($branches as $id => $name)<option value="{{ $id }}" @selected(request('branch') == $id)>{{ $name }}</option>@endforeach</select></div>
            <div class="actions">
                @if(request()->except('page', 'view', 'stage'))<a class="btn btn-ghost" href="{{ route('enquiries.index', ['view' => $view]) }}">{{ __('Reset') }}</a>@endif
                <button class="btn btn-primary"><x-icon name="search" class="ico ico-sm" />{{ __('Search') }}</button>
            </div>
        </div>
    </form>
</div>

@if($view === 'board')
    <div class="kanban" data-kanban="stage">
        @foreach($columns as $key => $col)
            <div class="kanban-col">
                <div class="kanban-col-head">
                    <span><x-badge :status="$key" :label="__($col['label'])" /> <span class="muted" data-count>{{ $col['rows']->count() }}</span></span>
                    <span class="sum">{{ money($col['sum']) }}</span>
                </div>
                <div class="kanban-list" data-value="{{ $key }}" data-label="{{ __($col['label']) }}">
                    @foreach($col['rows'] as $e)
                        <a class="kcard" href="{{ route('enquiries.show', $e) }}" @can('customers.manage') draggable="true" data-url="{{ route('enquiries.stage', $e) }}" @endcan>
                            <div class="t">{{ $e->customer->name }}</div>
                            <div class="m">{{ $e->doc_no }} · {{ __($e->service) }}</div>
                            <div class="m">{{ $e->state ? __($e->state) : '' }}@if($e->inspection_date) · {{ __('Insp.') }} {{ d($e->inspection_date, 'd M') }}@endif</div>
                            <div class="f">
                                <span class="row" style="gap:6px"><span class="avatar sm">{{ $e->salesman?->initials ?? '—' }}</span><span class="muted">{{ d($e->enquiry_date, 'd M') }}</span></span>
                                <b>{{ $e->estimate_amount ? money($e->estimate_amount) : '' }}</b>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
    <p class="small muted" style="margin-top:8px">{!! __('Drag a card to another column to move its stage. Moving to <b>Lost</b> here records “Not specified” — open the enquiry to give a reason.') !!}</p>
@else
    <div class="panel">
        <div class="tabs">
            <a href="{{ request()->fullUrlWithQuery(['stage' => null, 'page' => null]) }}" class="{{ request('stage') ? '' : 'active' }}">{{ __('All') }} <span class="n">{{ $total }}</span></a>
            @foreach(\App\Models\Enquiry::STAGES as $k => $label)
                <a href="{{ request()->fullUrlWithQuery(['stage' => $k, 'page' => null]) }}" class="{{ request('stage') === $k ? 'active' : '' }}">{{ __($label) }} <span class="n">{{ $stageCounts[$k] ?? 0 }}</span></a>
            @endforeach
        </div>
        <div class="toolbar" style="justify-content:space-between">
            <span class="small muted">{{ __(':n open · pipeline value (filtered, excl. won/lost)', ['n' => $open]) }} <b style="color:var(--text)">{{ money($pipelineValue) }}</b></span>
        </div>
        @if($rows->count())
            <div class="table-wrap">
                <table class="table">
                    <thead><tr>
                        <th>{{ __('Doc no') }}</th><th>{{ __('Date') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Service') }}</th><th class="hide-sm">{{ __('Site') }}</th><th class="hide-sm">{{ __('Inspection') }}</th>
                        <th class="hide-sm">{{ __('Salesman') }}</th><th>{{ __('Stage') }}</th><th class="num">{{ __('Estimate') }}</th><th class="chev-cell"></th>
                    </tr></thead>
                    <tbody>
                    @foreach($rows as $e)
                        <tr data-href="{{ route('enquiries.show', $e) }}">
                            <td class="code">{{ $e->doc_no }}</td>
                            <td class="nowrap">{{ d($e->enquiry_date) }}</td>
                            <td class="primary">{{ $e->customer->name }}<span class="sub">{{ $e->contact_no }}</span></td>
                            <td>{{ __($e->service) }}</td>
                            <td class="hide-sm small">{{ \Illuminate\Support\Str::limit($e->site_address, 42) }}<span class="sub">{{ $e->state ? __($e->state) : '' }}</span></td>
                            <td class="hide-sm nowrap">{{ d($e->inspection_date) }}</td>
                            <td class="hide-sm">{{ $e->salesman?->name ?? '—' }}</td>
                            <td><x-badge :status="$e->stage" /></td>
                            <td class="num">{{ $e->estimate_amount ? money($e->estimate_amount) : '—' }}</td>
                            <td class="chev-cell"><x-icon name="chevron-right" class="ico ico-sm" /></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="panel-foot">{{ $rows->links() }}</div>
        @else
            <x-empty icon="funnel" :title="__('No enquiries match these filters')">
                <a class="btn btn-ghost btn-sm" href="{{ route('enquiries.index') }}">{{ __('Clear filters') }}</a>
            </x-empty>
        @endif
    </div>
@endif
@endsection
