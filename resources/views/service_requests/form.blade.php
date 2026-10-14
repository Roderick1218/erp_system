@extends('layouts.app')
@section('title', $sr->exists ? __('Edit').' '.$sr->doc_no : __('New service request'))
@section('content')
@php($S = \App\Http\Controllers\ServiceRequestController::class)
@php($types = array_map('__', $S::TYPES))
@php($priorities = array_map('__', $S::PRIORITIES))
@php($statuses = array_map('__', $S::STATUSES))
<x-page-header :eyebrow="__('After-sales').' · '.__('Service').' · '.($sr->exists ? $sr->doc_no : __('New'))" :title="$sr->exists ? __('Edit service request') : __('New service request')" :sub="__('With a date and a technician it becomes Scheduled automatically.')">
    <x-slot:actions><a class="btn btn-ghost" href="{{ $sr->exists ? route('service-requests.show', $sr) : route('service-requests.index') }}">{{ __('Cancel') }}</a></x-slot:actions>
</x-page-header>

<form method="post" action="{{ $sr->exists ? route('service-requests.update', $sr) : route('service-requests.store') }}" class="panel" data-main-form>
    @csrf @if($sr->exists) @method('PUT') @endif
    <div class="form-section">
        <h4><span class="step">1</span>{{ __('What & for whom') }}</h4>
        <div class="form-grid cols-3">
            <x-field name="title" :label="__('Job title')" :value="$sr->title" required class="span-2" :placeholder="__('e.g. Leaking at porch roof after heavy rain')" />
            <x-field name="type" :label="__('Type')" type="select" :options="$types" :value="$sr->type" required />
            <x-field name="customer_id" :label="__('Customer')" type="select" :options="$customers" :placeholder="__('— Select —')" :value="$sr->customer_id" required class="span-2" />
            <x-field name="priority" :label="__('Priority')" type="select" :options="$priorities" :value="$sr->priority" required />
            <x-field name="project_id" :label="__('Related project')" type="select" :options="$projects" :placeholder="__('— None —')" :value="$sr->project_id" />
            <x-field name="warranty_id" :label="__('Warranty claim against')" type="select" :options="$warranties" :placeholder="__('— Not a warranty claim —')" :value="$sr->warranty_id" class="span-2" />
            <x-field name="description" :label="__('Description')" type="textarea" :value="$sr->description" class="span-all" :placeholder="__('What the customer reported, access notes, photos received on WhatsApp…')" />
        </div>
    </div>
    <div class="form-section">
        <h4><span class="step">2</span>{{ __('Appointment') }}</h4>
        <div class="form-grid cols-4">
            <x-field name="scheduled_at" :label="__('Date & time')" type="datetime-local" :value="$sr->scheduled_at" />
            <x-field name="duration_minutes" :label="__('Duration (min)')" type="number" min="15" step="15" :value="$sr->duration_minutes" required />
            <x-field name="assigned_to" :label="__('Technician / team lead')" type="select" :options="$employees" :placeholder="__('— Unassigned —')" :value="$sr->assigned_to" />
            <x-field name="status" :label="__('Status')" type="select" :options="$statuses" :value="$sr->status" required />
            @if($sr->exists)<x-field name="resolution" :label="__('Resolution / work done')" type="textarea" :value="$sr->resolution" class="span-all" />@endif
        </div>
    </div>
    <div class="form-actions">
        <a class="btn btn-ghost" href="{{ $sr->exists ? route('service-requests.show', $sr) : route('service-requests.index') }}">{{ __('Cancel') }}</a>
        <button class="btn btn-primary"><x-icon name="check" class="ico ico-sm" />{{ $sr->exists ? __('Save changes') : __('Create request') }}</button>
    </div>
</form>
@endsection
