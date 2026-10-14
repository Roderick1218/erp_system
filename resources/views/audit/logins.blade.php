@extends('layouts.app')
@section('title', __('Login Activity'))
@section('content')
@php
    $events = ['login' => __('Sign in'), 'logout' => __('Sign out'), 'failed' => __('Failed')];
@endphp
<x-page-header :eyebrow="__('System').' · '.__('Audit')" :title="__('Login Activity')" :count="$rows->total()" :sub="$failed24h ? __(':n failed sign-in attempt(s) in the last 24 hours', ['n' => $failed24h]) : __('No failed sign-ins in the last 24 hours')">
    <x-slot:actions><a class="btn btn-ghost" href="{{ route('audit.index') }}"><x-icon name="clipboard" class="ico ico-sm" />{{ __('Audit Log') }}</a></x-slot:actions>
</x-page-header>

<div class="panel">
    <form method="get" class="filters">
        <div class="filter-row">
            <div class="field grow"><label>{{ __('Search') }}</label><input name="q" value="{{ request('q') }}" placeholder="{{ __('Email or IP…') }}"></div>
            <div class="field"><label>{{ __('User') }}</label><select name="user" data-autosubmit><option value="">{{ __('Anyone') }}</option>@foreach($users as $id => $n)<option value="{{ $id }}" @selected(request('user') == $id)>{{ $n }}</option>@endforeach</select></div>
            <div class="field"><label>{{ __('Event') }}</label><select name="event" data-autosubmit><option value="">{{ __('All') }}</option>@foreach($events as $k => $v)<option value="{{ $k }}" @selected(request('event') === $k)>{{ $v }}</option>@endforeach</select></div>
            <div class="field"><label>{{ __('From') }}</label><input type="date" name="from" value="{{ request('from') }}"></div>
            <div class="field"><label>{{ __('To') }}</label><input type="date" name="to" value="{{ request('to') }}"></div>
            <div class="actions"><button class="btn btn-primary"><x-icon name="search" class="ico ico-sm" />{{ __('Filter') }}</button></div>
        </div>
    </form>
    @if($rows->count())
        <div class="table-wrap"><table class="table">
            <thead><tr><th>{{ __('When') }}</th><th>{{ __('User') }}</th><th>{{ __('Event') }}</th><th>IP</th><th class="hide-sm">{{ __('Device') }}</th></tr></thead>
            <tbody>@foreach($rows as $r)
                <tr>
                    <td class="small mono">{{ $r->created_at?->format('Y-m-d H:i:s') }}</td>
                    <td class="primary">{{ $r->user?->name ?? __('Unknown') }}<span class="sub">{{ $r->email }}</span></td>
                    <td><x-badge :status="$r->event" :label="$events[$r->event] ?? $r->event" :tone="['login' => 'success', 'logout' => 'neutral', 'failed' => 'danger'][$r->event] ?? 'neutral'" /></td>
                    <td class="mono small">{{ $r->ip }}</td>
                    <td class="hide-sm small muted" title="{{ $r->user_agent }}">{{ \Illuminate\Support\Str::limit($r->user_agent, 70) }}</td>
                </tr>
            @endforeach</tbody>
        </table></div>
        <div class="panel-foot">{{ $rows->links() }}</div>
    @else
        <x-empty icon="key" :title="__('No sign-in activity recorded')" />
    @endif
</div>
@endsection
