@extends('layouts.app')
@section('title', __('Worker Rates'))
@section('content')
<x-page-header :eyebrow="__('Inventory').' · '.__('Worker rate management')" :title="__('Worker Rates')" :count="$rows->count()" :sub="__('Standard labour and subcon rates used when estimating and costing jobs.')">
    <x-slot:actions>
        <button type="button" class="btn btn-primary" data-dialog="dlg-rate" data-title="{{ __('New rate') }}" data-action="{{ route('worker-rates.store') }}" data-method="POST"
                data-fill='{"trade":"","description":"","rate_type":"day","rate":"","is_active":true,"_action":"{{ route('worker-rates.store') }}"}'><x-icon name="plus" class="ico ico-sm" />{{ __('Add rate') }}</button>
    </x-slot:actions>
</x-page-header>

<div class="panel">
    <form method="get" class="toolbar">
        <div class="search"><x-icon name="search" class="ico ico-sm" /><input class="input" name="q" value="{{ request('q') }}" placeholder="{{ __('Search trade or description…') }}"></div>
        <select name="rate_type" class="input" style="width:auto;height:34px" data-autosubmit><option value="">{{ __('All rate types') }}</option>@foreach($types as $k => $v)<option value="{{ $k }}" @selected(request('rate_type') === $k)>{{ __($v) }}</option>@endforeach</select>
        @if(request()->hasAny(['q', 'rate_type']))<a class="btn btn-ghost btn-sm" href="{{ route('worker-rates.index') }}">{{ __('Reset') }}</a>@endif
    </form>
    @if($rows->count())
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>{{ __('Trade / work') }}</th><th class="hide-sm">{{ __('Description') }}</th><th class="num">{{ __('Rate') }}</th><th>{{ __('Basis') }}</th><th>{{ __('Status') }}</th><th class="actions"></th></tr></thead>
                <tbody>
                @foreach($rows as $r)
                    <tr>
                        <td class="primary">{{ $r->trade }}</td>
                        <td class="hide-sm muted">{{ $r->description ?? '—' }}</td>
                        <td class="num"><b>{{ money($r->rate) }}</b></td>
                        <td><span class="tag">{{ __($types[$r->rate_type] ?? $r->rate_type) }}</span></td>
                        <td><x-badge :status="$r->is_active ? 'active' : 'inactive'" /></td>
                        <td class="actions">
                            <button type="button" class="btn btn-ghost btn-sm" data-dialog="dlg-rate" data-title="{{ __('Edit rate') }}" data-action="{{ route('worker-rates.update', $r) }}" data-method="PUT"
                                    data-fill="{{ json_encode(['trade' => $r->trade, 'description' => $r->description, 'rate_type' => $r->rate_type, 'rate' => (float) $r->rate, 'is_active' => $r->is_active, '_action' => route('worker-rates.update', $r)]) }}">{{ __('Edit') }}</button>
                            <form method="post" action="{{ route('worker-rates.destroy', $r) }}" data-confirm="{{ __('Delete the :trade rate?', ['trade' => $r->trade]) }}" style="display:inline">@csrf @method('DELETE')
                                <button class="btn btn-danger-ghost btn-sm btn-icon" title="{{ __('Delete') }}"><x-icon name="trash" class="ico ico-sm" /></button></form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @else
        <x-empty icon="users" :title="__('No rates found')" />
    @endif
</div>

@if(session('reopen'))<span data-reopen-dialog="{{ session('reopen') }}" hidden></span>@endif
<dialog id="dlg-rate">
    <form method="post" action="{{ old('_action', route('worker-rates.store')) }}">
        @csrf <input type="hidden" name="_method" value="{{ old('_method', 'POST') }}"><input type="hidden" name="_action" value="{{ old('_action') }}">
        <div class="dlg-head"><h3>{{ __('Worker rate') }}</h3><button type="button" class="icon-btn" data-close><x-icon name="x" /></button></div>
        <div class="dlg-body">
            <div class="form-grid">
                <x-field name="trade" :label="__('Trade / work')" required :placeholder="__('e.g. Roofer, Torch-on membrane (labour)')" class="span-all" />
                <x-field name="rate" :label="__('Rate (RM)')" type="number" step="0.01" min="0" required />
                <x-field name="rate_type" :label="__('Basis')" type="select" :options="array_map(fn ($v) => __($v), $types)" value="day" required />
                <x-field name="description" :label="__('Description')" class="span-all" />
                <label class="check span-all"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', ! old('_action')))> {{ __('Active') }}</label>
            </div>
        </div>
        <div class="dlg-foot"><button type="button" class="btn btn-ghost" data-close>{{ __('Cancel') }}</button><button class="btn btn-primary">{{ __('Save') }}</button></div>
    </form>
</dialog>
@endsection
