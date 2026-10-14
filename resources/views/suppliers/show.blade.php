@extends('layouts.app')
@section('title', $s->name)
@section('content')
<x-page-header :eyebrow="__('Suppliers').' · '.$s->code" :title="$s->name" :sub="collect([$s->contact_person ? __('Attn:').' '.$s->contact_person : null, $s->phone, $s->payment_terms ? __('Terms:').' '.__($s->payment_terms) : null])->filter()->implode(' · ')">
    <x-slot:badge><x-badge :status="$s->status" /></x-slot:badge>
    <x-slot:actions>
        @if($s->phone)<a class="btn btn-ghost" href="https://wa.me/{{ preg_replace('/\D/', '', preg_replace('/^0/', '60', $s->phone)) }}" target="_blank" rel="noopener"><x-icon name="phone" class="ico ico-sm" />WhatsApp</a>@endif
        @can('inventory.manage')
            <a class="btn btn-ghost" href="{{ route('suppliers.edit', $s) }}"><x-icon name="pencil" class="ico ico-sm" />{{ __('Edit') }}</a>
            <a class="btn btn-primary" href="{{ route('purchase-orders.create', ['supplier_id' => $s->id]) }}"><x-icon name="plus" class="ico ico-sm" />{{ __('New PO') }}</a>
        @endcan
    </x-slot:actions>
</x-page-header>

