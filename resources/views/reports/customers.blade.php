@extends('layouts.app')
@section('title', __('Customer Report'))
@section('content')
<x-page-header :eyebrow="__('Customers').' · '.__('Reports')" :title="__('Customer Report')" :count="$rows->total()" :sub="__($by === 'inspection_date' ? 'Enquiries by inspection date, :from – :to, with their quotation / proforma / invoice trail.' : 'Enquiries by enquiry date, :from – :to, with their quotation / proforma / invoice trail.', ['from' => d($from), 'to' => d($to)])">
    <x-slot:actions>
        <a class="btn btn-ghost" href="{{ route('reports.salesman', request()->only('from', 'to', 'branch')) }}">{{ __('Salesman Report') }} <x-icon name="arrow-right" class="ico ico-sm" /></a>
        <a class="btn btn-ghost" href="{{ route('customers.index') }}"><x-icon name="arrow-left" class="ico ico-sm" />{{ __('Directory') }}</a>
    </x-slot:actions>
</x-page-header>

<div class="panel">
    <form method="get" class="filters">
        <div class="filter-row">
            <div class="field"><label>{{ __('From date') }}</label><input type="date" name="from" value="{{ $from->format('Y-m-d') }}"></div>
            <div class="field"><label>{{ __('To date') }}</label><input type="date" name="to" value="{{ $to->format('Y-m-d') }}"></div>
            <div class="field"><label>{{ __('Filter by') }}</label><select name="by"><option value="enquiry">{{ __('Enquiry date') }}</option><option value="inspection" @selected($by === 'inspection_date')>{{ __('Inspection date') }}</option></select></div>
            <div class="field"><label>{{ __('Salesman') }}</label><select name="salesman"><option value="">{{ __('All') }}</option>@foreach($salesmen as $id => $name)<option value="{{ $id }}" @selected(request('salesman') == $id)>{{ $name }}</option>@endforeach</select></div>
            <div class="field"><label>{{ __('Branch') }}</label><select name="branch"><option value="">{{ __('All') }}</option>@foreach($branches as $id => $name)<option value="{{ $id }}" @selected(request('branch') == $id)>{{ $name }}</option>@endforeach</select></div>
            <div class="field"><label>{{ __('Stage') }}</label><select name="stage"><option value="">{{ __('All') }}</option>@foreach(\App\Models\Enquiry::STAGES as $k => $v)<option value="{{ $k }}" @selected(request('stage') === $k)>{{ __($v) }}</option>@endforeach</select></div>
            <div class="actions">
                <a class="btn btn-ghost" href="{{ request()->fullUrlWithQuery(['export' => 'csv', 'page' => null]) }}"><x-icon name="download" class="ico ico-sm" />{{ __('Export CSV') }}</a>
                <button class="btn btn-primary"><x-icon name="search" class="ico ico-sm" />{{ __('Search') }}</button>
            </div>
        </div>
    </form>
    @if($rows->count())
        <div class="table-wrap">
            <table class="table compact">
                <thead><tr>
                    <th>{{ __('Enquiry date') }}</th><th>{{ __('Profile') }}</th><th>{{ __('Contact person') }}</th><th>{{ __('Contact no') }}</th><th>{{ __('Service') }}</th><th>{{ __('Site address') }}</th><th>{{ __('Inspection date') }}</th>
                    <th>{{ __('Salesman') }}</th><th>{{ __('Stage') }}</th><th>{{ __('Feedback') }}</th><th class="num">{{ __('Estimate') }}</th><th>{{ __('Quotation') }}</th><th>{{ __('Proforma') }}</th><th>{{ __('Invoice') }}</th>
                </tr></thead>
                <tbody>
                @foreach($rows as $e)
                    @php
                        $t = $e->trail;
                    @endphp
                    <tr data-href="{{ route('enquiries.show', $e) }}">
                        <td class="nowrap">{{ d($e->enquiry_date, 'Y-m-d') }}</td>
                        <td class="primary" style="min-width:150px">{{ $e->customer->name }}<span class="sub mono">{{ $e->customer->code }}</span></td>
                        <td>{{ $e->contact_person ?? '—' }}</td>
                        <td class="mono small nowrap">{{ $e->contact_no }}</td>
                        <td style="min-width:120px">{{ __($e->service) }}</td>
                        <td class="small" style="min-width:180px">{{ \Illuminate\Support\Str::limit($e->site_address, 60) }}</td>
                        <td class="nowrap">{{ d($e->inspection_date, 'Y-m-d') }}</td>
                        <td class="nowrap">{{ $e->salesman?->name ?? '—' }}</td>
                        <td><x-badge :status="$e->stage" /></td>
                        <td class="small" style="min-width:160px">{{ \Illuminate\Support\Str::limit($e->feedback, 60) ?: '—' }}</td>
                        <td class="num">{{ $e->estimate_amount ? money($e->estimate_amount, false) : '—' }}</td>
                        <td class="nowrap">@if($t['quotation'])<span class="code">{{ $t['quotation']->doc_no }}</span><span class="sub">{{ money($t['quotation']->total) }}</span>@else <span class="muted">—</span> @endif</td>
                        <td class="nowrap">@if($t['proforma'])<span class="code">{{ $t['proforma']->doc_no }}</span><span class="sub">{{ money($t['proforma']->total) }}</span>@else <span class="muted">—</span> @endif</td>
                        <td class="nowrap">@if($t['invoice'])<span class="code">{{ $t['invoice']->doc_no }}</span><span class="sub">{{ money($t['invoiced']) }}@if($t['invoiceCount'] > 1) · {{ __(':n inv.', ['n' => $t['invoiceCount']]) }}@endif</span>@else <span class="muted">—</span> @endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="panel-foot">{{ $rows->links() }}</div>
    @else
        <x-empty icon="chart" :title="__('No enquiries in this period')">{{ __('Try a wider date range.') }}</x-empty>
    @endif
</div>
@endsection
