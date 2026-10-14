@extends('layouts.app')
@section('title', $w->exists ? __('Edit').' '.$w->doc_no : __('Issue warranty'))
@section('content')
@php($serviceOptions = collect($services)->mapWithKeys(fn ($s) => [$s => __($s)])->all())
<x-page-header :eyebrow="__('After-sales').' · '.__('Warranty').' · '.($w->exists ? $w->doc_no : __('New'))" :title="$w->exists ? __('Edit warranty') : __('Issue warranty')" :sub="__('End date is calculated from the start date + period.')">
    <x-slot:actions><a class="btn btn-ghost" href="{{ $w->exists ? route('warranties.show', $w) : route('warranties.index') }}">{{ __('Cancel') }}</a></x-slot:actions>
</x-page-header>

<form method="post" action="{{ $w->exists ? route('warranties.update', $w) : route('warranties.store') }}" class="panel" data-main-form>
    @csrf @if($w->exists) @method('PUT') @endif
    <div class="form-section">
        <h4><span class="step">1</span>{{ __('Covered job') }}</h4>
        <div class="form-grid cols-3">
            <x-field name="customer_id" :label="__('Customer')" type="select" :options="$customers" :placeholder="__('— Select —')" :value="$w->customer_id" required class="span-2" />
            <x-field name="project_id" :label="__('Project')" type="select" :options="$projects" :placeholder="__('— None —')" :value="$w->project_id" />
            <x-field name="service" :label="__('Service / scope')" type="select" :options="$serviceOptions" placeholder="—" :value="$w->service" required />
            <x-field name="start_date" :label="__('Start date')" type="date" :value="$w->start_date" required :hint="__('Usually the completion / handover date.')" />
            <x-field name="years" :label="__('Period (years)')" type="number" min="1" max="30" :value="$w->years" required />
            <x-field name="coverage" :label="__('Coverage & exclusions (printed on certificate)')" type="textarea" :value="$w->coverage" class="span-all" />
            @if($w->exists)
                <x-field name="status" :label="__('Status')" type="select" :options="['active' => __('Active'), 'void' => __('Void')]" :value="$w->status === 'void' ? 'void' : 'active'" />
            @endif
        </div>
    </div>
    <div class="form-actions">
        <a class="btn btn-ghost" href="{{ $w->exists ? route('warranties.show', $w) : route('warranties.index') }}">{{ __('Cancel') }}</a>
        <button class="btn btn-primary"><x-icon name="check" class="ico ico-sm" />{{ $w->exists ? __('Save changes') : __('Issue warranty') }}</button>
    </div>
</form>
@endsection
