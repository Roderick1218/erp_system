@extends('layouts.app')
@section('title', __('Appointment Calendar'))
@push('head')
<style>
    .week .ev.tone-success { background: var(--success); } .week .ev.tone-warning { background: var(--warning); }
    .week .ev.tone-danger { background: var(--danger); } .week .ev.tone-neutral { background: #7d8da6; }
    .week .ev small { display: block; opacity: .85; }
    .week .now-line { position: absolute; left: 0; right: 0; height: 2px; background: var(--danger); z-index: 2; }
    .agenda { display: none; }
    @media (max-width: 760px) { .week-wrap { display: none; } .agenda { display: block; } }
</style>
@endpush
@section('content')
@php($S = \App\Http\Controllers\ServiceRequestController::class)
@php($slotH = 48)
@php($weekEnd = $start->copy()->addDays(6))
<x-page-header :eyebrow="__('After-sales').' · '.__('Calendar')" :title="__('Appointment Calendar')" :count="$events->flatten()->count()" :sub="d($start, 'd M').' – '.d($weekEnd, 'd M Y')">
    <x-slot:actions>
        <a class="btn btn-ghost" href="{{ route('service-requests.index') }}"><x-icon name="table" class="ico ico-sm" />{{ __('List') }}</a>
        <a class="btn btn-primary" href="{{ route('service-requests.create') }}"><x-icon name="plus" class="ico ico-sm" />{{ __('New request') }}</a>
    </x-slot:actions>
</x-page-header>

<div class="panel">
    <div class="panel-head">
        <div class="row">
            <a class="btn btn-ghost btn-sm" href="{{ route('service-requests.calendar', ['assignee' => request('assignee')]) }}">{{ __('Today') }}</a>
            <a class="btn btn-ghost btn-sm" href="{{ route('service-requests.calendar', ['week' => $start->copy()->subWeek()->toDateString(), 'assignee' => request('assignee')]) }}"><x-icon name="chevron-left" class="ico ico-sm" />{{ __('Back') }}</a>
            <a class="btn btn-ghost btn-sm" href="{{ route('service-requests.calendar', ['week' => $start->copy()->addWeek()->toDateString(), 'assignee' => request('assignee')]) }}">{{ __('Next') }}<x-icon name="chevron-right" class="ico ico-sm" /></a>
            <h3 style="margin-left:6px">{{ d($start, 'd M') }} – {{ d($weekEnd, 'd M Y') }}</h3>
        </div>
        <form method="get" class="row">
            <input type="hidden" name="week" value="{{ $start->toDateString() }}">
            <select name="assignee" class="input" style="height:32px;width:auto" data-autosubmit><option value="">{{ __('All technicians') }}</option>@foreach($employees as $id => $name)<option value="{{ $id }}" @selected(request('assignee') == $id)>{{ $name }}</option>@endforeach</select>
        </form>
    </div>

    <div class="week-wrap table-wrap">
        <div class="week">
            <div class="wh"></div>
            @foreach($days as $day)
                <div class="wh {{ $day->isToday() ? 'today' : '' }}">{{ $day->day }} {{ $day->translatedFormat('D') }}</div>
            @endforeach
            @for($h = $firstHour; $h <= $lastHour; $h++)
                <div class="tl">{{ sprintf('%02d:00', $h) }}</div>
                @foreach($days as $day)
                    <div class="slot {{ $day->isToday() ? 'today' : '' }}">
                        @if($day->isToday() && now()->hour === $h)<div class="now-line" style="top:{{ now()->minute / 60 * $slotH }}px"></div>@endif
                        @foreach(($events[$day->toDateString()] ?? collect())->filter(fn ($e) => max($firstHour, min($lastHour, $e->scheduled_at->hour)) === $h) as $e)
                            @php($mins = $e->scheduled_at->hour < $firstHour ? 0 : $e->scheduled_at->minute)
                            <a class="ev tone-{{ $e->status === 'done' ? 'success' : ($e->priority === 'urgent' ? 'danger' : ($e->status === 'in_progress' ? 'warning' : 'info')) }}"
                               href="{{ route('service-requests.show', $e) }}"
                               style="top:{{ $mins / 60 * $slotH }}px;height:{{ max(22, $e->duration_minutes / 60 * $slotH - 2) }}px"
                               title="{{ $e->doc_no }} · {{ $e->title }} · {{ $e->customer->name }} · {{ $e->scheduled_at->format('H:i') }} ({{ __(':n min', ['n' => $e->duration_minutes]) }}) · {{ $e->assignee?->display_name }}">
                                <b>{{ $e->scheduled_at->format('H:i') }}</b> {{ $e->title }}
                                <small>{{ $e->customer->name }}@if($e->assignee) · {{ $e->assignee->nickname ?? $e->assignee->name }}@endif</small>
                            </a>
                        @endforeach
                    </div>
                @endforeach
            @endfor
        </div>
    </div>

    <div class="agenda">
        @foreach($days as $day)
            <div class="filters-title" style="padding:12px 16px 0">{{ d($day, 'D, d M') }}@if($day->isToday()) · {{ __('Today') }} @endif</div>
            <ul class="list">
                @forelse($events[$day->toDateString()] ?? [] as $e)
                    <li><a class="li-link" href="{{ route('service-requests.show', $e) }}"><span class="mono">{{ $e->scheduled_at->format('H:i') }}</span> {{ $e->title }}</a><x-badge :status="$e->status" /></li>
                @empty
                    <li class="muted small">{{ __('No appointments') }}</li>
                @endforelse
            </ul>
        @endforeach
    </div>
    <div class="panel-foot"><span class="small muted">{{ __('Blue = scheduled/open · amber = in progress · green = done · red = urgent. Click an appointment to open it.') }}</span></div>
</div>
@endsection
