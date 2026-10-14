@extends('layouts.app')
@section('title', __('Company Settings'))
@section('content')
<x-page-header :eyebrow="__('System').' · '.__('Settings')" :title="__('Company Settings')" :sub="__('Defaults used across HR, sales documents and attendance.')" />

<div data-tab-scope>
<div class="panel" style="margin-bottom:16px">
    <div class="tabs" data-tabs>
        <button data-tab="general" class="active">{{ __('General') }}</button>
        <button data-tab="branches">{{ __('Branches') }} <span class="n">{{ $branches->count() }}</span></button>
        <button data-tab="departments">{{ __('Departments') }} <span class="n">{{ $departments->count() }}</span></button>
        <button data-tab="leave-types">{{ __('Leave types') }} <span class="n">{{ $leaveTypes->count() }}</span></button>
    </div>
</div>

<form method="post" action="{{ route('settings.update') }}" class="panel active" data-tab-panel="general" data-main-form>
    @csrf @method('PUT')
    <div class="form-section">
        <div class="form-grid cols-3">
            @foreach($fields as $key => $f)
                <x-field :name="$key" :label="__($f['label'])" :type="$f['type']" :value="$f['value']" :hint="$f['hint'] ? __($f['hint']) : null" :step="$f['type'] === 'number' ? '0.01' : null" :class="$f['type'] === 'textarea' ? 'span-all' : ''" />
            @endforeach
        </div>
    </div>
    <div class="form-actions"><button class="btn btn-primary"><x-icon name="check" class="ico ico-sm" />{{ __('Save settings') }}</button></div>
</form>

<div class="panel" data-tab-panel="branches">
    <div class="panel-head"><h3><x-icon name="building" />{{ __('Branches / companies') }}</h3>
        <button class="btn btn-primary btn-sm" data-dialog="dlg-branches" data-title="{{ __('Add branch') }}" data-fill="{{ json_encode(['id' => '', 'code' => '', 'name' => '', 'legal_name' => '', 'reg_no' => '', 'phone' => '', 'address' => '']) }}"><x-icon name="plus" class="ico ico-sm" />{{ __('Add') }}</button></div>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Legal name') }}</th><th class="hide-sm">{{ __('Reg. no.') }}</th><th class="hide-sm">{{ __('Phone') }}</th><th class="num">{{ __('Employees') }}</th><th class="num">{{ __('Customers') }}</th><th class="actions"></th></tr></thead>
        <tbody>@foreach($branches as $b)
            <tr><td class="code">{{ $b->code }}</td><td class="primary">{{ $b->name }}</td><td>{{ $b->legal_name }}</td><td class="hide-sm small">{{ $b->reg_no }}</td><td class="hide-sm small">{{ $b->phone }}</td>
                <td class="num">{{ $b->employees_count }}</td><td class="num">{{ $b->customers_count }}</td>
                <td class="actions">
                    <button class="btn btn-ghost btn-sm" data-dialog="dlg-branches" data-title="{{ __('Edit branch') }}" data-fill="{{ json_encode($b->only('id', 'code', 'name', 'legal_name', 'reg_no', 'phone', 'address')) }}" title="{{ __('Edit') }}"><x-icon name="pencil" class="ico ico-sm" /></button>
                    <form method="post" action="{{ route('settings.lookup.destroy', ['branches', $b->id]) }}" data-confirm="{{ __('Delete branch :code?', ['code' => $b->code]) }}" style="display:inline">@csrf @method('DELETE')<button class="btn btn-danger-ghost btn-sm btn-icon" title="{{ __('Delete') }}"><x-icon name="trash" class="ico ico-sm" /></button></form>
                </td></tr>
        @endforeach</tbody>
    </table></div>
</div>

<div class="panel" data-tab-panel="departments">
    <div class="panel-head"><h3><x-icon name="users" />{{ __('Departments') }}</h3>
        <button class="btn btn-primary btn-sm" data-dialog="dlg-departments" data-title="{{ __('Add department') }}" data-fill="{{ json_encode(['id' => '', 'code' => '', 'name' => '']) }}"><x-icon name="plus" class="ico ico-sm" />{{ __('Add') }}</button></div>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th class="num">{{ __('Employees') }}</th><th class="actions"></th></tr></thead>
        <tbody>@foreach($departments as $d)
            <tr><td class="code">{{ $d->code }}</td><td class="primary">{{ __($d->name) }}</td><td class="num">{{ $d->employees_count }}</td>
                <td class="actions">
                    <button class="btn btn-ghost btn-sm" data-dialog="dlg-departments" data-title="{{ __('Edit department') }}" data-fill="{{ json_encode($d->only('id', 'code', 'name')) }}" title="{{ __('Edit') }}"><x-icon name="pencil" class="ico ico-sm" /></button>
                    <form method="post" action="{{ route('settings.lookup.destroy', ['departments', $d->id]) }}" data-confirm="{{ __('Delete department :name?', ['name' => __($d->name)]) }}" style="display:inline">@csrf @method('DELETE')<button class="btn btn-danger-ghost btn-sm btn-icon" title="{{ __('Delete') }}"><x-icon name="trash" class="ico ico-sm" /></button></form>
                </td></tr>
        @endforeach</tbody>
    </table></div>
</div>

