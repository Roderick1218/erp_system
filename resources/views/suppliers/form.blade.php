@extends('layouts.app')
@section('title', $s->exists ? __('Edit').' '.$s->code : __('New supplier'))
@section('content')
<x-page-header :eyebrow="__('Suppliers').' · '.($s->exists ? $s->code : __('New'))" :title="$s->exists ? __('Edit supplier') : __('New supplier')" :sub="__('Fields marked * are required. Ctrl+S saves.')">
    <x-slot:actions><a class="btn btn-ghost" href="{{ $s->exists ? route('suppliers.show', $s) : route('suppliers.index') }}">{{ __('Cancel') }}</a></x-slot:actions>
</x-page-header>

<form method="post" action="{{ $s->exists ? route('suppliers.update', $s) : route('suppliers.store') }}" class="panel" data-main-form>
    @csrf @if($s->exists) @method('PUT') @endif
    <div class="form-section">
        <h4><span class="step">1</span>{{ __('Company') }}</h4>
        <div class="form-grid cols-3">
            <x-field name="code" :label="__('Supplier code')" :value="$s->code" required />
            <x-field name="name" :label="__('Company name')" :value="$s->name" required class="span-2" />
            <x-field name="contact_person" :label="__('Contact person')" :value="$s->contact_person" />
            <x-field name="phone" :label="__('Phone')" :value="$s->phone" />
            <x-field name="email" :label="__('Email')" type="email" :value="$s->email" />
            <x-field name="address" :label="__('Address')" type="textarea" :value="$s->address" class="span-all" />
        </div>
    </div>
    <div class="form-section">
        <h4><span class="step">2</span>{{ __('Terms') }}</h4>
        <div class="form-grid cols-3">
            <div class="field">
                <label for="f_terms">{{ __('Payment terms') }}</label>
                <input id="f_terms" name="payment_terms" list="terms-list" value="{{ old('payment_terms', $s->payment_terms) }}" placeholder="{{ __('COD, 30 days…') }}">
                <datalist id="terms-list">@foreach(['COD', '7 days', '14 days', '30 days', '60 days', '90 days'] as $t)<option value="{{ $t }}">{{ __($t) }}</option>@endforeach</datalist>
            </div>
            <x-field name="status" :label="__('Status')" type="select" :options="['active' => __('Active'), 'inactive' => __('Inactive')]" :value="$s->status" required />
        </div>
    </div>
    <div class="form-actions">
        <a class="btn btn-ghost" href="{{ $s->exists ? route('suppliers.show', $s) : route('suppliers.index') }}">{{ __('Cancel') }}</a>
        <button class="btn btn-primary"><x-icon name="check" class="ico ico-sm" />{{ $s->exists ? __('Save changes') : __('Create supplier') }}</button>
    </div>
</form>
@endsection
