@extends('layouts.app')
@section('title', $po->doc_no)
@section('content')
@php
    $ordered = (float) $po->lines->sum('qty');
    $received = (float) $po->lines->sum('received_qty');
    $receivedValue = $po->lines->sum(fn ($l) => (float) $l->received_qty * (float) $l->unit_price);
    $pct = $ordered > 0 ? round($received / $ordered * 100) : 0;
    $canReceive = in_array($po->status, ['ordered', 'partial'], true);
    $flow = ['draft' => __('Draft'), 'ordered' => __('Ordered'), 'partial' => __('Partially received'), 'received' => __('Received')];
    $flowKeys = array_keys($flow);
    $pos = array_search($po->status, $flowKeys, true);
    $sub = __('Ordered :date', ['date' => d($po->order_date)]).' · '.__('deliver to :wh', ['wh' => $po->warehouse->code.' '.$po->warehouse->name]).($po->expected_date ? ' · '.__('expected :date', ['date' => d($po->expected_date)]) : '');
@endphp
<x-page-header :eyebrow="__('Purchasing').' · '.$po->supplier->name" :title="$po->doc_no" :sub="$sub">
    <x-slot:badge><x-badge :status="$po->status" :label="isset($statuses[$po->status]) ? __($statuses[$po->status]) : null" /></x-slot:badge>
    <x-slot:actions>
        <button type="button" class="btn btn-ghost" onclick="window.print()"><x-icon name="printer" class="ico ico-sm" />{{ __('Print') }}</button>
        @if($po->status === 'draft')
            <a class="btn btn-ghost" href="{{ route('purchase-orders.edit', $po) }}"><x-icon name="pencil" class="ico ico-sm" />{{ __('Edit') }}</a>
            <form method="post" action="{{ route('purchase-orders.status', $po) }}">@csrf<input type="hidden" name="status" value="ordered">
                <button class="btn btn-primary"><x-icon name="check" class="ico ico-sm" />{{ __('Mark as ordered') }}</button></form>
        @endif
        @if($canReceive)
            <button type="button" class="btn btn-primary" data-dialog="dlg-receive"><x-icon name="arrow-down" class="ico ico-sm" />{{ __('Receive goods') }}</button>
        @endif
    </x-slot:actions>
</x-page-header>

@if($po->status !== 'cancelled')
    <div class="steps" style="margin-bottom:16px">
        @foreach($flow as $k => $label)
            @php
                $i = array_search($k, $flowKeys, true);
            @endphp
            @if($k === 'partial' && $po->status !== 'partial') @continue @endif
            <div class="step {{ $i < $pos ? 'done' : ($i === $pos ? 'current' : '') }}">{{ $label }}</div>
        @endforeach
    </div>
@else
    <div class="alert alert-danger"><x-icon name="x" />{{ __('This purchase order was cancelled.') }}</div>
@endif

<div class="grid g-main-side">
    <div class="stack">
        <div class="panel">
            <div class="panel-head"><h3><x-icon name="cube" />{{ __('Items') }}</h3>
                <div class="progress-cell" style="width:220px"><div class="progress" style="flex:1"><span style="width:{{ $pct }}%"></span></div><small>{{ $pct }}%</small></div>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th class="idx">#</th><th>{{ __('Item') }}</th><th class="num">{{ __('Ordered') }}</th><th class="num">{{ __('Received') }}</th><th class="num">{{ __('Outstanding') }}</th><th class="num">{{ __('Unit price') }}</th><th class="num">{{ __('Amount') }}</th></tr></thead>
                    <tbody>
                    @foreach($po->lines as $i => $l)
                        @php
                            $out = max(0, (float) $l->qty - (float) $l->received_qty);
                        @endphp
                        <tr data-href="{{ route('items.show', $l->item) }}">
                            <td class="idx">{{ $i + 1 }}</td>
                            <td class="primary">{{ $l->item->name }}<span class="sub mono">{{ $l->item->sku }}</span></td>
                            <td class="num">{{ qty($l->qty) }} <span class="muted small">{{ $l->item->uom }}</span></td>
                            <td class="num {{ (float) $l->received_qty > 0 ? 'text-success' : 'muted' }}">{{ qty($l->received_qty) }}</td>
                            <td class="num {{ $out > 0 ? 'text-warning' : 'muted' }}">{{ $out > 0 ? qty($out) : '—' }}</td>
                            <td class="num">{{ money($l->unit_price, false) }}</td>
                            <td class="num"><b>{{ money((float) $l->qty * (float) $l->unit_price, false) }}</b></td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot><tr><td colspan="6" class="right">{{ __('Total') }}</td><td class="num">{{ money($po->total) }}</td></tr></tfoot>
                </table>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head"><h3><x-icon name="arrow-down" />{{ __('Goods receipts') }}</h3><span class="panel-count">{{ __(':amount received', ['amount' => money($receivedValue)]) }}</span></div>
            @if($receipts->count())
                <div class="table-wrap"><table class="table compact">
                    <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Stock doc') }}</th><th>{{ __('Item') }}</th><th class="num">{{ __('Qty') }}</th><th class="hide-sm">{{ __('Remarks') }}</th><th class="hide-sm">{{ __('By') }}</th></tr></thead>
                    <tbody>@foreach($receipts as $m)
                        <tr><td>{{ d($m->moved_at, 'd M Y H:i') }}</td><td class="code">{{ $m->doc_no }}</td><td>{{ $m->item->name }}</td><td class="num text-success">+{{ qty($m->qty) }}</td><td class="hide-sm small muted">{{ $m->remarks }}</td><td class="hide-sm small">{{ $m->user?->name }}</td></tr>
                    @endforeach</tbody>
                </table></div>
            @else
                <x-empty icon="truck" :title="__('Nothing received yet')">{{ $canReceive ? __('Use “Receive goods” when the delivery arrives.') : ($po->status === 'draft' ? __('Mark the PO as ordered first.') : '') }}</x-empty>
            @endif
        </div>
    </div>

    <div class="stack">
        <div class="panel">
            <div class="panel-head"><h3><x-icon name="document" />{{ __('Details') }}</h3></div>
            <div class="panel-body">
                <dl class="dl">
                    <dt>{{ __('Supplier') }}</dt><dd><a href="{{ route('suppliers.show', $po->supplier) }}">{{ $po->supplier->name }}</a><div class="small muted">{{ $po->supplier->phone }}{{ $po->supplier->payment_terms ? ' · '.__($po->supplier->payment_terms) : '' }}</div></dd>
                    <dt>{{ __('Deliver to') }}</dt><dd>{{ $po->warehouse->code }} · {{ $po->warehouse->name }}</dd>
                    <dt>{{ __('Project') }}</dt><dd>@if($po->project)<a href="{{ route('projects.show', $po->project) }}">{{ $po->project->code }}</a><div class="small muted">{{ $po->project->name }}</div>@else — @endif</dd>
                    <dt>{{ __('Order date') }}</dt><dd>{{ d($po->order_date) }}</dd>
                    <dt>{{ __('Expected') }}</dt><dd>{{ d($po->expected_date) }}</dd>
                    <dt>{{ __('Created by') }}</dt><dd>{{ $po->creator?->name ?? '—' }}</dd>
                </dl>
                @if($po->notes)<hr><div class="small" style="white-space:pre-line">{{ $po->notes }}</div>@endif
            </div>
        </div>
        @if(in_array($po->status, ['draft', 'ordered'], true) && $received == 0)
            <form method="post" action="{{ route('purchase-orders.status', $po) }}" data-confirm="{{ __('Cancel :doc?', ['doc' => $po->doc_no]) }}">@csrf<input type="hidden" name="status" value="cancelled">
                <button class="btn btn-ghost btn-block"><x-icon name="x" class="ico ico-sm" />{{ __('Cancel purchase order') }}</button></form>
        @endif
        @if($po->status === 'draft')
            <form method="post" action="{{ route('purchase-orders.destroy', $po) }}" data-confirm="{{ __('Delete draft :doc?', ['doc' => $po->doc_no]) }}">@csrf @method('DELETE')
                <button class="btn btn-danger-ghost btn-block"><x-icon name="trash" class="ico ico-sm" />{{ __('Delete draft') }}</button></form>
        @endif
    </div>
