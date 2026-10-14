@extends('layouts.app')
@section('title', __('My Leave'))
@section('content')
<x-page-header :eyebrow="__('HR').' · '.__('Leave')" :title="__('My Leave')" :sub="$employee ? $employee->display_name.' · '.__('balances for :year', ['year' => $year]) : null">
    <x-slot:actions>
        @if($employee)<button class="btn btn-primary" data-dialog="dlg-apply"><x-icon name="plus" class="ico ico-sm" />{{ __('Apply for leave') }}</button>@endif
        @can('hr.manage')<a class="btn btn-ghost" href="{{ route('leave.approvals') }}">{{ __('Leave approval') }}</a>@endcan
    </x-slot:actions>
</x-page-header>

@if(! $employee)
    <div class="panel"><x-empty icon="user" :title="__('No employee profile linked')">{{ __('Ask HR to link your login to an employee record before applying for leave.') }}</x-empty></div>
@else
    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr));margin-bottom:16px">
        @foreach($balances as $b)
            @php($left = $b['entitled'] - $b['taken'])
            <div class="stat" style="flex-direction:column;gap:6px;border-top:3px solid {{ $b['type']->color }}">
                <div class="stat-label">{{ __($b['type']->name) }} <span class="mono muted">{{ $b['type']->code }}</span></div>
                @if($b['entitled'] > 0)
                    <div class="stat-value">{{ qty(max(0, $left)) }} <span class="small muted" style="font-weight:500">{{ __('of :n days left', ['n' => qty($b['entitled'])]) }}</span></div>
                    <div class="progress" style="width:100%"><span style="width:{{ min(100, $b['taken'] / $b['entitled'] * 100) }}%;background:{{ $b['type']->color }}"></span></div>
                @else
                    <div class="stat-value">{{ qty($b['taken']) }} <span class="small muted" style="font-weight:500">{{ __('days taken') }}</span></div>
                @endif
                <div class="stat-hint">{{ __(':n taken', ['n' => qty($b['taken'])]) }} @if($b['pending']) · {{ __(':n pending', ['n' => qty($b['pending'])]) }} @endif</div>
            </div>
        @endforeach
    </div>

    <div class="panel">
        <div class="panel-head"><h3><x-icon name="calendar" />{{ __('My requests') }}</h3><span class="panel-count">{{ $history->count() }}</span></div>
        @if($history->count())
            <div class="table-wrap"><table class="table">
                <thead><tr><th>{{ __('Type') }}</th><th>{{ __('Dates') }}</th><th class="num">{{ __('Days') }}</th><th class="hide-sm">{{ __('Reason') }}</th><th>{{ __('Status') }}</th><th class="hide-sm">{{ __('Reviewed') }}</th><th class="actions"></th></tr></thead>
                <tbody>@foreach($history as $l)
                    <tr>
                        <td><span class="tag" style="border-left:3px solid {{ $l->leaveType->color }}">{{ __($l->leaveType->name) }}</span></td>
                        <td class="small">{{ d($l->start_date, 'D, d M Y') }}@if(! $l->start_date->equalTo($l->end_date)) → {{ d($l->end_date, 'D, d M Y') }}@endif</td>
                        <td class="num">{{ qty($l->days) }}</td>
                        <td class="hide-sm small">{{ $l->reason }}</td>
                        <td><x-badge :status="$l->status" />@if($l->review_note)<span class="sub">{{ $l->review_note }}</span>@endif</td>
                        <td class="hide-sm small muted">{{ $l->reviewer?->name }}@if($l->reviewed_at)<br>{{ d($l->reviewed_at) }}@endif</td>
                        <td class="actions">
                            @if($l->status === 'pending')
                                <form method="post" action="{{ route('leave.cancel', $l) }}" data-confirm="{{ __('Cancel this leave request?') }}">@csrf<button class="btn btn-danger-ghost btn-sm">{{ __('Cancel') }}</button></form>
                            @endif
                        </td>
                    </tr>
                @endforeach</tbody>
            </table></div>
        @else
            <x-empty icon="calendar" :title="__('No leave requests yet')"><button class="btn btn-primary btn-sm" data-dialog="dlg-apply">{{ __('Apply for leave') }}</button></x-empty>
        @endif
    </div>

    <dialog id="dlg-apply">
        <form method="post" action="{{ route('leave.store') }}">
            @csrf
            <div class="dlg-head"><h3>{{ __('Apply for leave') }}</h3><button type="button" class="icon-btn" data-close><x-icon name="x" /></button></div>
            <div class="dlg-body form-grid">
                <x-field name="leave_type_id" :label="__('Leave type')" type="select" :options="$types->mapWithKeys(fn ($t) => [$t->id => __($t->name)])->all()" required class="span-all" />
                <x-field name="start_date" :label="__('From')" type="date" :value="today()->addDay()->toDateString()" required />
                <x-field name="end_date" :label="__('To')" type="date" :value="today()->addDay()->toDateString()" required />
                <label class="check span-all"><input type="checkbox" name="half_day" value="1" @checked(old('half_day'))> {{ __('Half day (single date only)') }}</label>
                <x-field name="reason" :label="__('Reason')" type="textarea" required class="span-all" />
                <div class="hint small muted span-all">{{ __('Sundays and public holidays are not counted. Your manager / HR will be notified.') }}</div>
            </div>
            <div class="dlg-foot"><button type="button" class="btn btn-ghost" data-close>{{ __('Cancel') }}</button><button class="btn btn-primary">{{ __('Submit request') }}</button></div>
        </form>
    </dialog>
    @if($errors->hasAny(['leave_type_id', 'start_date', 'end_date', 'reason', 'half_day']))<span data-reopen-dialog="dlg-apply"></span>@endif
@endif
@endsection
