@extends('layouts.app')
@section('title', __('Stock Master'))
@section('content')
<x-page-header :eyebrow="__('Stock').' · '.__('Master list')" :title="__('Stock')" :count="$summary['count']" :sub="__('On-hand quantity per warehouse, average cost and reorder alerts.')">
    <x-slot:actions>
        <a class="btn btn-ghost" href="{{ route('stock.export', request()->only('warehouse')) }}"><x-icon name="download" class="ico ico-sm" />{{ __('Export ledger') }}</a>
        @can('inventory.manage')
            <button type="button" class="btn btn-ghost" data-dialog="dlg-transfer"><x-icon name="swap" class="ico ico-sm" />{{ __('Transfer') }}</button>
            <button type="button" class="btn btn-ghost" data-dialog="dlg-stock" data-fill='{"type":"in"}'><x-icon name="arrow-down" class="ico ico-sm" />{{ __('Stock in / out') }}</button>
            <a class="btn btn-primary" href="{{ route('items.create') }}"><x-icon name="plus" class="ico ico-sm" />{{ __('New item') }}</a>
        @endcan
    </x-slot:actions>
</x-page-header>

{{-- Module shortcuts --}}
<div class="grid g-4" style="margin-bottom:16px">
    @can('inventory.manage')
        <a class="stat" href="{{ route('warehouses.index') }}"><div class="stat-ico tone-neutral"><x-icon name="building" /></div><div><div class="stat-label">{{ __('Warehouses') }}</div><div class="stat-value" style="font-size:18px">{{ $cards['warehouses'] }}</div><div class="stat-hint">{{ __('Add / edit store locations') }}</div></div></a>
    @endcan
    <a class="stat" href="{{ route('stock.index') }}"><div class="stat-ico tone-info"><x-icon name="swap" /></div><div><div class="stat-label">{{ __('Transactions') }}</div><div class="stat-value" style="font-size:18px">{{ $cards['movements'] }}</div><div class="stat-hint">{{ __('Stock in / out, last 30 days') }}</div></div></a>
    <a class="stat" href="{{ route('suppliers.index') }}"><div class="stat-ico tone-success"><x-icon name="truck" /></div><div><div class="stat-label">{{ __('Suppliers') }}</div><div class="stat-value" style="font-size:18px">{{ $cards['suppliers'] }}</div><div class="stat-hint">{{ __('Latest unit price per item') }}</div></div></a>
    @can('inventory.manage')
        <a class="stat" href="{{ route('purchase-orders.index') }}"><div class="stat-ico tone-warning"><x-icon name="document" /></div><div><div class="stat-label">{{ __('Purchase Orders') }}</div><div class="stat-value" style="font-size:18px">{{ $cards['openPo'] }}</div><div class="stat-hint">{{ __('Open (draft / ordered / partial)') }}</div></div></a>
    @endcan
</div>

