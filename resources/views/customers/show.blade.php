@extends('layouts.app')
@section('title', $c->name)
@section('content')
@php($outstanding = $invoices->whereIn('status', ['unpaid', 'partial'])->sum(fn ($i) => $i->balance))
<x-page-header :eyebrow="__('Customers').' · '.$c->code" :title="$c->name" :sub="trim(($c->contact_person ? __('Attn:').' '.$c->contact_person.' · ' : '').$c->phone.' · '.$c->city.', '.$c->state, ' ·,')">
    <x-slot:badge><x-badge :status="$c->status" /></x-slot:badge>
    <x-slot:actions>
        <a class="btn btn-ghost" href="https://wa.me/{{ preg_replace('/\D/', '', $c->phone) }}" target="_blank" rel="noopener"><x-icon name="phone" class="ico ico-sm" />WhatsApp</a>
        @can('customers.manage')
            <a class="btn btn-ghost" href="{{ route('customers.edit', $c) }}"><x-icon name="pencil" class="ico ico-sm" />{{ __('Edit') }}</a>
            <a class="btn btn-primary" href="{{ route('enquiries.create', ['customer_id' => $c->id]) }}"><x-icon name="plus" class="ico ico-sm" />{{ __('New enquiry') }}</a>
        @endcan
    </x-slot:actions>
</x-page-header>

