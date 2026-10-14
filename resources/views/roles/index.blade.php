@extends('layouts.app')
@section('title', __('Roles & Permissions'))
@section('content')
<x-page-header :eyebrow="__('System').' · '.__('Roles')" :title="__('Role Manager')" :count="$roles->count()" :sub="__('Each user gets one role. Users of type Admin always have every permission.')">
    <x-slot:actions>
        <a class="btn btn-ghost" href="{{ route('users.index') }}"><x-icon name="users" class="ico ico-sm" />{{ __('Users') }}</a>
        <button class="btn btn-primary" data-dialog="dlg-role-new"><x-icon name="plus" class="ico ico-sm" />{{ __('New role') }}</button>
    </x-slot:actions>
</x-page-header>

<div class="panel">
    <div class="panel-head"><h3><x-icon name="key" />{{ __('Permission matrix') }}</h3><span class="panel-count">{{ __(':n permissions', ['n' => count($permissions)]) }}</span></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr>
                <th style="min-width:260px">{{ __('Permission') }}</th>
                @foreach($roles as $r)
                    <th class="center" style="min-width:110px">{{ __($r->name) }}<div style="font-weight:400;text-transform:none;letter-spacing:0">{{ __(':n user(s)', ['n' => $r->users_count]) }}</div></th>
                @endforeach
            </tr></thead>
            <tbody>
            @foreach($permissions as $key => $label)
                <tr>
                    <td><span class="primary">{{ __($label) }}</span><span class="sub mono">{{ $key }}</span></td>
                    @foreach($roles as $r)
                        <td class="center">@if($r->permissions->contains('name', $key))<x-icon name="check" class="ico text-success" />@else<span class="muted">·</span>@endif</td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
            <tfoot><tr>
                <td>{{ __('Actions') }}</td>
                @foreach($roles as $r)
                    <td class="center" style="white-space:nowrap">
                        <button class="btn btn-ghost btn-sm" data-dialog="dlg-role-{{ $r->id }}">{{ __('Edit') }}</button>
                        @if(! $r->users_count)
                            <form method="post" action="{{ route('roles.destroy', $r) }}" data-confirm="{{ __('Delete role :name?', ['name' => __($r->name)]) }}" style="display:inline">@csrf @method('DELETE')<button class="btn btn-danger-ghost btn-sm btn-icon" title="{{ __('Delete') }}"><x-icon name="trash" class="ico ico-sm" /></button></form>
                        @endif
                    </td>
                @endforeach
            </tr></tfoot>
        </table>
    </div>
</div>

@foreach($roles->concat([null]) as $r)
    <dialog id="dlg-role-{{ $r?->id ?? 'new' }}" class="wide">
        <form method="post" action="{{ $r ? route('roles.update', $r) : route('roles.store') }}">
            @csrf @if($r) @method('PUT') @endif
            <div class="dlg-head"><h3>{{ $r ? __('Edit role — :name', ['name' => __($r->name)]) : __('New role') }}</h3><button type="button" class="icon-btn" data-close><x-icon name="x" /></button></div>
            <div class="dlg-body">
                <div class="field" style="margin-bottom:14px"><label>{{ __('Role name') }} <span class="req">*</span></label><input name="name" value="{{ $r?->name }}" required maxlength="64"></div>
                <div class="label" style="margin-bottom:8px">{{ __('Permissions') }}</div>
                <div class="grid g-2" style="gap:8px 16px">
                    @foreach($permissions as $key => $label)
                        <label class="check" style="align-items:flex-start;padding:8px 10px;border:1px solid var(--border);border-radius:8px">
                            <input type="checkbox" name="permissions[]" value="{{ $key }}" @checked($r && $r->permissions->contains('name', $key))>
                            <span>{{ __($label) }}<span class="sub mono small muted" style="display:block">{{ $key }}</span></span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="dlg-foot"><button type="button" class="btn btn-ghost" data-close>{{ __('Cancel') }}</button><button class="btn btn-primary">{{ __('Save role') }}</button></div>
        </form>
    </dialog>
@endforeach
@endsection
