@extends('layouts.app')
@section('title', $q->doc_no)
@section('content')
@php
    $st = $q->display_status;
@endphp
<x-page-header :eyebrow="__('Sales').' · '.__('Quotations').' · '.$q->doc_no" :title="__('Quotation :no', ['no' => $q->doc_no])" :sub="$q->customer->name.' · '.d($q->quote_date).' · '.__('valid until :date', ['date' => d($q->valid_until)])">
    <x-slot:badge><x-badge :status="$st" /></x-slot:badge>
    <x-slot:actions>
        <a class="btn btn-ghost" href="{{ route('quotations.print', $q) }}" target="_blank"><x-icon name="printer" class="ico ico-sm" />{{ __('Print / PDF') }}</a>
        @if($q->isEditable())<a class="btn btn-ghost" href="{{ route('quotations.edit', $q) }}"><x-icon name="pencil" class="ico ico-sm" />{{ __('Edit') }}</a>@endif
        @if($q->status === 'draft')
            <form method="post" action="{{ route('quotations.status', $q) }}">@csrf<input type="hidden" name="status" value="sent"><button class="btn btn-primary"><x-icon name="arrow-right" class="ico ico-sm" />{{ __('Mark as sent') }}</button></form>
        @endif
        @if($q->status === 'sent')
            <form method="post" action="{{ route('quotations.status', $q) }}" data-confirm="{{ __('Mark :no as rejected?', ['no' => $q->doc_no]) }}">@csrf<input type="hidden" name="status" value="rejected"><button class="btn btn-danger-ghost">{{ __('Rejected') }}</button></form>
            <form method="post" action="{{ route('quotations.status', $q) }}">@csrf<input type="hidden" name="status" value="accepted"><button class="btn btn-success"><x-icon name="check" class="ico ico-sm" />{{ __('Customer accepted') }}</button></form>
        @endif
        @if(in_array($q->status, ['sent', 'accepted'], true))
            <button type="button" class="btn btn-primary" data-dialog="dlg-invoice"><x-icon name="receipt" class="ico ico-sm" />{{ __('Create invoice') }}</button>
        @endif
        @if($q->status === 'accepted' && ! $q->project_id && auth()->user()->can('projects.manage'))
            <form method="post" action="{{ route('quotations.project', $q) }}">@csrf<button class="btn btn-navy"><x-icon name="briefcase" class="ico ico-sm" />{{ __('Create project') }}</button></form>
        @endif
        @if(in_array($q->status, ['rejected', 'expired'], true) || $st === 'expired')
            <form method="post" action="{{ route('quotations.status', $q) }}">@csrf<input type="hidden" name="status" value="sent"><button class="btn btn-ghost"><x-icon name="refresh" class="ico ico-sm" />{{ __('Re-open as sent') }}</button></form>
        @endif
    </x-slot:actions>
</x-page-header>

