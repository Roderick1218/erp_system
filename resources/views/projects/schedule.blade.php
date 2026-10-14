@extends('layouts.app')
@section('title', __('Project Schedule'))
@section('content')
@include('projects._nav', ['active' => 'schedule', 'title' => __('Project Schedule'), 'count' => $projects->count(), 'sub' => __('Six-week timeline').' · '.d($start, 'd M').' – '.d($end, 'd M Y')])

<div class="panel">
    <div class="panel-head">
        <div class="row">
            <a class="btn btn-ghost btn-sm btn-icon" href="{{ route('projects.schedule', ['start' => $start->copy()->subWeeks(2)->toDateString()]) }}" title="{{ __('2 weeks back') }}"><x-icon name="chevron-left" class="ico ico-sm" /></a>
            <a class="btn btn-ghost btn-sm" href="{{ route('projects.schedule') }}">{{ __('This week') }}</a>
            <a class="btn btn-ghost btn-sm btn-icon" href="{{ route('projects.schedule', ['start' => $start->copy()->addWeeks(2)->toDateString()]) }}" title="{{ __('2 weeks forward') }}"><x-icon name="chevron-right" class="ico ico-sm" /></a>
        </div>
        <div class="row small muted">
            @foreach(['scheduled', 'in_progress', 'on_hold', 'completed', 'planning'] as $k)
                <span class="row" style="gap:5px"><span class="gantt"><span class="bar tone-{{ status_tone($k) }}" style="display:inline-block;width:18px;height:10px;margin:0"></span></span>{{ \App\Support\Status::label($k) }}</span>
            @endforeach
        </div>
    </div>
    @if($projects->count())
        <div class="gantt">
            <table>
                <thead>
                    <tr>
                        <th class="name">{{ __('Project') }}</th>
                        @foreach($days as $day)
                            <th class="{{ $day->isWeekend() ? 'we' : '' }} {{ $day->isToday() ? 'today' : '' }}" title="{{ d($day, 'D, d M') }}">
                                @if($day->isMonday() || $loop->first)<div style="font-size:9.5px">{{ $day->translatedFormat('M') }}</div>@endif{{ $day->day }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                @foreach($projects as $p)
                    @php
                        $pEnd = $p->end_date ?? $p->start_date;
                        $from = $p->start_date->lt($start) ? $start : $p->start_date;
                        $to = $pEnd->gt($end) ? $end : $pEnd;
                        $offset = (int) $start->diffInDays($from);
                        $span = (int) $from->diffInDays($to) + 1;
                    @endphp
                    <tr>
                        <td class="name"><a href="{{ route('projects.show', $p) }}"><span class="mono small">{{ $p->code }}</span></a> <span class="small">{{ $p->customer->name }}</span></td>
                        @if($offset > 0)
                            @foreach(array_slice($days, 0, $offset) as $day)<td class="{{ $day->isWeekend() ? 'we' : '' }}"></td>@endforeach
                        @endif
                        <td colspan="{{ $span }}" title="{{ $p->code }} {{ $p->name }} · {{ d($p->start_date) }} → {{ d($pEnd) }} · {{ $p->progress }}%">
                            <a href="{{ route('projects.show', $p) }}" style="display:block;text-decoration:none">
                                <div class="bar tone-{{ status_tone($p->status) }}"><i style="width:{{ $p->progress }}%"></i>
                                    <span style="position:relative;z-index:1;color:#fff;font-size:10.5px;padding:0 6px;line-height:20px;white-space:nowrap;overflow:hidden;display:block">{{ $p->name }}</span>
                                </div>
                            </a>
                        </td>
                        @foreach(array_slice($days, $offset + $span) as $day)<td class="{{ $day->isWeekend() ? 'we' : '' }}"></td>@endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @else
        <x-empty icon="calendar" :title="__('No projects scheduled in this window')" />
    @endif
</div>
@endsection