<div class="grid g-main-side">
    <div class="panel" data-tab-scope>
        <div class="summary-bar" style="border-top:0;border-bottom:1px solid var(--border)">
            <div><small>{{ __('Items priced') }}</small><strong>{{ $latest->count() }}</strong></div>
            <div><small>{{ __('Purchase Orders') }}</small><strong>{{ $orders->count() }}</strong></div>
            <div><small>{{ __('Open orders') }}</small><strong>{{ money($orders->whereIn('status', ['ordered', 'partial'])->sum('total')) }}</strong></div>
            <div><small>{{ __('Received to date (cost)') }}</small><strong>{{ money($received) }}</strong></div>
        </div>
        <div class="tabs" data-tabs>
            <button data-tab="prices" class="active">{{ __('Latest prices') }} <span class="n">{{ $latest->count() }}</span></button>
            <button data-tab="orders">{{ __('Purchase Orders') }} <span class="n">{{ $orders->count() }}</span></button>
            <button data-tab="history">{{ __('Price history') }} <span class="n">{{ $prices->count() }}</span></button>
        </div>

        <div data-tab-panel="prices" class="active">
            @can('inventory.manage')
                <div class="toolbar" style="justify-content:flex-end"><button type="button" class="btn btn-ghost btn-sm" data-dialog="dlg-price"><x-icon name="plus" class="ico ico-sm" />{{ __('Record new price') }}</button></div>
            @endcan
            @if($latest->count())
                <div class="table-wrap"><table class="table">
                    <thead><tr><th>{{ __('Stock item') }}</th><th class="num">{{ __('Latest unit price') }}</th><th class="num hide-sm">{{ __('Previous') }}</th><th class="num">{{ __('Change') }}</th><th>{{ __('Effective') }}</th></tr></thead>
                    <tbody>@foreach($latest as $r)
                        <tr data-href="{{ route('items.show', $r['price']->item_id) }}">
                            <td class="primary">{{ $r['price']->item->name }}<span class="sub mono">{{ $r['price']->item->sku }} · {{ __('per :uom', ['uom' => $r['price']->item->uom]) }}</span></td>
                            <td class="num"><b>{{ money($r['price']->unit_price) }}</b></td>
                            <td class="num hide-sm muted">{{ $r['previous'] ? money($r['previous']->unit_price) : '—' }}</td>
                            <td class="num">@if($r['change'] !== null)<span class="{{ $r['change'] > 0 ? 'text-danger' : ($r['change'] < 0 ? 'text-success' : 'muted') }}">{{ $r['change'] > 0 ? '▲' : ($r['change'] < 0 ? '▼' : '') }} {{ number_format(abs($r['change']), 1) }}%</span>@else — @endif</td>
                            <td class="small">{{ d($r['price']->effective_date) }}</td>
                        </tr>
                    @endforeach</tbody>
                </table></div>
            @else
                <x-empty icon="tag" :title="__('No prices yet')">{{ __('Prices are recorded here or automatically when a PO is received.') }}</x-empty>
            @endif
        </div>

        <div data-tab-panel="orders" class="table-wrap">
            @if($orders->count())
                <table class="table">
                    <thead><tr><th>{{ __('PO no') }}</th><th>{{ __('Order date') }}</th><th class="hide-sm">{{ __('Deliver to') }}</th><th>{{ __('Status') }}</th><th class="num">{{ __('Total') }}</th></tr></thead>
                    <tbody>@foreach($orders as $po)
                        <tr data-href="{{ route('purchase-orders.show', $po) }}"><td class="code">{{ $po->doc_no }}</td><td>{{ d($po->order_date) }}</td><td class="hide-sm"><span class="tag">{{ $po->warehouse->code }}</span></td><td><x-badge :status="$po->status" /></td><td class="num">{{ money($po->total) }}</td></tr>
                    @endforeach</tbody>
                </table>
            @else <x-empty icon="document" :title="__('No purchase orders yet')" /> @endif
        </div>

        <div data-tab-panel="history" class="table-wrap">
            @if($prices->count())
                <table class="table compact">
                    <thead><tr><th>{{ __('Effective') }}</th><th>{{ __('Item') }}</th><th class="num">{{ __('Unit price') }}</th></tr></thead>
                    <tbody>@foreach($prices as $p)<tr><td>{{ d($p->effective_date) }}</td><td>{{ $p->item->name }}</td><td class="num mono">{{ money($p->unit_price) }}</td></tr>@endforeach</tbody>
                </table>
            @else <x-empty icon="tag" :title="__('No price history')" /> @endif
        </div>
    </div>

    <div class="stack">
        <div class="panel">
            <div class="panel-head"><h3><x-icon name="truck" />{{ __('Profile') }}</h3></div>
            <div class="panel-body">
                <dl class="dl">
                    <dt>{{ __('Code') }}</dt><dd class="mono">{{ $s->code }}</dd>
                    <dt>{{ __('Contact') }}</dt><dd>{{ $s->contact_person ?? '—' }}</dd>
                    <dt>{{ __('Phone') }}</dt><dd>{{ $s->phone ?? '—' }}</dd>
                    <dt>{{ __('Email') }}</dt><dd>{{ $s->email ?? '—' }}</dd>
                    <dt>{{ __('Address') }}</dt><dd style="white-space:pre-line">{{ $s->address ?? '—' }}</dd>
                    <dt>{{ __('Payment terms') }}</dt><dd>{{ $s->payment_terms ? __($s->payment_terms) : '—' }}</dd>
                </dl>
            </div>
        </div>
        @can('inventory.manage')
            <form method="post" action="{{ route('suppliers.destroy', $s) }}" data-confirm="{{ __('Delete supplier :code?', ['code' => $s->code]) }}">
                @csrf @method('DELETE')
                <button class="btn btn-danger-ghost btn-block"><x-icon name="trash" class="ico ico-sm" />{{ __('Delete supplier') }}</button>
            </form>
        @endcan
    </div>
</div>

@can('inventory.manage')
    @if(session('reopen'))<span data-reopen-dialog="{{ session('reopen') }}" hidden></span>@endif
    <dialog id="dlg-price">
        <form method="post" action="{{ route('suppliers.prices.store', $s) }}">
            @csrf
            <div class="dlg-head"><h3>{{ __('Record price — :name', ['name' => $s->name]) }}</h3><button type="button" class="icon-btn" data-close><x-icon name="x" /></button></div>
            <div class="dlg-body">
                <div class="form-grid">
                    <x-field name="item_id" :label="__('Stock item')" type="select" :options="$items->mapWithKeys(fn ($i) => [$i->id => $i->sku.' · '.$i->name.' ('.$i->uom.')'])->all()" :placeholder="'— '.__('Select item').' —'" required class="span-all" />
                    <x-field name="unit_price" :label="__('Unit price (RM)')" type="number" step="0.0001" min="0" required />
                    <x-field name="effective_date" :label="__('Effective from')" type="date" :value="now()->toDateString()" required />
                </div>
            </div>
            <div class="dlg-foot"><button type="button" class="btn btn-ghost" data-close>{{ __('Cancel') }}</button><button class="btn btn-primary">{{ __('Save price') }}</button></div>
        </form>
    </dialog>
@endcan
@endsection
