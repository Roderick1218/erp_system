@extends('layouts.app')
@section('title', __('Warranty'))
@section('content')
<x-page-header :eyebrow="__('After-sales').' · '.__('Warranty')" :title="__('Warranty')" :count="$rows->total()" :sub="__('Certificates issued on completed projects — know what is still covered before sending a team.')">
    <x-slot:actions><a class="btn btn-primary" href="{{ route('warranties.create') }}"><x-icon name="plus" class="ico ico-sm" />{{ __('Issue warranty') }}</a></x-slot:actions>
</x-page-header>

<div class="panel">
    <div class="tabs">
        @foreach(['active' => __('Active'), 'expiring' => __('Expiring ≤ 60 days'), 'expired' => __('Expired'), 'void' => __('Void')] as $k => $label)
            <a href="{{ request()->fullUrlWithQuery(['tab' => $k, 'page' => null]) }}" class="{{ $tab === $k ? 'active' : '' }}">{{ $label }} <span class="n">{{ $counts[$k] }}</span></a>
        @endforeach
    </div>
    <form method="get" class="filters">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <div class="filter-row">
            <div class="field grow"><label>{{ __('Search') }}</label><input name="q" value="{{ request('q') }}" placeholder="{{ __('Warranty no, customer, phone, project code…') }}"></div>
            <div class="field"><label>{{ __('Service') }}</label><select name="service"><option value="">{{ __('All') }}</option>@foreach(config('erp.services') as $s)<option value="{{ $s }}" @selected(request('service') === $s)>{{ __($s) }}</option>@endforeach</select></div>
            <div class="field"><label>{{ __('Period') }}</label><select name="years"><option value="">{{ __('All') }}</option>@foreach([1, 2, 3, 5, 10] as $y)<option value="{{ $y }}" @selected(request('years') == $y)>{{ trans_choice(':n year|:n years', $y, ['n' => $y]) }}</option>@endforeach</select></div>
            <div class="actions">
                @if(request()->except('page', 'tab'))<a class="btn btn-ghost" href="{{ route('warranties.index', ['tab' => $tab]) }}">{{ __('Reset') }}</a>@endif
                <button class="btn btn-primary"><x-icon name="search" class="ico ico-sm" />{{ __('Search') }}</button>
            </div>
        </div>
    </form>
    @if($rows->count())
        <div class="table-wrap"><table class="table">
            <thead><tr><th>{{ __('Warranty no') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Service') }}</th><th class="hide-sm">{{ __('Project') }}</th><th>{{ __('Period') }}</th><th class="num">{{ __('Days left') }}</th><th>{{ __('Status') }}</th><th class="chev-cell"></th></tr></thead>
            <tbody>@foreach($rows as $w)
                @php($left = $w->days_left)
                <tr data-href="{{ route('warranties.show', $w) }}">
                    <td class="code">{{ $w->doc_no }}</td>
                    <td class="primary">{{ $w->customer->name }}<span class="sub">{{ $w->customer->phone }}</span></td>
                    <td>{{ __($w->service) }}</td>
                    <td class="hide-sm">@if($w->project)<span class="code">{{ $w->project->code }}</span>@else<span class="muted">—</span>@endif</td>
                    <td class="small nowrap">{{ d($w->start_date) }} → {{ d($w->end_date) }}<span class="sub">{{ trans_choice(':n year|:n years', $w->years, ['n' => $w->years]) }}</span></td>
                    <td class="num {{ $left < 0 ? 'text-danger' : ($left <= 60 ? 'text-warning' : '') }}"><b>{{ $left < 0 ? __('Expired') : number_format($left) }}</b></td>
                    <td><x-badge :status="$w->status === 'void' ? 'void' : ($left < 0 ? 'expired' : ($left <= 60 ? 'expiring' : 'active'))" /></td>
                    <td class="chev-cell"><x-icon name="chevron-right" class="ico ico-sm" /></td>
                </tr>
            @endforeach</tbody>
        </table></div>
        <div class="panel-foot">{{ $rows->links() }}</div>
    @else
        <x-empty icon="shield" :title="__('No warranties in this list')" />
    @endif
</div>
@endsection
