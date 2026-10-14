@extends('layouts.app')
@section('title', $e->display_name)
@section('content')
@php($exp = $e->expiringDocuments(30))
<x-page-header :eyebrow="__('HR').' · '.__('Employees').' · '.$e->emp_no" :title="$e->display_name" :sub="trim(($e->position ?? '').' · '.($e->department ? __($e->department->name) : '').' · '.($e->branch?->legal_name ?? ''), ' ·')">
    <x-slot:badge><x-badge :status="$e->status" /></x-slot:badge>
    <x-slot:actions>
        @if($e->phone)<a class="btn btn-ghost" href="https://wa.me/{{ preg_replace('/\D/', '', preg_replace('/^0/', '60', $e->phone)) }}" target="_blank" rel="noopener"><x-icon name="phone" class="ico ico-sm" />WhatsApp</a>@endif
        <a class="btn btn-primary" href="{{ route('employees.edit', $e) }}"><x-icon name="pencil" class="ico ico-sm" />{{ __('Edit') }}</a>
    </x-slot:actions>
</x-page-header>

@if($exp)
    <div class="alert alert-{{ collect($exp)->contains(fn ($d) => $d->isPast()) ? 'danger' : 'warning' }}"><x-icon name="exclamation" />
        <div><b>{{ __('Document attention needed:') }}</b> {{ collect($exp)->map(fn ($d, $l) => $d->isPast() ? __(':doc expired :date', ['doc' => __($l), 'date' => d($d)]) : __(':doc expires :date', ['doc' => __($l), 'date' => d($d)]))->implode(' · ') }}</div></div>
@endif

