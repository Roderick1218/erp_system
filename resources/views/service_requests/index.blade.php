@extends('layouts.app')
@section('title', __('Service Requests'))
@section('content')
@php($S = \App\Http\Controllers\ServiceRequestController::class)
<x-page-header :eyebrow="__('After-sales').' · '.__('Service')" :title="__('Service Requests')" :count="$rows->total()" :sub="__('Inspections, repairs, aftercare visits and complaints — who goes where, when.')">
    <x-slot:actions>
        <a class="btn btn-ghost" href="{{ route('service-requests.calendar') }}"><x-icon name="calendar" class="ico ico-sm" />{{ __('Calendar') }}</a>
        <a class="btn btn-primary" href="{{ route('service-requests.create') }}"><x-icon name="plus" class="ico ico-sm" />{{ __('New request') }}</a>
    </x-slot:actions>
</x-page-header>

<div class="panel">
    <div class="tabs">
        <a href="{{ request()->fullUrlWithQuery(['status' => null, 'page' => null]) }}" class="{{ request('status') ? '' : 'active' }}">{{ __('All') }} <span class="n">{{ $counts->sum() }}</span></a>
        @foreach($S::STATUSES as $k => $label)
            <a href="{{ request()->fullUrlWithQuery(['status' => $k, 'page' => null]) }}" class="{{ request('status') === $k ? 'active' : '' }}">{{ __($label) }} <span class="n">{{ $counts[$k] ?? 0 }}</span></a>
        @endforeach
    </div>
    <form method="get" class="filters">
        <input type="hidden" name="status" value="{{ request('status') }}">
        <div class="filter-row">
            <div class="field grow"><label>{{ __('Search') }}</label><input name="q" value="{{ request('q') }}" placeholder="{{ __('SR no, title, customer, phone…') }}"></div>
            <div class="field"><label>{{ __('Type') }}</label><select name="type"><option value="">{{ __('All') }}</option>@foreach($S::TYPES as $k => $v)<option value="{{ $k }}" @selected(request('type') === $k)>{{ __($v) }}</option>@endforeach</select></div>
            <div class="field"><label>{{ __('Priority') }}</label><select name="priority"><option value="">{{ __('All') }}</option>@foreach($S::PRIORITIES as $k => $v)<option value="{{ $k }}" @selected(request('priority') === $k)>{{ __($v) }}</option>@endforeach</select></div>
            <div class="field"><label>{{ __('Assigned to') }}</label><select name="assignee"><option value="">{{ __('Anyone') }}</option>@foreach($employees as $id => $name)<option value="{{ $id }}" @selected(request('assignee') == $id)>{{ $name }}</option>@endforeach</select></div>
            <div class="field"><label>{{ __('From') }}</label><input type="date" name="from" value="{{ request('from') }}"></div>
            <div class="field"><label>{{ __('To') }}</label><input type="date" name="to" value="{{ request('to') }}"></div>
            <label class="check" style="margin-bottom:8px"><input type="checkbox" name="mine" value="1" @checked(request('mine'))> {{ __('Assigned to me') }}</label>
            <div class="actions">
                @if(request()->except('page', 'status'))<a class="btn btn-ghost" href="{{ route('service-requests.index', ['status' => request('status')]) }}">{{ __('Reset') }}</a>@endif
                <button class="btn btn-primary"><x-icon name="search" class="ico ico-sm" />{{ __('Search') }}</button>
            </div>
        </div>
    </form>
    @if($rows->count())
        <div class="table-wrap"><table class="table">
            <thead><tr><th>{{ __('Ref') }}</th><th>{{ __('Job') }}</th><th>{{ __('Customer') }}</th><th>{{ __('When') }}</th><th class="hide-sm">{{ __('Assigned') }}</th><th>{{ __('Priority') }}</th><th>{{ __('Status') }}</th><th class="chev-cell"></th></tr></thead>
            <tbody>@foreach($rows as $s)
                <tr data-href="{{ route('service-requests.show', $s) }}">
                    <td class="code">{{ $s->doc_no }}</td>
                    <td class="primary">{{ $s->title }}<span class="sub">{{ __($S::TYPES[$s->type] ?? $s->type) }}@if($s->project) · {{ $s->project->code }}@endif @if($s->warranty_id) · <b>{{ __('Warranty') }}</b>@endif</span></td>
                    <td>{{ $s->customer->name }}<span class="sub">{{ $s->customer->phone }}</span></td>
                    <td class="nowrap {{ $s->scheduled_at && $s->scheduled_at->isPast() && in_array($s->status, ['open', 'scheduled']) ? 'text-danger' : '' }}">{{ d($s->scheduled_at, 'D, d M') }}<span class="sub">{{ $s->scheduled_at ? $s->scheduled_at->format('H:i').' · '.__(':n min', ['n' => $s->duration_minutes]) : __('Not scheduled') }}</span></td>
                    <td class="hide-sm">{{ $s->assignee?->display_name ?? '—' }}</td>
                    <td><x-badge :status="$s->priority" :label="__($S::PRIORITIES[$s->priority] ?? $s->priority)" /></td>
                    <td><x-badge :status="$s->status" /></td>
                    <td class="chev-cell"><x-icon name="chevron-right" class="ico ico-sm" /></td>
                </tr>
            @endforeach</tbody>
        </table></div>
        <div class="panel-foot">{{ $rows->links() }}</div>
    @else
        <x-empty icon="wrench" :title="__('No service requests match these filters')" />
    @endif
</div>
@endsection
