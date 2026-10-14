@extends('layouts.app')
@section('title', __('Project Calendar'))
@section('content')
@include('projects._nav', ['active' => 'calendar', 'title' => __('Project Calendar'), 'count' => $count, 'sub' => __('Projects on site each day of :month. Click a pill to open the project.', ['month' => $month->translatedFormat('F Y')])])

<div class="panel">
    <div class="panel-head">
        <div class="row">
            <a class="btn btn-ghost btn-sm btn-icon" href="{{ route('projects.calendar', ['month' => $month->copy()->subMonth()->format('Y-m')]) }}" title="{{ __('Previous month') }}"><x-icon name="chevron-left" class="ico ico-sm" /></a>
            <a class="btn btn-ghost btn-sm" href="{{ route('projects.calendar') }}">{{ __('Today') }}</a>
            <a class="btn btn-ghost btn-sm btn-icon" href="{{ route('projects.calendar', ['month' => $month->copy()->addMonth()->format('Y-m')]) }}" title="{{ __('Next month') }}"><x-icon name="chevron-right" class="ico ico-sm" /></a>
            <h3 style="margin-left:6px">{{ $month->translatedFormat('F Y') }}</h3>
        </div>
        <form method="get" class="row"><input type="month" name="month" value="{{ $month->format('Y-m') }}" class="input" style="width:auto;height:32px" data-autosubmit></form>
    </div>
    <div class="table-wrap">
        <div class="cal" style="min-width:760px">
            @foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dow)<div class="cal-dow">{{ __($dow) }}</div>@endforeach
            @foreach($days as $date => $list)
                @php($day = \Illuminate\Support\Carbon::parse($date))
                @php($holiday = $holidays[$date] ?? null)
                <div class="cal-day {{ $day->month !== $month->month ? 'other' : '' }} {{ $day->isToday() ? 'today' : '' }} {{ $holiday ? 'holiday' : '' }}">
                    <div class="dn"><b>{{ $day->day }}</b>@if($holiday)<span class="small text-danger" title="{{ __($holiday->name) }}" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:75%">{{ __($holiday->name) }}</span>@endif</div>
                    @foreach($list->take(3) as $p)
                        <a class="cal-pill tone-{{ status_tone($p->status) }}" href="{{ route('projects.show', $p) }}" title="{{ $p->code }} — {{ $p->name }} ({{ $p->customer->name }}) · {{ \App\Support\Status::label($p->status) }}">{{ $p->code }} · {{ $p->customer->name }}</a>
                    @endforeach
                    @if($list->count() > 3)
                        <details><summary class="cal-more" style="cursor:pointer">{{ __('+:n more', ['n' => $list->count() - 3]) }}</summary>
                            @foreach($list->slice(3) as $p)<a class="cal-pill tone-{{ status_tone($p->status) }}" href="{{ route('projects.show', $p) }}" title="{{ $p->name }}">{{ $p->code }} · {{ $p->customer->name }}</a>@endforeach
                        </details>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
    <div class="panel-foot">
        <div class="row small muted">
            @foreach(['scheduled', 'in_progress', 'on_hold', 'completed', 'planning'] as $k)
                <span class="cal-pill tone-{{ status_tone($k) }}" style="display:inline-block;margin:0">{{ \App\Support\Status::label($k) }}</span>
            @endforeach
            <span class="cal-day holiday" style="min-height:0;padding:2px 8px;border:1px solid var(--border);border-radius:5px">{{ __('Public holiday') }}</span>
        </div>
    </div>
</div>
@endsection
