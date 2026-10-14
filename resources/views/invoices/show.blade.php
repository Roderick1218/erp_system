@extends('layouts.app')
@section('title', $inv->doc_no)
@section('content')
@php
    $kind = $inv->type === 'proforma' ? __('Proforma invoice') : __('Tax invoice');
    $canPay = in_array($inv->status, ['unpaid', 'partial'], true);
    $pct = (float) $inv->total > 0 ? min(100, round((float) $inv->paid_amount / (float) $inv->total * 100)) : 0;
@endphp
<x-page-header :eyebrow="__('Sales').' · '.__('Invoices').' · '.$inv->doc_no" :title="$kind.' '.$inv->doc_no" :sub="$inv->customer->name.' · '.d($inv->invoice_date).' · '.__('due :date', ['date' => d($inv->due_date)])">
    <x-slot:badge><x-badge :status="$inv->display_status" /></x-slot:badge>
    <x-slot:actions>
        <a class="btn btn-ghost" href="{{ route('invoices.print', $inv) }}" target="_blank"><x-icon name="printer" class="ico ico-sm" />{{ __('Print / PDF') }}</a>
        @if($inv->isEditable())<a class="btn btn-ghost" href="{{ route('invoices.edit', $inv) }}"><x-icon name="pencil" class="ico ico-sm" />{{ __('Edit') }}</a>@endif
        @if($inv->status === 'draft')
            <form method="post" action="{{ route('invoices.issue', $inv) }}">@csrf<button class="btn btn-primary"><x-icon name="arrow-right" class="ico ico-sm" />{{ __('Issue invoice') }}</button></form>
        @endif
        @if($canPay)<button type="button" class="btn btn-success" data-dialog="dlg-pay"><x-icon name="banknotes" class="ico ico-sm" />{{ __('Record payment') }}</button>@endif
    </x-slot:actions>
</x-page-header>

@if($inv->status === 'draft')
    <div class="alert alert-info"><x-icon name="info" />{{ __('This is a draft — it is not counted as receivable until you issue it.') }}</div>
@elseif($inv->is_overdue)
    <div class="alert alert-danger"><x-icon name="exclamation" />{{ __('Overdue by :n day(s) — balance :balance.', ['n' => (int) $inv->due_date->diffInDays(today()), 'balance' => money($inv->balance)]) }}</div>
@elseif($inv->status === 'void')
    <div class="alert alert-warning"><x-icon name="x" />{{ __('This invoice has been voided and is excluded from all totals.') }}</div>
@endif

