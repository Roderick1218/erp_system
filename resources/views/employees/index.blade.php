@extends('layouts.app')
@section('title', __('Employees'))
@section('content')
<x-page-header :eyebrow="__('HR').' · '.__('Employees')" :title="__('Employees')" :count="$rows->total()" :sub="__('Staff and foreign workers, with permit / passport / CIDB / GDL expiry tracking.')">
    <x-slot:actions>
        <a class="btn btn-ghost" href="{{ request()->fullUrlWithQuery(['expiring' => request('expiring') ? null : 1, 'page' => null]) }}"><x-icon name="exclamation" class="ico ico-sm" />{{ request('expiring') ? __('Show all') : __('Expiring documents') }}</a>
        <a class="btn btn-primary" href="{{ route('employees.create') }}"><x-icon name="plus" class="ico ico-sm" />{{ __('Add employee') }}</a>
    </x-slot:actions>
</x-page-header>

<div class="panel">
    <div class="tabs">
        @foreach(['active' => 'Active', 'resigned' => 'Resigned / suspended', 'all' => 'All'] as $k => $label)
            <a href="{{ request()->fullUrlWithQuery(['tab' => $k, 'page' => null]) }}" class="{{ $tab === $k ? 'active' : '' }}">{{ __($label) }} <span class="n">{{ $counts[$k] }}</span></a>
        @endforeach
    </div>

    <form method="get" class="filters">
        <input type="hidden" name="tab" value="{{ $tab }}">
        @if(request('expiring'))<input type="hidden" name="expiring" value="1">@endif
        <div class="filters-title"><x-icon name="funnel" class="ico ico-sm" />{{ __('Filters') }}</div>
        <div class="filter-row">
            <div class="field grow"><label>{{ __('Search') }}</label><input name="q" value="{{ request('q') }}" placeholder="{{ __('Name, nickname, emp no, phone, IC / passport…') }}"></div>
            <div class="field"><label>{{ __('Branch') }}</label><select name="branch"><option value="">{{ __('All') }}</option>@foreach($branches as $id => $name)<option value="{{ $id }}" @selected(request('branch') == $id)>{{ $name }}</option>@endforeach</select></div>
            <div class="field"><label>{{ __('Department') }}</label><select name="department"><option value="">{{ __('All') }}</option>@foreach($departments as $id => $name)<option value="{{ $id }}" @selected(request('department') == $id)>{{ __($name) }}</option>@endforeach</select></div>
            <div class="field"><label>{{ __('Nationality') }}</label><select name="nationality"><option value="">{{ __('All') }}</option>@foreach($nationalities as $n)<option value="{{ $n }}" @selected(request('nationality') === $n)>{{ $n }}</option>@endforeach</select></div>
            <div class="field"><label>{{ __('Employment') }}</label><select name="employment_type"><option value="">{{ __('All') }}</option>@foreach(\App\Models\Employee::EMPLOYMENT_TYPES as $k => $v)<option value="{{ $k }}" @selected(request('employment_type') === $k)>{{ __($v) }}</option>@endforeach</select></div>
            <div class="actions">
                @if(request()->except('page', 'tab'))<a class="btn btn-ghost" href="{{ route('employees.index', ['tab' => $tab]) }}">{{ __('Reset') }}</a>@endif
                <button class="btn btn-primary"><x-icon name="search" class="ico ico-sm" />{{ __('Search') }}</button>
            </div>
        </div>
    </form>

    @if($rows->count())
        <div class="table-wrap">
            <table class="table">
                <thead><tr>
                    <th>{{ __('Emp no') }}</th><th>{{ __('Name') }}</th><th class="hide-sm">{{ __('Position') }}</th><th class="hide-sm">{{ __('Department') }}</th><th class="hide-sm">{{ __('Branch') }}</th>
                    <th class="hide-sm">{{ __('Nationality') }}</th><th class="hide-sm">{{ __('Phone') }}</th><th class="hide-sm">{{ __('Hired') }}</th><th>{{ __('ID documents') }}</th><th>{{ __('Status') }}</th><th class="chev-cell"></th>
                </tr></thead>
                <tbody>
                @foreach($rows as $e)
                    @php($exp = $e->expiringDocuments(30))
                    <tr data-href="{{ route('employees.show', $e) }}">
                        <td class="code">{{ $e->emp_no }}</td>
                        <td class="primary"><span style="display:flex;align-items:center;gap:9px"><span class="avatar sm">{{ $e->initials }}</span><span>{{ $e->name }}@if($e->nickname)<span class="sub">{{ $e->nickname }}</span>@endif</span></span></td>
                        <td class="hide-sm">{{ $e->position }}<span class="sub">{{ __(\App\Models\Employee::EMPLOYMENT_TYPES[$e->employment_type] ?? $e->employment_type) }}</span></td>
                        <td class="hide-sm">{{ $e->department ? __($e->department->name) : '' }}</td>
                        <td class="hide-sm"><span class="tag" title="{{ $e->branch?->legal_name }}">{{ $e->branch?->code }}</span></td>
                        <td class="hide-sm small">{{ $e->nationality }}</td>
                        <td class="hide-sm mono small">{{ $e->phone }}</td>
                        <td class="hide-sm small">{{ d($e->hire_date) }}</td>
                        <td>
                            @if($exp)
                                @php($expired = collect($exp)->contains(fn ($dt) => $dt->isPast()))
                                <span class="badge badge-{{ $expired ? 'danger' : 'warning' }}" title="{{ collect($exp)->map(fn ($dt, $l) => __($l).': '.d($dt))->implode(' · ') }}">{{ $expired ? __('Expired') : __('Expiring') }} · {{ collect(array_keys($exp))->map(fn ($l) => __($l))->implode(', ') }}</span>
                            @else
                                <span class="muted small">{{ __('OK') }}</span>
                            @endif
                        </td>
                        <td><x-badge :status="$e->status" /></td>
                        <td class="chev-cell"><x-icon name="chevron-right" class="ico ico-sm" /></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="panel-foot">{{ $rows->links() }}</div>
    @else
        <x-empty icon="identification" :title="__('No employees match these filters')">
            <a class="btn btn-ghost btn-sm" href="{{ route('employees.index') }}">{{ __('Clear filters') }}</a>
        </x-empty>
    @endif
</div>
@endsection
