{{-- Stock movement + transfer dialogs. Needs StockController::lookups() vars.
     Open with: <button data-dialog="dlg-stock" data-fill='{"type":"in","item_id":3}'>  --}}
@can('inventory.manage')
@if(session('reopen'))<span data-reopen-dialog="{{ session('reopen') }}" hidden></span>@endif

<dialog id="dlg-stock">
    <form method="post" action="{{ route('stock.store') }}">
        @csrf
        <div class="dlg-head"><h3>{{ __('Record stock movement') }}</h3><button type="button" class="icon-btn" data-close><x-icon name="x" /></button></div>
        <div class="dlg-body">
            <div class="segmented stock-types" style="margin-bottom:14px;display:flex">
                @foreach(['in' => [__('Stock in'), 'arrow-down'], 'out' => [__('Stock out'), 'arrow-up'], 'project' => [__('Issue to project'), 'briefcase'], 'adjust' => [__('Adjust'), 'refresh']] as $k => [$label, $icon])
                    <label style="flex:1;margin:0"><input type="radio" name="type" value="{{ $k }}" data-v="{{ $k }}" class="sr-only" @checked(old('type', 'in') === $k)>
                        <span class="seg-btn"><x-icon :name="$icon" class="ico ico-sm" />{{ $label }}</span></label>
                @endforeach
            </div>
            <div class="form-grid">
                <div class="field span-all {{ $errors->has('item_id') ? 'has-error' : '' }}">
                    <label>{{ __('Item') }} <span class="req">*</span></label>
                    <select name="item_id" required>
                        <option value="">— {{ __('Select item') }} —</option>
                        @foreach($dlgItems as $it)<option value="{{ $it->id }}" data-cost="{{ (float) $it->unit_cost }}" data-uom="{{ $it->uom }}" @selected(old('item_id') == $it->id)>{{ $it->sku }} · {{ $it->name }}</option>@endforeach
                    </select>
                    @error('item_id')<div class="error">{{ $message }}</div>@enderror
                </div>
                <x-field name="warehouse_id" :label="__('Warehouse')" type="select" :options="$dlgWarehouses->mapWithKeys(fn ($w) => [$w->id => $w->code.' · '.$w->name])->all()" :value="old('warehouse_id', $dlgWarehouses->first()?->id)" required />
                <x-field name="qty" :label="__('Quantity')" type="number" step="0.01" required :hint="__('Adjust: use a negative number to reduce stock.')" />
                <x-field name="unit_cost" :label="__('Unit cost (RM)')" type="number" step="0.0001" min="0" class="when-in" :hint="__('Leave blank to use the current average cost.')" />
                <x-field name="supplier_id" :label="__('Supplier')" type="select" :options="$dlgSuppliers->pluck('name', 'id')->all()" placeholder="—" class="when-in" />
                <x-field name="project_id" :label="__('Project')" type="select" :options="$dlgProjects->mapWithKeys(fn ($p) => [$p->id => $p->code.' · '.$p->name])->all()" :placeholder="'— '.__('Select project').' —'" class="when-project span-all" :hint="__('The material cost is booked to this project automatically.')" />
                <x-field name="reference" :label="__('Reference')" :placeholder="__('DO no., PO no., job ref…')" />
                <x-field name="moved_at" :label="__('Date & time')" type="datetime-local" :value="now()->format('Y-m-d\TH:i')" />
                <x-field name="remarks" :label="__('Remarks')" class="span-all" />
            </div>
        </div>
        <div class="dlg-foot"><button type="button" class="btn btn-ghost" data-close>{{ __('Cancel') }}</button><button class="btn btn-primary"><x-icon name="check" class="ico ico-sm" />{{ __('Save movement') }}</button></div>
    </form>
</dialog>

<dialog id="dlg-transfer">
    <form method="post" action="{{ route('stock.transfer') }}">
        @csrf
        <div class="dlg-head"><h3>{{ __('Transfer between warehouses') }}</h3><button type="button" class="icon-btn" data-close><x-icon name="x" /></button></div>
        <div class="dlg-body">
            <div class="form-grid">
                <x-field name="item_id" :label="__('Item')" type="select" :options="$dlgItems->mapWithKeys(fn ($i) => [$i->id => $i->sku.' · '.$i->name])->all()" :placeholder="'— '.__('Select item').' —'" required class="span-all" />
                <x-field name="from_warehouse_id" :label="__('From')" type="select" :options="$dlgWarehouses->mapWithKeys(fn ($w) => [$w->id => $w->code.' · '.$w->name])->all()" placeholder="—" required />
                <x-field name="to_warehouse_id" :label="__('To')" type="select" :options="$dlgWarehouses->mapWithKeys(fn ($w) => [$w->id => $w->code.' · '.$w->name])->all()" placeholder="—" required />
                <x-field name="qty" :label="__('Quantity')" type="number" step="0.01" min="0.01" required />
                <x-field name="reference" :label="__('Reference')" />
                <x-field name="remarks" :label="__('Remarks')" class="span-all" />
            </div>
        </div>
        <div class="dlg-foot"><button type="button" class="btn btn-ghost" data-close>{{ __('Cancel') }}</button><button class="btn btn-primary"><x-icon name="swap" class="ico ico-sm" />{{ __('Transfer') }}</button></div>
    </form>
</dialog>

@once
@push('head')
<style>
    .stock-types { padding: 3px; }
    .stock-types .seg-btn { display: flex; align-items: center; justify-content: center; gap: 6px; height: 32px; border-radius: 6px; font-size: 12.5px; font-weight: 500; color: var(--text-2); cursor: pointer; white-space: nowrap; }
    .stock-types input:checked + .seg-btn { background: var(--surface); color: var(--accent); box-shadow: var(--shadow-sm); }
    .stock-types input:focus-visible + .seg-btn { outline: 2px solid var(--accent); }
</style>
@endpush
@push('scripts')
<script>
(function () {
    const dlg = document.getElementById('dlg-stock');
    if (!dlg) return;
    const sync = () => {
        const type = (dlg.querySelector('input[name=type]:checked') || {}).value || 'in';
        dlg.querySelectorAll('.when-in').forEach((el) => el.classList.toggle('hidden', type !== 'in'));
        dlg.querySelectorAll('.when-project').forEach((el) => el.classList.toggle('hidden', type !== 'project'));
        const p = dlg.querySelector('[name=project_id]'); if (p) p.required = type === 'project';
        const q = dlg.querySelector('[name=qty]'); if (q) q.min = type === 'adjust' ? '' : '0.01';
    };
    dlg.addEventListener('change', sync);
    // data-fill sets the radio via .value on the first match only — fix it up after opening.
    document.addEventListener('click', (e) => {
        const o = e.target.closest('[data-dialog="dlg-stock"]');
        if (!o) return;
        const fill = o.dataset.fill ? JSON.parse(o.dataset.fill) : {};
        const t = fill.type || 'in';
        dlg.querySelectorAll('input[name=type]').forEach((r) => { r.value = r.dataset.v; r.checked = r.value === t; });
        sync();
    });
    sync();
})();
</script>
@endpush
@endonce
@endcan
