{{-- Line-item editor shared by quotations & invoices (public/js/app.js [data-lines]).
     Needs: $lines (array of rows), $items (stock items), $doc (model with discount / tax_rate). --}}
@php
    $rows = old('lines', $lines);
    $uoms = config('erp.uoms');
@endphp
<div data-lines>
    <div class="table-wrap">
        <table class="table lines">
            <thead><tr><th class="idx">#</th><th style="width:230px">{{ __('Stock item (optional)') }}</th><th class="c-desc">{{ __('Description') }} <span class="req" style="color:var(--danger)">*</span></th><th class="c-qty num" style="width:112px">{{ __('Qty') }}</th><th class="c-uom" style="width:104px">{{ __('UOM') }}</th><th class="c-price num">{{ __('Unit price') }}</th><th class="c-amt num">{{ __('Amount') }}</th><th class="c-del"></th></tr></thead>
            <tbody>
            @foreach($rows as $i => $l)
                <tr>
                    <td class="idx" style="padding-top:16px">{{ $loop->iteration }}</td>
                    <td style="min-width:200px;max-width:230px">
                        <select class="input" name="lines[{{ $i }}][item_id]" data-item>
                            <option value="">{{ __('— Service / custom —') }}</option>
                            @foreach($items as $it)<option value="{{ $it->id }}" data-name="{{ $it->name }}" data-item-uom="{{ $it->uom }}" data-item-price="{{ $it->selling_price }}" @selected((string) ($l['item_id'] ?? '') === (string) $it->id)>{{ $it->sku }} — {{ \Illuminate\Support\Str::limit($it->name, 40) }}</option>@endforeach
                        </select>
                    </td>
                    <td class="c-desc"><input class="input" name="lines[{{ $i }}][description]" value="{{ $l['description'] ?? '' }}" data-desc placeholder="{{ __('e.g. Labour & workmanship') }}"></td>
                    <td class="c-qty"><input class="input" name="lines[{{ $i }}][qty]" type="number" step="0.01" min="0.01" value="{{ isset($l['qty']) ? (float) $l['qty'] : 1 }}" data-qty style="text-align:right" required></td>
                    <td class="c-uom"><input class="input" name="lines[{{ $i }}][uom]" value="{{ $l['uom'] ?? 'LOT' }}" data-uom list="uom-list"></td>
                    <td class="c-price"><input class="input" name="lines[{{ $i }}][unit_price]" type="number" step="0.01" min="0" value="{{ isset($l['unit_price']) ? (float) $l['unit_price'] : '' }}" data-price style="text-align:right" required></td>
                    <td class="c-amt" data-amount>0.00</td>
                    <td class="c-del" style="padding-top:12px"><button type="button" class="btn btn-ghost btn-sm btn-icon" data-del-line title="{{ __('Remove line') }}"><x-icon name="trash" class="ico ico-sm" /></button></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <template>
        <tr>
            <td class="idx" style="padding-top:16px">+</td>
            <td style="min-width:200px;max-width:230px">
                <select class="input" name="lines[__i__][item_id]" data-item>
                    <option value="">{{ __('— Service / custom —') }}</option>
                    @foreach($items as $it)<option value="{{ $it->id }}" data-name="{{ $it->name }}" data-item-uom="{{ $it->uom }}" data-item-price="{{ $it->selling_price }}">{{ $it->sku }} — {{ \Illuminate\Support\Str::limit($it->name, 40) }}</option>@endforeach
                </select>
            </td>
            <td class="c-desc"><input class="input" name="lines[__i__][description]" data-desc placeholder="{{ __('e.g. Labour & workmanship') }}"></td>
            <td class="c-qty"><input class="input" name="lines[__i__][qty]" type="number" step="0.01" min="0.01" value="1" data-qty style="text-align:right" required></td>
            <td class="c-uom"><input class="input" name="lines[__i__][uom]" value="LOT" data-uom list="uom-list"></td>
            <td class="c-price"><input class="input" name="lines[__i__][unit_price]" type="number" step="0.01" min="0" data-price style="text-align:right" required></td>
            <td class="c-amt" data-amount>0.00</td>
            <td class="c-del" style="padding-top:12px"><button type="button" class="btn btn-ghost btn-sm btn-icon" data-del-line title="{{ __('Remove line') }}"><x-icon name="trash" class="ico ico-sm" /></button></td>
        </tr>
    </template>
    <datalist id="uom-list">@foreach($uoms as $u)<option value="{{ $u }}">@endforeach</datalist>
    @error('lines')<div class="error-text" style="padding:8px 16px">{{ $message }}</div>@enderror

    <div class="row between" style="padding:12px 16px;align-items:flex-start">
        <button type="button" class="btn btn-ghost btn-sm" data-add-line><x-icon name="plus" class="ico ico-sm" />{{ __('Add line') }}</button>
        <table class="totals">
            <tr><td>{{ __('Subtotal') }}</td><td data-subtotal>0.00</td></tr>
            <tr><td>{{ __('Discount (RM)') }}</td><td><input class="input" type="number" step="0.01" min="0" name="discount" value="{{ old('discount', (float) $doc->discount) }}"></td></tr>
            <tr><td>{{ __('SST / tax rate (%)') }}</td><td><input class="input" type="number" step="0.01" min="0" max="100" name="tax_rate" value="{{ old('tax_rate', (float) $doc->tax_rate) }}"></td></tr>
            <tr><td>{{ __('Tax') }}</td><td data-tax>0.00</td></tr>
            <tr class="grand"><td>{{ __('Total (RM)') }}</td><td data-total>0.00</td></tr>
        </table>
    </div>
    <p class="small muted" style="margin:0;padding:0 16px 12px">{{ __('Totals here are a preview — the server recalculates every amount when you save.') }}</p>
</div>

@once
@push('scripts')
<script>
    // Picking a stock item fills UOM and unit price (options carry data-item-* so they
    // never collide with the [data-uom]/[data-price] inputs the shared editor reads).
    document.addEventListener('change', function (e) {
        if (!e.target.matches('[data-lines] [data-item]')) return;
        const opt = e.target.selectedOptions[0];
        const tr = e.target.closest('tr');
        if (!opt || !opt.value || !tr) return;
        const uom = tr.querySelector('input[data-uom]');
        const price = tr.querySelector('input[data-price]');
        if (uom && opt.dataset.itemUom) uom.value = opt.dataset.itemUom;
        if (price && opt.dataset.itemPrice && !(parseFloat(price.value) > 0)) price.value = opt.dataset.itemPrice;
        price?.dispatchEvent(new Event('input', { bubbles: true }));
    });
</script>
@endpush
@endonce