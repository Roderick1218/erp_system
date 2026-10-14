@extends('layouts.app')
@section('title', $i->doc_no)
@section('content')
@php($I = \App\Http\Controllers\IncidentController::class)
<x-page-header :eyebrow="__('After-sales').' · '.__('Safety').' · '.$i->doc_no" :title="__($I::TYPES[$i->type] ?? $i->type).' — '.d($i->occurred_at, 'd M Y H:i')" :sub="$i->location">
    <x-slot:badge><x-badge :status="$i->severity" :label="__(':level severity', ['level' => __($I::SEVERITIES[$i->severity] ?? $i->severity)])" /> <x-badge :status="$i->status" /></x-slot:badge>
    <x-slot:actions><a class="btn btn-ghost" href="{{ route('incidents.edit', $i) }}"><x-icon name="pencil" class="ico ico-sm" />{{ __('Edit / close') }}</a></x-slot:actions>
</x-page-header>

<div class="grid g-main-side">
    <div class="stack">
        <div class="panel"><div class="panel-head"><h3><x-icon name="exclamation" />{{ __('What happened') }}</h3></div><div class="panel-body" style="white-space:pre-line">{{ $i->description }}</div></div>
        <div class="panel"><div class="panel-head"><h3><x-icon name="check-badge" />{{ __('Action taken') }}</h3></div>
            <div class="panel-body" style="white-space:pre-line">@if($i->action_taken){{ $i->action_taken }}@else<span class="muted">{{ __('No corrective action recorded yet.') }}</span>@endif</div></div>
    </div>
    <div class="stack">
        <div class="panel">
            <div class="panel-head"><h3><x-icon name="info" />{{ __('Details') }}</h3></div>
            <div class="panel-body">
                <dl class="dl">
                    <dt>{{ __('Reference') }}</dt><dd class="mono">{{ $i->doc_no }}</dd>
                    <dt>{{ __('Occurred') }}</dt><dd>{{ d($i->occurred_at, 'D, d M Y H:i') }}</dd>
                    <dt>{{ __('Project') }}</dt><dd>@if($i->project)@can('projects.view')<a href="{{ route('projects.show', $i->project) }}">{{ $i->project->code }}</a>@else{{ $i->project->code }}@endcan<div class="small muted">{{ $i->project->customer->name }}</div>@else — @endif</dd>
                    <dt>{{ __('Person') }}</dt><dd>{{ $i->employee?->display_name ?? '—' }}</dd>
                    <dt>{{ __('Reported by') }}</dt><dd>{{ $i->reporter?->name ?? '—' }}<div class="small muted">{{ d($i->created_at, 'd M Y H:i') }}</div></dd>
                </dl>
            </div>
        </div>
        <form method="post" action="{{ route('incidents.destroy', $i) }}" data-confirm="{{ __('Delete :doc? Safety records should normally be kept.', ['doc' => $i->doc_no]) }}">@csrf @method('DELETE')
            <button class="btn btn-danger-ghost btn-block"><x-icon name="trash" class="ico ico-sm" />{{ __('Delete') }}</button>
        </form>
    </div>
</div>
@endsection
