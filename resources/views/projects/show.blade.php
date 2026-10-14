@extends('layouts.app')
@section('title', $p->code)
@section('content')
@php($canManage = auth()->user()->can('projects.manage'))
@php($margin = (float) $p->contract_value - $actual)
@php($budgetPct = $p->budget_cost > 0 ? round($actual / $p->budget_cost * 100) : null)
@php($catOptions = collect(\App\Models\ProjectCost::CATEGORIES)->mapWithKeys(fn ($c) => [$c => __($c)])->all())
@php($taskStatuses = ['todo' => __('To do'), 'doing' => __('Doing'), 'done' => __('Done')])
<x-page-header :eyebrow="__('Project').' · '.$p->code" :title="$p->name" :sub="$p->customer->name.' · '.($p->site_address ?? __($p->state))">
    <x-slot:badge><x-badge :status="$p->status" />@if($p->category !== 'normal')<span class="count-chip">{{ __(ucfirst($p->category)) }}</span>@endif</x-slot:badge>
    <x-slot:actions>
        @if($p->site_address)<a class="btn btn-ghost" target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&query={{ urlencode($p->site_address) }}"><x-icon name="map-pin" class="ico ico-sm" />{{ __('Map') }}</a>@endif
        @if($canManage)
            <div class="dropdown">
                <button type="button" class="btn btn-ghost" data-dropdown><x-icon name="refresh" class="ico ico-sm" />{{ __('Status') }}</button>
                <div class="dropdown-menu">
                    <div class="head">{{ __('Move to') }}</div>
                    @foreach(\App\Models\Project::STATUSES as $k => $label)
                        @continue($k === $p->status)
                        <form method="post" action="{{ route('projects.status', $p) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="{{ $k }}"><button type="submit"><x-badge :status="$k" :label="__($label)" /></button></form>
                    @endforeach
                </div>
            </div>
            <a class="btn btn-ghost" href="{{ route('projects.edit', $p) }}"><x-icon name="pencil" class="ico ico-sm" />{{ __('Edit') }}</a>
        @endif
        @can('sales.manage')<a class="btn btn-primary" href="{{ route('invoices.create', ['project_id' => $p->id]) }}"><x-icon name="receipt" class="ico ico-sm" />{{ __('Invoice') }}</a>@endcan
    </x-slot:actions>
</x-page-header>

