@extends('layouts.app')
@section('title', $inv->exists ? __('Edit').' '.$inv->doc_no : ($inv->type === 'proforma' ? __('New proforma') : __('New invoice')))
@section('content')
@php
    $kind = $inv->type === 'proforma' ? __('Proforma invoice') : __('Tax invoice');
@endphp
<x-page-header :eyebrow="__('Sales').' · '.__('Invoices').' · '.($inv->exists ? $inv->doc_no : __('New'))" :title="$inv->exists ? __('Edit').' '.$inv->doc_no : ($inv->type === 'proforma' ? __('New proforma invoice') : __('New tax invoice'))" :sub="__('Fields marked * are required. Ctrl+S saves.')">
    <x-slot:actions><a class="btn btn-ghost" href="{{ $inv->exists ? route('invoices.show', $inv) : route('invoices.index') }}">{{ __('Cancel') }}</a></x-slot:actions>
</x-page-header>

<form method="post" action="{{ $inv->exists ? route('invoices.update', $inv) : route('invoices.store') }}" class="panel" data-main-form>
    @csrf @if($inv->exists) @method('PUT') @endif
    <input type="hidden" name="quotation_id" value="{{ old('quotation_id', $inv->quotation_id) }}">
    <div class="form-section">
        <h4><span class="step">1</span>{{ __('Header') }}</h4>
        <div class="form-grid cols-4">
            @if($inv->exists)
                <div class="field"><label>{{ __('Type') }}</label><input value="{{ $kind }}" readonly></div>
            @else
                <x-field name="type" :label="__('Type')" type="select" :options="['invoice' => __('Tax invoice'), 'proforma' => __('Proforma invoice')]" :value="$inv->type" required />
            @endif
            <x-field name="customer_id" :label="__('Customer')" type="select" class="span-2" required :placeholder="__('— Select customer —')"
                     :options="$customers->mapWithKeys(fn ($c) => [$c->id => $c->name.' · '.$c->code.' · '.$c->phone])->all()" :value="$inv->customer_id" />
            <x-field name="project_id" :label="__('Project')" type="select" :placeholder="__('— None —')"
                     :options="$projects->mapWithKeys(fn ($p) => [$p->id => $p->code.' — '.\Illuminate\Support\Str::limit($p->name, 30)])->all()" :value="$inv->project_id" :hint="__('Must belong to the same customer.')" />
            <x-field name="invoice_date" :label="__('Invoice date')" type="date" :value="$inv->invoice_date" required />
            <x-field name="due_date" :label="__('Due date')" type="date" :value="$inv->due_date" />
            @if($inv->quotation_id)<div class="field"><label>{{ __('From quotation') }}</label><input value="{{ $inv->quotation?->doc_no ?? '#'.$inv->quotation_id }}" readonly></div>@endif
        </div>
    </div>
    <div class="form-section" style="padding:18px 0 0">
        <h4 style="padding:0 20px"><span class="step">2</span>{{ __('Lines') }}</h4>
        @include('quotations._lines', ['doc' => $inv])
    </div>
    <div class="form-section">
        <h4><span class="step">3</span>{{ __('Remarks') }}</h4>
        <x-field name="notes" :label="__('Remarks (printed) — bank details, payment instructions…')" type="textarea" :value="$inv->notes" />
    </div>
    <div class="form-actions">
        <a class="btn btn-ghost" href="{{ $inv->exists ? route('invoices.show', $inv) : route('invoices.index') }}">{{ __('Cancel') }}</a>
        <button class="btn btn-ghost" name="action" value="save"><x-icon name="check" class="ico ico-sm" />{{ $inv->exists ? __('Save') : __('Save draft') }}</button>
        @if(! $inv->exists || $inv->status === 'draft')
            <button class="btn btn-primary" name="action" value="issue"><x-icon name="arrow-right" class="ico ico-sm" />{{ __('Save & issue') }}</button>
        @endif
    </div>
</form>
@endsection
