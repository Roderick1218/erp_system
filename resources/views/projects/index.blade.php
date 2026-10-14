@extends('layouts.app')
@section('title', __('Project List'))
@section('content')
@include('projects._nav', ['active' => 'list', 'title' => __('Project List'), 'count' => $rows->total(), 'sub' => __('All jobs from confirmed quotations — schedule, progress and cost at a glance.')])

<div class="panel">
    <div class="tabs">
        <a href="{{ request()->fullUrlWithQuery(['status' => null, 'page' => null]) }}" class="{{ request('status') ? '' : 'active' }}">{{ __('All') }} <span class="n">{{ $statusCounts->sum() }}</span></a>
        @foreach(\App\Models\Project::STATUSES as $k => $label)
            <a href="{{ request()->fullUrlWithQuery(['status' => $k, 'page' => null]) }}" class="{{ request('status') === $k ? 'active' : '' }}">{{ __($label) }} <span class="n">{{ $statusCounts[$k] ?? 0 }}</span></a>
        @endforeach
    </div>
    @include('projects._filters', ['keepView' => 'list'])

    @if($rows->count())
        <div class="table-wrap">
            <table class="table">
                <thead><tr>
                    <th class="idx">#</th><th>{{ __('Project') }}</th><th>{{ __('Customer') }}</th><th class="hide-sm">{{ __('Schedule') }}</th><th style="min-width:130px">{{ __('Progress') }}</th><th>{{ __('Status') }}</th>
                    <th class="num">{{ __('Contract') }}</th><th class="num hide-sm">{{ __('Actual cost') }}</th><th class="num hide-sm">{{ __('Margin') }}</th><th class="chev-cell"></th>
                </tr></thead>
                <tbody>
                @foreach($rows as $i => $p)
                    @php($actual = (float) $p->costs_sum_amount)
                    @php($margin = (float) $p->contract_value - $actual)
                    <tr data-href="{{ route('projects.show', $p) }}">
                        <td class="idx">{{ $rows->firstItem() + $i }}</td>
                        <td><span class="code">{{ $p->code }}</span>@if($p->category !== 'normal') <span class="tag">{{ __(ucfirst($p->category)) }}</span>@endif<span class="sub" style="color:var(--text);font-weight:600">{{ $p->name }}</span></td>
                        <td>{{ $p->customer->name }}<span class="sub">{{ __($p->state) }} · {{ $p->manager?->name ?? __('No manager') }}</span></td>
                        <td class="hide-sm small nowrap">{{ d($p->start_date, 'd M') }} → {{ d($p->end_date, 'd M Y') }}</td>
                        <td><div class="progress-cell"><div class="progress" style="flex:1"><span style="width:{{ $p->progress }}%"></span></div><small>{{ $p->progress }}%</small></div></td>
                        <td><x-badge :status="$p->status" /></td>
                        <td class="num">{{ money($p->contract_value) }}</td>
                        <td class="num hide-sm">{{ money($actual) }}@if($p->budget_cost > 0)<span class="sub {{ $actual > $p->budget_cost ? 'text-danger' : '' }}">{{ __(':pct% of budget', ['pct' => round($actual / $p->budget_cost * 100)]) }}</span>@endif</td>
                        <td class="num hide-sm {{ $margin < 0 ? 'text-danger' : '' }}">{{ money($margin) }}@if($p->contract_value > 0)<span class="sub">{{ round($margin / $p->contract_value * 100) }}%</span>@endif</td>
                        <td class="chev-cell"><x-icon name="chevron-right" class="ico ico-sm" /></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="panel-foot">{{ $rows->links() }}</div>
    @else
        <x-empty icon="briefcase" :title="__('No projects match these filters')">
            <a class="btn btn-ghost btn-sm" href="{{ route('projects.index') }}">{{ __('Clear filters') }}</a>
        </x-empty>
    @endif
</div>
@endsection