<div class="panel" data-tab-panel="leave-types">
    <div class="panel-head"><h3><x-icon name="calendar" />{{ __('Leave types') }}</h3>
        <button class="btn btn-primary btn-sm" data-dialog="dlg-leave-types" data-title="{{ __('Add leave type') }}" data-fill="{{ json_encode(['id' => '', 'code' => '', 'name' => '', 'days_per_year' => 0, 'is_paid' => true, 'color' => '#2b7bb9']) }}"><x-icon name="plus" class="ico ico-sm" />{{ __('Add') }}</button></div>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th class="num">{{ __('Days / year') }}</th><th>{{ __('Paid') }}</th><th class="num">{{ __('Requests') }}</th><th class="actions"></th></tr></thead>
        <tbody>@foreach($leaveTypes as $t)
            <tr><td class="code">{{ $t->code }}</td><td class="primary"><span style="display:inline-block;width:10px;height:10px;border-radius:3px;background:{{ $t->color }};margin-right:6px"></span>{{ __($t->name) }}</td>
                <td class="num">{{ $t->days_per_year > 0 ? qty($t->days_per_year) : __('No limit') }}</td><td>@if($t->is_paid)<span class="badge badge-success plain">{{ __('Paid') }}</span>@else<span class="badge badge-neutral plain">{{ __('Unpaid') }}</span>@endif</td>
                <td class="num">{{ $t->requests_count }}</td>
                <td class="actions">
                    <button class="btn btn-ghost btn-sm" data-dialog="dlg-leave-types" data-title="{{ __('Edit leave type') }}" data-fill="{{ json_encode(['id' => $t->id, 'code' => $t->code, 'name' => $t->name, 'days_per_year' => (float) $t->days_per_year, 'is_paid' => $t->is_paid, 'color' => $t->color]) }}" title="{{ __('Edit') }}"><x-icon name="pencil" class="ico ico-sm" /></button>
                    <form method="post" action="{{ route('settings.lookup.destroy', ['leave-types', $t->id]) }}" data-confirm="{{ __('Delete leave type :name?', ['name' => __($t->name)]) }}" style="display:inline">@csrf @method('DELETE')<button class="btn btn-danger-ghost btn-sm btn-icon" title="{{ __('Delete') }}"><x-icon name="trash" class="ico ico-sm" /></button></form>
                </td></tr>
        @endforeach</tbody>
    </table></div>
    <div class="panel-foot small muted">{{ __('Unpaid leave types are deducted in payroll at basic salary ÷ 26 per day.') }}</div>
</div>
</div>

<dialog id="dlg-branches">
    <form method="post" action="{{ route('settings.lookup.store', 'branches') }}">
        @csrf <input type="hidden" name="id">
        <div class="dlg-head"><h3>{{ __('Branch') }}</h3><button type="button" class="icon-btn" data-close><x-icon name="x" /></button></div>
        <div class="dlg-body form-grid">
            <x-field name="code" :label="__('Code')" required /><x-field name="name" :label="__('Short name')" required />
            <x-field name="legal_name" :label="__('Legal name (Sdn Bhd)')" class="span-all" /><x-field name="reg_no" :label="__('Registration no.')" /><x-field name="phone" :label="__('Phone')" />
            <x-field name="address" :label="__('Address')" type="textarea" class="span-all" />
        </div>
        <div class="dlg-foot"><button type="button" class="btn btn-ghost" data-close>{{ __('Cancel') }}</button><button class="btn btn-primary">{{ __('Save') }}</button></div>
    </form>
</dialog>
<dialog id="dlg-departments">
    <form method="post" action="{{ route('settings.lookup.store', 'departments') }}">
        @csrf <input type="hidden" name="id">
        <div class="dlg-head"><h3>{{ __('Department') }}</h3><button type="button" class="icon-btn" data-close><x-icon name="x" /></button></div>
        <div class="dlg-body form-grid"><x-field name="code" :label="__('Code')" required /><x-field name="name" :label="__('Name')" required /></div>
        <div class="dlg-foot"><button type="button" class="btn btn-ghost" data-close>{{ __('Cancel') }}</button><button class="btn btn-primary">{{ __('Save') }}</button></div>
    </form>
</dialog>
<dialog id="dlg-leave-types">
    <form method="post" action="{{ route('settings.lookup.store', 'leave-types') }}">
        @csrf <input type="hidden" name="id">
        <div class="dlg-head"><h3>{{ __('Leave type') }}</h3><button type="button" class="icon-btn" data-close><x-icon name="x" /></button></div>
        <div class="dlg-body form-grid">
            <x-field name="code" :label="__('Code')" required /><x-field name="name" :label="__('Name')" required />
            <x-field name="days_per_year" :label="__('Days per year')" type="number" step="0.5" min="0" required :hint="__('0 = no fixed entitlement')" />
            <x-field name="color" :label="__('Colour')" type="color" required />
            <label class="check span-all"><input type="checkbox" name="is_paid" value="1" checked> {{ __('Paid leave') }}</label>
        </div>
        <div class="dlg-foot"><button type="button" class="btn btn-ghost" data-close>{{ __('Cancel') }}</button><button class="btn btn-primary">{{ __('Save') }}</button></div>
    </form>
</dialog>
@endsection
