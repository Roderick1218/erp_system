@extends('layouts.app')
@section('title', $e->doc_no)
@section('content')
@php
    $flow = ['new', 'inspection', 'quotation', 'negotiation', 'won'];
    $pos = array_search($e->stage, $flow, true);
    $canManage = auth()->user()->can('customers.manage');
    $canSales = auth()->user()->can('sales.manage');
@endphp
<x-page-header :eyebrow="__('Customers').' · '.__('Enquiries').' · '.$e->doc_no" :title="$e->customer->name.' — '.__($e->service)" :sub="d($e->enquiry_date).' · '.($e->salesman?->name ?? __('Unassigned')).' · '.($e->branch?->legal_name ?? '')">
    <x-slot:badge><x-badge :status="$e->stage" /></x-slot:badge>
    <x-slot:actions>
        @if($e->contact_no)<a class="btn btn-ghost" href="https://wa.me/{{ preg_replace('/\D/', '', $e->contact_no) }}" target="_blank" rel="noopener"><x-icon name="phone" class="ico ico-sm" />WhatsApp</a>@endif
        @if($canManage)<a class="btn btn-ghost" href="{{ route('enquiries.edit', $e) }}"><x-icon name="pencil" class="ico ico-sm" />{{ __('Edit') }}</a>@endif
        @if($canSales && ! in_array($e->stage, ['won', 'lost'], true))
            <a class="btn btn-primary" href="{{ route('quotations.create', ['enquiry_id' => $e->id]) }}"><x-icon name="document" class="ico ico-sm" />{{ __('Create quotation') }}</a>
        @endif
        @if($e->stage === 'won' && ! $e->project_id && auth()->user()->can('projects.manage'))
            <a class="btn btn-primary" href="{{ route('projects.create', ['enquiry_id' => $e->id]) }}"><x-icon name="briefcase" class="ico ico-sm" />{{ __('Convert to project') }}</a>
        @endif
    </x-slot:actions>
</x-page-header>

<div class="panel" style="margin-bottom:16px">
    <div class="panel-body">
        <div class="steps">
            @foreach($flow as $i => $s)
                @php
                    $cls = $e->stage === 'lost' ? ($i === 0 ? 'done' : '') : ($pos !== false && $i < $pos ? 'done' : ($i === $pos ? 'current' : ''));
                @endphp
                <div class="step {{ $cls }}">{{ __(\App\Models\Enquiry::STAGES[$s]) }}</div>
            @endforeach
            @if($e->stage === 'lost')<div class="step lost">{{ __('Lost') }}</div>@endif
        </div>
        @if($canManage && ! in_array($e->stage, ['won', 'lost'], true))
            <div class="row" style="margin-top:12px">
                <span class="small muted">{{ __('Move to:') }}</span>
                @foreach(\App\Models\Enquiry::STAGES as $k => $label)
                    @continue($k === $e->stage || $k === 'lost')
                    <form method="post" action="{{ route('enquiries.stage', $e) }}">@csrf @method('PATCH')<input type="hidden" name="stage" value="{{ $k }}"><button class="btn btn-ghost btn-sm">{{ __($label) }}</button></form>
                @endforeach
                <button type="button" class="btn btn-danger-ghost btn-sm" data-dialog="dlg-lost">{{ __('Mark lost…') }}</button>
            </div>
        @elseif($canManage)
            <div class="row" style="margin-top:12px">
                <form method="post" action="{{ route('enquiries.stage', $e) }}">@csrf @method('PATCH')<input type="hidden" name="stage" value="negotiation"><button class="btn btn-ghost btn-sm"><x-icon name="refresh" class="ico ico-sm" />{{ __('Re-open (back to Negotiation)') }}</button></form>
            </div>
        @endif
    </div>
</div>

