@extends('layouts.app')
@section('title', __('Warehouses'))
@section('content')
<x-page-header :eyebrow="__('Stock').' · '.__('Warehouses')" :title="__('Warehouses')" :count="$warehouses->count()" :sub="__('Store locations that hold stock. Each movement belongs to one warehouse.')">
    <x-slot:actions>
        <a class="btn btn-ghost" href="{{ route('items.index') }}"><x-icon name="cube" class="ico ico-sm" />{{ __('Stock list') }}</a>
        <button type="button" class="btn btn-primary" data-dialog="dlg-warehouse" data-title="{{ __('New warehouse') }}" data-action="{{ route('warehouses.store') }}" data-method="POST"
                data-fill='{"code":"","name":"","branch_id":"","address":"","is_active":true,"_action":"{{ route('warehouses.store') }}"}'><x-icon name="plus" class="ico ico-sm" />{{ __('Add warehouse') }}</button>
    </x-slot:actions>
</x-page-header>

@if($warehouses->count())
    <div class="grid g-3">
        @foreach($warehouses as $w)
            @php
                $s = $stats[$w->id] ?? null;
            @endphp
            <div class="panel">
                <div class="panel-head">
                    <h3><x-icon name="building" /><span class="code mono">{{ $w->code }}</span></h3>
                    <x-badge :status="$w->is_active ? 'active' : 'inactive'" />
                </div>
                <div class="panel-body">
                    <div style="font-weight:600;font-size:15px">{{ $w->name }}</div>
                    <div class="small muted">{{ $w->branch?->legal_name ?? __('No branch') }}</div>
                    @if($w->address)<div class="small" style="margin-top:6px;white-space:pre-line">{{ $w->address }}</div>@endif
                    <div class="summary-bar" style="margin:14px -16px -16px">
                        <div><small>{{ __('Items in stock') }}</small><strong>{{ $s->items ?? 0 }}</strong></div>
                        <div><small>{{ __('Stock value') }}</small><strong>{{ money($s->value ?? 0) }}</strong></div>
                    </div>
                </div>
                <div class="panel-foot">
                    <a class="small" href="{{ route('items.index', ['warehouse' => $w->id]) }}">{{ __('View stock →') }}</a>
                    <div class="row" style="gap:6px">
                        <button type="button" class="btn btn-ghost btn-sm" data-dialog="dlg-warehouse" data-title="{{ __('Edit :code', ['code' => $w->code]) }}" data-action="{{ route('warehouses.update', $w) }}" data-method="PUT"
                                data-fill="{{ json_encode(['code' => $w->code, 'name' => $w->name, 'branch_id' => $w->branch_id, 'address' => $w->address, 'is_active' => $w->is_active, '_action' => route('warehouses.update', $w)]) }}"><x-icon name="pencil" class="ico ico-sm" />{{ __('Edit') }}</button>
                        <form method="post" action="{{ route('warehouses.destroy', $w) }}" data-confirm="{{ __('Remove warehouse :code?', ['code' => $w->code]) }}">@csrf @method('DELETE')
                            <button class="btn btn-danger-ghost btn-sm btn-icon" title="{{ __('Delete') }}"><x-icon name="trash" class="ico ico-sm" /></button></form>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@else
    <div class="panel"><x-empty icon="building" :title="__('No warehouses yet')">{{ __('Add your first store location to start recording stock.') }}</x-empty></div>
@endif

@if(session('reopen'))<span data-reopen-dialog="{{ session('reopen') }}" hidden></span>@endif
<dialog id="dlg-warehouse">
    <form method="post" action="{{ old('_action', route('warehouses.store')) }}">
        @csrf <input type="hidden" name="_method" value="{{ old('_method', 'POST') }}"><input type="hidden" name="_action" value="{{ old('_action') }}">
        <div class="dlg-head"><h3>{{ __('Warehouse') }}</h3><button type="button" class="icon-btn" data-close><x-icon name="x" /></button></div>
        <div class="dlg-body">
            <div class="form-grid">
                <x-field name="code" :label="__('Code')" required :placeholder="__('e.g. KV-HQ')" />
                <x-field name="name" :label="__('Name')" required />
                <x-field name="branch_id" :label="__('Branch')" type="select" :options="$branches" placeholder="—" class="span-all" />
                <x-field name="address" :label="__('Address')" type="textarea" class="span-all" />
                <label class="check span-all"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', ! old('_action')))> {{ __('Active') }}</label>
            </div>
        </div>
        <div class="dlg-foot"><button type="button" class="btn btn-ghost" data-close>{{ __('Cancel') }}</button><button class="btn btn-primary">{{ __('Save') }}</button></div>
    </form>
</dialog>
@endsection
