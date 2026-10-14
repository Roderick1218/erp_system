@extends('layouts.app')
@section('title', __('Costing Summary Report'))
@section('content')
@php($tCost = $rows->sum('cost'))
@php($tRev = $rows->sum('revenue'))
@php($maxCost = max(1, $rows->max('cost')))
@php($groupLabels = ['month' => __('Month'), 'category' => __('Cost category'), 'branch' => __('Branch')])
<x-page-header :eyebrow="__('Finance').' · '.__('Costing Summary')" :title="__('Costing Summary Report')" :sub="d($from).' – '.d($to).' · '.__('grouped by :group', ['group' => $groupLabels[$group]])">
    <x-slot:actions>
        <a class="btn btn-ghost" href="{{ route('costing.projects') }}"><x-icon name="table" class="ico ico-sm" />{{ __('Project costs') }}</a>
        <a class="btn btn-ghost" href="{{ route('costing.export', request()->query()) }}"><x-icon name="download" class="ico ico-sm" />{{ __('Export CSV') }}</a>
    </x-slot:actions>
</x-page-header>

<div class="kpis" style="margin-bottom:16px">
    <x-stat :label="__('Total cost')" :value="money($tCost)" icon="banknotes" tone="warning" />
    @if($group !== 'category')
        <x-stat :label="__('Revenue (invoiced)')" :value="money($tRev)" icon="receipt" tone="info" />
        <x-stat :label="__('Gross margin')" :value="money($tRev - $tCost)" icon="trending" :tone="$tRev - $tCost < 0 ? 'danger' : 'success'" :hint="$tRev > 0 ? __(':pct% of revenue', ['pct' => round(($tRev - $tCost) / $tRev * 100, 1)]) : null" />
    @endif
</div>

<div class="panel">
    <form method="get" class="filters">
        <div class="filter-row">
            <div class="field"><label>{{ __('From') }}</label><input type="date" name="from" value="{{ $from->toDateString() }}"></div>
            <div class="field"><label>{{ __('To') }}</label><input type="date" name="to" value="{{ $to->toDateString() }}"></div>
            <div class="field"><label>{{ __('Group by') }}</label><select name="group">@foreach($groupLabels as $k => $v)<option value="{{ $k }}" @selected($group === $k)>{{ $v }}</option>@endforeach</select></div>
            <div class="actions"><button class="btn btn-primary"><x-icon name="search" class="ico ico-sm" />{{ __('Run report') }}</button></div>
        </div>
    </form>

    @if($rows->count() && ($tCost > 0 || $tRev > 0))
        <div class="table-wrap">
            <table class="table">
                <thead><tr>
                    <th>{{ ['month' => __('Month'), 'category' => __('Category'), 'branch' => __('Branch')][$group] }}</th>
                    @if($group !== 'category')@foreach($categories as $c)<th class="num hide-sm">{{ __($c) }}</th>@endforeach @endif
                    <th class="num">{{ __('Total cost') }}</th><th style="min-width:160px" class="hide-sm">{{ __('Share of cost') }}</th>
                    @if($group !== 'category')<th class="num">{{ __('Revenue') }}</th><th class="num">{{ __('Gross margin') }}</th>@endif
                </tr></thead>
                <tbody>
                @foreach($rows as $r)
                    @php($label = $group === 'category' ? __($r['label']) : $r['label'])
                    <tr title="{{ $group !== 'category' ? __(':label: cost :cost · revenue :revenue', ['label' => $label, 'cost' => money($r['cost']), 'revenue' => money($r['revenue'])]) : __(':label: cost :cost', ['label' => $label, 'cost' => money($r['cost'])]) }}">
                        <td class="primary">{{ $label }}</td>
                        @if($group !== 'category')@foreach($categories as $c)<td class="num hide-sm {{ empty($r['cat'][$c]) ? 'muted' : '' }}">{{ empty($r['cat'][$c]) ? '—' : money($r['cat'][$c], false) }}</td>@endforeach @endif
                        <td class="num"><b>{{ money($r['cost'], false) }}</b></td>
                        <td class="hide-sm"><div class="progress-cell"><div class="progress" style="flex:1;height:10px"><span style="width:{{ $r['cost'] / $maxCost * 100 }}%;border-radius:0 4px 4px 0"></span></div><small>{{ $tCost > 0 ? round($r['cost'] / $tCost * 100) : 0 }}%</small></div></td>
                        @if($group !== 'category')
                            <td class="num">{{ money($r['revenue'], false) }}</td>
                            <td class="num {{ $r['margin'] < 0 ? 'text-danger' : '' }}">{{ money($r['margin'], false) }}@if($r['marginPct'] !== null)<span class="sub">{{ $r['marginPct'] }}%</span>@endif</td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
                <tfoot><tr>
                    <td>{{ __('Total') }}</td>
                    @if($group !== 'category')@foreach($categories as $c)<td class="num hide-sm">{{ money($rows->sum(fn ($r) => $r['cat'][$c] ?? 0), false) }}</td>@endforeach @endif
                    <td class="num">{{ money($tCost, false) }}</td><td class="hide-sm"></td>
                    @if($group !== 'category')<td class="num">{{ money($tRev, false) }}</td><td class="num">{{ money($tRev - $tCost, false) }}</td>@endif
                </tr></tfoot>
            </table>
        </div>
        <div class="panel-foot"><span class="small muted">{{ __('Cost = project cost lines by cost date. Revenue = issued invoices (excl. proforma & void) by invoice date.') }}@if($group === 'category') {{ __('Revenue is not split by cost category.') }}@endif</span></div>
    @else
        <x-empty icon="chart" :title="__('No costs or invoices in this period')" />
    @endif
</div>
@endsection
