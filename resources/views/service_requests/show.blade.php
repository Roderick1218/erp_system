@extends('layouts.app')
@section('title', $sr->doc_no)
@section('content')
@php($S = \App\Http\Controllers\ServiceRequestController::class)
<x-page-header :eyebrow="__('After-sales').' · '.__('Service').' · '.$sr->doc_no" :title="$sr->title" :sub="__($S::TYPES[$sr->type] ?? $sr->type).' · '.$sr->customer->name.' · '.($sr->scheduled_at ? d($sr->scheduled_at, 'D, d M Y H:i') : __('Not scheduled'))">
    <x-slot:badge><x-badge :status="$sr->status" /> <x-badge :status="$sr->priority" :label="__(':level priority', ['level' => __($S::PRIORITIES[$sr->priority] ?? $sr->priority)])" /></x-slot:badge>
    <x-slot:actions>
        <a class="btn btn-ghost" href="https://wa.me/{{ preg_replace('/\D/', '', $sr->customer->phone) }}" target="_blank" rel="noopener"><x-icon name="phone" class="ico ico-sm" />WhatsApp</a>
        <a class="btn btn-ghost" href="{{ route('service-requests.edit', $sr) }}"><x-icon name="pencil" class="ico ico-sm" />{{ __('Edit') }}</a>
    </x-slot:actions>
</x-page-header>

<div class="grid g-main-side">
    <div class="stack">
        <div class="panel">
            <div class="panel-head"><h3><x-icon name="refresh" />{{ __('Progress') }}</h3></div>
            <div class="panel-body">
                <div class="steps" style="margin-bottom:16px">
                    @php($order = ['open', 'scheduled', 'in_progress', 'done'])
                    @php($pos = array_search($sr->status, $order, true))
                    @foreach($order as $i => $st)
                        <div class="step {{ $sr->status === 'cancelled' ? '' : ($pos !== false && $i < $pos ? 'done' : ($i === $pos ? 'current' : '')) }}">{{ __($S::STATUSES[$st]) }}</div>
                    @endforeach
                </div>
                @if($sr->status === 'cancelled')<div class="alert alert-danger"><x-icon name="x" />{{ __('This request was cancelled.') }}</div>@endif
                <form method="post" action="{{ route('service-requests.status', $sr) }}" class="stack">
                    @csrf @method('PATCH')
                    <x-field name="resolution" :label="__('Resolution / work done')" type="textarea" :value="$sr->resolution" :placeholder="__('Required before closing: what was found, what was fixed, materials used.')" />
                    <div class="row">
                        @if(in_array($sr->status, ['open', 'scheduled']))<button class="btn btn-primary" name="status" value="in_progress"><x-icon name="bolt" class="ico ico-sm" />{{ __('Start job') }}</button>@endif
                        @if($sr->status !== 'done')<button class="btn btn-success" name="status" value="done"><x-icon name="check" class="ico ico-sm" />{{ __('Mark done') }}</button>@endif
                        @if($sr->status === 'done')<button class="btn btn-ghost" name="status" value="in_progress">{{ __('Re-open') }}</button>@endif
                        @if(! in_array($sr->status, ['done', 'cancelled']))<button class="btn btn-danger-ghost" name="status" value="cancelled" onclick="return confirm(@js(__('Cancel this request?')))">{{ __('Cancel request') }}</button>@endif
                        @if($sr->status === 'done')<button class="btn btn-ghost" name="status" value="done">{{ __('Save resolution') }}</button>@endif
                    </div>
                </form>
            </div>
        </div>
        <div class="panel">
            <div class="panel-head"><h3><x-icon name="document" />{{ __('Description') }}</h3></div>
            <div class="panel-body" style="white-space:pre-line">{{ $sr->description ?: __('No description.') }}</div>
        </div>
        <div class="panel">
            <div class="panel-head"><h3><x-icon name="clock" />{{ __('Other requests from this customer') }}</h3></div>
            @if($history->count())
                <div class="table-wrap"><table class="table compact">
                    <tbody>@foreach($history as $h)
                        <tr data-href="{{ route('service-requests.show', $h) }}"><td class="code">{{ $h->doc_no }}</td><td>{{ $h->title }}</td><td class="nowrap small">{{ d($h->scheduled_at) }}</td><td><x-badge :status="$h->status" /></td></tr>
                    @endforeach</tbody>
                </table></div>
            @else <x-empty icon="inbox" :title="__('First request from this customer')" /> @endif
        </div>
    </div>
    <div class="stack">
        <div class="panel">
            <div class="panel-head"><h3><x-icon name="calendar" />{{ __('Appointment') }}</h3></div>
            <div class="panel-body">
                <dl class="dl">
                    <dt>{{ __('When') }}</dt><dd>{{ $sr->scheduled_at ? d($sr->scheduled_at, 'D, d M Y H:i') : '—' }}<div class="small muted">{{ __(':n minutes', ['n' => $sr->duration_minutes]) }}</div></dd>
                    <dt>{{ __('Technician') }}</dt><dd>{{ $sr->assignee?->display_name ?? __('Unassigned') }}@if($sr->assignee?->phone)<div class="small muted">{{ $sr->assignee->phone }}</div>@endif</dd>
                    <dt>{{ __('Customer') }}</dt><dd>@can('customers.view')<a href="{{ route('customers.show', $sr->customer) }}">{{ $sr->customer->name }}</a>@else{{ $sr->customer->name }}@endcan<div class="small muted">{{ $sr->customer->phone }}</div></dd>
                    <dt>{{ __('Address') }}</dt><dd>{{ $sr->project?->site_address ?? trim($sr->customer->address.', '.$sr->customer->postcode.' '.$sr->customer->city, ', ') }}</dd>
                    <dt>{{ __('Project') }}</dt><dd>@if($sr->project)@can('projects.view')<a href="{{ route('projects.show', $sr->project) }}">{{ $sr->project->code }}</a>@else{{ $sr->project->code }}@endcan @else — @endif</dd>
                    <dt>{{ __('Warranty') }}</dt><dd>@if($sr->warranty)<a href="{{ route('warranties.show', $sr->warranty) }}">{{ $sr->warranty->doc_no }}</a>
                        <div class="small {{ $sr->warranty->end_date->isPast() ? 'text-danger' : 'text-success' }}">{{ $sr->warranty->end_date->isPast() ? __('Expired on :date', ['date' => d($sr->warranty->end_date)]) : __('Covered until :date', ['date' => d($sr->warranty->end_date)]) }}</div>@else — @endif</dd>
                    <dt>{{ __('Created') }}</dt><dd>{{ d($sr->created_at, 'd M Y H:i') }}@if($sr->creator)<div class="small muted">{{ __('by') }} {{ $sr->creator->name }}</div>@endif</dd>
                </dl>
            </div>
        </div>
        <form method="post" action="{{ route('service-requests.destroy', $sr) }}" data-confirm="{{ __('Delete :doc?', ['doc' => $sr->doc_no]) }}">@csrf @method('DELETE')
            <button class="btn btn-danger-ghost btn-block"><x-icon name="trash" class="ico ico-sm" />{{ __('Delete request') }}</button>
        </form>
    </div>
</div>
@endsection
