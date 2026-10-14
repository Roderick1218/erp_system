@extends('layouts.app')
@section('title', $u->exists ? __('Edit user') : __('New user'))
@section('content')
@php
    $self = $u->exists && $u->is(auth()->user());
    $roleOptions = collect($roles)->mapWithKeys(fn ($r, $k) => [is_int($k) ? $r : $k => __($r)])->all();
    $typeOptions = collect(\App\Models\User::TYPES)->map(fn ($v) => __($v))->all();
@endphp
<x-page-header :eyebrow="__('System').' · '.__('Users').' · '.($u->exists ? $u->email : __('New'))" :title="$u->exists ? __('Edit user') : __('New user')" :sub="__('Ctrl+S saves.')">
    <x-slot:actions><a class="btn btn-ghost" href="{{ route('users.index') }}">{{ __('Cancel') }}</a></x-slot:actions>
</x-page-header>

@if(session('error'))<div class="alert alert-danger"><x-icon name="exclamation" />{{ session('error') }}</div>@endif

<form method="post" action="{{ $u->exists ? route('users.update', $u) : route('users.store') }}" class="panel" data-main-form>
    @csrf @if($u->exists) @method('PUT') @endif
    <div class="form-section">
        <h4><span class="step">1</span>{{ __('Account') }}</h4>
        <div class="form-grid">
            <x-field name="name" :label="__('Display name')" :value="$u->name" required />
            <x-field name="email" :label="__('Login email')" type="email" :value="$u->email" required />
            <x-field name="password" :label="$u->exists ? __('New password') : __('Password')" type="password" :required="! $u->exists" :hint="$u->exists ? __('Leave blank to keep the current password.') : __('At least 8 characters.')" />
            <x-field name="password_confirmation" :label="__('Confirm password')" type="password" :required="! $u->exists" />
        </div>
    </div>
    <div class="form-section">
        <h4><span class="step">2</span>{{ __('Access') }}</h4>
        <div class="form-grid cols-3">
            <x-field name="user_type" :label="__('User type')" type="select" :options="$typeOptions" :value="$u->user_type" required :readonly="$self" :hint="__('Admin = full access. Manager / Staff = per role.')" />
            @if($self)<input type="hidden" name="user_type" value="{{ $u->user_type }}">@endif
            <x-field name="role" :label="__('Role')" type="select" :options="$roleOptions" :placeholder="__('— No role (self-service only) —')" :value="$u->exists ? $u->getRoleNames()->first() : null" />
            <x-field name="branch_id" :label="__('Branch')" type="select" :options="$branches" placeholder="—" :value="$u->branch_id" />
            <x-field name="employee_id" :label="__('Linked employee')" type="select" :options="$employees + ($u->employee ? [$u->employee->id => $u->employee->emp_no.' — '.$u->employee->display_name] : [])" :placeholder="__('— None —')" :value="$u->employee_id" class="span-2" :hint="__('Required for clock-in, leave and payslips.')" />
            <label class="check" style="align-self:end;padding-bottom:8px">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $u->is_active)) @disabled($self)> {{ __('Active (can sign in)') }}
                @if($self)<input type="hidden" name="is_active" value="1">@endif
            </label>
        </div>
    </div>
    <div class="form-actions">
        @if($u->exists && ! $self)
            <button type="submit" form="del-user" class="btn btn-danger-ghost" style="margin-right:auto"><x-icon name="trash" class="ico ico-sm" />{{ __('Delete') }}</button>
        @endif
        <a class="btn btn-ghost" href="{{ route('users.index') }}">{{ __('Cancel') }}</a>
        <button class="btn btn-primary"><x-icon name="check" class="ico ico-sm" />{{ $u->exists ? __('Save changes') : __('Create user') }}</button>
    </div>
</form>
@if($u->exists && ! $self)
    <form id="del-user" method="post" action="{{ route('users.destroy', $u) }}" data-confirm="{{ __('Delete user :email? They will no longer be able to sign in.', ['email' => $u->email]) }}">@csrf @method('DELETE')</form>
@endif
@endsection