<div class="grid g-main-side">
    <div class="panel" data-tab-scope>
        <div class="summary-bar" style="border-top:0;border-bottom:1px solid var(--border)">
            <div><small>{{ __('Enquiries') }}</small><strong>{{ $enquiries->count() }}</strong></div>
            <div><small>{{ __('Projects') }}</small><strong>{{ $projects->count() }}</strong></div>
            <div><small>{{ __('Total quoted') }}</small><strong>{{ money($quotations->sum('total')) }}</strong></div>
            <div><small>{{ __('Invoiced') }}</small><strong>{{ money($invoices->where('status', '!=', 'void')->sum('total')) }}</strong></div>
            <div><small>{{ __('Outstanding') }}</small><strong class="{{ $outstanding > 0 ? 'text-danger' : '' }}">{{ money($outstanding) }}</strong></div>
        </div>
        <div class="tabs" data-tabs>
            <button data-tab="enquiries" class="active">{{ __('Enquiries') }} <span class="n">{{ $enquiries->count() }}</span></button>
            <button data-tab="projects">{{ __('Projects') }} <span class="n">{{ $projects->count() }}</span></button>
            @can('sales.manage')
                <button data-tab="quotations">{{ __('Quotations') }} <span class="n">{{ $quotations->count() }}</span></button>
                <button data-tab="invoices">{{ __('Invoices') }} <span class="n">{{ $invoices->count() }}</span></button>
            @endcan
            <button data-tab="warranty">{{ __('Warranty & service') }} <span class="n">{{ $warranties->count() + $services->count() }}</span></button>
            <button data-tab="history">{{ __('History') }}</button>
        </div>

        <div data-tab-panel="enquiries" class="active table-wrap">
            @if($enquiries->count())
                <table class="table">
                    <thead><tr><th>{{ __('Doc no') }}</th><th>{{ __('Date') }}</th><th>{{ __('Service') }}</th><th class="hide-sm">{{ __('Salesman') }}</th><th>{{ __('Stage') }}</th><th class="num">{{ __('Estimate') }}</th></tr></thead>
                    <tbody>@foreach($enquiries as $e)
                        <tr data-href="{{ route('enquiries.show', $e) }}"><td class="code">{{ $e->doc_no }}</td><td>{{ d($e->enquiry_date) }}</td><td>{{ __($e->service) }}<span class="sub">{{ \Illuminate\Support\Str::limit($e->site_address, 50) }}</span></td><td class="hide-sm">{{ $e->salesman?->name }}</td><td><x-badge :status="$e->stage" /></td><td class="num">{{ $e->estimate_amount ? money($e->estimate_amount) : '—' }}</td></tr>
                    @endforeach</tbody>
                </table>
            @else <x-empty icon="funnel" :title="__('No enquiries yet')" /> @endif
        </div>

        <div data-tab-panel="projects" class="table-wrap">
            @if($projects->count())
                <table class="table">
                    <thead><tr><th>{{ __('Project') }}</th><th>{{ __('Dates') }}</th><th>{{ __('Progress') }}</th><th>{{ __('Status') }}</th><th class="num">{{ __('Contract') }}</th></tr></thead>
                    <tbody>@foreach($projects as $p)
                        <tr data-href="{{ route('projects.show', $p) }}"><td><span class="code">{{ $p->code }}</span><span class="sub">{{ $p->name }}</span></td><td class="small">{{ d($p->start_date) }} → {{ d($p->end_date) }}</td>
                            <td><div class="progress-cell"><div class="progress" style="flex:1"><span style="width:{{ $p->progress }}%"></span></div><small>{{ $p->progress }}%</small></div></td>
                            <td><x-badge :status="$p->status" /></td><td class="num">{{ money($p->contract_value) }}</td></tr>
                    @endforeach</tbody>
                </table>
            @else <x-empty icon="briefcase" :title="__('No projects yet')" /> @endif
        </div>

        @can('sales.manage')
            <div data-tab-panel="quotations" class="table-wrap">
                @if($quotations->count())
                    <table class="table"><thead><tr><th>{{ __('Doc no') }}</th><th>{{ __('Date') }}</th><th>{{ __('Valid until') }}</th><th>{{ __('Status') }}</th><th class="num">{{ __('Total') }}</th></tr></thead>
                        <tbody>@foreach($quotations as $qt)<tr data-href="{{ route('quotations.show', $qt) }}"><td class="code">{{ $qt->doc_no }}</td><td>{{ d($qt->quote_date) }}</td><td>{{ d($qt->valid_until) }}</td><td><x-badge :status="$qt->status" /></td><td class="num">{{ money($qt->total) }}</td></tr>@endforeach</tbody>
                    </table>
                @else <x-empty icon="document" :title="__('No quotations yet')" /> @endif
            </div>
            <div data-tab-panel="invoices" class="table-wrap">
                @if($invoices->count())
                    <table class="table"><thead><tr><th>{{ __('Doc no') }}</th><th>{{ __('Date') }}</th><th>{{ __('Due') }}</th><th>{{ __('Status') }}</th><th class="num">{{ __('Total') }}</th><th class="num">{{ __('Balance') }}</th></tr></thead>
                        <tbody>@foreach($invoices as $inv)<tr data-href="{{ route('invoices.show', $inv) }}"><td class="code">{{ $inv->doc_no }}</td><td>{{ d($inv->invoice_date) }}</td><td>{{ d($inv->due_date) }}</td><td><x-badge :status="$inv->is_overdue ? 'overdue' : $inv->status" /></td><td class="num">{{ money($inv->total) }}</td><td class="num">{{ money($inv->balance) }}</td></tr>@endforeach</tbody>
                    </table>
                @else <x-empty icon="receipt" :title="__('No invoices yet')" /> @endif
            </div>
        @endcan

        <div data-tab-panel="warranty" class="table-wrap">
            @if($warranties->count() || $services->count())
                <table class="table"><thead><tr><th>{{ __('Ref') }}</th><th>{{ __('What') }}</th><th>{{ __('Date') }}</th><th>{{ __('Status') }}</th></tr></thead>
                    <tbody>
                    @foreach($warranties as $w)<tr><td class="code">{{ $w->doc_no }}</td><td>{{ __('Warranty') }} — {{ __($w->service) }} ({{ __(':n yr', ['n' => $w->years]) }})</td><td>{{ d($w->start_date) }} → {{ d($w->end_date) }}</td><td><x-badge :status="$w->end_date->isPast() ? 'expired' : 'active'" /></td></tr>@endforeach
                    @foreach($services as $s)<tr><td class="code">{{ $s->doc_no }}</td><td>{{ $s->title }}<span class="sub">{{ $s->assignee?->display_name }}</span></td><td>{{ d($s->scheduled_at, 'd M Y H:i') }}</td><td><x-badge :status="$s->status" /></td></tr>@endforeach
                    </tbody>
                </table>
            @else <x-empty icon="shield" :title="__('No warranty or service records')" /> @endif
        </div>

        <div data-tab-panel="history" class="panel-body">
            <ul class="feed">
                @forelse($activity as $a)
                    <li><span class="dot"></span><div><b>{{ $a->causer?->name ?? __('System') }}</b> {{ __($a->description) }}
                        @if($a->event === 'updated' && $a->properties->has('attributes'))<div class="small muted">{{ __('Changed:') }} {{ collect($a->properties['attributes'])->keys()->implode(', ') }}</div>@endif
                        <div class="when">{{ d($a->created_at, 'd M Y H:i') }}</div></div></li>
                @empty
                    <li class="muted">{{ __('No changes recorded yet.') }}</li>
                @endforelse
            </ul>
        </div>
    </div>

    <div class="stack">
        <div class="panel">
            <div class="panel-head"><h3><x-icon name="user" />{{ __('Profile') }}</h3></div>
            <div class="panel-body">
                <dl class="dl">
                    <dt>{{ __('Code') }}</dt><dd class="mono">{{ $c->code }}</dd>
                    <dt>{{ __('Type') }}</dt><dd>{{ __(ucfirst($c->type)) }}</dd>
                    <dt>{{ __('Phone') }}</dt><dd>{{ $c->phone }}@if($c->phone2)<br>{{ $c->phone2 }}@endif</dd>
                    <dt>{{ __('Email') }}</dt><dd>{{ $c->email ?? '—' }}</dd>
                    <dt>{{ $c->document_type ?? __('Document') }}</dt><dd>{{ $c->document_id ?? '—' }}</dd>
                    <dt>{{ __('Address') }}</dt><dd>{{ $c->address }}@if($c->postcode)<br>{{ $c->postcode }} {{ $c->city }}, {{ $c->state }}@endif</dd>
                    <dt>{{ __('Branch') }}</dt><dd>{{ $c->branch?->legal_name ?? '—' }}</dd>
                    <dt>{{ __('Source') }}</dt><dd>{{ $c->source ? __($c->source) : '—' }}</dd>
                    <dt>{{ __('Created') }}</dt><dd>{{ d($c->created_at) }} @if($c->creator){{ __('by') }} {{ $c->creator->name }}@endif</dd>
                </dl>
                @if($c->notes)<hr><div class="small" style="white-space:pre-line">{{ $c->notes }}</div>@endif
            </div>
        </div>
        @can('customers.manage')
            <form method="post" action="{{ route('customers.destroy', $c) }}" data-confirm="{{ __('Delete customer :code? This cannot be undone.', ['code' => $c->code]) }}">
                @csrf @method('DELETE')
                <button class="btn btn-danger-ghost btn-block"><x-icon name="trash" class="ico ico-sm" />{{ __('Delete customer') }}</button>
            </form>
        @endcan
    </div>
</div>
@endsection
