@extends('layouts.app')
@section('title', __('Suppliers'))
@section('content')
<x-page-header :eyebrow="__('Inventory').' · '.__('Suppliers')" :title="__('Suppliers')" :count="$rows->total()" :sub="__('Vendors, their payment terms and the latest unit price for each stock item.')">
    <x-slot:actions>
        @can('inventory.manage')<a class="btn btn-primary" href="{{ route('suppliers.create') }}"><x-icon name="plus" class="ico ico-sm" />{{ __('Add supplier') }}</a>@endcan
    </x-slot:actions>
</x-page-header>

<div class="panel">
    <div class="tabs">
        <a href="{{ request()->fullUrlWithQuery(['status' => null, 'page' => null]) }}" class="{{ request('status') ? '' : 'active' }}">{{ __('All') }} <span class="n">{{ $statusCounts->sum() }}</span></a>
        @foreach(['active' => 'Active', 'inactive' => 'Inactive'] as $k => $label)
            <a href="{{ request()->fullUrlWithQuery(['status' => $k, 'page' => null]) }}" class="{{ request('status') === $k ? 'active' : '' }}">{{ __($label) }} <span class="n">{{ $statusCounts[$k] ?? 0 }}</span></a>
        @endforeach
    </div>
    <form method="get" class="toolbar">
        <input type="hidden" name="status" value="{{ request('status') }}">
        <div class="search"><x-icon name="search" class="ico ico-sm" /><input class="input" name="q" value="{{ request('q') }}" placeholder="{{ __('Code, name, contact, phone…') }}"></div>
        <select name="terms" class="input" style="width:auto;height:34px" data-autosubmit><option value="">{{ __('All payment terms') }}</option>@foreach($terms as $t)<option value="{{ $t }}" @selected(request('terms') === $t)>{{ __($t) }}</option>@endforeach</select>
        <button class="btn btn-ghost btn-sm">{{ __('Search') }}</button>
        @if(request()->hasAny(['q', 'terms']))<a class="btn btn-ghost btn-sm" href="{{ route('suppliers.index', ['status' => request('status')]) }}">{{ __('Reset') }}</a>@endif
    </form>
    @if($rows->count())
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Supplier') }}</th><th class="hide-sm">{{ __('Phone / email') }}</th><th>{{ __('Terms') }}</th><th class="num">{{ __('Items priced') }}</th><th class="num">{{ __('Open POs') }}</th><th class="num hide-sm">{{ __('On order') }}</th><th>{{ __('Status') }}</th><th class="chev-cell"></th></tr></thead>
                <tbody>
                @foreach($rows as $s)
                    <tr data-href="{{ route('suppliers.show', $s) }}">
                        <td class="code">{{ $s->code }}</td>
                        <td class="primary">{{ $s->name }}@if($s->contact_person)<span class="sub">{{ __('Attn:') }} {{ $s->contact_person }}</span>@endif</td>
                        <td class="hide-sm small">{{ $s->phone ?? '—' }}@if($s->email)<span class="sub">{{ $s->email }}</span>@endif</td>
                        <td>@if($s->payment_terms)<span class="tag">{{ __($s->payment_terms) }}</span>@else — @endif</td>
                        <td class="num">{{ $itemCounts[$s->id] ?? 0 }}</td>
                        <td class="num">{{ $s->open_po_count ?: '—' }}</td>
                        <td class="num hide-sm">{{ $s->open_po_value ? money($s->open_po_value) : '—' }}</td>
                        <td><x-badge :status="$s->status" /></td>
                        <td class="chev-cell"><x-icon name="chevron-right" class="ico ico-sm" /></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="panel-foot">{{ $rows->links() }}</div>
    @else
        <x-empty icon="truck" :title="__('No suppliers match')"><a class="btn btn-ghost btn-sm" href="{{ route('suppliers.index') }}">{{ __('Clear filters') }}</a></x-empty>
    @endif
</div>
@endsection
