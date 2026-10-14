@extends('layouts.app')
@section('title', $po->exists ? __('Edit').' '.$po->doc_no : __('New purchase order'))
@section('content')
@php
    $rows = old('lines', $lines);
    if (! count($rows)) { $rows = [['item_id' => '', 'qty' => 1, 'unit_price' => '']]; }
@endphp
<x-page-header :eyebrow="__('Purchasing').' · '.($po->exists ? $po->doc_no : __('New'))" :title="$po->exists ? __('Edit purchase order') : __('New purchase order')" :sub="__('Prices pre-fill from the supplier’s latest price list. Ctrl+S saves as draft.')">
    <x-slot:actions><a class="btn btn-ghost" href="{{ $po->exists ? route('purchase-orders.show', $po) : route('purchase-orders.index') }}">{{ __('Cancel') }}</a></x-slot:actions>
</x-page-header>

<form method="post" action="{{ $po->exists ? route('purchase-orders.update', $po) : route('purchase-orders.store') }}" class="panel" data-main-form id="po-form">
    @csrf @if($po->exists) @method('PUT') @endif
    <div class="form-section">
        <h4><span class="step">1</span>{{ __('Supplier & delivery') }}</h4>
        <div class="form-grid cols-3">
            <x-field name="supplier_id" :label="__('Supplier')" type="select" :options="$suppliers" :placeholder="'— '.__('Select supplier').' —'" :value="$po->supplier_id" required />
            <x-field name="warehouse_id" :label="__('Deliver to warehouse')" type="select" :options="$warehouses" :value="$po->warehouse_id" required />
            <x-field name="project_id" :label="__('For project (optional)')" type="select" :options="$projects" :placeholder="'— '.__('Stock replenishment').' —'" :value="$po->project_id" />
            <x-field name="order_date" :label="__('Order date')" type="date" :value="$po->order_date" required />
            <x-field name="expected_date" :label="__('Expected delivery')" type="date" :value="$po->expected_date" />
        </div>
    </div>
    <div class="form-section">
        <h4><span class="step">2</span>{{ __('Items') }}</h4>
        <div data-lines class="table-wrap">
            <table class="table lines">
                <thead><tr><th class="c-desc">{{ __('Item') }}</th><th class="c-uom">{{ __('UOM') }}</th><th class="c-qty num">{{ __('Qty') }}</th><th class="c-price num">{{ __('Unit price (RM)') }}</th><th class="c-amt">{{ __('Amount') }}</th><th class="c-del"></th></tr></thead>
                <tbody>
                @foreach($rows as $i => $l)
                    <tr>
                        <td class="c-desc">
                            <select name="lines[{{ $i }}][item_id]" data-item required class="input {{ $errors->has("lines.$i.item_id") ? 'has-error' : '' }}">
                                <option value="">— {{ __('Select item') }} —</option>
                                @foreach($items as $it)<option value="{{ $it->id }}" data-u="{{ $it->uom }}" data-cost="{{ (float) $it->unit_cost }}" @selected((string) ($l['item_id'] ?? '') === (string) $it->id)>{{ $it->sku }} · {{ $it->name }}</option>@endforeach
                            </select>
                            @error("lines.$i.item_id")<div class="error-text">{{ $message }}</div>@enderror
                        </td>
                        <td class="c-uom"><input class="input line-uom" readonly tabindex="-1"></td>
                        <td class="c-qty"><input class="input right" type="number" step="0.01" min="0.01" name="lines[{{ $i }}][qty]" value="{{ $l['qty'] ?? 1 }}" data-qty required></td>
                        <td class="c-price"><input class="input right" type="number" step="0.0001" min="0" name="lines[{{ $i }}][unit_price]" value="{{ $l['unit_price'] ?? '' }}" data-price required></td>
                        <td class="c-amt" data-amount>0.00</td>
                        <td class="c-del"><button type="button" class="btn btn-ghost btn-sm btn-icon" data-del-line title="{{ __('Remove line') }}"><x-icon name="trash" class="ico ico-sm" /></button></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <template>
                <tr>
                    <td class="c-desc"><select name="lines[__i__][item_id]" data-item required class="input"><option value="">— {{ __('Select item') }} —</option>@foreach($items as $it)<option value="{{ $it->id }}" data-u="{{ $it->uom }}" data-cost="{{ (float) $it->unit_cost }}">{{ $it->sku }} · {{ $it->name }}</option>@endforeach</select></td>
                    <td class="c-uom"><input class="input line-uom" readonly tabindex="-1"></td>
                    <td class="c-qty"><input class="input right" type="number" step="0.01" min="0.01" name="lines[__i__][qty]" value="1" data-qty required></td>
                    <td class="c-price"><input class="input right" type="number" step="0.0001" min="0" name="lines[__i__][unit_price]" data-price required></td>
                    <td class="c-amt" data-amount>0.00</td>
                    <td class="c-del"><button type="button" class="btn btn-ghost btn-sm btn-icon" data-del-line title="{{ __('Remove line') }}"><x-icon name="trash" class="ico ico-sm" /></button></td>
                </tr>
            </template>
            <div class="row between" style="margin-top:12px;align-items:flex-start">
                <button type="button" class="btn btn-ghost btn-sm" data-add-line><x-icon name="plus" class="ico ico-sm" />{{ __('Add line') }}</button>
                <table class="totals">
                    <tr class="grand"><td>{{ __('Total') }}</td><td>RM <span data-total>0.00</span></td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="form-section">
        <h4><span class="step">3</span>{{ __('Notes') }}</h4>
        <x-field name="notes" type="textarea" :value="$po->notes" :placeholder="__('Delivery instructions, site contact, special terms…')" />
    </div>
    <div class="form-actions">
        <a class="btn btn-ghost" href="{{ $po->exists ? route('purchase-orders.show', $po) : route('purchase-orders.index') }}">{{ __('Cancel') }}</a>
        <button class="btn btn-ghost" name="submit" value="draft"><x-icon name="document" class="ico ico-sm" />{{ __('Save as draft') }}</button>
        <button class="btn btn-primary" name="submit" value="order"><x-icon name="check" class="ico ico-sm" />{{ __('Save & mark ordered') }}</button>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('po-form');
    const prices = @json($priceMap);
    const supplier = form.querySelector('[name=supplier_id]');
    const priceFor = (opt) => {
        const map = prices[supplier.value] || {};
        return map[opt.value] !== undefined ? map[opt.value] : parseFloat(opt.dataset.cost || 0);
    };
    const fillRow = (sel, force) => {
        const tr = sel.closest('tr');
        const opt = sel.selectedOptions[0];
        tr.querySelector('.line-uom').value = opt && opt.value ? opt.dataset.u : '';
        const price = tr.querySelector('input[data-price]');
        if (opt && opt.value && (force || !parseFloat(price.value))) price.value = priceFor(opt);
    };
    form.addEventListener('change', (e) => {
        if (e.target.matches('select[data-item]')) { fillRow(e.target, true); form.dispatchEvent(new Event('input')); }
        if (e.target === supplier) {
            // New supplier → refresh line prices from that supplier's list (only where we have one).
            form.querySelectorAll('select[data-item]').forEach((s) => {
                const map = prices[supplier.value] || {};
                if (s.value && map[s.value] !== undefined) s.closest('tr').querySelector('input[data-price]').value = map[s.value];
            });
            form.dispatchEvent(new Event('input'));
        }
    });
    form.querySelectorAll('select[data-item]').forEach((s) => fillRow(s, false));
    form.dispatchEvent(new Event('input'));
})();
</script>
@endpush
