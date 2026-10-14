@extends('layouts.app')
@section('title', $item->name)
@section('content')
@php
    $onHand = (float) $item->balances->sum('qty');
    $state = $onHand <= 0 ? 'out' : ($item->reorder_level > 0 && $onHand <= (float) $item->reorder_level ? 'low' : 'ok');
    $typeTone = ['in' => 'success', 'transfer_in' => 'info', 'out' => 'warning', 'project' => 'info', 'transfer_out' => 'neutral', 'adjust' => 'neutral'];
@endphp
<x-page-header :eyebrow="__('Stock').' · '.$item->sku" :title="$item->name" :sub="($item->category ? __($item->category) : __('Uncategorised')).' · '.__('UOM').' '.$item->uom">
    <x-slot:badge>
        @if(! $item->is_active)<x-badge status="inactive" />
        @elseif($state === 'out')<x-badge status="void" :label="__('Out of stock')" />
        @elseif($state === 'low')<x-badge status="pending" :label="__('Reorder')" />
        @else<x-badge status="active" :label="__('In stock')" />@endif
    </x-slot:badge>
    <x-slot:actions>
        <a class="btn btn-ghost" href="{{ route('items.index') }}"><x-icon name="arrow-left" class="ico ico-sm" />{{ __('Stock list') }}</a>
        @can('inventory.manage')
            <a class="btn btn-ghost" href="{{ route('items.edit', $item) }}"><x-icon name="pencil" class="ico ico-sm" />{{ __('Edit') }}</a>
            <button type="button" class="btn btn-ghost" data-dialog="dlg-transfer" data-fill='{"item_id":{{ $item->id }}}'><x-icon name="swap" class="ico ico-sm" />{{ __('Transfer') }}</button>
            <button type="button" class="btn btn-primary" data-dialog="dlg-stock" data-fill='{"type":"in","item_id":{{ $item->id }}}'><x-icon name="arrow-down" class="ico ico-sm" />{{ __('Stock in / out') }}</button>
        @endcan
    </x-slot:actions>
</x-page-header>

<div class="panel" style="margin-bottom:16px">
    <div class="summary-bar" style="border-top:0">
        <div><small>{{ __('On hand') }}</small><strong class="{{ $state === 'out' ? 'text-danger' : ($state === 'low' ? 'text-warning' : '') }}">{{ qty($onHand) }} {{ $item->uom }}</strong></div>
        <div><small>{{ __('Stock value') }}</small><strong>{{ money($onHand * (float) $item->unit_cost) }}</strong></div>
        <div><small>{{ __('Average unit cost') }}</small><strong>{{ money($item->unit_cost) }}</strong></div>
        <div><small>{{ __('Selling price') }}</small><strong>{{ (float) $item->selling_price ? money($item->selling_price) : '—' }}</strong></div>
        <div><small>{{ __('Reorder level') }}</small><strong>{{ (float) $item->reorder_level ? qty($item->reorder_level).' '.$item->uom : '—' }}</strong></div>
        <div><small>{{ __('Issued to projects (30 d)') }}</small><strong>{{ qty($issued30) }} {{ $item->uom }}</strong></div>
    </div>
</div>

