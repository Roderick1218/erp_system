@extends('layouts.app')
@section('title', $w->doc_no)
@section('content')
@php($left = $w->days_left)
<x-page-header :eyebrow="__('After-sales').' · '.__('Warranty').' · '.$w->doc_no" :title="__($w->service).' — '.$w->customer->name" :sub="d($w->start_date).' → '.d($w->end_date).' · '.trans_choice(':n year|:n years', $w->years, ['n' => $w->years])">
    <x-slot:badge><x-badge :status="$w->status === 'void' ? 'void' : ($left < 0 ? 'expired' : ($left <= 60 ? 'expiring' : 'active'))" /></x-slot:badge>
    <x-slot:actions>
        <a class="btn btn-ghost" href="{{ route('warranties.print', $w) }}" target="_blank"><x-icon name="printer" class="ico ico-sm" />{{ __('Certificate') }}</a>
        <a class="btn btn-ghost" href="{{ route('warranties.edit', $w) }}"><x-icon name="pencil" class="ico ico-sm" />{{ __('Edit') }}</a>
        <a class="btn btn-primary" href="{{ route('service-requests.create', ['warranty_id' => $w->id]) }}"><x-icon name="wrench" class="ico ico-sm" />{{ __('Warranty claim') }}</a>
    </x-slot:actions>
</x-page-header>

<div class="grid g-main-side">
    <div class="stack">
        <div class="panel">
            <div class="summary-bar" style="border-top:0">
                <div><small>{{ __('Starts') }}</small><strong>{{ d($w->start_date) }}</strong></div>
                <div><small>{{ __('Ends') }}</small><strong>{{ d($w->end_date) }}</strong></div>
                <div><small>{{ __('Days left') }}</small><strong class="{{ $left < 0 ? 'text-danger' : ($left <= 60 ? 'text-warning' : 'text-success') }}">{{ $left < 0 ? __('Expired :n days ago', ['n' => abs($left)]) : number_format($left) }}</strong></div>
                <div><small>{{ __('Claims') }}</small><strong>{{ $w->serviceRequests->count() }}</strong></div>
            </div>
        </div>
        <div class="panel">
            <div class="panel-head"><h3><x-icon name="wrench" />{{ __('Service requests under this warranty') }}</h3></div>
            @if($w->serviceRequests->count())
                <div class="table-wrap"><table class="table">
                    <thead><tr><th>{{ __('Ref') }}</th><th>{{ __('Title') }}</th><th>{{ __('Scheduled') }}</th><th class="hide-sm">{{ __('Assigned') }}</th><th>{{ __('Status') }}</th></tr></thead>
                    <tbody>@foreach($w->serviceRequests->sortByDesc('scheduled_at') as $s)
                        <tr data-href="{{ route('service-requests.show', $s) }}"><td class="code">{{ $s->doc_no }}</td><td>{{ $s->title }}</td><td class="nowrap">{{ d($s->scheduled_at, 'd M Y H:i') }}</td><td class="hide-sm">{{ $s->assignee?->display_name }}</td><td><x-badge :status="$s->status" /></td></tr>
                    @endforeach</tbody>
                </table></div>
            @else <x-empty icon="check-badge" :title="__('No claims made')" /> @endif
        </div>
        <div class="panel">
            <div class="panel-head"><h3><x-icon name="shield" />{{ __('Coverage') }}</h3></div>
            <div class="panel-body" style="white-space:pre-line">{{ $w->coverage ?: '—' }}</div>
        </div>
    </div>
    <div class="stack">
        <div class="panel">
            <div class="panel-head"><h3><x-icon name="user" />{{ __('Customer & project') }}</h3></div>
            <div class="panel-body">
                <dl class="dl">
                    <dt>{{ __('Customer') }}</dt><dd>@can('customers.view')<a href="{{ route('customers.show', $w->customer) }}">{{ $w->customer->name }}</a>@else{{ $w->customer->name }}@endcan<div class="small muted">{{ $w->customer->phone }}</div></dd>
                    <dt>{{ __('Project') }}</dt><dd>@if($w->project)@can('projects.view')<a href="{{ route('projects.show', $w->project) }}">{{ $w->project->code }}</a>@else{{ $w->project->code }}@endcan<div class="small muted">{{ $w->project->site_address }}</div>@else — @endif</dd>
                    <dt>{{ __('Service') }}</dt><dd>{{ __($w->service) }}</dd>
                    <dt>{{ __('Issued') }}</dt><dd>{{ d($w->created_at) }}</dd>
                </dl>
            </div>
        </div>
        <form method="post" action="{{ route('warranties.destroy', $w) }}" data-confirm="{{ __('Delete warranty :doc?', ['doc' => $w->doc_no]) }}">@csrf @method('DELETE')
            <button class="btn btn-danger-ghost btn-block"><x-icon name="trash" class="ico ico-sm" />{{ __('Delete warranty') }}</button>
        </form>
    </div>
</div>
@endsection
