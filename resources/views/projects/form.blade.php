@extends('layouts.app')
@section('title', $p->exists ? __('Edit').' '.$p->code : __('New project'))
@section('content')
@php
    $serviceOptions = collect($services)->mapWithKeys(fn ($s) => [$s => __($s)])->all();
    $stateOptions = collect($states)->mapWithKeys(fn ($s) => [$s => __($s)])->all();
    $statusOptions = collect(\App\Models\Project::STATUSES)->map(fn ($l) => __($l))->all();
@endphp
<x-page-header :eyebrow="__('Project').' · '.($p->exists ? $p->code : __('New'))" :title="$p->exists ? __('Edit project') : __('New project')" :sub="__('Fields marked * are required. Ctrl+S saves.')">
    <x-slot:actions><a class="btn btn-ghost" href="{{ $p->exists ? route('projects.show', $p) : route('projects.index') }}">{{ __('Cancel') }}</a></x-slot:actions>
</x-page-header>

@if(! $p->exists && $p->enquiry_id)
    <div class="alert alert-info"><x-icon name="info" />{!! $quotationId
        ? __('Pre-filled from enquiry :doc and its quotation. The enquiry will be marked <b>Won</b> when you save.', ['doc' => e($p->enquiry?->doc_no)])
        : __('Pre-filled from enquiry :doc. The enquiry will be marked <b>Won</b> when you save.', ['doc' => e($p->enquiry?->doc_no)]) !!}</div>
@endif

<form method="post" action="{{ $p->exists ? route('projects.update', $p) : route('projects.store') }}" class="panel" data-main-form>
    @csrf @if($p->exists) @method('PUT') @endif
    <input type="hidden" name="enquiry_id" value="{{ old('enquiry_id', $p->enquiry_id) }}">
    @if($quotationId)<input type="hidden" name="quotation_id" value="{{ $quotationId }}">@endif
    <div class="form-section">
        <h4><span class="step">1</span>{{ __('Job') }}</h4>
        <div class="form-grid cols-3">
            <x-field name="name" :label="__('Project name')" :value="$p->name" required class="span-2" :placeholder="__('e.g. Roof Replacement — Puchong')" />
            <x-field name="category" :label="__('Category')" type="select" :options="['normal' => __('Normal project'), 'temporary' => __('Temporary project'), 'aftercare' => __('Aftercare project')]" :value="$p->category" required />
            <x-field name="customer_id" :label="__('Customer')" type="select" :options="$customers" :placeholder="__('— Select customer —')" :value="$p->customer_id" required class="span-2" />
            <x-field name="service" :label="__('Service')" type="select" :options="$serviceOptions" placeholder="—" :value="$p->service" />
            <x-field name="site_address" :label="__('Site address')" type="textarea" :value="$p->site_address" class="span-2" />
            <x-field name="state" :label="__('State')" type="select" :options="$stateOptions" placeholder="—" :value="$p->state" />
        </div>
    </div>
    <div class="form-section">
        <h4><span class="step">2</span>{{ __('Schedule & team') }}</h4>
        <div class="form-grid cols-4">
            <x-field name="start_date" :label="__('Start date')" type="date" :value="$p->start_date" />
            <x-field name="end_date" :label="__('Target completion')" type="date" :value="$p->end_date" />
            <x-field name="manager_id" :label="__('Project manager / supervisor')" type="select" :options="$managers" placeholder="—" :value="$p->manager_id" />
            <x-field name="branch_id" :label="__('Branch')" type="select" :options="$branches" placeholder="—" :value="$p->branch_id" />
            <x-field name="status" :label="__('Status')" type="select" :options="$statusOptions" :value="$p->status" required />
            <x-field name="progress" :label="__('Progress %')" type="number" min="0" max="100" :value="$p->progress" />
        </div>
    </div>
    <div class="form-section">
        <h4><span class="step">3</span>{{ __('Money') }}</h4>
        <div class="form-grid cols-4">
            <x-field name="contract_value" :label="__('Contract value (RM)')" type="number" step="0.01" min="0" :value="$p->contract_value" :hint="__('Usually the accepted quotation total.')" />
            <x-field name="budget_cost" :label="__('Budget cost (RM)')" type="number" step="0.01" min="0" :value="$p->budget_cost" :hint="__('Target cost — costing compares actual against this.')" />
            <x-field name="notes" :label="__('Notes')" type="textarea" :value="$p->notes" class="span-all" />
        </div>
    </div>
    <div class="form-actions">
        <a class="btn btn-ghost" href="{{ $p->exists ? route('projects.show', $p) : route('projects.index') }}">{{ __('Cancel') }}</a>
        <button class="btn btn-primary"><x-icon name="check" class="ico ico-sm" />{{ $p->exists ? __('Save changes') : __('Create project') }}</button>
    </div>
</form>
@endsection
