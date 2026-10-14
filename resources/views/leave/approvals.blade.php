@extends('layouts.app')
@section('title', __('Leave Approval'))
@section('content')
<x-page-header :eyebrow="__('HR').' · '.__('Leave')" :title="__('Leave Approval')" :count="$counts['pending'] ?? 0" :sub="__('Review requests oldest-first. Remaining balance is shown before you decide.')">
    <x-slot:actions><a class="btn btn-ghost" href="{{ route('attendance.board') }}"><x-icon name="identification" class="ico ico-sm" />{{ __('Attendance Board') }}</a></x-slot:actions>
</x-page-header>

<div class="grid g-main-side">
    <div class="panel">
        <div class="tabs">
            @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $k => $label)
                <a href="{{ route('leave.approvals', ['tab' => $k]) }}" class="{{ $tab === $k ? 'active' : '' }}">{{ __($label) }} <span class="n">{{ $counts[$k] ?? 0 }}</span></a>
            @endforeach
        </div>
        @forelse($rows as $l)
            <div style="padding:14px 16px;border-bottom:1px solid var(--border);display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap">
                <span class="avatar">{{ $l->employee->initials }}</span>
                <div style="flex:1;min-width:220px">
                    <div class="row" style="gap:8px"><b><a href="{{ route('employees.show', $l->employee) }}" style="color:inherit">{{ $l->employee->display_name }}</a></b>
                        <span class="tag" style="border-left:3px solid {{ $l->leaveType->color }}">{{ __($l->leaveType->name) }}</span><x-badge :status="$l->status" /></div>
                    <div class="small muted">{{ $l->employee->position }} · {{ $l->employee->department ? __($l->employee->department->name) : '' }}</div>
                    <div style="margin-top:6px"><b>{{ d($l->start_date, 'D, d M Y') }}</b>@if(! $l->start_date->equalTo($l->end_date)) → <b>{{ d($l->end_date, 'D, d M Y') }}</b>@endif · {{ __(':n day(s)', ['n' => qty($l->days)]) }}</div>
                    @if($l->reason)<div class="small" style="margin-top:4px">“{{ $l->reason }}”</div>@endif
                    <div class="small muted" style="margin-top:4px">
                        @if(! is_null($l->balance_left)){{ __('Remaining :type balance (:year):', ['type' => __($l->leaveType->name), 'year' => now()->year]) }} <b class="{{ $l->balance_left < $l->days && $l->status === 'pending' ? 'text-danger' : '' }}">{{ __(':n day(s)', ['n' => qty($l->balance_left)]) }}</b> · @endif
                        {{ __('Applied :ago', ['ago' => $l->created_at->diffForHumans()]) }}
                        @if($l->reviewer) · {{ __('Reviewed by :name :date', ['name' => $l->reviewer->name, 'date' => d($l->reviewed_at)]) }}@endif
                        @if($l->review_note) · {{ __('Note:') }} {{ $l->review_note }}@endif
                    </div>
                </div>
                @if($l->status === 'pending')
                    <div class="row">
                        <button class="btn btn-danger-ghost btn-sm" data-dialog="dlg-reject" data-action="{{ route('leave.reject', $l) }}" data-title="{{ __('Reject — :name', ['name' => $l->employee->display_name]) }}">{{ __('Reject') }}</button>
                        <form method="post" action="{{ route('leave.approve', $l) }}">@csrf<button class="btn btn-success btn-sm"><x-icon name="check" class="ico ico-sm" />{{ __('Approve') }}</button></form>
                    </div>
                @endif
            </div>
        @empty
            <x-empty icon="check-badge" :title="$tab === 'pending' ? __('No requests waiting') : __('Nothing here yet')" />
        @endforelse
        @if($rows->hasPages())<div class="panel-foot">{{ $rows->links() }}</div>@endif
    </div>

    <div class="panel">
        <div class="panel-head navy"><h3><x-icon name="calendar" />{{ __('On leave today') }}</h3><span class="panel-count">{{ $onLeaveToday->count() }}</span></div>
        <ul class="list">
            @forelse($onLeaveToday as $l)
                <li><span>{{ $l->employee->display_name }}<div class="meta">{{ __($l->leaveType->name) }}</div></span><span class="date">{{ __('until :date', ['date' => d($l->end_date, 'd M')]) }}</span></li>
            @empty
                <li class="muted">{{ __('Everyone is in today.') }}</li>
            @endforelse
        </ul>
    </div>
</div>

<dialog id="dlg-reject">
    <form method="post" action="">
        @csrf
        <div class="dlg-head"><h3>{{ __('Reject leave') }}</h3><button type="button" class="icon-btn" data-close><x-icon name="x" /></button></div>
        <div class="dlg-body"><x-field name="review_note" :label="__('Reason (shown to the employee)')" type="textarea" required /></div>
        <div class="dlg-foot"><button type="button" class="btn btn-ghost" data-close>{{ __('Cancel') }}</button><button class="btn btn-danger">{{ __('Reject request') }}</button></div>
    </form>
</dialog>
@endsection
