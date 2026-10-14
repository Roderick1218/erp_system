@extends('layouts.app')
@section('title', $i->exists ? __('Edit').' '.$i->doc_no : __('Report incident'))
@section('content')
@php($I = \App\Http\Controllers\IncidentController::class)
@php($types = array_map('__', $I::TYPES))
@php($severities = array_map('__', $I::SEVERITIES))
@php($statuses = array_map('__', $I::STATUSES))
<x-page-header :eyebrow="__('After-sales').' · '.__('Safety').' · '.($i->exists ? $i->doc_no : __('New'))" :title="$i->exists ? __('Edit incident') : __('Report incident')" :sub="__('Report facts only — who, where, when, what happened.')">
    <x-slot:actions><a class="btn btn-ghost" href="{{ $i->exists ? route('incidents.show', $i) : route('incidents.index') }}">{{ __('Cancel') }}</a></x-slot:actions>
</x-page-header>

<form method="post" action="{{ $i->exists ? route('incidents.update', $i) : route('incidents.store') }}" class="panel" data-main-form>
    @csrf @if($i->exists) @method('PUT') @endif
    <div class="form-section">
        <h4><span class="step">1</span>{{ __('What happened') }}</h4>
        <div class="form-grid cols-3">
            <x-field name="occurred_at" :label="__('Date & time')" type="datetime-local" :value="$i->occurred_at" required />
            <x-field name="type" :label="__('Type')" type="select" :options="$types" :value="$i->type" required />
            <x-field name="severity" :label="__('Severity')" type="select" :options="$severities" :value="$i->severity" required />
            <x-field name="project_id" :label="__('Project / site')" type="select" :options="$projects" :placeholder="__('— Not on a project —')" :value="$i->project_id" />
            <x-field name="employee_id" :label="__('Person involved')" type="select" :options="$employees" placeholder="—" :value="$i->employee_id" />
            <x-field name="location" :label="__('Exact location')" :value="$i->location" :placeholder="__('e.g. roof edge, east side')" />
            <x-field name="description" :label="__('Description')" type="textarea" :value="$i->description" required class="span-all" />
        </div>
    </div>
    <div class="form-section">
        <h4><span class="step">2</span>{{ __('Follow-up') }}</h4>
        <div class="form-grid cols-3">
            <x-field name="action_taken" :label="__('Corrective / preventive action')" type="textarea" :value="$i->action_taken" class="span-2" :hint="__('Required before the incident can be closed.')" />
            <x-field name="status" :label="__('Status')" type="select" :options="$statuses" :value="$i->status" required />
        </div>
    </div>
    <div class="form-actions">
        <a class="btn btn-ghost" href="{{ $i->exists ? route('incidents.show', $i) : route('incidents.index') }}">{{ __('Cancel') }}</a>
        <button class="btn btn-primary"><x-icon name="check" class="ico ico-sm" />{{ $i->exists ? __('Save changes') : __('Submit report') }}</button>
    </div>
</form>
@endsection