<div class="panel" data-tab-scope>
    <div class="summary-bar" style="border-top:0;border-bottom:1px solid var(--border)">
        <div><small>{{ __('Progress') }}</small><strong>{{ $p->progress }}%</strong><div class="progress" style="margin-top:6px"><span style="width:{{ $p->progress }}%"></span></div></div>
        <div><small>{{ __('Contract value') }}</small><strong>{{ money($p->contract_value) }}</strong></div>
        <div><small>{{ __('Budget cost') }}</small><strong>{{ money($p->budget_cost) }}</strong></div>
        <div><small>{{ __('Actual cost') }}</small><strong class="{{ $budgetPct !== null && $budgetPct > 100 ? 'text-danger' : '' }}">{{ money($actual) }}</strong>@if($budgetPct !== null)<small>{{ __(':pct% of budget', ['pct' => $budgetPct]) }}</small>@endif</div>
        <div><small>{{ __('Margin') }}</small><strong class="{{ $margin < 0 ? 'text-danger' : 'text-success' }}">{{ money($margin) }}</strong>@if($p->contract_value > 0)<small>{{ round($margin / $p->contract_value * 100, 1) }}%</small>@endif</div>
        <div><small>{{ __('Invoiced / collected') }}</small><strong>{{ money($invoiced) }}</strong><small>{{ __(':amount received', ['amount' => money($collected)]) }}</small></div>
    </div>
    <div class="tabs" data-tabs>
        <button data-tab="overview" class="active">{{ __('Overview') }}</button>
        <button data-tab="tasks">{{ __('Tasks') }} <span class="n">{{ $p->tasks->where('status', 'done')->count() }}/{{ $p->tasks->count() }}</span></button>
        <button data-tab="costs">{{ __('Costs') }} <span class="n">{{ $p->costs->count() }}</span></button>
        <button data-tab="stock">{{ __('Stock used') }} <span class="n">{{ $movements->count() }}</span></button>
        <button data-tab="documents">{{ __('Documents') }} <span class="n">{{ $quotations->count() + $invoices->count() + $warranties->count() + $services->count() }}</span></button>
        <button data-tab="history">{{ __('History') }}</button>
    </div>

    {{-- ================= Overview ================= --}}
    <div data-tab-panel="overview" class="active panel-body">
        <div class="grid g-main-side">
            <div>
                <h4 style="font-size:13px;margin-bottom:10px">{{ __('Progress updates') }}</h4>
                @if($canManage)
                    <form method="post" action="{{ route('projects.updates.store', $p) }}" class="panel" style="margin-bottom:14px">
                        @csrf
                        <div class="panel-body stack" style="padding:12px">
                            <x-field name="note" type="textarea" :placeholder="__('What happened on site today? Weather, materials, issues…')" required />
                            <div class="row between">
                                <label class="row small" style="gap:8px;flex:1;min-width:220px">{{ __('Progress') }}
                                    <input type="range" name="progress" min="0" max="100" step="5" value="{{ old('progress', $p->progress) }}" style="flex:1;accent-color:var(--accent)" oninput="this.nextElementSibling.textContent=this.value+'%'">
                                    <b class="mono" style="width:40px">{{ old('progress', $p->progress) }}%</b>
                                </label>
                                <button class="btn btn-primary btn-sm"><x-icon name="check" class="ico ico-sm" />{{ __('Post update') }}</button>
                            </div>
                        </div>
                    </form>
                @endif
                <ul class="feed">
                    @forelse($p->updates->sortByDesc('created_at') as $u)
                        <li><span class="dot"></span><div style="min-width:0;flex:1">
                            <div class="row between"><b>{{ $u->user?->name ?? __('System') }}</b>@if(! is_null($u->progress))<span class="tag">{{ $u->progress }}%</span>@endif</div>
                            <div style="white-space:pre-line">{{ $u->note }}</div>
                            <div class="when">{{ d($u->created_at, 'd M Y H:i') }} · {{ $u->created_at->diffForHumans() }}</div>
                        </div></li>
                    @empty
                        <li class="muted">{{ __('No progress updates yet.') }}</li>
                    @endforelse
                </ul>
            </div>
            <div class="panel">
                <div class="panel-head"><h3><x-icon name="briefcase" />{{ __('Details') }}</h3></div>
                <div class="panel-body">
                    <dl class="dl">
                        <dt>{{ __('Code') }}</dt><dd class="mono">{{ $p->code }}</dd>
                        <dt>{{ __('Customer') }}</dt><dd>@can('customers.view')<a href="{{ route('customers.show', $p->customer) }}">{{ $p->customer->name }}</a>@else{{ $p->customer->name }}@endcan<div class="small muted">{{ $p->customer->phone }}</div></dd>
                        <dt>{{ __('Service') }}</dt><dd>{{ $p->service ? __($p->service) : '—' }}</dd>
                        <dt>{{ __('Site') }}</dt><dd>{{ $p->site_address ?? '—' }}@if($p->state)<div class="small muted">{{ __($p->state) }}</div>@endif</dd>
                        <dt>{{ __('Schedule') }}</dt><dd>{{ d($p->start_date) }} → {{ d($p->end_date) }}@if($p->start_date && $p->end_date)<div class="small muted">{{ __(':n days', ['n' => $p->start_date->diffInDays($p->end_date) + 1]) }}</div>@endif</dd>
                        <dt>{{ __('Manager') }}</dt><dd>{{ $p->manager?->name ?? '—' }}</dd>
                        <dt>{{ __('Branch') }}</dt><dd>{{ $p->branch?->legal_name ?? '—' }}</dd>
                        <dt>{{ __('Enquiry') }}</dt><dd>@if($p->enquiry)@can('customers.view')<a href="{{ route('enquiries.show', $p->enquiry) }}">{{ $p->enquiry->doc_no }}</a>@else{{ $p->enquiry->doc_no }}@endcan @else — @endif</dd>
                    </dl>
                    @if($p->notes)<hr><div class="small" style="white-space:pre-line">{{ $p->notes }}</div>@endif
                </div>
            </div>
        </div>
    </div>

    {{-- ================= Tasks ================= --}}
    <div data-tab-panel="tasks">
        @if($canManage)
            <div class="toolbar"><span class="muted small">{{ __('Work breakdown for the site team.') }}</span><span style="flex:1"></span>
                <button type="button" class="btn btn-primary btn-sm" data-dialog="dlg-task" data-title="{{ __('Add task') }}" data-action="{{ route('project-tasks.store', $p) }}" data-method="POST"
                        data-fill='{"title":"","assignee_id":"","start_date":"{{ $p->start_date?->toDateString() }}","end_date":"{{ $p->end_date?->toDateString() }}","status":"todo"}'><x-icon name="plus" class="ico ico-sm" />{{ __('Add task') }}</button>
            </div>
        @endif
        @if($p->tasks->count())
            <div class="table-wrap"><table class="table">
                <thead><tr><th>{{ __('Task') }}</th><th>{{ __('Assignee') }}</th><th class="hide-sm">{{ __('Dates') }}</th><th>{{ __('Status') }}</th>@if($canManage)<th class="actions"></th>@endif</tr></thead>
                <tbody>@foreach($p->tasks->sortBy('sort') as $t)
                    <tr>
                        <td class="primary" style="{{ $t->status === 'done' ? 'text-decoration:line-through;color:var(--muted)' : '' }}">{{ $t->title }}</td>
                        <td>{{ $t->assignee?->display_name ?? '—' }}</td>
                        <td class="hide-sm small nowrap {{ $t->status !== 'done' && $t->end_date?->isPast() && ! $t->end_date->isToday() ? 'text-danger' : '' }}">{{ d($t->start_date, 'd M') }} → {{ d($t->end_date, 'd M') }}</td>
                        <td>
                            @if($canManage)
                                <form method="post" action="{{ route('project-tasks.update', $t) }}">@csrf @method('PATCH')
                                    <select name="status" class="input" style="height:30px;width:auto" data-autosubmit>@foreach($taskStatuses as $k => $v)<option value="{{ $k }}" @selected($t->status === $k)>{{ $v }}</option>@endforeach</select>
                                </form>
                            @else <x-badge :status="$t->status" /> @endif
                        </td>
                        @if($canManage)
                            <td class="actions">
                                <button type="button" class="btn btn-ghost btn-sm btn-icon" title="{{ __('Edit') }}" data-dialog="dlg-task" data-title="{{ __('Edit task') }}" data-action="{{ route('project-tasks.update', $t) }}" data-method="PATCH"
                                        data-fill="{{ json_encode(['title' => $t->title, 'assignee_id' => $t->assignee_id, 'start_date' => $t->start_date?->toDateString(), 'end_date' => $t->end_date?->toDateString(), 'status' => $t->status]) }}"><x-icon name="pencil" class="ico ico-sm" /></button>
                                <form method="post" action="{{ route('project-tasks.destroy', $t) }}" style="display:inline" data-confirm="{{ __('Delete task “:title”?', ['title' => $t->title]) }}">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm btn-icon" title="{{ __('Delete') }}"><x-icon name="trash" class="ico ico-sm" /></button></form>
                            </td>
                        @endif
                    </tr>
                @endforeach</tbody>
            </table></div>
        @else <x-empty icon="clipboard" :title="__('No tasks yet')" /> @endif
    </div>

    {{-- ================= Costs ================= --}}
    <div data-tab-panel="costs">
        <div class="panel-body" style="border-bottom:1px solid var(--border)">
            <div class="grid g-3">
                @foreach(\App\Models\ProjectCost::CATEGORIES as $cat)
                    @php($amt = (float) ($costByCat[$cat] ?? 0))
                    <div class="row between" style="gap:8px">
                        <span class="small" style="width:80px">{{ __($cat) }}</span>
                        <div class="progress" style="flex:1"><span style="width:{{ $actual > 0 ? $amt / $actual * 100 : 0 }}%"></span></div>
                        <span class="mono small" style="width:100px;text-align:right">{{ money($amt, false) }}</span>
                    </div>
                @endforeach
            </div>
            <div class="row between" style="margin-top:12px">
                <span class="small muted">{{ __('Actual :actual of budget :budget', ['actual' => money($actual), 'budget' => money($p->budget_cost)]) }}</span>
                @if($budgetPct !== null)<div class="progress {{ $budgetPct > 100 ? 'over' : '' }}" style="width:260px"><span style="width:{{ min(100, $budgetPct) }}%"></span></div>@endif
            </div>
        </div>
        @if($canManage)
            <div class="toolbar"><span class="muted small">{{ __('Material issued from stock is booked here automatically.') }}</span><span style="flex:1"></span>
                <button type="button" class="btn btn-primary btn-sm" data-dialog="dlg-cost"><x-icon name="plus" class="ico ico-sm" />{{ __('Add cost') }}</button>
            </div>
        @endif
        @if($p->costs->count())
            <div class="table-wrap"><table class="table">
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Category') }}</th><th>{{ __('Description') }}</th><th class="hide-sm">{{ __('Supplier / ref') }}</th><th class="num">{{ __('Amount') }}</th>@if($canManage)<th class="actions"></th>@endif</tr></thead>
                <tbody>@foreach($p->costs->sortByDesc('cost_date') as $cost)
                    <tr>
                        <td class="nowrap">{{ d($cost->cost_date) }}</td>
                        <td><span class="tag">{{ __($cost->category) }}</span></td>
                        <td>{{ $cost->description }}@if($cost->stock_movement_id)<span class="sub">{{ __('From stock issue') }}</span>@endif</td>
                        <td class="hide-sm small">{{ $cost->supplier?->name }}@if($cost->reference)<span class="sub mono">{{ $cost->reference }}</span>@endif</td>
                        <td class="num">{{ money($cost->amount) }}</td>
                        @if($canManage)
                            <td class="actions">@unless($cost->stock_movement_id)<form method="post" action="{{ route('project-costs.destroy', $cost) }}" data-confirm="{{ __('Delete this cost line?') }}">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm btn-icon" title="{{ __('Delete') }}"><x-icon name="trash" class="ico ico-sm" /></button></form>@endunless</td>
                        @endif
                    </tr>
                @endforeach</tbody>
                <tfoot><tr><td colspan="4">{{ __('Total') }}</td><td class="num">{{ money($actual) }}</td>@if($canManage)<td></td>@endif</tr></tfoot>
            </table></div>
        @else <x-empty icon="banknotes" :title="__('No costs recorded yet')" /> @endif
    </div>

    {{-- ================= Stock ================= --}}
    <div data-tab-panel="stock" class="table-wrap">
        @if($movements->count())
            <table class="table">
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Ref') }}</th><th>{{ __('Item') }}</th><th class="hide-sm">{{ __('Warehouse') }}</th><th class="num">{{ __('Qty') }}</th><th class="num">{{ __('Cost') }}</th></tr></thead>
                <tbody>@foreach($movements as $m)
                    <tr><td class="nowrap">{{ d($m->moved_at) }}</td><td class="code">{{ $m->doc_no }}</td>
                        <td>@can('inventory.view')<a href="{{ route('items.show', $m->item) }}">{{ $m->item->name }}</a>@else{{ $m->item->name }}@endcan<span class="sub mono">{{ $m->item->sku }}</span></td>
                        <td class="hide-sm">{{ $m->warehouse->code }}</td><td class="num">{{ qty(abs($m->qty)) }} {{ $m->item->uom }}</td><td class="num">{{ money(abs($m->qty) * $m->unit_cost) }}</td></tr>
                @endforeach</tbody>
            </table>
        @else
            <x-empty icon="cube" :title="__('No stock issued to this project')">@can('inventory.manage')<a class="btn btn-ghost btn-sm" href="{{ route('stock.index', ['project_id' => $p->id]) }}">{{ __('Issue stock') }}</a>@endcan</x-empty>
        @endif
    </div>

    {{-- ================= Documents ================= --}}
    <div data-tab-panel="documents" class="table-wrap">
        @if($quotations->count() + $invoices->count() + $warranties->count() + $services->count())
            <table class="table">
                <thead><tr><th>{{ __('Type') }}</th><th>{{ __('Doc no') }}</th><th>{{ __('Date') }}</th><th>{{ __('Status') }}</th><th class="num">{{ __('Amount') }}</th></tr></thead>
                <tbody>
                @foreach($quotations as $d)<tr @can('sales.manage') data-href="{{ route('quotations.show', $d) }}" @endcan><td>{{ __('Quotation') }}</td><td class="code">{{ $d->doc_no }}</td><td>{{ d($d->quote_date) }}</td><td><x-badge :status="$d->status" /></td><td class="num">{{ money($d->total) }}</td></tr>@endforeach
                @foreach($invoices as $d)<tr @can('sales.manage') data-href="{{ route('invoices.show', $d) }}" @endcan><td>{{ $d->type === 'proforma' ? __('Proforma') : __('Invoice') }}</td><td class="code">{{ $d->doc_no }}</td><td>{{ d($d->invoice_date) }}</td><td><x-badge :status="$d->is_overdue ? 'overdue' : $d->status" /></td><td class="num">{{ money($d->total) }}@if($d->balance > 0 && $d->status !== 'void')<span class="sub">{{ __('Bal :amount', ['amount' => money($d->balance)]) }}</span>@endif</td></tr>@endforeach
                @foreach($warranties as $d)<tr @can('service.manage') data-href="{{ route('warranties.show', $d) }}" @endcan><td>{{ __('Warranty') }}</td><td class="code">{{ $d->doc_no }}</td><td>{{ d($d->start_date) }} → {{ d($d->end_date) }}</td><td><x-badge :status="$d->end_date->isPast() ? 'expired' : 'active'" /></td><td class="num">{{ __(':n yr', ['n' => $d->years]) }}</td></tr>@endforeach
                @foreach($services as $d)<tr @can('service.manage') data-href="{{ route('service-requests.show', $d) }}" @endcan><td>{{ __('Service request') }}</td><td class="code">{{ $d->doc_no }}</td><td>{{ d($d->scheduled_at, 'd M Y H:i') }}</td><td><x-badge :status="$d->status" /></td><td class="num small">{{ $d->assignee?->display_name }}</td></tr>@endforeach
                </tbody>
            </table>
        @else <x-empty icon="document" :title="__('No documents linked yet')" /> @endif
        @can('service.manage')
            <div class="panel-foot"><span></span><div class="row">
                <a class="btn btn-ghost btn-sm" href="{{ route('warranties.create', ['project_id' => $p->id]) }}"><x-icon name="shield" class="ico ico-sm" />{{ __('Issue warranty') }}</a>
                <a class="btn btn-ghost btn-sm" href="{{ route('service-requests.create', ['project_id' => $p->id]) }}"><x-icon name="wrench" class="ico ico-sm" />{{ __('Service request') }}</a>
            </div></div>
        @endcan
    </div>

    {{-- ================= History ================= --}}
    <div data-tab-panel="history" class="panel-body">
        <ul class="feed">
            @forelse($activity as $a)
                <li><span class="dot"></span><div><b>{{ $a->causer?->name ?? __('System') }}</b> {{ __($a->description) }}
                    @if($a->event === 'updated' && $a->properties->has('attributes'))<div class="small muted">@foreach($a->properties['attributes'] as $k => $v){{ $k }}: {{ $a->properties['old'][$k] ?? '—' }} → {{ is_scalar($v) ? $v : json_encode($v) }}@if(! $loop->last) · @endif @endforeach</div>@endif
                    <div class="when">{{ d($a->created_at, 'd M Y H:i') }}</div></div></li>
            @empty
                <li class="muted">{{ __('No changes recorded yet.') }}</li>
            @endforelse
        </ul>
    </div>