<div class="grid g-main-side">
    <div class="panel">
        <div class="panel-head"><h3><x-icon name="swap" />{{ __('Movement ledger') }}</h3><a class="small" href="{{ route('stock.index', ['item' => $item->id]) }}">{{ __('Open in transactions →') }}</a></div>
        @if($movements->count())
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Doc no') }}</th><th>{{ __('Type') }}</th><th class="hide-sm">{{ __('Warehouse') }}</th><th class="hide-sm">{{ __('Reference') }}</th><th class="num">{{ __('In') }}</th><th class="num">{{ __('Out') }}</th><th class="num">{{ __('Balance') }}</th><th class="hide-sm">{{ __('By') }}</th></tr></thead>
                    <tbody>
                    @foreach($movements as $m)
                        <tr>
                            <td class="nowrap">{{ d($m->moved_at) }}<span class="sub">{{ $m->moved_at->format('H:i') }}</span></td>
                            <td class="code">{{ $m->doc_no }}</td>
                            <td><x-badge :status="$m->type" :tone="$typeTone[$m->type] ?? 'neutral'" :label="__(\App\Services\StockService::TYPES[$m->type] ?? $m->type)" /></td>
                            <td class="hide-sm"><span class="tag">{{ $m->warehouse->code }}</span></td>
                            <td class="hide-sm small">
                                @if($m->project)<a href="{{ route('projects.show', $m->project) }}">{{ $m->project->code }}</a><br>@endif
                                @if($m->supplier){{ $m->supplier->name }}<br>@endif
                                <span class="muted">{{ $m->reference }} {{ $m->remarks ? '· '.$m->remarks : '' }}</span>
                            </td>
                            <td class="num text-success">{{ $m->qty > 0 ? qty($m->qty) : '' }}</td>
                            <td class="num text-danger">{{ $m->qty < 0 ? qty(abs($m->qty)) : '' }}</td>
                            <td class="num"><b>{{ qty($m->balance_after) }}</b></td>
                            <td class="hide-sm small muted">{{ $m->user?->name ?? __('System') }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="panel-foot">{{ $movements->links() }}</div>
        @else
            <x-empty icon="swap" :title="__('No movements yet')">{{ __('Record a stock-in to set the opening balance.') }}</x-empty>
        @endif
    </div>

    <div class="stack">
        <div class="panel">
            <div class="panel-head"><h3><x-icon name="building" />{{ __('By warehouse') }}</h3></div>
            <ul class="list">
                @forelse($item->balances->sortBy('warehouse.code') as $b)
                    <li><span><span class="tag">{{ $b->warehouse->code }}</span> {{ $b->warehouse->name }}</span><b class="mono">{{ qty($b->qty) }} {{ $item->uom }}</b></li>
                @empty
                    <li class="muted">{{ __('Not stocked in any warehouse yet.') }}</li>
                @endforelse
            </ul>
        </div>

        <div class="panel">
            <div class="panel-head"><h3><x-icon name="truck" />{{ __('Supplier prices') }}</h3><span class="panel-count">{{ __('latest per supplier') }}</span></div>
            @if($latestPrices->count())
                <ul class="list">
                    @foreach($latestPrices as $i => $p)
                        <li><span><a class="li-link" href="{{ route('suppliers.show', $p->supplier) }}">{{ $p->supplier->name }}</a><div class="meta">{{ __('since :date', ['date' => d($p->effective_date)]) }}</div></span>
                            <span class="right"><b class="mono">{{ money($p->unit_price) }}</b>@if($i === 0 && $latestPrices->count() > 1)<br><x-badge status="active" :label="__('Cheapest')" />@endif</span></li>
                    @endforeach
                </ul>
                @if($prices->count() > $latestPrices->count())
                    <details class="panel-body" style="border-top:1px solid var(--border)"><summary class="small" style="cursor:pointer">{{ __('Price history (:n)', ['n' => $prices->count()]) }}</summary>
                        <table class="table compact" style="margin-top:8px"><tbody>
                            @foreach($prices as $p)<tr><td class="small">{{ d($p->effective_date) }}</td><td class="small">{{ $p->supplier->name }}</td><td class="num mono">{{ money($p->unit_price, false) }}</td></tr>@endforeach
                        </tbody></table>
                    </details>
                @endif
            @else
                <x-empty icon="truck" :title="__('No supplier prices')">{{ __('Prices are recorded from supplier pages and PO receipts.') }}</x-empty>
            @endif
        </div>

        @can('inventory.manage')
            @if($movements->total() === 0)
                <form method="post" action="{{ route('items.destroy', $item) }}" data-confirm="{{ __('Delete item :sku?', ['sku' => $item->sku]) }}">
                    @csrf @method('DELETE')
                    <button class="btn btn-danger-ghost btn-block"><x-icon name="trash" class="ico ico-sm" />{{ __('Delete item') }}</button>
                </form>
            @endif
        @endcan
    </div>
</div>

@include('stock._dialogs')
@endsection
