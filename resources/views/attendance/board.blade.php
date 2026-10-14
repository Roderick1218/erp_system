@extends('layouts.app')
@section('title', __('Attendance Board'))
@section('content')
@php
    $labels = ['present' => [__('On time'), 'success'], 'late' => [__('Late'), 'warning'], 'on_leave' => [__('On leave'), 'info'], 'absent' => [__('Absent'), 'danger'], 'off' => [__('Off / holiday'), 'neutral'], 'upcoming' => ['—', 'neutral']];
    $visible = $statusFilter ? $rows->where('status', $statusFilter) : $rows;
    if ($date->isToday()) { $labels['absent'] = [__('Not clocked in'), 'warning']; }
@endphp
<x-page-header :eyebrow="__('HR').' · '.__('Attendance')" :title="__('Attendance Board')" :sub="$date->translatedFormat(app()->getLocale() === 'zh_CN' ? 'Y年n月j日 l' : 'l, d F Y').($holiday ? ' · '.__($holiday->name) : '')">
    <x-slot:actions>
        <a class="btn btn-ghost" href="{{ request()->fullUrlWithQuery(['date' => $date->copy()->subDay()->toDateString()]) }}" title="{{ __('Previous day') }}"><x-icon name="chevron-left" class="ico ico-sm" /></a>
        <a class="btn btn-ghost" href="{{ request()->fullUrlWithQuery(['date' => today()->toDateString()]) }}">{{ __('Today') }}</a>
        <a class="btn btn-ghost" href="{{ request()->fullUrlWithQuery(['date' => $date->copy()->addDay()->toDateString()]) }}" title="{{ __('Next day') }}"><x-icon name="chevron-right" class="ico ico-sm" /></a>
        <a class="btn btn-ghost" href="{{ route('attendance.report', ['month' => $date->format('Y-m')]) }}"><x-icon name="table" class="ico ico-sm" />{{ __('Monthly report') }}</a>
        <button class="btn btn-primary" data-dialog="dlg-manual" data-fill='{"employee_id":"","clock_in":"08:30","clock_out":"","remarks":""}'><x-icon name="plus" class="ico ico-sm" />{{ __('Manual entry') }}</button>
    </x-slot:actions>
</x-page-header>

<div class="kpis" style="margin-bottom:16px">
    <x-stat :label="__('Present')" :value="($counts['present'] ?? 0) + ($counts['late'] ?? 0).' / '.$rows->count()" icon="check-badge" tone="success" :href="request()->fullUrlWithQuery(['status' => null])" />
    <x-stat :label="__('Late')" :value="$counts['late'] ?? 0" icon="clock" tone="warning" :href="request()->fullUrlWithQuery(['status' => 'late'])" />
    <x-stat :label="__('On leave')" :value="$counts['on_leave'] ?? 0" icon="calendar" tone="info" :href="request()->fullUrlWithQuery(['status' => 'on_leave'])" />
    <x-stat :label="$date->isToday() ? __('Not clocked in') : __('Absent')" :value="$counts['absent'] ?? 0" icon="exclamation" tone="danger" :href="request()->fullUrlWithQuery(['status' => 'absent'])" />
</div>

