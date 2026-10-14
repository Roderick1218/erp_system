@extends('layouts.app')
@section('title', $c->exists ? __('Edit').' '.$c->code : __('New customer'))
@section('content')
<x-page-header :eyebrow="__('Customers').' · '.($c->exists ? $c->code : __('New'))" :title="$c->exists ? __('Edit customer') : __('New customer')" :sub="__('Fields marked * are required. Ctrl+S saves.')">
    <x-slot:actions><a class="btn btn-ghost" href="{{ $c->exists ? route('customers.show', $c) : route('customers.index') }}">{{ __('Cancel') }}</a></x-slot:actions>
</x-page-header>

<form method="post" action="{{ $c->exists ? route('customers.update', $c) : route('customers.store') }}" class="panel" data-main-form>
    @csrf @if($c->exists) @method('PUT') @endif
    <div class="form-section">
        <h4><span class="step">1</span>{{ __('Who is the customer?') }}</h4>
        <div class="form-grid cols-3">
            <x-field name="name" :label="__('Customer / company name')" :value="$c->name" required class="span-2" />
            <x-field name="type" :label="__('Type')" type="select" :options="['individual' => __('Individual'), 'company' => __('Company'), 'government' => __('Government')]" :value="$c->type" required />
            <x-field name="contact_person" :label="__('Contact person')" :value="$c->contact_person" :hint="__('For companies — who do we talk to?')" />
            <x-field name="phone" :label="__('Mobile / WhatsApp')" :value="$c->phone" required placeholder="+60123456789" />
            <x-field name="phone2" :label="__('Alternative phone')" :value="$c->phone2" />
            <x-field name="email" :label="__('Email')" type="email" :value="$c->email" />
            <x-field name="document_type" :label="__('Document type')" type="select" :options="['IC' => __('IC (MyKad)'), 'BRN' => __('Business reg. no.'), 'PASSPORT' => __('Passport')]" placeholder="—" :value="$c->document_type" />
            <x-field name="document_id" :label="__('Document ID')" :value="$c->document_id" />
        </div>
    </div>
    <div class="form-section">
        <h4><span class="step">2</span>{{ __('Address') }}</h4>
        <div class="form-grid cols-4">
            <x-field name="address" :label="__('Street address')" type="textarea" :value="$c->address" class="span-all" />
            <x-field name="postcode" :label="__('Postcode')" :value="$c->postcode" />
            <x-field name="city" :label="__('City / area')" :value="$c->city" />
            <x-field name="state" :label="__('State')" type="select" :options="$states" placeholder="—" :value="$c->state" />
        </div>
    </div>
    <div class="form-section">
        <h4><span class="step">3</span>{{ __('Internal') }}</h4>
        <div class="form-grid cols-3">
            <x-field name="branch_id" :label="__('Handled by branch')" type="select" :options="$branches" placeholder="—" :value="$c->branch_id" />
            <x-field name="source" :label="__('How did they find us?')" type="select" :options="$sources" placeholder="—" :value="$c->source" />
            <x-field name="status" :label="__('Status')" type="select" :options="['active' => __('Active'), 'inactive' => __('Inactive'), 'blacklist' => __('Blacklist')]" :value="$c->status" required />
            <x-field name="notes" :label="__('Notes')" type="textarea" :value="$c->notes" class="span-all" />
        </div>
    </div>
    <div class="form-actions">
        <a class="btn btn-ghost" href="{{ $c->exists ? route('customers.show', $c) : route('customers.index') }}">{{ __('Cancel') }}</a>
        @unless($c->exists)<button class="btn btn-ghost" name="then_enquiry" value="1">{{ __('Save & add enquiry') }}</button>@endunless
        <button class="btn btn-primary"><x-icon name="check" class="ico ico-sm" />{{ $c->exists ? __('Save changes') : __('Create customer') }}</button>
    </div>
</form>
@endsection
