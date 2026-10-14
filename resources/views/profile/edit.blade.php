@extends('layouts.app')
@section('title', __('My profile'))
@section('suppress-errors', '1')
@section('content')
<x-page-header :eyebrow="__('Account').' · '.__('Profile')" :title="__('My profile')" :sub="__('Update your own login, contact details, language and password.')" />

<div class="grid g-main-side">
    <div class="stack">
        <form method="post" action="{{ route('profile.update') }}" class="panel" data-main-form>
            @csrf @method('PUT')
            @if($errors->default->any())
                <div class="alert alert-danger" style="margin:16px 20px 0"><x-icon name="exclamation" /><ul style="margin:0">@foreach($errors->default->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
            @endif
            <div class="form-section">
                <h4><span class="step">1</span>{{ __('Login account') }}</h4>
                <div class="form-grid">
                    <x-field name="name" :label="__('Display name')" :value="$user->name" required />
                    <x-field name="email" :label="__('Login email')" type="email" :value="$user->email" required :hint="__('Changing your login email needs your current password below.')" />
                    <x-field name="current_password" :label="__('Current password (only if changing email)')" type="password" />
                    <x-field name="locale" :label="__('Language')" type="select" :options="\App\Http\Middleware\SetLocale::LOCALES" :value="$user->locale" required />
                </div>
            </div>
            @if($employee)
                <div class="form-section">
                    <h4><span class="step">2</span>{{ __('My contact details') }}</h4>
                    <div class="form-grid">
                        <x-field name="nickname" :label="__('Nickname')" :value="$employee->nickname" />
                        <x-field name="phone" :label="__('Mobile phone')" :value="$employee->phone" />
                        <x-field name="personal_email" :label="__('Personal email')" type="email" :value="$employee->email" />
                        <x-field name="emergency_contact" :label="__('Emergency contact (name & phone)')" :value="$employee->emergency_contact" />
                        <x-field name="address" :label="__('Home address')" type="textarea" :value="$employee->address" class="span-all" />
                    </div>
                </div>
            @endif
            <div class="form-actions">
                <button class="btn btn-primary"><x-icon name="check" class="ico ico-sm" />{{ __('Save changes') }}</button>
            </div>
        </form>

        <form method="post" action="{{ route('profile.password') }}" class="panel" id="password">
            @csrf @method('PUT')
            <div class="panel-head"><h3><x-icon name="key" />{{ __('Change password') }}</h3></div>
            <div class="panel-body">
                @if($errors->password->any())
                    <div class="alert alert-danger"><x-icon name="exclamation" /><ul style="margin:0">@foreach($errors->password->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
                @endif
                <div class="form-grid cols-3">
                    <div class="field"><label for="cp">{{ __('Current password') }} <span class="req">*</span></label><input id="cp" type="password" name="current_password" required autocomplete="current-password"></div>
                    <div class="field"><label for="np">{{ __('New password') }} <span class="req">*</span></label><input id="np" type="password" name="password" required minlength="8" autocomplete="new-password"></div>
                    <div class="field"><label for="np2">{{ __('Confirm new password') }} <span class="req">*</span></label><input id="np2" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"></div>
                </div>
                <div class="hint small muted" style="margin-top:8px">{{ __('At least 8 characters, with letters and numbers.') }}</div>
            </div>
            <div class="panel-foot" style="justify-content:flex-end"><button class="btn btn-navy"><x-icon name="lock" class="ico ico-sm" />{{ __('Update password') }}</button></div>
        </form>
    </div>

    <div class="stack">
        <div class="panel">
            <div class="panel-body" style="display:flex;gap:14px;align-items:center">
                <span class="avatar" style="width:52px;height:52px;font-size:18px;border-radius:12px">{{ $user->initials }}</span>
                <div><b style="font-size:16px">{{ $user->name }}</b><div class="small muted">{{ $user->email }}</div>
                    <div style="margin-top:4px"><span class="tag">{{ __(\App\Models\User::TYPES[$user->user_type] ?? $user->user_type) }}</span> @foreach($user->getRoleNames() as $r)<span class="tag">{{ __($r) }}</span>@endforeach</div></div>
            </div>
        </div>
        <div class="panel">
            <div class="panel-head"><h3><x-icon name="identification" />{{ __('Employment record') }}</h3><span class="panel-count">{{ __('maintained by HR') }}</span></div>
            <div class="panel-body">
                @if($employee)
                    <dl class="dl">
                        <dt>{{ __('Employee no.') }}</dt><dd class="mono">{{ $employee->emp_no }}</dd>
                        <dt>{{ __('Full name') }}</dt><dd>{{ $employee->name }}</dd>
                        <dt>{{ __('Position') }}</dt><dd>{{ $employee->position ?? '—' }}</dd>
                        <dt>{{ __('Department') }}</dt><dd>{{ $employee->department?->name ? __($employee->department->name) : '—' }}</dd>
                        <dt>{{ __('Branch') }}</dt><dd>{{ $employee->branch?->legal_name ?? '—' }}</dd>
                        <dt>{{ __('Join date') }}</dt><dd>{{ d($employee->hire_date) }}</dd>
                        <dt>{{ __('Bank account') }}</dt><dd>{{ $employee->bank_name }} {{ $employee->bank_account ? '••••'.substr($employee->bank_account, -4) : '—' }}</dd>
                    </dl>
                    <p class="small muted" style="margin:12px 0 0">{{ __('Need to change your IC, bank account or salary details? Please contact HR.') }}</p>
                @else
                    <x-empty icon="user" :title="__('No employee profile linked')">{{ __('Ask HR to link your login to an employee record.') }}</x-empty>
                @endif
            </div>
        </div>
        <div class="panel">
            <div class="panel-head"><h3><x-icon name="shield" />{{ __('Security') }}</h3></div>
            <div class="panel-body small">
                <dl class="dl">
                    <dt>{{ __('Last sign-in') }}</dt><dd>{{ $user->last_login_at ? $user->last_login_at->format('d M Y H:i') : '—' }}</dd>
                    <dt>{{ __('Account created') }}</dt><dd>{{ d($user->created_at) }}</dd>
                </dl>
            </div>
        </div>
    </div>
</div>
@if($errors->password->any())
    @push('scripts')<script>document.getElementById('password').scrollIntoView();</script>@endpush
@endif
@endsection