</div>

@if($canManage)
    <dialog id="dlg-task">
        <form method="post" action="{{ route('project-tasks.store', $p) }}">
            @csrf <input type="hidden" name="_method" value="POST">
            <div class="dlg-head"><h3>{{ __('Add task') }}</h3><button type="button" class="icon-btn" data-close><x-icon name="x" /></button></div>
            <div class="dlg-body form-grid">
                <x-field name="title" :label="__('Task')" required class="span-all" />
                <x-field name="assignee_id" :label="__('Assignee')" type="select" :options="$employeeOptions" :placeholder="__('— Unassigned —')" />
                <x-field name="status" :label="__('Status')" type="select" :options="$taskStatuses" value="todo" required />
                <x-field name="start_date" :label="__('Start')" type="date" />
                <x-field name="end_date" :label="__('End')" type="date" />
            </div>
            <div class="dlg-foot"><button type="button" class="btn btn-ghost" data-close>{{ __('Cancel') }}</button><button class="btn btn-primary">{{ __('Save task') }}</button></div>
        </form>
    </dialog>

    <dialog id="dlg-cost">
        <form method="post" action="{{ route('project-costs.store', $p) }}">
            @csrf
            <div class="dlg-head"><h3>{{ __('Add cost') }} — {{ $p->code }}</h3><button type="button" class="icon-btn" data-close><x-icon name="x" /></button></div>
            <div class="dlg-body form-grid">
                <x-field name="cost_date" :label="__('Date')" type="date" :value="today()" required />
                <x-field name="category" :label="__('Category')" type="select" :options="$catOptions" required />
                <x-field name="description" :label="__('Description')" required class="span-all" :placeholder="__('e.g. 3 workers × 2 days, lorry to site, scaffolding rental')" />
                <x-field name="amount" :label="__('Amount (RM)')" type="number" step="0.01" min="0.01" required />
                <x-field name="supplier_id" :label="__('Supplier / subcon')" type="select" :options="$suppliers" placeholder="—" />
                <x-field name="reference" :label="__('Reference (bill / DO no.)')" class="span-all" />
            </div>
            <div class="dlg-foot"><button type="button" class="btn btn-ghost" data-close>{{ __('Cancel') }}</button><button class="btn btn-primary">{{ __('Save cost') }}</button></div>
        </form>
    </dialog>
    @if($errors->hasAny(['amount', 'description', 'category', 'cost_date']))<span data-reopen-dialog="dlg-cost"></span>@elseif($errors->has('title'))<span data-reopen-dialog="dlg-task"></span>@endif
@endif
@endsection
