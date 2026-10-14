@extends('layouts.app')
@section('title', __('Project Board'))
@section('content')
@include('projects._nav', ['active' => 'board', 'title' => __('Project Board'), 'count' => $columns->flatten()->count(), 'sub' => auth()->user()->can('projects.manage') ? __('Drag a card to another column to change its status.') : __('Projects grouped by status.')])

<div class="panel" style="margin-bottom:16px">@include('projects._filters', ['keepView' => 'board'])</div>

@php($canMove = auth()->user()->can('projects.manage'))
<div class="kanban" data-kanban="status">
    @foreach(\App\Models\Project::STATUSES as $key => $label)
        @php($list = $columns[$key] ?? collect())
        <div class="kanban-col">
            <div class="kanban-col-head">
                <span><x-badge :status="$key" :label="__($label)" /> <span class="mono small muted" data-count>{{ $list->count() }}</span></span>
                <span class="sum">{{ money($list->sum('contract_value')) }}</span>
            </div>
            <div class="kanban-list" data-value="{{ $key }}" data-label="{{ __($label) }}">
                @foreach($list as $p)
                    <a class="kcard" href="{{ route('projects.show', $p) }}" @if($canMove) draggable="true" data-url="{{ route('projects.status', $p) }}" @endif>
                        <div class="row between"><span class="code mono small" style="color:var(--brand)">{{ $p->code }}</span>@if($p->category !== 'normal')<span class="tag">{{ __(ucfirst($p->category)) }}</span>@endif</div>
                        <div class="t">{{ $p->name }}</div>
                        <div class="m">{{ $p->customer->name }} · {{ __($p->state) }}</div>
                        <div class="progress-cell" style="margin-top:8px"><div class="progress" style="flex:1"><span style="width:{{ $p->progress }}%"></span></div><small>{{ $p->progress }}%</small></div>
                        <div class="f">
                            <span class="muted">{{ d($p->start_date, 'd M') }} → {{ d($p->end_date, 'd M') }}</span>
                            @if($p->manager)<span class="avatar sm" title="{{ $p->manager->name }}">{{ $p->manager->initials }}</span>@endif
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
@endsection