<div class="panel">
    <form method="get" class="filters">
        <div class="filter-row">
            <div class="field"><label>{{ __('Date') }}</label><input type="date" name="date" value="{{ $date->toDateString() }}" data-autosubmit></div>
            <div class="field grow"><label>{{ __('Search') }}</label><input name="q" value="{{ request('q') }}" placeholder="{{ __('Name or emp no…') }}"></div>
            <div class="field"><label>{{ __('Branch') }}</label><select name="branch" data-autosubmit><option value="">{{ __('All') }}</option>@foreach($branches as $id => $n)<option value="{{ $id }}" @selected(request('branch') == $id)>{{ $n }}</option>@endforeach</select></div>
            <div class="field"><label>{{ __('Department') }}</label><select name="department" data-autosubmit><option value="">{{ __('All') }}</option>@foreach($departments as $id => $n)<option value="{{ $id }}" @selected(request('department') == $id)>{{ __($n) }}</option>@endforeach</select></div>
            <div class="field"><label>{{ __('Status') }}</label><select name="status" data-autosubmit><option value="">{{ __('All') }}</option>@foreach($labels as $k => [$l])@continue($k === 'upcoming')<option value="{{ $k }}" @selected($statusFilter === $k)>{{ $l }}</option>@endforeach</select></div>
            <div class="actions"><button class="btn btn-primary"><x-icon name="search" class="ico ico-sm" />{{ __('Apply') }}</button></div>
        </div>
    </form>
    @if($visible->count())
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>{{ __('Employee') }}</th><th class="hide-sm">{{ __('Department') }}</th><th class="hide-sm">{{ __('Branch') }}</th><th>{{ __('Clock in') }}</th><th>{{ __('Clock out') }}</th><th class="num">{{ __('Hours') }}</th><th>{{ __('Status') }}</th><th class="hide-sm">{{ __('Remarks') }}</th><th class="actions"></th></tr></thead>
                <tbody>
                @foreach($visible as $r)
                    @php [$label, $tone] = $labels[$r['status']]; @endphp
                    <tr>
                        <td class="primary"><a href="{{ route('employees.show', $r['e']) }}" style="color:inherit">{{ $r['e']->display_name }}</a><span class="sub">{{ $r['e']->emp_no }} · {{ $r['e']->position }}</span></td>
                        <td class="hide-sm">{{ $r['e']->department ? __($r['e']->department->name) : '' }}</td>
                        <td class="hide-sm"><span class="tag">{{ $r['e']->branch?->code }}</span></td>
                        <td class="mono">{{ $r['att']?->clock_in?->format('H:i') ?? '—' }}</td>
                        <td class="mono">{{ $r['att']?->clock_out?->format('H:i') ?? '—' }}</td>
                        <td class="num">{{ $r['att']?->hours !== null ? qty($r['att']->hours) : '—' }}</td>
                        <td><x-badge :status="$r['status']" :label="$label" :tone="$tone" />@if($r['leave'])<span class="sub">{{ __($r['leave']->leaveType->name) }}</span>@endif</td>
                        <td class="hide-sm small muted">{{ $r['att']?->remarks }}@if($r['att'] && $r['att']->source === 'manual') <span class="tag">{{ __('manual') }}</span>@endif</td>
                        <td class="actions">
                            @if($r['att'])
                                <button class="btn btn-ghost btn-sm" data-dialog="dlg-edit" data-action="{{ route('attendance.update', $r['att']) }}" data-title="{{ __('Edit — :name', ['name' => $r['e']->display_name]) }}"
                                        data-fill="{{ json_encode(['clock_in' => $r['att']->clock_in?->format('H:i'), 'clock_out' => $r['att']->clock_out?->format('H:i'), 'remarks' => $r['att']->remarks]) }}">{{ __('Edit') }}</button>
                            @elseif(! $date->isFuture())
                                <button class="btn btn-ghost btn-sm" data-dialog="dlg-manual" data-fill="{{ json_encode(['employee_id' => $r['e']->id, 'clock_in' => '08:30', 'clock_out' => '17:30', 'remarks' => '']) }}">{{ __('Add') }}</button>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @else
        <x-empty icon="identification" :title="__('Nobody matches these filters')" />
    @endif
</div>

<dialog id="dlg-edit">
    <form method="post" action="">
        @csrf @method('PUT')
        <div class="dlg-head"><h3>{{ __('Edit attendance') }}</h3><button type="button" class="icon-btn" data-close><x-icon name="x" /></button></div>
        <div class="dlg-body form-grid">
            <x-field name="clock_in" :label="__('Clock in')" type="time" required />
            <x-field name="clock_out" :label="__('Clock out')" type="time" />
            <x-field name="remarks" :label="__('Reason for correction')" class="span-all" :placeholder="__('e.g. Forgot to clock out — confirmed by supervisor')" />
        </div>
        <div class="dlg-foot"><button type="button" class="btn btn-ghost" data-close>{{ __('Cancel') }}</button><button class="btn btn-primary">{{ __('Save') }}</button></div>
    </form>
</dialog>

<dialog id="dlg-manual">
    <form method="post" action="{{ route('attendance.manual') }}">
        @csrf
        <div class="dlg-head"><h3>{{ __('Manual attendance entry') }}</h3><button type="button" class="icon-btn" data-close><x-icon name="x" /></button></div>
        <div class="dlg-body form-grid">
            <x-field name="employee_id" :label="__('Employee')" type="select" :options="$rows->mapWithKeys(fn ($r) => [$r['e']->id => $r['e']->emp_no.' — '.$r['e']->display_name])->all()" :placeholder="__('— Select —')" required class="span-all" />
            <x-field name="work_date" :label="__('Date')" type="date" :value="$date->toDateString()" required class="span-all" />
            <x-field name="clock_in" :label="__('Clock in')" type="time" required />
            <x-field name="clock_out" :label="__('Clock out')" type="time" />
            <x-field name="remarks" :label="__('Remarks')" class="span-all" :placeholder="__('e.g. Site work, clocked in via WhatsApp photo')" />
        </div>
        <div class="dlg-foot"><button type="button" class="btn btn-ghost" data-close>{{ __('Cancel') }}</button><button class="btn btn-primary">{{ __('Add record') }}</button></div>
    </form>
</dialog>
@endsection
