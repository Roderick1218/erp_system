@extends('layouts.app')
@section('title', __('Customer Profile'))
@section('content')
<x-page-header :eyebrow="__('Customers').' · '.__('Profiles')" :title="__('Customer Profile')" :count="$rows->total()" :sub="__('Every person or company we have quoted, served or invoiced.')">
    <x-slot:actions>
        <a class="btn btn-ghost" href="{{ route('customers.export', request()->query()) }}"><x-icon name="download" class="ico ico-sm" />{{ __('Export CSV') }}</a>
        @can('customers.manage')<a class="btn btn-primary" href="{{ route('customers.create') }}"><x-icon name="plus" class="ico ico-sm" />{{ __('Add New') }}</a>@endcan
    </x-slot:actions>
</x-page-header>

<div class="panel">
    {{-- Status tabs (counts) --}}
    <div class="tabs">
        @php($total = $statusCounts->sum())
        <a href="{{ request()->fullUrlWithQuery(['status' => null, 'page' => null]) }}" class="{{ request('status') ? '' : 'active' }}">{{ __('All') }} <span class="n">{{ $total }}</span></a>
        @foreach(['active' => 'Active', 'inactive' => 'Inactive', 'blacklist' => 'Blacklist'] as $k => $label)
            <a href="{{ request()->fullUrlWithQuery(['status' => $k, 'page' => null]) }}" class="{{ request('status') === $k ? 'active' : '' }}">{{ __($label) }} <span class="n">{{ $statusCounts[$k] ?? 0 }}</span></a>
        @endforeach
    </div>

    <form method="get" class="filters">
        <input type="hidden" name="status" value="{{ request('status') }}">
        <div class="filters-title"><x-icon name="funnel" class="ico ico-sm" />{{ __('Filters') }}</div>
        <div class="filter-row">
            <div class="field grow"><label>{{ __('Search') }}</label><input name="q" value="{{ request('q') }}" placeholder="{{ __('Code, name, phone, IC/BRN, city…') }}"></div>
            <div class="field"><label>{{ __('From date') }}</label><input type="date" name="from" value="{{ request('from') }}"></div>
            <div class="field"><label>{{ __('To date') }}</label><input type="date" name="to" value="{{ request('to') }}"></div>
            <div class="field"><label>{{ __('Branch') }}</label><select name="branch"><option value="">{{ __('All') }}</option>@foreach($branches as $id => $name)<option value="{{ $id }}" @selected(request('branch') == $id)>{{ $name }}</option>@endforeach</select></div>
            <div class="field"><label>{{ __('Type') }}</label><select name="type"><option value="">{{ __('All') }}</option>@foreach(['individual' => 'Individual', 'company' => 'Company', 'government' => 'Government'] as $k => $v)<option value="{{ $k }}" @selected(request('type') === $k)>{{ __($v) }}</option>@endforeach</select></div>
            <div class="field"><label>{{ __('State') }}</label><select name="state"><option value="">{{ __('All') }}</option>@foreach(config('erp.states') as $s)<option @selected(request('state') === $s)>{{ $s }}</option>@endforeach</select></div>
            <div class="field"><label>{{ __('Sort by') }}</label><select name="sort"><option value="created">{{ __('Newest') }}</option><option value="code" @selected(request('sort') === 'code')>{{ __('Code') }}</option><option value="name" @selected(request('sort') === 'name')>{{ __('Name') }}</option><option value="state" @selected(request('sort') === 'state')>{{ __('State') }}</option></select></div>
            <div class="actions">
                @if(request()->except('page', 'status'))<a class="btn btn-ghost" href="{{ route('customers.index', ['status' => request('status')]) }}">{{ __('Reset') }}</a>@endif
                <button class="btn btn-primary"><x-icon name="search" class="ico ico-sm" />{{ __('Search') }}</button>
            </div>
        </div>
    </form>

    @if($rows->count())
        <div class="table-wrap">
            <table class="table">
                <thead><tr>
                    <th class="idx">#</th><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th class="hide-sm">{{ __('Phone') }}</th><th class="hide-sm">{{ __('Location') }}</th><th class="hide-sm">{{ __('Branch') }}</th>
                    <th>{{ __('Type') }}</th><th class="num hide-sm">{{ __('Enquiries') }}</th><th class="num hide-sm">{{ __('Projects') }}</th><th>{{ __('Status') }}</th><th class="chev-cell"></th>
                </tr></thead>
                <tbody>
                @foreach($rows as $i => $c)
                    <tr data-href="{{ route('customers.show', $c) }}">
                        <td class="idx">{{ $rows->firstItem() + $i }}</td>
                        <td class="code">{{ $c->code }}</td>
                        <td class="primary">{{ $c->name }}@if($c->contact_person)<span class="sub">{{ __('Attn:') }} {{ $c->contact_person }}</span>@endif</td>
                        <td class="hide-sm mono small">{{ $c->phone }}</td>
                        <td class="hide-sm">{{ $c->city }}<span class="sub">{{ $c->state }}</span></td>
                        <td class="hide-sm"><span class="tag" title="{{ $c->branch?->legal_name }}">{{ $c->branch?->code }}</span></td>
                        <td><span class="tag">{{ __(ucfirst($c->type)) }}</span></td>
                        <td class="num hide-sm">{{ $c->enquiries_count }}</td>
                        <td class="num hide-sm">{{ $c->projects_count }}</td>
                        <td><x-badge :status="$c->status" /></td>
                        <td class="chev-cell"><x-icon name="chevron-right" class="ico ico-sm" /></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="panel-foot">{{ $rows->links() }}</div>
    @else
        <x-empty icon="users" :title="__('No customers match these filters')">
            <a class="btn btn-ghost btn-sm" href="{{ route('customers.index') }}">{{ __('Clear filters') }}</a>
        </x-empty>
    @endif
</div>
@endsection
