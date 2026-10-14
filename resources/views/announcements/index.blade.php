@extends('layouts.app')
@section('title', __('Announcements'))
@section('content')
<x-page-header :eyebrow="__('HR').' · '.__('Announcements')" :title="__('Announcements')" :count="$rows->total()" :sub="__('Company memos shown to everyone on the dashboard. Pinned items stay on top.')">
    <x-slot:actions>
        <button class="btn btn-primary" data-dialog="dlg-ann" data-action="{{ route('announcements.store') }}" data-method="POST" data-title="{{ __('New announcement') }}"
                data-fill="{{ json_encode(['title' => '', 'body' => '', 'published_on' => today()->toDateString(), 'is_pinned' => false]) }}"><x-icon name="plus" class="ico ico-sm" />{{ __('New announcement') }}</button>
    </x-slot:actions>
</x-page-header>

<div class="panel">
    <form method="get" class="toolbar">
        <div class="search"><x-icon name="search" class="ico ico-sm" /><input class="input" name="q" value="{{ request('q') }}" placeholder="{{ __('Search title or content…') }}"></div>
        <select name="year" class="input" style="width:auto;height:34px" data-autosubmit><option value="">{{ __('All years') }}</option>@foreach(range(now()->year, now()->year - 4) as $y)<option @selected(request('year') == $y)>{{ $y }}</option>@endforeach</select>
    </form>
    @forelse($rows as $a)
        <div style="padding:14px 16px;border-bottom:1px solid var(--border);display:flex;gap:14px;align-items:flex-start">
            <div style="flex:1;min-width:0">
                <div class="row" style="gap:8px"><span class="date mono small muted">{{ $a->published_on->format('Y-m-d') }}</span>@if($a->is_pinned)<span class="badge badge-info plain">{{ __('Pinned') }}</span>@endif</div>
                <div style="font-weight:600;margin-top:2px">{{ $a->title }}</div>
                @if($a->body)<div class="small" style="white-space:pre-line;margin-top:4px;color:var(--text-2)">{{ $a->body }}</div>@endif
                <div class="small muted" style="margin-top:4px">{{ __('by') }} {{ $a->author?->name ?? __('System') }}</div>
            </div>
            <div class="row" style="flex-shrink:0">
                <form method="post" action="{{ route('announcements.update', $a) }}">@csrf @method('PUT')<input type="hidden" name="toggle_pin" value="1"><button class="btn btn-ghost btn-sm">{{ $a->is_pinned ? __('Unpin') : __('Pin') }}</button></form>
                <button class="btn btn-ghost btn-sm" data-dialog="dlg-ann" data-action="{{ route('announcements.update', $a) }}" data-method="PUT" data-title="{{ __('Edit announcement') }}" title="{{ __('Edit') }}"
                        data-fill="{{ json_encode(['title' => $a->title, 'body' => $a->body, 'published_on' => $a->published_on->toDateString(), 'is_pinned' => $a->is_pinned]) }}"><x-icon name="pencil" class="ico ico-sm" /></button>
                <form method="post" action="{{ route('announcements.destroy', $a) }}" data-confirm="{{ __('Delete this announcement?') }}">@csrf @method('DELETE')<button class="btn btn-danger-ghost btn-sm btn-icon" title="{{ __('Delete') }}"><x-icon name="trash" class="ico ico-sm" /></button></form>
            </div>
        </div>
    @empty
        <x-empty icon="megaphone" :title="__('No announcements')" />
    @endforelse
    @if($rows->hasPages())<div class="panel-foot">{{ $rows->links() }}</div>@endif
</div>

<dialog id="dlg-ann" class="wide">
    <form method="post" action="{{ route('announcements.store') }}">
        @csrf <input type="hidden" name="_method" value="POST">
        <div class="dlg-head"><h3>{{ __('New announcement') }}</h3><button type="button" class="icon-btn" data-close><x-icon name="x" /></button></div>
        <div class="dlg-body form-grid">
            <x-field name="title" :label="__('Title')" required class="span-all" :placeholder="__('Memo — …')" />
            <x-field name="published_on" :label="__('Publish date')" type="date" :value="today()->toDateString()" required />
            <label class="check" style="align-self:end;padding-bottom:8px"><input type="checkbox" name="is_pinned" value="1"> {{ __('Pin to top') }}</label>
            <x-field name="body" :label="__('Content')" type="textarea" class="span-all" />
        </div>
        <div class="dlg-foot"><button type="button" class="btn btn-ghost" data-close>{{ __('Cancel') }}</button><button class="btn btn-primary">{{ __('Save') }}</button></div>
    </form>
</dialog>
@endsection
