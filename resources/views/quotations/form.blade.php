@extends('layouts.app')
@section('title', $q->exists ? __('Edit').' '.$q->doc_no : __('New quotation'))
@section('content')
<x-page-header :eyebrow="__('Sales').' · '.__('Quotations').' · '.($q->exists ? $q->doc_no : __('New'))" :title="$q->exists ? __('Edit quotation :no', ['no' => $q->doc_no]) : __('New quotation')" :sub="$enquiry ? __('For enquiry :no', ['no' => $enquiry->doc_no]).' · '.__($enquiry->service).' · '.$enquiry->site_address : __('Fields marked * are required. Ctrl+S saves.')">
    <x-slot:actions><a class="btn btn-ghost" href="{{ $q->exists ? route('quotations.show', $q) : ($enquiry ? route('enquiries.show', $enquiry) : route('quotations.index')) }}">{{ __('Cancel') }}</a></x-slot:actions>
</x-page-header>

<form method="post" action="{{ $q->exists ? route('quotations.update', $q) : route('quotations.store') }}" class="panel" data-main-form>
    @csrf @if($q->exists) @method('PUT') @endif
    <input type="hidden" name="enquiry_id" value="{{ old('enquiry_id', $q->enquiry_id) }}">
    <div class="form-section">
        <h4><span class="step">1</span>{{ __('Header') }}</h4>
        <div class="form-grid cols-4">
            <x-field name="customer_id" :label="__('Customer')" type="select" class="span-2" required :placeholder="__('— Select customer —')"
                     :options="$customers->mapWithKeys(fn ($c) => [$c->id => $c->name.' · '.$c->code.' · '.$c->phone])->all()" :value="$q->customer_id" />
            <x-field name="quote_date" :label="__('Quotation date')" type="date" :value="$q->quote_date" required />
            <x-field name="valid_until" :label="__('Valid until')" type="date" :value="$q->valid_until" />
            <x-field name="salesman_id" :label="__('Salesman')" type="select" :options="$salesmen" placeholder="—" :value="$q->salesman_id" />
        </div>
    </div>
    <div class="form-section" style="padding:18px 0 0">
        <h4 style="padding:0 20px"><span class="step">2</span>{{ __('Items & workmanship') }}</h4>
        @include('quotations._lines', ['doc' => $q])
    </div>
    <div class="form-section">
        <h4><span class="step">3</span>{{ __('Terms & notes') }}</h4>
        <div class="form-grid">
            <x-field name="terms" :label="__('Terms & conditions (printed)')" type="textarea" :value="$q->terms" />
            <x-field name="notes" :label="__('Remarks (printed under items)')" type="textarea" :value="$q->notes" />
        </div>
    </div>
    <div class="form-actions">
        <a class="btn btn-ghost" href="{{ $q->exists ? route('quotations.show', $q) : route('quotations.index') }}">{{ __('Cancel') }}</a>
        @unless($q->exists)<button class="btn btn-ghost" name="action" value="send"><x-icon name="arrow-right" class="ico ico-sm" />{{ __('Save as sent') }}</button>@endunless
        <button class="btn btn-primary" name="action" value="save"><x-icon name="check" class="ico ico-sm" />{{ $q->exists ? __('Save changes') : __('Save draft') }}</button>
    </div>
</form>
@endsection