<div class="grid g-main-side">
    <div class="panel">
        <div class="panel-body" style="padding:24px">
            <div class="row between" style="align-items:flex-start;margin-bottom:18px">
                <div>
                    <div class="label">{{ __('Bill to') }}</div>
                    <div style="font-weight:600;font-size:15px">{{ $q->customer->name }}</div>
                    <div class="small muted" style="white-space:pre-line">{{ $q->customer->contact_person ? __('Attn:').' '.$q->customer->contact_person."\n" : '' }}{{ $q->customer->address }}{{ $q->customer->postcode ? "\n".$q->customer->postcode.' '.$q->customer->city.', '.$q->customer->state : '' }}</div>
                    <div class="small muted">{{ $q->customer->phone }}</div>
                </div>
                @if($q->enquiry)
                    <div class="right">
                        <div class="label">{{ __('Site') }}</div>
                        <div class="small" style="max-width:280px">{{ $q->enquiry->site_address }}</div>
                        <div class="small muted">{{ __($q->enquiry->service) }}</div>
                    </div>
                @endif
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th class="idx">#</th><th>{{ __('Description') }}</th><th class="num">{{ __('Qty') }}</th><th>{{ __('UOM') }}</th><th class="num">{{ __('Unit price') }}</th><th class="num">{{ __('Amount') }}</th></tr></thead>
                    <tbody>
                    @foreach($q->lines as $l)
                        <tr><td class="idx">{{ $loop->iteration }}</td><td>{{ $l->description }}@if($l->item)<span class="sub mono">{{ $l->item->sku }}</span>@endif</td><td class="num">{{ qty($l->qty) }}</td><td>{{ $l->uom }}</td><td class="num">{{ money($l->unit_price, false) }}</td><td class="num">{{ money($l->amount, false) }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <table class="totals" style="margin-top:12px">
                <tr><td>{{ __('Subtotal') }}</td><td>{{ money($q->subtotal) }}</td></tr>
                @if((float) $q->discount)<tr><td>{{ __('Discount') }}</td><td>− {{ money($q->discount) }}</td></tr>@endif
                @if((float) $q->tax)<tr><td>{{ __('Tax (:rate%)', ['rate' => qty($q->tax_rate)]) }}</td><td>{{ money($q->tax) }}</td></tr>@endif
                <tr class="grand"><td>{{ __('Total') }}</td><td>{{ money($q->total) }}</td></tr>
            </table>
            @if($q->notes)<hr><div class="label">{{ __('Remarks') }}</div><div class="small" style="white-space:pre-line">{{ $q->notes }}</div>@endif
            @if($q->terms)<hr><div class="label">{{ __('Terms & conditions') }}</div><div class="small muted" style="white-space:pre-line">{{ $q->terms }}</div>@endif
        </div>
    </div>

    <div class="stack">
        <div class="panel">
            <div class="panel-head"><h3><x-icon name="info" />{{ __('Summary') }}</h3></div>
            <div class="panel-body">
                <dl class="dl">
                    <dt>{{ __('Customer') }}</dt><dd>@can('customers.view')<a href="{{ route('customers.show', $q->customer) }}">{{ $q->customer->name }}</a>@else{{ $q->customer->name }}@endcan</dd>
                    <dt>{{ __('Enquiry') }}</dt><dd>@if($q->enquiry)<a class="mono" href="{{ route('enquiries.show', $q->enquiry) }}">{{ $q->enquiry->doc_no }}</a> <x-badge :status="$q->enquiry->stage" />@else — @endif</dd>
                    <dt>{{ __('Project') }}</dt><dd>@if($q->project)<a class="mono" href="{{ route('projects.show', $q->project) }}">{{ $q->project->code }}</a>@else — @endif</dd>
                    <dt>{{ __('Salesman') }}</dt><dd>{{ $q->salesman?->name ?? '—' }}</dd>
                    <dt>{{ __('Lines') }}</dt><dd>{{ $q->lines->count() }}</dd>
                    <dt>{{ __('Total') }}</dt><dd><b>{{ money($q->total) }}</b></dd>
                </dl>
            </div>
        </div>
        <div class="panel">
            <div class="panel-head"><h3><x-icon name="receipt" />{{ __('Invoices from this quotation') }}</h3></div>
            @if($q->invoices->count())
                <ul class="list">
                    @foreach($q->invoices as $inv)
                        <li><a class="li-link mono" href="{{ route('invoices.show', $inv) }}">{{ $inv->doc_no }}</a><span class="row" style="gap:8px"><x-badge :status="$inv->display_status" /><b>{{ money($inv->total) }}</b></span></li>
                    @endforeach
                </ul>
                <div class="panel-foot small"><span>{{ __('Invoiced :done of :total', ['done' => money($q->invoices->where('type', 'invoice')->where('status', '!=', 'void')->sum('total')), 'total' => money($q->total)]) }}</span></div>
            @else
                <x-empty icon="receipt" :title="__('Not invoiced yet')" />
            @endif
        </div>
        @if($q->status === 'draft')
            <form method="post" action="{{ route('quotations.destroy', $q) }}" data-confirm="{{ __('Delete draft :no?', ['no' => $q->doc_no]) }}">
                @csrf @method('DELETE')
                <button class="btn btn-danger-ghost btn-block"><x-icon name="trash" class="ico ico-sm" />{{ __('Delete draft') }}</button>
            </form>
        @endif
    </div>
</div>

@if(in_array($q->status, ['sent', 'accepted'], true))
<dialog id="dlg-invoice">
    <form method="post" action="{{ route('quotations.invoice', $q) }}">
        @csrf
        <div class="dlg-head"><h3>{{ __('Create invoice from :no', ['no' => $q->doc_no]) }}</h3><button type="button" class="icon-btn" data-close><x-icon name="x" /></button></div>
        <div class="dlg-body stack">
            <div class="field"><label>{{ __('Document type') }}</label>
                <select name="type"><option value="invoice">{{ __('Tax invoice') }}</option><option value="proforma">{{ __('Proforma invoice (request for deposit)') }}</option></select>
            </div>
            <div class="field"><label>{{ __('Amount to bill (% of quotation)') }}</label>
                <div class="input-group"><span class="addon">%</span><input type="number" name="percent" value="100" min="1" max="100" step="0.01" required></div>
                <div class="hint">{{ __('100% copies every line. Use e.g. 50 for a deposit invoice — it becomes a single “50% of quotation” line.') }}</div>
            </div>
            @if($q->status === 'sent')<div class="alert alert-info" style="margin:0"><x-icon name="info" />{{ __('Creating an invoice also marks this quotation as accepted and its enquiry as won.') }}</div>@endif
        </div>
        <div class="dlg-foot"><button type="button" class="btn btn-ghost" data-close>{{ __('Cancel') }}</button><button class="btn btn-primary">{{ __('Create draft invoice') }}</button></div>
    </form>
</dialog>
@endif
@endsection
