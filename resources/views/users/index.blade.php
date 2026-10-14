@extends('layouts.app')
@section('title', __('Users'))
@section('content')
<x-page-header :eyebrow="__('System').' · '.__('Users')" :title="__('Users')" :count="$rows->total()" :sub="__('Login accounts. User type Admin bypasses all permissions; everyone else gets access from their role.')">
    <x-slot:actions>
        <a class="btn btn-ghost" href="{{ route('roles.index') }}"><x-icon name="key" class="ico ico-sm" />{{ __('Roles & Permissions') }}</a>
        <a class="btn btn-primary" href="{{ route('users.create') }}"><x-icon name="plus" class="ico ico-sm" />{{ __('Add user') }}</a>
    </x-slot:actions>
</x-page-header>

<div class="panel">
    <form method="get" class="filters">
        <div class="filter-row">
            <div class="field grow"><label>{{ __('Search') }}</label><input name="q" value="{{ request('q') }}" placeholder="{{ __('Name or email…') }}"></div>
            <div class="field"><label>{{ __('Role') }}</label><select name="role" data-autosubmit><option value="">{{ __('All') }}</option>@foreach($roles as $r)<option value="{{ $r }}" @selected(request('role') === $r)>{{ __($r) }}</option>@endforeach</select></div>
            <div class="field"><label>{{ __('Type') }}</label><select name="type" data-autosubmit><option value="">{{ __('All') }}</option>@foreach(\App\Models\User::TYPES as $k => $v)<option value="{{ $k }}" @selected(request('type') === $k)>{{ __($v) }}</option>@endforeach</select></div>
            <div class="field"><label>{{ __('Status') }}</label><select name="status" data-autosubmit><option value="">{{ __('All') }}</option><option value="active" @selected(request('status') === 'active')>{{ __('Active') }}</option><option value="inactive" @selected(request('status') === 'inactive')>{{ __('Inactive') }}</option></select></div>
            <div class="actions"><button class="btn btn-primary"><x-icon name="search" class="ico ico-sm" />{{ __('Search') }}</button></div>
        </div>
    </form>
    @if($rows->count())
        <div class="table-wrap"><table class="table">
            <thead><tr><th>{{ __('User') }}</th><th>{{ __('Type') }}</th><th>{{ __('Role') }}</th><th class="hide-sm">{{ __('Employee') }}</th><th class="hide-sm">{{ __('Branch') }}</th><th class="hide-sm">{{ __('Last login') }}</th><th>{{ __('Status') }}</th><th class="actions"></th></tr></thead>
            <tbody>@foreach($rows as $u)
                <tr data-href="{{ route('users.edit', $u) }}">
                    <td class="primary"><span style="display:flex;align-items:center;gap:9px"><span class="avatar sm">{{ $u->initials }}</span><span>{{ $u->name }}@if($u->is(auth()->user())) <span class="tag">{{ __('you') }}</span>@endif<span class="sub">{{ $u->email }}</span></span></span></td>
                    <td><span class="tag">{{ __(\App\Models\User::TYPES[$u->user_type] ?? $u->user_type) }}</span></td>
                    <td>{{ $u->roles->pluck('name')->map(fn ($r) => __($r))->implode(', ') ?: '—' }}</td>
                    <td class="hide-sm">@if($u->employee)<a href="{{ route('employees.show', $u->employee) }}">{{ $u->employee->emp_no }}</a>@else<span class="muted">—</span>@endif</td>
                    <td class="hide-sm"><span class="tag">{{ $u->branch?->code }}</span></td>
                    <td class="hide-sm small muted">{{ $u->last_login_at ? $u->last_login_at->diffForHumans() : __('Never') }}</td>
                    <td><x-badge :status="$u->is_active ? 'active' : 'inactive'" /></td>
                    <td class="actions">
                        @unless($u->is(auth()->user()))
                            <form method="post" action="{{ route('users.update', $u) }}" data-confirm="{{ $u->is_active ? __('Deactivate :email?', ['email' => $u->email]) : __('Activate :email?', ['email' => $u->email]) }}">@csrf @method('PUT')<input type="hidden" name="toggle_active" value="1"><button class="btn btn-ghost btn-sm">{{ $u->is_active ? __('Deactivate') : __('Activate') }}</button></form>
                        @endunless
                    </td>
                </tr>
            @endforeach</tbody>
        </table></div>
        <div class="panel-foot">{{ $rows->links() }}</div>
    @else
        <x-empty icon="users" :title="__('No users match these filters')" />
    @endif
</div>
@endsection