<div class="panel">
    <div class="tabs">
        <a href="{{ request()->fullUrlWithQuery(['stock' => null, 'page' => null]) }}" class="{{ request('stock') ? '' : 'active' }}">{{ __('All items') }} <span class="n">{{ $summary['count'] }}</span></a>
        <a href="{{ request()->fullUrlWithQuery(['stock' => 'low', 'page' => null]) }}" class="{{ request('stock') === 'low' ? 'active' : '' }}">{{ __('Reorder needed') }} <span class="n">{{ $summary['low'] + $summary['out'] }}</span></a>
        <a href="{{ request()->fullUrlWithQuery(['stock' => 'out', 'page' => null]) }}" class="{{ request('stock') === 'out' ? 'active' : '' }}">{{ __('Out of stock') }} <span class="n">{{ $summary['out'] }}</span></a>
    </div>

    <form method="get" class="filters">
        <input type="hidden" name="stock" value="{{ request('stock') }}">
        <div class="filters-title"><x-icon name="funnel" class="ico ico-sm" />{{ __('Filters') }}</div>
        <div class="filter-row">
            <div class="field grow"><label>{{ __('Search') }}</label><input name="q" value="{{ request('q') }}" placeholder="{{ __('SKU or item name…') }}"></div>
            <div class="field"><label>{{ __('Category') }}</label><select name="category"><option value="">{{ __('All') }}</option>@foreach($categories as $cat)<option value="{{ $cat }}" @selected(request('category') === $cat)>{{ __($cat) }}</option>@endforeach</select></div>
            <div class="field"><label>{{ __('Warehouse') }}</label><select name="warehouse"><option value="">{{ __('All warehouses') }}</option>@foreach($warehouses as $w)<option value="{{ $w->id }}" @selected(request('warehouse') == $w->id)>{{ $w->code }} · {{ $w->name }}</option>@endforeach</select></div>
            <div class="field"><label>{{ __('Status') }}</label><select name="status"><option value="">{{ __('Active') }}</option><option value="inactive" @selected(request('status') === 'inactive')>{{ __('Inactive') }}</option><option value="all" @selected(request('status') === 'all')>{{ __('All') }}</option></select></div>
            <div class="actions">
                @if(request()->except('page', 'stock'))<a class="btn btn-ghost" href="{{ route('items.index', ['stock' => request('stock')]) }}">{{ __('Reset') }}</a>@endif
                <button class="btn btn-primary"><x-icon name="search" class="ico ico-sm" />{{ __('Search') }}</button>
            </div>
        </div>
    </form>

    @if($rows->count())
        <div class="table-wrap">
            <table class="table">
                <thead><tr>
                    <th class="idx">#</th><th>{{ __('Stock') }}</th><th class="hide-sm">{{ __('Category') }}</th><th class="num">{{ __('Balance') }}</th><th class="hide-sm">{{ __('By warehouse') }}</th>
                    <th class="num hide-sm">{{ __('Reorder at') }}</th><th class="num hide-sm">{{ __('Avg cost') }}</th><th class="num">{{ __('Stock value') }}</th><th>{{ __('Status') }}</th><th class="chev-cell"></th>
                </tr></thead>
                <tbody>
                @foreach($rows as $i => $it)
                    @php
                        $onHand = $it->on_hand;
                        $state = $onHand <= 0 ? 'out' : ($it->reorder_level > 0 && $onHand <= (float) $it->reorder_level ? 'low' : 'ok');
                    @endphp
                    <tr data-href="{{ route('items.show', $it) }}" class="{{ $state !== 'ok' ? 'row-'.$state : '' }}">
                        <td class="idx">{{ $rows->firstItem() + $i }}</td>
                        <td>
                            <div class="row" style="gap:10px;flex-wrap:nowrap">
                                <span class="sku-chip">{{ mb_strtoupper(mb_substr(preg_replace('/[^A-Za-z0-9]/', '', $it->sku), 0, 2)) }}</span>
                                <span style="min-width:0"><span class="primary">{{ $it->name }}</span><span class="sub mono">{{ $it->sku }}</span></span>
                            </div>
                        </td>
                        <td class="hide-sm">{{ $it->category ? __($it->category) : '—' }}</td>
                        <td class="num"><b class="{{ $state === 'out' ? 'text-danger' : ($state === 'low' ? 'text-warning' : '') }}" style="font-size:15px">{{ qty($onHand) }}</b> <span class="muted small">{{ $it->uom }}</span></td>
                        <td class="hide-sm">
                            @foreach($it->balances->where('qty', '!=', 0)->sortBy('warehouse.code') as $b)
                                <span class="tag" title="{{ $b->warehouse->name }}">{{ $b->warehouse->code }} <b>{{ qty($b->qty) }}</b></span>
                            @endforeach
                        </td>
                        <td class="num hide-sm muted">{{ (float) $it->reorder_level ? qty($it->reorder_level) : '—' }}</td>
                        <td class="num hide-sm">{{ money($it->unit_cost, false) }}</td>
                        <td class="num">{{ money($onHand * (float) $it->unit_cost, false) }}</td>
                        <td>
                            @if(! $it->is_active)<x-badge status="inactive" />
                            @elseif($state === 'out')<x-badge status="void" :label="__('Out of stock')" />
                            @elseif($state === 'low')<x-badge status="pending" :label="__('Reorder')" />
                            @else<x-badge status="active" :label="__('In stock')" />@endif
                        </td>
                        <td class="chev-cell"><x-icon name="chevron-right" class="ico ico-sm" /></td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot><tr><td colspan="7" class="right">{{ request()->hasAny(['q', 'category', 'warehouse', 'stock']) ? __('Stock value (filtered)') : __('Stock value') }}</td><td class="num">{{ money($filteredValue) }}</td><td colspan="2"></td></tr></tfoot>
            </table>
        </div>
        <div class="panel-foot">{{ $rows->links() }}</div>
    @else
        <x-empty icon="cube" :title="__('No items match these filters')">
            <a class="btn btn-ghost btn-sm" href="{{ route('items.index') }}">{{ __('Clear filters') }}</a>
        </x-empty>
    @endif
</div>

@include('stock._dialogs')
@endsection

@push('head')
<style>
    .sku-chip { width: 32px; height: 32px; flex-shrink: 0; display: grid; place-items: center; border-radius: 8px; border: 1px solid var(--border-strong); background: var(--accent-soft); color: var(--navy); font-family: var(--mono); font-size: 11px; font-weight: 600; }
    :root[data-theme="dark"] .sku-chip { color: var(--brand-light); }
    .table tr.row-out td:first-child { box-shadow: inset 3px 0 0 var(--danger); }
    .table tr.row-low td:first-child { box-shadow: inset 3px 0 0 var(--warning); }
</style>
@endpush
