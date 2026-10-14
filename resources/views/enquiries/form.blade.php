@extends('layouts.app')
@section('title', $e->exists ? __('Edit').' '.$e->doc_no : __('New enquiry'))
@section('content')
<x-page-header :eyebrow="__('Customers').' · '.__('Enquiries').' · '.($e->exists ? $e->doc_no : __('New'))" :title="$e->exists ? __('Edit enquiry') : __('New enquiry')" :sub="__('Fields marked * are required. Ctrl+S saves.')">
    <x-slot:actions><a class="btn btn-ghost" href="{{ $e->exists ? route('enquiries.show', $e) : route('enquiries.index') }}">{{ __('Cancel') }}</a></x-slot:actions>
</x-page-header>

<form method="post" action="{{ $e->exists ? route('enquiries.update', $e) : route('enquiries.store') }}" class="panel" data-main-form>
    @csrf @if($e->exists) @method('PUT') @endif
    <div class="form-section">
        <h4><span class="step">1</span>{{ __('Customer') }}</h4>
        <div class="form-grid cols-3">
            <div class="field span-2 {{ $errors->has('customer_id') ? 'has-error' : '' }}">
                <label for="f_customer_id">{{ __('Customer') }} <span class="req">*</span></label>
                <select id="f_customer_id" name="customer_id" required data-customer-select>
                    <option value="">{{ __('— Select customer —') }}</option>
                    @foreach($customers as $c)
                        <option value="{{ $c->id }}" @selected((string) old('customer_id', $e->customer_id) === (string) $c->id)
                            data-contact="{{ $c->contact_person ?: $c->name }}" data-phone="{{ $c->phone }}" data-state="{{ $c->state }}" data-branch="{{ $c->branch_id }}"
                            data-address="{{ trim($c->address.($c->postcode ? ', '.$c->postcode.' '.$c->city : ''), ', ') }}">{{ $c->name }} · {{ $c->code }} · {{ $c->phone }}</option>
                    @endforeach
                </select>
                @error('customer_id')<div class="error">{{ $message }}</div>@enderror
                <div class="hint">{!! __('Not in the list? <a href=":url">Create the customer first</a> (use “Save & add enquiry”).', ['url' => route('customers.create')]) !!}</div>
            </div>
            <x-field name="enquiry_date" :label="__('Enquiry date')" type="date" :value="$e->enquiry_date" required />
            <x-field name="contact_person" :label="__('Contact person')" :value="$e->contact_person" />
            <x-field name="contact_no" :label="__('Contact no')" :value="$e->contact_no" />
            <x-field name="branch_id" :label="__('Branch')" type="select" :options="$branches" :placeholder="__('— Same as customer —')" :value="$e->branch_id" />
        </div>
    </div>
    <div class="form-section">
        <h4><span class="step">2</span>{{ __('Job & site') }}</h4>
        <div class="form-grid cols-3">
            <x-field name="service" :label="__('Service')" type="select" :options="$services" :placeholder="__('— Select —')" :value="$e->service" required />
            <x-field name="state" :label="__('State')" type="select" :options="$states" placeholder="—" :value="$e->state" />
            <x-field name="inspection_date" :label="__('Inspection date')" type="date" :value="$e->inspection_date" />
            <x-field name="site_address" :label="__('Site address')" type="textarea" :value="$e->site_address" class="span-all" />
        </div>
    </div>
    <div class="form-section">
        <h4><span class="step">3</span>{{ __('Sales follow-up') }}</h4>
        <div class="form-grid cols-3">
            <x-field name="salesman_id" :label="__('Salesman')" type="select" :options="$salesmen" :placeholder="__('— Unassigned —')" :value="$e->salesman_id" />
            <x-field name="stage" :label="__('Stage')" type="select" :options="array_map('__', \App\Models\Enquiry::STAGES)" :value="$e->stage" required />
            <x-field name="estimate_amount" :label="__('Estimate (RM)')" type="number" step="0.01" min="0" :value="$e->estimate_amount" />
            <x-field name="lost_reason" :label="__('Lost reason')" :value="$e->lost_reason" :hint="__('Required when stage is Lost.')" />
            <x-field name="feedback" :label="__('Feedback / notes')" type="textarea" :value="$e->feedback" class="span-2" />
        </div>
    </div>
    <div class="form-actions">
        <a class="btn btn-ghost" href="{{ $e->exists ? route('enquiries.show', $e) : route('enquiries.index') }}">{{ __('Cancel') }}</a>
        <button class="btn btn-primary"><x-icon name="check" class="ico ico-sm" />{{ $e->exists ? __('Save changes') : __('Record enquiry') }}</button>
    </div>
</form>

@push('scripts')
<script>
    // Picking a customer fills empty contact/site fields from their profile (never overwrites what you typed).
    document.querySelector('[data-customer-select]')?.addEventListener('change', function () {
        const o = this.selectedOptions[0];
        if (!o || !o.value) return;
        const fill = (name, val) => { const el = document.querySelector(`[name="${name}"]`); if (el && !el.value && val) el.value = val; };
        fill('contact_person', o.dataset.contact); fill('contact_no', o.dataset.phone);
        fill('site_address', o.dataset.address); fill('state', o.dataset.state); fill('branch_id', o.dataset.branch);
    });
</script>
@endpush
@endsection
