@extends('layouts.app')
@section('title', __('Audit Log'))
@section('content')
@php
    $eventLabels = ['created' => __('Record created'), 'updated' => __('Record updated'), 'deleted' => __('Record deleted')];
@endphp
<x-page-header :eyebrow="__('System').' · '.__('Audit')" :title="__('Audit Log')" :count="$rows->total()" :sub="__('Every create / update / delete on core records, with old and new values. Entries cannot be edited.')">
    <x-slot:actions><a class="btn btn-ghost" href="{{ route('audit.logins') }}"><x-icon name="key" class="ico ico-sm" />{{ __('Login Activity') }}</a></x-slot:actions>
</x-page-header>

<div class="panel">
    <form method="get" class="filters">
        <div class="filter-row">
            <div class="field"><label>{{ __('Record type') }}</label><select name="log" data-autosubmit><option value="">{{ __('All') }}</option>@foreach($logs as $l)<option value="{{ $l }}" @selected(request('log') === $l)>{{ __($l) }}</option>@endforeach</select></div>
            <div class="field"><label>{{ __('Action') }}</label><select name="event" data-autosubmit><option value="">{{ __('All') }}</option>@foreach($eventLabels as $ev => $evLabel)<option value="{{ $ev }}" @selected(request('event') === $ev)>{{ $evLabel }}</option>@endforeach</select></div>
            <div class="field"><label>{{ __('By user') }}</label><select name="causer" data-autosubmit><option value="">{{ __('Anyone') }}</option>@foreach($users as $id => $n)<option value="{{ $id }}" @selected(request('causer') == $id)>{{ $n }}</option>@endforeach</select></div>
            <div class="field"><label>{{ __('From') }}</label><input type="date" name="from" value="{{ request('from') }}"></div>
            <div class="field"><label>{{ __('To') }}</label><input type="date" name="to" value="{{ request('to') }}"></div>
            <div class="actions">
                @if(request()->except('page'))<a class="btn btn-ghost" href="{{ route('audit.index') }}">{{ __('Reset') }}</a>@endif
                <button class="btn btn-primary"><x-icon name="search" class="ico ico-sm" />{{ __('Filter') }}</button>
            </div>
        </div>
    </form>
    @if($rows->count())
        <div class="table-wrap"><table class="table">
            <thead><tr><th style="width:150px">{{ __('When') }}</th><th>{{ __('User') }}</th><th>{{ __('Action') }}</th><th>{{ __('Record') }}</th><th>{{ __('Changes') }}</th></tr></thead>
            <tbody>@foreach($rows as $a)
                @php($new = $a->properties['attributes'] ?? [])
                @php($old = $a->properties['old'] ?? [])
                <tr style="vertical-align:top">
                    <td class="small mono">{{ $a->created_at->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $a->causer?->name ?? __('System') }}</td>
                    <td><x-badge :status="$a->event" :label="$eventLabels[$a->event] ?? __($a->description)" :tone="['created' => 'success', 'updated' => 'info', 'deleted' => 'danger'][$a->event] ?? 'neutral'" /></td>
                    <td><span class="tag">{{ __($a->log_name) }}</span> <span class="mono small">#{{ $a->subject_id }}</span>
                        @php($sub = $a->subject)
                        @if($sub)<span class="sub">{{ $sub->doc_no ?? $sub->code ?? $sub->emp_no ?? $sub->name ?? $sub->title ?? '' }}</span>@endif</td>
                    <td class="small">
                        @if($a->event === 'updated' && $new)
                            @foreach($new as $k => $v)
                                <div><span class="muted">{{ $k }}:</span> <s class="text-danger">{{ is_scalar($old[$k] ?? null) ? \Illuminate\Support\Str::limit((string) $old[$k], 60) : json_encode($old[$k] ?? null) }}</s> → <b>{{ is_scalar($v) ? \Illuminate\Support\Str::limit((string) $v, 60) : json_encode($v) }}</b></div>
                            @endforeach
                        @elseif($new)
                            <details><summary class="muted" style="cursor:pointer">{{ __(':n field(s)', ['n' => count($new)]) }}</summary>
                                @foreach($new as $k => $v)<div><span class="muted">{{ $k }}:</span> {{ is_scalar($v) ? \Illuminate\Support\Str::limit((string) $v, 80) : json_encode($v) }}</div>@endforeach
                            </details>
                        @elseif($old)
                            <span class="muted">{{ __(':n field(s) removed', ['n' => count($old)]) }}</span>
                        @else
                            <span class="muted">{{ __($a->description) }}</span>
                        @endif
                    </td>
                </tr>
            @endforeach</tbody>
        </table></div>
        <div class="panel-foot">{{ $rows->links() }}</div>
    @else
        <x-empty icon="clipboard" :title="__('No activity matches these filters')" />
    @endif
</div>
@endsection
