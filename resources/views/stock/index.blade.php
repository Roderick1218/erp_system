@extends('layouts.app')
@section('title', __('Stock Transactions'))
@section('content')
@php
    $typeTone = ['in' => 'success', 'transfer_in' => 'info', 'out' => 'warning', 'project' => 'info', 'transfer_out' => 'neutral', 'adjust' => 'neutral'];
@endphp
<x-page-header :eyebrow="__('Stock').' · '.__('Transactions')" :title="__('Stock Transactions')" :count="$rows->total()" :sub="__('Every stock-in, stock-out, project issue, transfer and adjustment — append-only.')">
    <x-slot:actions>
        <a class="btn btn-ghost" href="{{ route('stock.export', request()->query()) }}"><x-icon name="download" class="ico ico-sm" />{{ __('Export CSV') }}</a>
        @can('inventory.manage')
            <button type="button" class="btn btn-ghost" data-dialog="dlg-transfer"><x-icon name="swap" class="ico ico-sm" />{{ __('Transfer') }}</button>
            <button type="button" class="btn btn-ghost" data-dialog="dlg-stock" data-fill='{"type":"project"}'><x-icon name="briefcase" class="ico ico-sm" />{{ __('Issue to project') }}</button>
            <button type="button" class="btn btn-primary" data-dialog="dlg-stock" data-fill='{"type":"in"}'><x-icon name="plus" class="ico ico-sm" />{{ __('Record movement') }}</button>
        @endcan
    </x-slot:actions>
</x-page-header>

<div class="grid g-4" style="margin-bottom:16px">
    <x-stat :label="__('Quantity in')" :value="qty($totals['in'])" icon="arrow-down" tone="success" :hint="__(':amount at cost', ['amount' => money($totals['value_in'])])" />
    <x-stat :label="__('Quantity out')" :value="qty($totals['out'])" icon="arrow-up" tone="warning" :hint="__(':amount at cost', ['amount' => money($totals['value_out'])])" />
    <x-stat :label="__('Transactions')" :value="number_format($rows->total())" icon="swap" tone="info" :hint="request()->hasAny(['from', 'to']) ? __('In selected period') : __('All time')" />
    <x-stat :label="__('Net value change')" :value="money($totals['value_in'] - $totals['value_out'])" icon="banknotes" tone="neutral" :hint="__('In minus out, at cost')" />
</div>

<div class="panel">
    <div class="tabs">
        <a href="{{ request()->fullUrlWithQuery(['type' => null, 'page' => null]) }}" class="{{ request('type') ? '' : 'active' }}">{{ __('All') }}</a>
        @foreach(\App\Services\StockService::TYPES as $k => $label)
            <a href="{{ request()->fullUrlWithQuery(['type' => $k, 'page' => null]) }}" class="{{ request('type') === $k ? 'active' : '' }}">{{ __($label) }}</a>
        @endforeach
    </div>
    <form method="get" class="filters">
        <input type="hidden" name="type" value="{{ request('type') }}">
        <div class="filters-title"><x-icon name="funnel" class="ico ico-sm" />{{ __('Filters') }}</div>
        <div class="filter-row">
            <div class="field grow"><label>{{ __('Search') }}</label><input name="q" value="{{ request('q') }}" placeholder="{{ __('Doc no, reference, remarks…') }}"></div>
            <div class="field"><label>{{ __('From date') }}</label><input type="date" name="from" value="{{ request('from') }}"></div>
            <div class="field"><label>{{ __('To date') }}</label><input type="date" name="to" value="{{ request('to') }}"></div>
            <div class="field"><label>{{ __('Item') }}</label><select name="item"><option value="">{{ __('All items') }}</option>@foreach($dlgItems as $it)<option value="{{ $it->id }}" @selected(request('item') == $it->id)>{{ $it->sku }} · {{ \Illuminate\Support\Str::limit($it->name, 30) }}</option>@endforeach</select></div>
            <div class="field"><label>{{ __('Warehouse') }}</label><select name="warehouse"><option value="">{{ __('All') }}</option>@foreach($dlgWarehouses as $w)<option value="{{ $w->id }}" @selected(request('warehouse') == $w->id)>{{ $w->code }}</option>@endforeach</select></div>
            <div class="field"><label>{{ __('Project') }}</label><select name="project"><option value="">{{ __('All') }}</option>@foreach(\App\Models\Project::orderByDesc('id')->get(['id', 'code']) as $p)<option value="{{ $p->id }}" @selected(request('project') == $p->id)>{{ $p->code }}</option>@endforeach</select></div>
            <div class="actions">
                @if(request()->except('page', 'type'))<a class="btn btn-ghost" href="{{ route('stock.index', ['type' => request('type')]) }}">{{ __('Reset') }}</a>@endif
                <button class="btn btn-primary"><x-icon name="search" class="ico ico-sm" />{{ __('Search') }}</button>
            </div>
        </div>
    </form>

    @if($rows->count())
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Doc no') }}</th><th>{{ __('Type') }}</th><th>{{ __('Item') }}</th><th class="hide-sm">{{ __('WH') }}</th><th class="hide-sm">{{ __('Project / supplier / ref') }}</th><th class="num">{{ __('Qty') }}</th><th class="num hide-sm">{{ __('Unit cost') }}</th><th class="num hide-sm">{{ __('Value') }}</th><th class="num">{{ __('Balance') }}</th><th class="hide-sm">{{ __('By') }}</th></tr></thead>
                <tbody>
                @foreach($rows as $m)
                    <tr data-href="{{ route('items.show', $m->item_id) }}">
                        <td class="nowrap">{{ d($m->moved_at) }}<span class="sub">{{ $m->moved_at->format('H:i') }}</span></td>
                        <td class="code">{{ $m->doc_no }}</td>
                        <td><x-badge :status="$m->type" :tone="$typeTone[$m->type] ?? 'neutral'" :label="__(\App\Services\StockService::TYPES[$m->type] ?? $m->type)" /></td>
                        <td class="primary">{{ \Illuminate\Support\Str::limit($m->item->name, 46) }}<span class="sub mono">{{ $m->item->sku }}</span></td>
                        <td class="hide-sm"><span class="tag">{{ $m->warehouse->code }}</span></td>
                        <td class="hide-sm small">
                            @if($m->project)<a href="{{ route('projects.show', $m->project) }}">{{ $m->project->code }}</a>@endif
                            @if($m->supplier)<span>{{ $m->supplier->name }}</span>@endif
                            <span class="muted">{{ $m->reference }}{{ $m->remarks ? ' · '.$m->remarks : '' }}</span>
                        </td>
                        <td class="num"><b class="{{ $m->qty > 0 ? 'text-success' : 'text-danger' }}">{{ $m->qty > 0 ? '+' : '−' }}{{ qty(abs($m->qty)) }}</b> <span class="muted small">{{ $m->item->uom }}</span></td>
                        <td class="num hide-sm">{{ money($m->unit_cost, false) }}</td>
                        <td class="num hide-sm">{{ money(abs($m->qty) * (float) $m->unit_cost, false) }}</td>
                        <td class="num">{{ qty($m->balance_after) }}</td>
                        <td class="hide-sm small muted">{{ $m->user?->name ?? __('System') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="panel-foot">{{ $rows->links() }}</div>
    @else
        <x-empty icon="swap" :title="__('No transactions match these filters')">
            <a class="btn btn-ghost btn-sm" href="{{ route('stock.index') }}">{{ __('Clear filters') }}</a>
        </x-empty>
    @endif
</div>

@include('stock._dialogs')
@endsection
