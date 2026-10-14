@extends('layouts.app')
@section('title', __('My Attendance'))
@section('content')
@php($today = $employee ? $rows->firstWhere(fn ($r) => $r->work_date->isToday()) : null)
<x-page-header :eyebrow="__('HR').' · '.__('Attendance')" :title="__('My Attendance')" :sub="$employee ? $employee->display_name.' · '.$employee->emp_no : null">
    <x-slot:actions>
        <form method="get" class="row"><input type="month" name="month" value="{{ $month }}" class="input" style="width:auto;height:36px" data-autosubmit></form>
    </x-slot:actions>
</x-page-header>

@if(! $employee)
    <div class="panel"><x-empty icon="user" :title="__('No employee profile linked')">{{ __('Ask HR to link your login to an employee record.') }}</x-empty></div>
@else
    <div class="grid g-4" style="margin-bottom:16px">
        <x-stat :label="__('Days present')" :value="$rows->whereNotNull('clock_in')->count()" icon="check-badge" tone="success" />
        <x-stat :label="__('Late arrivals')" :value="$rows->where('is_late', true)->count()" icon="clock" tone="warning" />
        <x-stat :label="__('Hours worked')" :value="qty($rows->sum(fn ($r) => $r->hours ?? 0))" icon="chart" tone="info" />
        <x-stat :label="__('Missing clock-out')" :value="$rows->filter(fn ($r) => $r->clock_in && ! $r->clock_out && ! $r->work_date->isToday())->count()" icon="exclamation" tone="danger" />
    </div>
    <div class="panel">
        <div class="panel-head"><h3><x-icon name="calendar" />{{ $start->translatedFormat('F Y') }}</h3>
            @if(! $today?->clock_out && $start->isSameMonth(now()))
                <form method="post" action="{{ route('attendance.clock') }}">@csrf<button class="btn btn-sm {{ $today ? 'btn-danger' : 'btn-success' }}">{{ $today ? __('Clock Out') : __('Clock In') }}</button></form>
            @endif
        </div>
        @if($rows->count())
            <div class="table-wrap"><table class="table">
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Clock in') }}</th><th>{{ __('Clock out') }}</th><th class="num">{{ __('Hours') }}</th><th>{{ __('Status') }}</th><th class="hide-sm">{{ __('Source') }}</th></tr></thead>
                <tbody>@foreach($rows as $r)
                    <tr><td>{{ $r->work_date->translatedFormat('D, d M') }}</td><td class="mono">{{ $r->clock_in?->format('H:i') ?? '—' }}</td><td class="mono">{{ $r->clock_out?->format('H:i') ?? '—' }}</td>
                        <td class="num">{{ $r->hours !== null ? qty($r->hours) : '—' }}</td>
                        <td>@if($r->is_late)<x-badge status="late" />@else<x-badge status="present" :label="__('On time')" />@endif @if($r->clock_in && ! $r->clock_out && ! $r->work_date->isToday())<x-badge status="pending" :label="__('No clock-out')" />@endif</td>
                        <td class="hide-sm small muted">{{ __(ucfirst($r->source)) }}</td></tr>
                @endforeach</tbody>
            </table></div>
        @else
            <x-empty icon="clock" :title="__('No attendance records this month')" />
        @endif
    </div>
@endif
@endsection