<div class="grid g-main-side">
    <div class="stack">
        <div class="panel">
            <div class="panel-head"><h3><x-icon name="document" />{{ __('Quotations') }}</h3>
                @if($canSales && ! in_array($e->stage, ['won', 'lost'], true))<a class="btn btn-ghost btn-sm" href="{{ route('quotations.create', ['enquiry_id' => $e->id]) }}"><x-icon name="plus" class="ico ico-sm" />{{ __('New') }}</a>@endif
            </div>
            @if($e->quotations->count())
                <div class="table-wrap"><table class="table">
                    <thead><tr><th>{{ __('Doc no') }}</th><th>{{ __('Date') }}</th><th>{{ __('Valid until') }}</th><th>{{ __('Status') }}</th><th class="num">{{ __('Total') }}</th></tr></thead>
                    <tbody>@foreach($e->quotations as $qt)
                        <tr @if($canSales) data-href="{{ route('quotations.show', $qt) }}" @endif><td class="code">{{ $qt->doc_no }}</td><td>{{ d($qt->quote_date) }}</td><td>{{ d($qt->valid_until) }}</td><td><x-badge :status="$qt->display_status" /></td><td class="num">{{ money($qt->total) }}</td></tr>
                    @endforeach</tbody>
                </table></div>
            @else
                <x-empty icon="document" :title="__('No quotation yet')">{{ __('After the site inspection, prepare a quotation from here.') }}</x-empty>
            @endif
        </div>

        @if($e->project)
            <div class="panel">
                <div class="panel-head"><h3><x-icon name="briefcase" />{{ __('Project') }}</h3></div>
                <div class="table-wrap"><table class="table"><tbody>
                    <tr @can('projects.view') data-href="{{ route('projects.show', $e->project) }}" @endcan><td class="code">{{ $e->project->code }}</td><td class="primary">{{ $e->project->name }}</td>
                        <td><div class="progress-cell"><div class="progress" style="flex:1"><span style="width:{{ $e->project->progress }}%"></span></div><small>{{ $e->project->progress }}%</small></div></td>
                        <td><x-badge :status="$e->project->status" /></td></tr>
                </tbody></table></div>
            </div>
        @endif

        <div class="panel">
            <div class="panel-head"><h3><x-icon name="clock" />{{ __('History') }}</h3></div>
            <div class="panel-body">
                <ul class="feed">
                    @forelse($activity as $a)
                        <li><span class="dot"></span><div><b>{{ $a->causer?->name ?? __('System') }}</b> {{ __($a->description) }}
                            @if($a->event === 'updated' && isset($a->properties['attributes']['stage']))<div class="small muted">{{ __('Stage') }} → {{ __(\App\Models\Enquiry::STAGES[$a->properties['attributes']['stage']] ?? $a->properties['attributes']['stage']) }}</div>
                            @elseif($a->event === 'updated' && $a->properties->has('attributes'))<div class="small muted">{{ __('Changed:') }} {{ collect($a->properties['attributes'])->keys()->implode(', ') }}</div>@endif
                            <div class="when">{{ d($a->created_at, 'd M Y H:i') }}</div></div></li>
                    @empty
                        <li class="muted">{{ __('No changes recorded yet.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    <div class="stack">
        <div class="panel">
            <div class="panel-head"><h3><x-icon name="info" />{{ __('Details') }}</h3></div>
            <div class="panel-body">
                <dl class="dl">
                    <dt>{{ __('Doc no') }}</dt><dd class="mono">{{ $e->doc_no }}</dd>
                    <dt>{{ __('Customer') }}</dt><dd>@can('customers.view')<a href="{{ route('customers.show', $e->customer) }}">{{ $e->customer->name }}</a>@else{{ $e->customer->name }}@endcan<div class="small muted">{{ $e->customer->code }}</div></dd>
                    <dt>{{ __('Contact') }}</dt><dd>{{ $e->contact_person ?? '—' }}<div class="small muted">{{ $e->contact_no }}</div></dd>
                    <dt>{{ __('Service') }}</dt><dd>{{ __($e->service) }}</dd>
                    <dt>{{ __('Site address') }}</dt><dd>{{ $e->site_address ?? '—' }}<div class="small muted">{{ $e->state ? __($e->state) : '' }}</div></dd>
                    <dt>{{ __('Enquiry date') }}</dt><dd>{{ d($e->enquiry_date) }}</dd>
                    <dt>{{ __('Inspection') }}</dt><dd>{{ d($e->inspection_date) }}</dd>
                    <dt>{{ __('Salesman') }}</dt><dd>{{ $e->salesman?->name ?? '—' }}</dd>
                    <dt>{{ __('Estimate') }}</dt><dd>{{ $e->estimate_amount ? money($e->estimate_amount) : '—' }}</dd>
                    @if($e->stage === 'lost')<dt>{{ __('Lost reason') }}</dt><dd class="text-danger">{{ __($e->lost_reason) }}</dd>@endif
                </dl>
                @if($e->feedback)<hr><div class="label" style="margin-bottom:4px">{{ __('Feedback') }}</div><div class="small" style="white-space:pre-line">{{ $e->feedback }}</div>@endif
            </div>
        </div>
        @if($canManage)
            <form method="post" action="{{ route('enquiries.destroy', $e) }}" data-confirm="{{ __('Delete enquiry :no? This cannot be undone.', ['no' => $e->doc_no]) }}">
                @csrf @method('DELETE')
                <button class="btn btn-danger-ghost btn-block"><x-icon name="trash" class="ico ico-sm" />{{ __('Delete enquiry') }}</button>
            </form>
        @endif
    </div>
</div>

@if($canManage)
<dialog id="dlg-lost">
    <form method="post" action="{{ route('enquiries.stage', $e) }}">
        @csrf @method('PATCH')
        <input type="hidden" name="stage" value="lost">
        <div class="dlg-head"><h3>{{ __('Mark :no as lost', ['no' => $e->doc_no]) }}</h3><button type="button" class="icon-btn" data-close><x-icon name="x" /></button></div>
        <div class="dlg-body">
            <div class="field"><label>{{ __('Why did we lose it?') }} <span class="req">*</span></label>
                <select name="lost_reason" required>
                    <option value="">{{ __('— Select —') }}</option>
                    @foreach(['Price too high', 'Chose other contractor', 'Project postponed', 'Customer not responding', 'Out of service area', 'Other'] as $r)<option value="{{ $r }}">{{ __($r) }}</option>@endforeach
                </select>
            </div>
        </div>
        <div class="dlg-foot"><button type="button" class="btn btn-ghost" data-close>{{ __('Cancel') }}</button><button class="btn btn-danger">{{ __('Mark lost') }}</button></div>
    </form>
</dialog>
@endif
@endsection
