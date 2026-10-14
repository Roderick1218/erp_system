@extends('layouts.app')
@section('title', __('Incident Report'))
@section('content')
@php($I = \App\Http\Controllers\IncidentController::class)
<x-page-header :eyebrow="__('After-sales').' · '.__('Safety')" :title="__('Incident Report')" :count="$rows->total()" :sub="__('Injuries, near misses and damage on site — report every one, close each with a corrective action.')">
    <x-slot:actions><a class="btn btn-primary" href="{{ route('incidents.create') }}"><x-icon name="plus" class="ico ico-sm" />{{ __('Report incident') }}</a></x-slot:actions>
</x-page-header>

<div class="kpis" style="margin-bottom:16px">
    <x-stat :label="__('Days since last injury')" :value="$daysSince === null ? __('No injuries') : $daysSince" icon="shield" :tone="$daysSince === null || $daysSince > 30 ? 'success' : 'danger'" />
    <x-stat :label="__('Open')" :value="$counts['open'] ?? 0" icon="exclamation" tone="warning" />
    <x-stat :label="__('Investigating')" :value="$counts['investigating'] ?? 0" icon="search" tone="info" />
    <x-stat :label="__('Closed')" :value="$counts['closed'] ?? 0" icon="check-badge" tone="success" />
</div>

<div class="panel">
    <div class="tabs">
        <a href="{{ request()->fullUrlWithQuery(['status' => null, 'page' => null]) }}" class="{{ request('status') ? '' : 'active' }}">{{ __('All') }} <span class="n">{{ $counts->sum() }}</span></a>
        @foreach($I::STATUSES as $k => $label)
            <a href="{{ request()->fullUrlWithQuery(['status' => $k, 'page' => null]) }}" class="{{ request('status') === $k ? 'active' : '' }}">{{ __($label) }} <span class="n">{{ $counts[$k] ?? 0 }}</span></a>
        @endforeach
    </div>
    <form method="get" class="filters">
        <input type="hidden" name="status" value="{{ request('status') }}">
        <div class="filter-row">
            <div class="field grow"><label>{{ __('Search') }}</label><input name="q" value="{{ request('q') }}" placeholder="{{ __('INC no, description, location, project…') }}"></div>
            <div class="field"><label>{{ __('Type') }}</label><select name="type"><option value="">{{ __('All') }}</option>@foreach($I::TYPES as $k => $v)<option value="{{ $k }}" @selected(request('type') === $k)>{{ __($v) }}</option>@endforeach</select></div>
            <div class="field"><label>{{ __('Severity') }}</label><select name="severity"><option value="">{{ __('All') }}</option>@foreach($I::SEVERITIES as $k => $v)<option value="{{ $k }}" @selected(request('severity') === $k)>{{ __($v) }}</option>@endforeach</select></div>
            <div class="field"><label>{{ __('From') }}</label><input type="date" name="from" value="{{ request('from') }}"></div>
            <div class="field"><label>{{ __('To') }}</label><input type="date" name="to" value="{{ request('to') }}"></div>
            <div class="actions">
                @if(request()->except('page', 'status'))<a class="btn btn-ghost" href="{{ route('incidents.index') }}">{{ __('Reset') }}</a>@endif
                <button class="btn btn-primary"><x-icon name="search" class="ico ico-sm" />{{ __('Search') }}</button>
            </div>
        </div>
    </form>
    @if($rows->count())
        <div class="table-wrap"><table class="table">
            <thead><tr><th>{{ __('Ref') }}</th><th>{{ __('When') }}</th><th>{{ __('Type') }}</th><th>{{ __('What happened') }}</th><th class="hide-sm">{{ __('Project / person') }}</th><th>{{ __('Severity') }}</th><th>{{ __('Status') }}</th><th class="chev-cell"></th></tr></thead>
            <tbody>@foreach($rows as $i)
                <tr data-href="{{ route('incidents.show', $i) }}">
                    <td class="code">{{ $i->doc_no }}</td>
                    <td class="nowrap">{{ d($i->occurred_at) }}<span class="sub">{{ $i->occurred_at->format('H:i') }}</span></td>
                    <td><span class="tag">{{ __($I::TYPES[$i->type] ?? $i->type) }}</span></td>
                    <td>{{ \Illuminate\Support\Str::limit($i->description, 70) }}@if($i->location)<span class="sub">{{ $i->location }}</span>@endif</td>
                    <td class="hide-sm">{{ $i->project?->code ?? '—' }}<span class="sub">{{ $i->employee?->display_name }}</span></td>
                    <td><x-badge :status="$i->severity" :label="__($I::SEVERITIES[$i->severity] ?? $i->severity)" /></td>
                    <td><x-badge :status="$i->status" /></td>
                    <td class="chev-cell"><x-icon name="chevron-right" class="ico ico-sm" /></td>
                </tr>
            @endforeach</tbody>
        </table></div>
        <div class="panel-foot">{{ $rows->links() }}</div>
    @else
        <x-empty icon="shield" :title="__('No incidents recorded')" />
    @endif
</div>
@endsection
