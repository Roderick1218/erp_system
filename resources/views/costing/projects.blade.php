@extends('layouts.app')
@section('title', __('Project Cost Management'))
@section('content')
<x-page-header :eyebrow="__('Finance').' · '.__('Project Cost')" :title="__('Project Cost Management')" :count="$rows->count()" :sub="__('Budget vs actual cost for every project, split by cost category.')">
    <x-slot:actions>
        <a class="btn btn-ghost" href="{{ route('costing.summary') }}"><x-icon name="chart" class="ico ico-sm" />{{ __('Costing Summary Report') }}</a>
    </x-slot:actions>
</x-page-header>

<div class="kpis" style="margin-bottom:16px">
    <x-stat :label="__('Contract value')" :value="money($totals['contract'])" icon="document" tone="info" :hint="__(':n projects', ['n' => $rows->count()])" />
    <x-stat :label="__('Budget cost')" :value="money($totals['budget'])" icon="clipboard" tone="neutral" />
    <x-stat :label="__('Actual cost')" :value="money($totals['actual'])" icon="banknotes" :tone="$totals['actual'] > $totals['budget'] ? 'danger' : 'warning'" :hint="$totals['budget'] > 0 ? __(':pct% of budget', ['pct' => round($totals['actual'] / $totals['budget'] * 100)]) : null" />
    <x-stat :label="__('Gross margin')" :value="money($totals['margin'])" icon="trending" :tone="$totals['margin'] < 0 ? 'danger' : 'success'" :hint="$totals['contract'] > 0 ? __(':pct% of contract', ['pct' => round($totals['margin'] / $totals['contract'] * 100, 1)]) : null" />
    <x-stat :label="__('Over budget')" :value="$overCount" icon="exclamation" :tone="$overCount ? 'danger' : 'success'" :hint="__('Projects above budget')" :href="request()->fullUrlWithQuery(['flag' => 'over'])" />
</div>

<div class="panel">
    <form method="get" class="filters">
        <div class="filter-row">
            <div class="field grow"><label>{{ __('Search') }}</label><input name="q" value="{{ request('q') }}" placeholder="{{ __('Customer name, mobile, project code or invoice no…') }}"></div>
            <div class="field"><label>{{ __('Branch') }}</label><select name="branch"><option value="">{{ __('All') }}</option>@foreach($branches as $id => $name)<option value="{{ $id }}" @selected(request('branch') == $id)>{{ $name }}</option>@endforeach</select></div>
            <div class="field"><label>{{ __('Status') }}</label><select name="status"><option value="">{{ __('All (excl. cancelled)') }}</option>@foreach(\App\Models\Project::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ __($v) }}</option>@endforeach</select></div>
            <div class="field"><label>{{ __('Show') }}</label><select name="flag"><option value="">{{ __('All projects') }}</option><option value="over" @selected(request('flag') === 'over')>{{ __('Over budget only') }}</option></select></div>
            <div class="actions">
                @if(request()->except('page'))<a class="btn btn-ghost" href="{{ route('costing.projects') }}">{{ __('Reset') }}</a>@endif
                <button class="btn btn-primary"><x-icon name="search" class="ico ico-sm" />{{ __('Search') }}</button>
            </div>
        </div>
    </form>

    @if($rows->count())
        <div class="table-wrap">
            <table class="table compact">
                <thead><tr>
                    <th>{{ __('Project') }}</th><th class="num">{{ __('Contract') }}</th><th class="num">{{ __('Budget') }}</th>
                    @foreach($categories as $c)<th class="num hide-sm">{{ __($c) }}</th>@endforeach
                    <th class="num">{{ __('Actual') }}</th><th style="min-width:140px">{{ __('Budget used') }}</th><th class="num">{{ __('Margin') }}</th>
                </tr></thead>
                <tbody>
                @foreach($rows as $r)
                    <tr @can('projects.view') data-href="{{ route('projects.show', $r['p']) }}" @endcan>
                        <td><span class="code">{{ $r['p']->code }}</span> <x-badge :status="$r['p']->status" /><span class="sub" style="color:var(--text)">{{ $r['p']->customer->name }}</span><span class="sub">{{ \Illuminate\Support\Str::limit($r['p']->name, 40) }}</span></td>
                        <td class="num">{{ money($r['p']->contract_value, false) }}</td>
                        <td class="num">{{ money($r['p']->budget_cost, false) }}</td>
                        @foreach($categories as $c)<td class="num hide-sm {{ empty($r['cat'][$c]) ? 'muted' : '' }}">{{ empty($r['cat'][$c]) ? '—' : money($r['cat'][$c], false) }}</td>@endforeach
                        <td class="num"><b>{{ money($r['actual'], false) }}</b></td>
                        <td>
                            @if($r['budgetPct'] !== null)
                                <div class="progress-cell"><div class="progress {{ $r['budgetPct'] > 100 ? 'over' : '' }}" style="flex:1"><span style="width:{{ min(100, $r['budgetPct']) }}%"></span></div><small class="{{ $r['budgetPct'] > 100 ? 'text-danger' : '' }}">{{ $r['budgetPct'] }}%</small></div>
                            @else <span class="muted small">{{ __('No budget') }}</span> @endif
                        </td>
                        <td class="num {{ $r['margin'] < 0 ? 'text-danger' : '' }}">{{ money($r['margin'], false) }}@if($r['marginPct'] !== null)<span class="sub">{{ $r['marginPct'] }}%</span>@endif</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot><tr>
                    <td>{{ __('Total') }} ({{ $rows->count() }})</td><td class="num">{{ money($totals['contract'], false) }}</td><td class="num">{{ money($totals['budget'], false) }}</td>
                    @foreach($categories as $c)<td class="num hide-sm">{{ money($totals['cat'][$c], false) }}</td>@endforeach
                    <td class="num">{{ money($totals['actual'], false) }}</td>
                    <td>{{ $totals['budget'] > 0 ? round($totals['actual'] / $totals['budget'] * 100).'%' : '' }}</td>
                    <td class="num {{ $totals['margin'] < 0 ? 'text-danger' : '' }}">{{ money($totals['margin'], false) }}</td>
                </tr></tfoot>
            </table>
        </div>
        <div class="panel-foot"><span class="small muted">{{ __("All amounts in RM. Material lines come from stock issued to the project; other lines are entered on the project's Costs tab.") }}</span></div>
    @else
        <x-empty icon="chart" :title="__('No projects match these filters')"><a class="btn btn-ghost btn-sm" href="{{ route('costing.projects') }}">{{ __('Clear filters') }}</a></x-empty>
    @endif
</div>
@endsection