<div class="grid g-main-side">
    <div class="panel">
        <div class="panel-body" style="padding:24px">
            <div class="row between" style="align-items:flex-start;margin-bottom:18px">
                <div>
                    <div class="label">{{ __('Bill to') }}</div>
                    <div style="font-weight:600;font-size:15px">{{ $inv->customer->name }}</div>
                    <div class="small muted" style="white-space:pre-line">{{ $inv->customer->contact_person ? __('Attn:').' '.$inv->customer->contact_person."\n" : '' }}{{ $inv->customer->address }}{{ $inv->customer->postcode ? "\n".$inv->customer->postcode.' '.$inv->customer->city.', '.$inv->customer->state : '' }}</div>
                </div>
                @if($inv->project)
                    <div class="right"><div class="label">{{ __('Project') }}</div><div class="mono">{{ $inv->project->code }}</div><div class="small muted" style="max-width:260px">{{ $inv->project->name }}</div></div>
                @endif
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th class="idx">#</th><th>{{ __('Description') }}</th><th class="num">{{ __('Qty') }}</th><th>{{ __('UOM') }}</th><th class="num">{{ __('Unit price') }}</th><th class="num">{{ __('Amount') }}</th></tr></thead>
                    <tbody>
                    @foreach($inv->lines as $l)
                        <tr><td class="idx">{{ $loop->iteration }}</td><td>{{ $l->description }}@if($l->item)<span class="sub mono">{{ $l->item->sku }}</span>@endif</td><td class="num">{{ qty($l->qty) }}</td><td>{{ $l->uom }}</td><td class="num">{{ money($l->unit_price, false) }}</td><td class="num">{{ money($l->amount, false) }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <table class="totals" style="margin-top:12px">
                <tr><td>{{ __('Subtotal') }}</td><td>{{ money($inv->subtotal) }}</td></tr>
                @if((float) $inv->discount)<tr><td>{{ __('Discount') }}</td><td>− {{ money($inv->discount) }}</td></tr>@endif
                @if((float) $inv->tax)<tr><td>{{ __('Tax (:rate%)', ['rate' => qty($inv->tax_rate)]) }}</td><td>{{ money($inv->tax) }}</td></tr>@endif
                <tr class="grand"><td>{{ __('Total') }}</td><td>{{ money($inv->total) }}</td></tr>
                <tr><td>{{ __('Paid') }}</td><td>{{ money($inv->paid_amount) }}</td></tr>
                <tr class="grand"><td>{{ __('Balance due') }}</td><td class="{{ $inv->balance > 0 && $inv->status !== 'void' ? 'text-danger' : '' }}">{{ money($inv->status === 'void' ? 0 : $inv->balance) }}</td></tr>
            </table>
            @if($inv->notes)<hr><div class="label">{{ __('Remarks') }}</div><div class="small" style="white-space:pre-line">{{ $inv->notes }}</div>@endif
        </div>
    </div>

    <div class="stack">
        <div class="panel">
            <div class="panel-head"><h3><x-icon name="banknotes" />{{ __('Payments') }}</h3><span class="panel-count">{{ __(':pct% paid', ['pct' => $pct]) }}</span></div>
            <div class="panel-body" style="padding-bottom:8px"><div class="progress" style="height:9px"><span style="width:{{ $pct }}%"></span></div></div>
            @if($inv->payments->count())
                <ul class="list">
                    @foreach($inv->payments as $p)
                        <li>
                            <span><b>{{ money($p->amount) }}</b><div class="meta">{{ d($p->paid_on) }} · {{ __($p->method) }}{{ $p->reference ? ' · '.$p->reference : '' }}</div></span>
                            <form method="post" action="{{ route('payments.destroy', $p) }}" data-confirm="{{ __('Remove this payment of :amount? The balance will be recalculated.', ['amount' => money($p->amount)]) }}">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm btn-icon" title="{{ __('Remove') }}"><x-icon name="trash" class="ico ico-sm" /></button></form>
                        </li>
                    @endforeach
                </ul>
            @else
                <x-empty icon="banknotes" :title="__('No payments yet')">@if($canPay)<button type="button" class="btn btn-ghost btn-sm" data-dialog="dlg-pay">{{ __('Record payment') }}</button>@endif</x-empty>
            @endif
        </div>
        <div class="panel">
            <div class="panel-head"><h3><x-icon name="info" />{{ __('Details') }}</h3></div>
            <div class="panel-body">
                <dl class="dl">
                    <dt>{{ __('Customer') }}</dt><dd>@can('customers.view')<a href="{{ route('customers.show', $inv->customer) }}">{{ $inv->customer->name }}</a>@else{{ $inv->customer->name }}@endcan</dd>
                    <dt>{{ __('Quotation') }}</dt><dd>@if($inv->quotation)<a class="mono" href="{{ route('quotations.show', $inv->quotation) }}">{{ $inv->quotation->doc_no }}</a>@else — @endif</dd>
                    <dt>{{ __('Project') }}</dt><dd>@if($inv->project)<a class="mono" href="{{ route('projects.show', $inv->project) }}">{{ $inv->project->code }}</a>@else — @endif</dd>
                    <dt>{{ __('Created by') }}</dt><dd>{{ $inv->creator?->name ?? '—' }}</dd>
                </dl>
            </div>
        </div>
        @if($inv->status === 'draft')
            <form method="post" action="{{ route('invoices.destroy', $inv) }}" data-confirm="{{ __('Delete draft :no?', ['no' => $inv->doc_no]) }}">@csrf @method('DELETE')<button class="btn btn-danger-ghost btn-block"><x-icon name="trash" class="ico ico-sm" />{{ __('Delete draft') }}</button></form>
        @elseif($inv->status !== 'void' && ! $inv->payments->count())
            <button type="button" class="btn btn-danger-ghost btn-block" data-dialog="dlg-void"><x-icon name="x" class="ico ico-sm" />{{ __('Void invoice') }}</button>
        @endif
    </div>
</div>

@if($canPay)
<dialog id="dlg-pay">
    <form method="post" action="{{ route('payments.store', $inv) }}">
        @csrf
        <div class="dlg-head"><h3>{{ __('Record payment') }} — {{ $inv->doc_no }}</h3><button type="button" class="icon-btn" data-close><x-icon name="x" /></button></div>
        <div class="dlg-body">
            <div class="form-grid">
                <div class="field"><label>{{ __('Amount (RM)') }} <span class="req">*</span></label><input type="number" name="amount" step="0.01" min="0.01" max="{{ $inv->balance }}" value="{{ $inv->balance }}" required><div class="hint">{{ __('Balance due :amount', ['amount' => money($inv->balance)]) }}</div></div>
                <div class="field"><label>{{ __('Date received') }} <span class="req">*</span></label><input type="date" name="paid_on" value="{{ today()->format('Y-m-d') }}" max="{{ today()->format('Y-m-d') }}" required></div>
                <div class="field"><label>{{ __('Method') }} <span class="req">*</span></label><select name="method" required>@foreach($methods as $m)<option value="{{ $m }}">{{ __($m) }}</option>@endforeach</select></div>
                <div class="field"><label>{{ __('Reference') }}</label><input name="reference" placeholder="{{ __('Bank ref / cheque no') }}"></div>
            </div>
        </div>
        <div class="dlg-foot"><button type="button" class="btn btn-ghost" data-close>{{ __('Cancel') }}</button><button class="btn btn-success">{{ __('Save payment') }}</button></div>
    </form>
</dialog>
@endif
@if($inv->status !== 'void' && $inv->status !== 'draft')
<dialog id="dlg-void">
    <form method="post" action="{{ route('invoices.void', $inv) }}">
        @csrf
        <div class="dlg-head"><h3>{{ __('Void :no', ['no' => $inv->doc_no]) }}</h3><button type="button" class="icon-btn" data-close><x-icon name="x" /></button></div>
        <div class="dlg-body">
            <p class="small muted" style="margin-top:0">{{ __('The document number stays reserved; the invoice is removed from receivables. This cannot be undone.') }}</p>
            <div class="field"><label>{{ __('Reason') }}</label><input name="reason" placeholder="{{ __('e.g. Wrong amount, re-issued as INV-…') }}"></div>
        </div>
        <div class="dlg-foot"><button type="button" class="btn btn-ghost" data-close>{{ __('Cancel') }}</button><button class="btn btn-danger">{{ __('Void invoice') }}</button></div>
    </form>
</dialog>
@endif
@endsection
