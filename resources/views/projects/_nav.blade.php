{{-- Shared header for the Project list / board / calendar / schedule views. --}}
@php
    $views = [
        route('projects.index', ['view' => 'list']) => [__('List'), 'table'],
        route('projects.index', ['view' => 'board']) => [__('Board'), 'columns'],
        route('projects.calendar') => [__('Calendar'), 'calendar'],
        route('projects.schedule') => [__('Schedule'), 'bars'],
    ];
    $currentUrl = [
        'list' => route('projects.index', ['view' => 'list']),
        'board' => route('projects.index', ['view' => 'board']),
        'calendar' => route('projects.calendar'),
        'schedule' => route('projects.schedule'),
    ][$active];
@endphp
<x-page-header :eyebrow="__('Project').' · '.__(ucfirst($active))" :title="$title" :count="$count ?? null" :sub="$sub ?? null">
    <x-slot:actions>
        <x-view-switch :current="$currentUrl" :views="$views" />
        @can('projects.manage')<a class="btn btn-primary" href="{{ route('projects.create') }}"><x-icon name="plus" class="ico ico-sm" />{{ __('New project') }}</a>@endcan
    </x-slot:actions>
</x-page-header>
@push('head')
<style>
    .page-head .segmented { background: rgba(255,255,255,.08); border-color: rgba(255,255,255,.18); }
    .page-head .segmented a { color: #c6d6ee; }
    .page-head .segmented a.active { background: #fff; color: var(--navy); }
</style>
@endpush
