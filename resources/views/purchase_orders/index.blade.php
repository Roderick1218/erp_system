@extends('layouts.app')
@section('title', __('Purchase Orders'))
@section('content')
<x-page-header :eyebrow="__('Inventory').' · '.__('Purchasing')" :title="__('Purchase Orders')" :count="$rows->total()" :sub="__('Order from suppliers, then receive goods straight into a warehouse.')">
    <x-slot:actions>
        <a class="btn btn-primary" href="{{ route('purchase-orders.create') }}"><x-icon name="plus" class="ico ico-sm" />{{ __('New PO') }}</a>
    </x-slot:actions>
</x-page-header>

<div class="grid g-4" style="margin-bottom:16px">
    <x-stat :label="__('Drafts')" :value="$statusCounts['draft'] ?? 0" icon="pencil" tone="neutral" :hint="__('Not yet sent to supplier')" :href="route('purchase-orders.index', ['status' => 'draft'])" />
    <x-stat :label="__('Awaiting delivery')" :value="($statusCounts['ordered'] ?? 0) + ($statusCounts['partial'] ?? 0)" icon="truck" tone="info" :hint="__(':amount on order', ['amount' => money($openValue)])" :href="route('purchase-orders.index', ['status' => 'open'])" />
    <x-stat :label="__('Late deliveries')" :value="$overdue" icon="exclamation" :tone="$overdue ? 'danger' : 'success'" :hint="__('Past expected date')" />
    <x-stat :label="__('Received')" :value="$statusCounts['received'] ?? 0" icon="check-badge" tone="success" :hint="__('Fully received')" :href="route('purchase-orders.index', ['status' => 'received'])" />
</div>

<div class="panel">
    <div class="tabs">
        <a href="{{ request()->fullUrlWithQuery(['status' => null, 'page' => null]) }}" class="{{ request('status') ? '' : 'active' }}">{{ __('All') }} <span class="n">{{ $statusCounts->sum() }}</span></a>
        @foreach(\App\Http\Controllers\PurchaseOrderController::STATUSES as $k => $label)
            <a href="{{ request()->fullUrlWithQuery(['status' => $k, 'page' => null]) }}" class="{{ request('status') === $k ? 'active' : '' }}">{{ __($label) }} <span class="n">{{ $statusCounts[$k] ?? 0 }}</span></a>
        @endforeach
    </div>
    <form method="get" class="filters">
        <input type="hidden" name="status" value="{{ request('status') }}">
        <div class="filter-row">
            <div class="field grow"><label>{{ __('Search') }}</label><input name="q" value="{{ request('q') }}" placeholder="{{ __('PO no, supplier, notes…') }}"></div>
            <div class="field"><label>{{ __('Supplier') }}</label><select name="supplier"><option value="">{{ __('All') }}</option>@foreach($suppliers as $id => $n)<option value="{{ $id }}" @selected(request('supplier') == $id)>{{ $n }}</option>@endforeach</select></div>
            <div class="field"><label>{{ __('Warehouse') }}</label><select name="warehouse"><option value="">{{ __('All') }}</option>@foreach($warehouses as $id => $n)<option value="{{ $id }}" @selected(request('warehouse') == $id)>{{ $n }}</option>@endforeach</select></div>
            <div class="field"><label>{{ __('From') }}</label><input type="date" name="from" value="{{ request('from') }}"></div>
            <div class="field"><label>{{ __('To') }}</label><input type="date" name="to" value="{{ request('to') }}"></div>
            <div class="actions">
                @if(request()->except('page', 'status'))<a class="btn btn-ghost" href="{{ route('purchase-orders.index', ['status' => request('status')]) }}">{{ __('Reset') }}</a>@endif
                <button class="btn btn-primary"><x-icon name="search" class="ico ico-sm" />{{ __('Search') }}</button>
            </div>
        </div>
    </form>
    @if($rows->count())
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>{{ __('PO no') }}</th><th>{{ __('Supplier') }}</th><th>{{ __('Order date') }}</th><th class="hide-sm">{{ __('Expected') }}</th><th class="hide-sm">{{ __('Deliver to') }}</th><th class="hide-sm">{{ __('Project') }}</th><th class="num">{{ __('Lines') }}</th><th class="num">{{ __('Total') }}</th><th>{{ __('Status') }}</th><th class="chev-cell"></th></tr></thead>
                <tbody>
                @foreach($rows as $po)
                    @php
                        $late = in_array($po->status, ['ordered', 'partial']) && $po->expected_date && $po->expected_date->lt(today());
                    @endphp
                    <tr data-href="{{ route('purchase-orders.show', $po) }}">
                        <td class="code">{{ $po->doc_no }}</td>
                        <td class="primary">{{ $po->supplier->name }}</td>
                        <td>{{ d($po->order_date) }}</td>
                        <td class="hide-sm {{ $late ? 'text-danger' : '' }}">{{ d($po->expected_date) }}@if($late)<span class="sub text-danger">{{ __('Late') }}</span>@endif</td>
                        <td class="hide-sm"><span class="tag">{{ $po->warehouse->code }}</span></td>
                        <td class="hide-sm small">{{ $po->project?->code ?? '—' }}</td>
                        <td class="num">{{ $po->lines_count }}</td>
                        <td class="num"><b>{{ money($po->total) }}</b></td>
                        <td><x-badge :status="$po->status" :label="isset(\App\Http\Controllers\PurchaseOrderController::STATUSES[$po->status]) ? __(\App\Http\Controllers\PurchaseOrderController::STATUSES[$po->status]) : null" /></td>
                        <td class="chev-cell"><x-icon name="chevron-right" class="ico ico-sm" /></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="panel-foot">{{ $rows->links() }}</div>
    @else
        <x-empty icon="document" :title="__('No purchase orders here')">
            <a class="btn btn-primary btn-sm" href="{{ route('purchase-orders.create') }}">{{ __('Create a purchase order') }}</a>
        </x-empty>
    @endif
</div>
@endsection
