@extends('layouts.app')
@section('title', __('Salesman Report'))
@section('content')
@php
    $maxWon = max(1, $rows->max('wonValue'));
    $overallWin = ($totals['won'] + $totals['lost']) ? round($totals['won'] / ($totals['won'] + $totals['lost']) * 100) : null;
@endphp
<x-page-header :eyebrow="__('Customers').' · '.__('Reports')" :title="__('Salesman Report')" :sub="__('Enquiry funnel per salesman, :from – :to.', ['from' => d($from), 'to' => d($to)])">
    <x-slot:actions>
        <a class="btn btn-ghost" href="{{ route('reports.customers', request()->only('from', 'to', 'branch')) }}"><x-icon name="arrow-left" class="ico ico-sm" />{{ __('Customer Report') }}</a>
    </x-slot:actions>
</x-page-header>

<div class="kpis" style="margin-bottom:16px">
    <x-stat :label="__('Enquiries')" :value="number_format($totals['enquiries'])" icon="funnel" tone="info" />
    <x-stat :label="__('Quotations sent')" :value="number_format($totals['quotes'])" icon="document" tone="neutral" :hint="__(':amount quoted', ['amount' => money($totals['quoteValue'])])" />
    <x-stat :label="__('Won')" :value="number_format($totals['won'])" icon="check-badge" tone="success" :hint="money($totals['wonValue'])" />
    <x-stat :label="__('Win rate')" :value="$overallWin !== null ? $overallWin.'%' : '—'" icon="trending" tone="warning" :hint="__(':n lost', ['n' => $totals['lost']])" />
</div>

<div class="panel">
    <form method="get" class="filters">
        <div class="filter-row">
            <div class="field"><label>{{ __('From date') }}</label><input type="date" name="from" value="{{ $from->format('Y-m-d') }}"></div>
            <div class="field"><label>{{ __('To date') }}</label><input type="date" name="to" value="{{ $to->format('Y-m-d') }}"></div>
            <div class="field"><label>{{ __('Branch') }}</label><select name="branch"><option value="">{{ __('All') }}</option>@foreach($branches as $id => $name)<option value="{{ $id }}" @selected(request('branch') == $id)>{{ $name }}</option>@endforeach</select></div>
            <div class="actions">
                <a class="btn btn-ghost" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}"><x-icon name="download" class="ico ico-sm" />{{ __('Export CSV') }}</a>
                <button class="btn btn-primary"><x-icon name="search" class="ico ico-sm" />{{ __('Search') }}</button>
            </div>
        </div>
    </form>
    @if($rows->count())
        <div class="table-wrap">
            <table class="table">
                <thead><tr>
                    <th>{{ __('Salesman') }}</th><th class="num">{{ __('Enquiries') }}</th><th class="num">{{ __('Inspected') }}</th><th class="num">{{ __('Quotations') }}</th><th class="num">{{ __('Quoted') }}</th>
                    <th class="num">{{ __('Won') }}</th><th class="num">{{ __('Lost') }}</th><th class="num">{{ __('Open') }}</th><th class="num">{{ __('Win rate') }}</th><th class="num" title="{{ __('Average days from enquiry to first quotation sent') }}">{{ __('Days to quote') }}</th>
                    <th style="min-width:200px">{{ __('Won value') }}</th>
                </tr></thead>
                <tbody>
                @foreach($rows as $r)
                    <tr title="{{ __(':name: won :amount from :n enquiries', ['name' => $r['name'], 'amount' => money($r['wonValue']), 'n' => $r['enquiries']]) }}">
                        <td class="primary"><span class="row" style="gap:8px;flex-wrap:nowrap"><span class="avatar sm">{{ collect(preg_split('/\s+/', $r['name']))->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode('') }}</span>
                            @if($r['id'])<a href="{{ route('enquiries.index', ['salesman' => $r['id'], 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]) }}">{{ $r['name'] }}</a>@else{{ $r['name'] }}@endif</span></td>
                        <td class="num">{{ $r['enquiries'] }}</td>
                        <td class="num">{{ $r['inspected'] }}</td>
                        <td class="num">{{ $r['quotes'] }}</td>
                        <td class="num">{{ money($r['quoteValue'], false) }}</td>
                        <td class="num"><b>{{ $r['won'] }}</b></td>
                        <td class="num">{{ $r['lost'] }}</td>
                        <td class="num">{{ $r['open'] }}</td>
                        <td class="num">{{ $r['winRate'] !== null ? $r['winRate'].'%' : '—' }}</td>
                        <td class="num">{{ $r['daysToQuote'] ?? '—' }}</td>
                        <td><div class="progress-cell"><div class="progress" style="flex:1;height:10px"><span style="width:{{ $r['wonValue'] / $maxWon * 100 }}%;border-radius:0 4px 4px 0"></span></div><small style="width:auto;min-width:86px">{{ money($r['wonValue'], false) }}</small></div></td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot><tr><td>{{ __('Total') }}</td><td class="num">{{ $totals['enquiries'] }}</td><td></td><td class="num">{{ $totals['quotes'] }}</td><td class="num">{{ money($totals['quoteValue'], false) }}</td><td class="num">{{ $totals['won'] }}</td><td class="num">{{ $totals['lost'] }}</td><td></td><td class="num">{{ $overallWin !== null ? $overallWin.'%' : '—' }}</td><td></td><td>{{ money($totals['wonValue']) }}</td></tr></tfoot>
            </table>
        </div>
        <div class="panel-foot small muted"><span>{{ __('Win rate = won ÷ (won + lost) for enquiries received in the period. “Days to quote” = average days from enquiry to the first quotation sent.') }}</span></div>
    @else
        <x-empty icon="chart" :title="__('No sales activity in this period')">{{ __('Try a wider date range.') }}</x-empty>
    @endif
</div>
@endsection