<div class="grid g-side-main">
    <div class="stack">
        <div class="panel">
            <div class="panel-body" style="display:flex;gap:14px;align-items:center">
                <span class="avatar" style="width:56px;height:56px;font-size:18px;border-radius:14px">{{ $e->initials }}</span>
                <div style="min-width:0">
                    <div style="font-weight:700">{{ $e->name }}</div>
                    <div class="small muted">{{ $e->emp_no }} · {{ __(\App\Models\Employee::EMPLOYMENT_TYPES[$e->employment_type] ?? $e->employment_type) }}</div>
                    <div class="small muted">{{ __('Joined :date', ['date' => d($e->hire_date)]) }}@if($e->hire_date) · {{ $e->hire_date->diffForHumans(null, true) }}@endif</div>
                </div>
            </div>
            <div class="summary-bar" style="grid-template-columns:repeat(3,1fr)">
                <div><small>{{ __('Age') }}</small><strong>{{ $e->age ?? '—' }}</strong></div>
                <div><small>{{ __('Present this month') }}</small><strong>{{ $attendance->whereNotNull('clock_in')->count() }}</strong></div>
                <div><small>{{ __('Late') }}</small><strong>{{ $attendance->where('is_late', true)->count() }}</strong></div>
            </div>
        </div>
        <div class="panel">
            <div class="panel-head"><h3><x-icon name="phone" />{{ __('Contact') }}</h3></div>
            <div class="panel-body">
                <dl class="dl">
                    <dt>{{ __('Phone') }}</dt><dd>{{ $e->phone ?? '—' }}</dd>
                    <dt>{{ __('Email') }}</dt><dd>{{ $e->email ?? '—' }}</dd>
                    <dt>{{ __('Address') }}</dt><dd>{{ $e->address ?? '—' }}</dd>
                    <dt>{{ __('Emergency') }}</dt><dd>{{ $e->emergency_contact ?? '—' }}</dd>
                </dl>
            </div>
        </div>
        <form method="post" action="{{ route('employees.destroy', $e) }}" data-confirm="{{ __('Delete employee :no? Employees with payslips cannot be deleted — set them to Resigned instead.', ['no' => $e->emp_no]) }}">
            @csrf @method('DELETE')
            <button class="btn btn-danger-ghost btn-block"><x-icon name="trash" class="ico ico-sm" />{{ __('Delete employee') }}</button>
        </form>
    </div>

    <div class="panel" data-tab-scope>
        <div class="tabs" data-tabs>
            <button data-tab="profile" class="active">{{ __('Profile') }}</button>
            <button data-tab="documents">{{ __('ID documents') }} @if($exp)<span class="n" style="background:var(--danger-soft);color:var(--danger)">{{ count($exp) }}</span>@endif</button>
            <button data-tab="attendance">{{ __('Attendance') }} <span class="n">{{ $attendance->count() }}</span></button>
            <button data-tab="leave">{{ __('Leave') }}</button>
            <button data-tab="payslips">{{ __('Payslips') }} <span class="n">{{ $payslips->count() }}</span></button>
            <button data-tab="account">{{ __('Login account') }}</button>
        </div>

        <div data-tab-panel="profile" class="active panel-body">
            <dl class="dl cols-2">
                <dt>{{ __('Full name') }}</dt><dd>{{ $e->name }}</dd>
                <dt>{{ __('Nickname') }}</dt><dd>{{ $e->nickname ?? '—' }}</dd>
                <dt>{{ __('Gender') }}</dt><dd>{{ ['M' => __('Male'), 'F' => __('Female')][$e->gender] ?? '—' }}</dd>
                <dt>{{ __('Date of birth') }}</dt><dd>{{ d($e->dob) }}</dd>
                <dt>{{ __('Nationality') }}</dt><dd>{{ $e->nationality }}</dd>
                <dt>{{ __('IC no.') }}</dt><dd class="mono">{{ $e->ic_no ?? '—' }}</dd>
                <dt>{{ __('Position') }}</dt><dd>{{ $e->position ?? '—' }}</dd>
                <dt>{{ __('Department') }}</dt><dd>{{ $e->department ? __($e->department->name) : '—' }}</dd>
                <dt>{{ __('Branch') }}</dt><dd>{{ $e->branch?->legal_name ?? '—' }}</dd>
                <dt>{{ __('Employment') }}</dt><dd>{{ __(\App\Models\Employee::EMPLOYMENT_TYPES[$e->employment_type] ?? $e->employment_type) }}</dd>
                <dt>{{ __('Hire date') }}</dt><dd>{{ d($e->hire_date) }}</dd>
                <dt>{{ __('Resign date') }}</dt><dd>{{ d($e->resign_date) }}</dd>
            </dl>
            <hr>
            <h4 style="font-size:13px;margin-bottom:10px">{{ __('Payroll & statutory') }}</h4>
            <dl class="dl cols-2">
                <dt>{{ __('Basic salary') }}</dt><dd>{{ money($e->basic_salary) }}</dd>
                <dt>{{ __('Fixed allowance') }}</dt><dd>{{ money($e->fixed_allowance) }}</dd>
                <dt>{{ __('EPF no.') }}</dt><dd class="mono">{{ $e->epf_no ?? '—' }}</dd>
                <dt>{{ __('SOCSO no.') }}</dt><dd class="mono">{{ $e->socso_no ?? '—' }}</dd>
                <dt>{{ __('Tax no.') }}</dt><dd class="mono">{{ $e->tax_no ?? '—' }}</dd>
                <dt>{{ __('Bank') }}</dt><dd>{{ $e->bank_name ?? '—' }} <span class="mono">{{ $e->bank_account }}</span></dd>
            </dl>
        </div>

        <div data-tab-panel="documents" class="table-wrap">
            <table class="table">
                <thead><tr><th>{{ __('Document') }}</th><th>{{ __('Number') }}</th><th>{{ __('Expiry') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                @foreach(\App\Models\Employee::DOCUMENTS as $col => [$label, $noCol])
                    @php($st = $e->documentStatus($col))
                    <tr>
                        <td class="primary">{{ __($label) }}</td>
                        <td class="mono small">{{ $e->{$noCol} ?? '—' }}</td>
                        <td>{{ d($e->{$col}) }}@if($e->{$col})<span class="sub">{{ $e->{$col}->isPast() ? __('expired :ago', ['ago' => $e->{$col}->diffForHumans()]) : __('in :n days', ['n' => (int) now()->startOfDay()->diffInDays($e->{$col})]) }}</span>@endif</td>
                        <td>@if($st)<x-badge :status="$st" :label="['expired' => __('Expired'), 'expiring' => __('Expiring ≤30d'), 'active' => __('Valid')][$st]" />@else<span class="muted small">{{ __('Not recorded') }}</span>@endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div data-tab-panel="attendance" class="table-wrap">
            @if($attendance->count())
                <table class="table">
                    <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Clock in') }}</th><th>{{ __('Clock out') }}</th><th class="num">{{ __('Hours') }}</th><th>{{ __('Status') }}</th><th class="hide-sm">{{ __('Source') }}</th></tr></thead>
                    <tbody>@foreach($attendance as $a)
                        <tr><td>{{ d($a->work_date, 'D, d M') }}</td><td class="mono">{{ $a->clock_in?->format('H:i') ?? '—' }}</td><td class="mono">{{ $a->clock_out?->format('H:i') ?? '—' }}</td>
                            <td class="num">{{ $a->hours !== null ? qty($a->hours) : '—' }}</td>
                            <td>@if($a->is_late)<x-badge status="late" />@else<x-badge status="present" :label="__('On time')" />@endif</td>
                            <td class="hide-sm small muted">{{ __(ucfirst($a->source)) }}</td></tr>
                    @endforeach</tbody>
                </table>
            @else <x-empty icon="clock" :title="__('No attendance this month')" /> @endif
        </div>

        <div data-tab-panel="leave">
            <div class="panel-body">
                <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px">
                    @foreach($balances as $b)
                        <div style="border:1px solid var(--border);border-radius:9px;padding:10px 12px;border-left:3px solid {{ $b['type']->color }}">
                            <div class="small muted">{{ __($b['type']->name) }}</div>
                            @if($b['entitled'] > 0)
                                <div style="font-size:18px;font-weight:700">{{ qty(max(0, $b['entitled'] - $b['taken'])) }} <span class="small muted" style="font-weight:400">{{ __('/ :n left', ['n' => qty($b['entitled'])]) }}</span></div>
                            @else
                                <div style="font-size:18px;font-weight:700">{{ qty($b['taken']) }} <span class="small muted" style="font-weight:400">{{ __('taken') }}</span></div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="table-wrap">
                @if($leaves->count())
                    <table class="table">
                        <thead><tr><th>{{ __('Type') }}</th><th>{{ __('Dates') }}</th><th class="num">{{ __('Days') }}</th><th class="hide-sm">{{ __('Reason') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>@foreach($leaves as $l)
                            <tr><td>{{ __($l->leaveType->name) }}</td><td class="small">{{ d($l->start_date) }} → {{ d($l->end_date) }}</td><td class="num">{{ qty($l->days) }}</td><td class="hide-sm small">{{ $l->reason }}@if($l->review_note)<span class="sub">{{ __('Note:') }} {{ $l->review_note }}</span>@endif</td><td><x-badge :status="$l->status" /></td></tr>
                        @endforeach</tbody>
                    </table>
                @else <x-empty icon="calendar" :title="__('No leave requests')" /> @endif
            </div>
        </div>

        <div data-tab-panel="payslips" class="table-wrap">
            @if($payslips->count())
                <table class="table">
                    <thead><tr><th>{{ __('Pay month') }}</th><th class="num">{{ __('Gross') }}</th><th class="num">{{ __('EPF (EE)') }}</th><th class="num">{{ __('Net pay') }}</th><th>{{ __('Run') }}</th><th></th></tr></thead>
                    <tbody>@foreach($payslips as $p)
                        <tr><td class="code">{{ $p->run->period }}</td><td class="num">{{ money($p->gross) }}</td><td class="num">{{ money($p->epf_employee) }}</td><td class="num"><b>{{ money($p->net_pay) }}</b></td><td><x-badge :status="$p->run->status" /></td>
                            <td class="actions"><a class="btn btn-ghost btn-sm" href="{{ route('payslips.show', $p) }}"><x-icon name="printer" class="ico ico-sm" />{{ __('Payslip') }}</a></td></tr>
                    @endforeach</tbody>
                </table>
            @else <x-empty icon="banknotes" :title="__('No payslips yet')" /> @endif
        </div>

        <div data-tab-panel="account" class="panel-body">
            @if($e->user)
                <dl class="dl">
                    <dt>{{ __('Login email') }}</dt><dd>{{ $e->user->email }}</dd>
                    <dt>{{ __('User type') }}</dt><dd>{{ __(\App\Models\User::TYPES[$e->user->user_type] ?? $e->user->user_type) }}</dd>
                    <dt>{{ __('Role') }}</dt><dd>{{ $e->user->getRoleNames()->map(fn ($r) => __($r))->implode(', ') ?: '—' }}</dd>
                    <dt>{{ __('Status') }}</dt><dd><x-badge :status="$e->user->is_active ? 'active' : 'inactive'" /></dd>
                    <dt>{{ __('Last login') }}</dt><dd>{{ $e->user->last_login_at ? d($e->user->last_login_at, 'd M Y H:i') : __('Never') }}</dd>
                </dl>
                @can('system.manage')<a class="btn btn-ghost btn-sm" style="margin-top:12px" href="{{ route('users.edit', $e->user) }}">{{ __('Manage account') }}</a>@endcan
            @else
                <x-empty icon="key" :title="__('No login account')">{{ __('This employee cannot sign in or clock in on the web yet.') }}
                    @can('system.manage')<div style="margin-top:10px"><a class="btn btn-primary btn-sm" href="{{ route('users.create', ['employee_id' => $e->id]) }}">{{ __('Create login') }}</a></div>@endcan
                </x-empty>
            @endif
        </div>
    </div>
</div>
@endsection
