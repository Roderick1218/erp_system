<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Sign in') }} · ERP System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body>
<div class="auth">
    <header class="auth-top">
        <div class="row" style="gap:12px">
            <span class="brand-mark" style="width:40px;height:40px">ERP</span>
            <div class="brand-text"><b style="font-size:17px">ERP <span>System</span></b><small>Enterprise Resource Planning</small></div>
        </div>
        <div class="auth-lang" role="group" aria-label="Language / 语言">
            @foreach(\App\Http\Middleware\SetLocale::LOCALES as $code => $label)
                <form method="post" action="{{ route('locale', $code) }}" style="display:contents">@csrf
                    <button type="submit" class="{{ app()->getLocale() === $code ? 'active' : '' }}" @if(app()->getLocale() === $code) aria-current="true" @endif>{{ $label }}</button>
                </form>
            @endforeach
        </div>
    </header>

    <div class="auth-body">
        <section class="auth-hero">
            <span class="pill"><x-icon name="sparkles" class="ico ico-sm" />{{ __('Business management system') }}</span>
            <h1>{{ __('Enquiries, projects, stock and people —') }} <em>{{ __('all in one system.') }}</em></h1>
            <p>{{ __('From the first enquiry to the final invoice, every team works from the same live data.') }}</p>
            <div class="auth-features">
                @foreach([
                    ['funnel', 'Sales & quotations', 'Enquiry → inspection → quotation → project → invoice'],
                    ['cube', 'Live inventory', 'Live stock by warehouse, issued straight to project cost'],
                    ['identification', 'People & payroll', 'Attendance, leave, payroll & permit-expiry alerts'],
                    ['chart', 'Project costing', 'Budget vs actual costing for every project'],
                ] as [$icon, $title, $text])
                    <div class="auth-feature">
                        <span class="ico-tile"><x-icon :name="$icon" /></span>
                        <div><b>{{ __($title) }}</b><span>{{ __($text) }}</span></div>
                    </div>
                @endforeach
            </div>
        </section>

        <main class="auth-card">
            <span class="card-ico"><x-icon name="lock" /></span>
            <h2>{{ __('Welcome back') }}</h2>
            <p class="lead">{{ __('Sign in with your company account.') }}</p>
            @if($errors->any())<div class="alert alert-danger"><x-icon name="exclamation" />{{ $errors->first() }}</div>@endif
            <form method="post" action="{{ route('login') }}" class="stack">
                @csrf
                <div class="field">
                    <label for="email">{{ __('Email') }}</label>
                    <div class="input-icon">
                        <x-icon name="user" />
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="name@company.com">
                    </div>
                </div>
                <div class="field">
                    <label for="password">{{ __('Password') }}</label>
                    <div class="input-icon">
                        <x-icon name="key" />
                        <input id="password" type="password" name="password" required autocomplete="current-password" data-pw>
                        <button type="button" class="pw-toggle" aria-pressed="false" aria-label="{{ __('Show password') }}" title="{{ __('Show password') }}"
                                data-show="{{ __('Show password') }}" data-hide="{{ __('Hide password') }}"><x-icon name="eye" /></button>
                    </div>
                </div>
                <label class="check"><input type="checkbox" name="remember" value="1"> {{ __('Keep me signed in') }}</label>
                <button class="btn btn-primary btn-lg btn-block" type="submit">{{ __('Sign in') }} <x-icon name="arrow-right" /></button>
            </form>
            <p class="auth-help"><x-icon name="info" class="ico ico-sm" /><span>{{ __('Forgot your password? Ask HR or the system admin to reset it, then change it under My profile.') }}</span></p>
            @if(app()->isLocal())
                <div class="demo-box">
                    {{ __('Demo accounts — click to fill (password: password)') }}
                    <div class="demo-grid">
                        @foreach(['admin@erp.local' => 'Admin', 'manager@erp.local' => 'Manager', 'sales@erp.local' => 'Salesman', 'store@erp.local' => 'Storekeeper', 'hr@erp.local' => 'HR', 'staff@erp.local' => 'Staff'] as $email => $role)
                            <button type="button" title="{{ $email }}" data-fill="{{ $email }}">{{ __($role) }}</button>
                        @endforeach
                    </div>
                </div>
            @endif
        </main>
    </div>

    <footer class="auth-foot">
        <span>© {{ date('Y') }} ERP System</span>
        <span class="row" style="gap:6px"><x-icon name="shield" class="ico ico-sm" />{{ __('Secure sign-in') }} · v2.0</span>
    </footer>
</div>
<script>
    document.querySelector('.pw-toggle').addEventListener('click', function () {
        var input = document.getElementById('password'), show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        this.setAttribute('aria-pressed', show);
        this.setAttribute('aria-label', show ? this.dataset.hide : this.dataset.show);
        this.title = this.getAttribute('aria-label');
    });
    document.querySelectorAll('[data-fill]').forEach(function (b) {
        b.addEventListener('click', function () {
            document.getElementById('email').value = b.dataset.fill;
            document.getElementById('password').value = 'password';
            document.querySelector('form[action$="/login"] button[type=submit]').focus();
        });
    });
</script>
</body>
</html>