</div>

@if($canReceive)
    @if(session('reopen'))<span data-reopen-dialog="{{ session('reopen') }}" hidden></span>@endif
    <dialog id="dlg-receive" class="wide">
        <form method="post" action="{{ route('purchase-orders.receive', $po) }}">
            @csrf
            <div class="dlg-head"><h3>{{ __('Receive goods — :doc into :wh', ['doc' => $po->doc_no, 'wh' => $po->warehouse->code]) }}</h3><button type="button" class="icon-btn" data-close><x-icon name="x" /></button></div>
            <div class="dlg-body">
                @error('receive')<div class="alert alert-danger"><x-icon name="exclamation" />{{ $message }}</div>@enderror
                <table class="table compact">
                    <thead><tr><th>{{ __('Item') }}</th><th class="num">{{ __('Outstanding') }}</th><th class="num" style="width:150px">{{ __('Receive now') }}</th></tr></thead>
                    <tbody>
                    @foreach($po->lines as $l)
                        @php
                            $out = max(0, (float) $l->qty - (float) $l->received_qty);
                        @endphp
                        <tr>
                            <td>{{ $l->item->name }}<span class="sub mono">{{ money($l->unit_price) }} / {{ $l->item->uom }}</span></td>
                            <td class="num">{{ qty($out) }} {{ $l->item->uom }}</td>
                            <td class="num">
                                @if($out > 0)
                                    <input class="input right receive-qty" type="number" step="0.01" min="0" max="{{ $out }}" name="receive[{{ $l->id }}]" value="{{ old('receive.'.$l->id, qty($out)) }}" data-out="{{ $out }}">
                                    @error('receive.'.$l->id)<div class="error-text">{{ $message }}</div>@enderror
                                @else <x-badge status="received" /> @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                <div class="form-grid" style="margin-top:14px">
                    <x-field name="reference" :label="__('Supplier DO / invoice no.')" :placeholder="__('e.g. DO-88213')" />
                    <x-field name="received_at" :label="__('Received at')" type="datetime-local" :value="now()->format('Y-m-d\TH:i')" />
                </div>
                <div class="row" style="margin-top:10px;gap:8px">
                    <button type="button" class="btn btn-ghost btn-sm" onclick="document.querySelectorAll('.receive-qty').forEach(i => i.value = i.dataset.out)">{{ __('Receive all outstanding') }}</button>
                    <button type="button" class="btn btn-ghost btn-sm" onclick="document.querySelectorAll('.receive-qty').forEach(i => i.value = 0)">{{ __('Clear') }}</button>
                </div>
            </div>
            <div class="dlg-foot"><button type="button" class="btn btn-ghost" data-close>{{ __('Cancel') }}</button><button class="btn btn-primary"><x-icon name="check" class="ico ico-sm" />{{ __('Post receipt') }}</button></div>
        </form>
    </dialog>
@endif
@endsection
