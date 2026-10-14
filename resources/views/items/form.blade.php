@extends('layouts.app')
@section('title', $item->exists ? __('Edit').' '.$item->sku : __('New item'))
@section('content')
<x-page-header :eyebrow="__('Stock').' · '.($item->exists ? $item->sku : __('New'))" :title="$item->exists ? __('Edit item') : __('New stock item')" :sub="__('Quantities are never typed here — they come from stock movements. Ctrl+S saves.')">
    <x-slot:actions><a class="btn btn-ghost" href="{{ $item->exists ? route('items.show', $item) : route('items.index') }}">{{ __('Cancel') }}</a></x-slot:actions>
</x-page-header>

<form method="post" action="{{ $item->exists ? route('items.update', $item) : route('items.store') }}" class="panel" data-main-form>
    @csrf @if($item->exists) @method('PUT') @endif
    <div class="form-section">
        <h4><span class="step">1</span>{{ __('Identification') }}</h4>
        <div class="form-grid cols-3">
            <x-field name="sku" :label="__('SKU / item code')" :value="$item->sku" required :placeholder="__('e.g. ZC-GR26')" />
            <x-field name="name" :label="__('Item name')" :value="$item->name" required class="span-2" />
            <div class="field">
                <label for="f_category">{{ __('Category') }}</label>
                <input id="f_category" name="category" list="cat-list" value="{{ old('category', $item->category) }}" placeholder="{{ __('Pick or type a new one') }}">
                <datalist id="cat-list">@foreach($categories as $c)<option value="{{ $c }}">{{ __($c) }}</option>@endforeach</datalist>
            </div>
            <x-field name="uom" :label="__('Unit of measure')" type="select" :options="$uoms" :value="$item->uom" required />
            <div class="field" style="justify-content:flex-end">
                <input type="hidden" name="is_active" value="0">
                <label class="check" style="height:36px"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $item->is_active))> {{ __('Active (can be used in new transactions)') }}</label>
            </div>
        </div>
    </div>
    <div class="form-section">
        <h4><span class="step">2</span>{{ __('Pricing & replenishment') }}</h4>
        <div class="form-grid cols-3">
            @if($item->exists)
                <div class="field"><label>{{ __('Average unit cost') }}</label><input value="{{ money($item->unit_cost) }}" readonly><div class="hint">{{ __('Updated automatically by stock receipts (moving average).') }}</div></div>
            @else
                <x-field name="unit_cost" :label="__('Initial unit cost (RM)')" type="number" step="0.0001" min="0" :hint="__('Starting cost before the first receipt.')" />
            @endif
            <x-field name="selling_price" :label="__('Default selling price (RM)')" type="number" step="0.01" min="0" :value="$item->selling_price" :hint="__('Pre-fills quotation lines.')" />
            <x-field name="reorder_level" :label="__('Reorder level')" type="number" step="0.01" min="0" :value="$item->reorder_level" :hint="__('Alert when total on hand falls to this. 0 = no alert.')" />
        </div>
    </div>
    <div class="form-actions">
        <a class="btn btn-ghost" href="{{ $item->exists ? route('items.show', $item) : route('items.index') }}">{{ __('Cancel') }}</a>
        <button class="btn btn-primary"><x-icon name="check" class="ico ico-sm" />{{ $item->exists ? __('Save changes') : __('Create item') }}</button>
    </div>
</form>
@endsection
