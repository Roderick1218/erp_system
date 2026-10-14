@extends('layouts.app')
@section('title', __('Holidays'))
@section('content')
<x-page-header :eyebrow="__('HR').' · '.__('Holidays')" :title="__('Public holidays :year', ['year' => $year])" :count="$count" :sub="$next ? __('Next:').' '.__($next->name).' · '.d($next->date, 'D, d M Y') : null">
    <x-slot:actions>
        <a class="btn btn-ghost" href="{{ route('holidays.index', ['year' => $year - 1]) }}"><x-icon name="chevron-left" class="ico ico-sm" />{{ $year - 1 }}</a>
        <a class="btn btn-ghost" href="{{ route('holidays.index', ['year' => $year + 1]) }}">{{ $year + 1 }}<x-icon name="chevron-right" class="ico ico-sm" /></a>
        <button class="btn btn-primary" data-dialog="dlg-holiday"><x-icon name="plus" class="ico ico-sm" />{{ __('Add holiday') }}</button>
    </x-slot:actions>
</x-page-header>

<div class="alert alert-info"><x-icon name="info" />{{ __('Holidays are skipped when counting leave days and show as “H” on the attendance report. Branch-specific holidays (e.g. a state Sultan\'s birthday) only apply to that branch.') }}</div>

@if($count)
    <div class="grid g-3">
        @foreach($byMonth as $month => $list)
            <div class="panel">
                <div class="panel-head navy"><h3><x-icon name="calendar" />{{ $list->first()->date->translatedFormat(app()->getLocale() === 'zh_CN' ? 'n月' : 'F') }}</h3><span class="panel-count">{{ $list->count() }}</span></div>
                <ul class="list">
                    @foreach($list as $h)
                        <li style="{{ $h->date->isPast() ? 'opacity:.6' : '' }}">
                            <span style="min-width:0">{{ __($h->name) }}<div class="meta">{{ d($h->date, 'D, d M') }} · {{ $h->branch ? $h->branch->legal_name : __('All branches') }}</div></span>
                            <form method="post" action="{{ route('holidays.destroy', $h) }}" data-confirm="{{ __('Remove “:name”?', ['name' => __($h->name)]) }}">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm btn-icon" title="{{ __('Remove') }}"><x-icon name="trash" class="ico ico-sm" /></button></form>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
@else
    <div class="panel"><x-empty icon="flag" :title="__('No holidays recorded for :year', ['year' => $year])"><button class="btn btn-primary btn-sm" data-dialog="dlg-holiday">{{ __('Add holiday') }}</button></x-empty></div>
@endif

<dialog id="dlg-holiday">
    <form method="post" action="{{ route('holidays.store') }}">
        @csrf
        <div class="dlg-head"><h3>{{ __('Add holiday') }}</h3><button type="button" class="icon-btn" data-close><x-icon name="x" /></button></div>
        <div class="dlg-body form-grid">
            <x-field name="date" :label="__('Date')" type="date" required />
            <x-field name="branch_id" :label="__('Applies to')" type="select" :options="$branches" :placeholder="__('All branches')" />
            <x-field name="name" :label="__('Holiday name')" required class="span-all" :placeholder="__('e.g. Hari Raya Aidilfitri')" />
        </div>
        <div class="dlg-foot"><button type="button" class="btn btn-ghost" data-close>{{ __('Cancel') }}</button><button class="btn btn-primary">{{ __('Add') }}</button></div>
    </form>
</dialog>
@if($errors->hasAny(['date', 'name']))<span data-reopen-dialog="dlg-holiday"></span>@endif
@endsection
