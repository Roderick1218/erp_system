<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('Dashboard')) · ERP System</title>
    <script>
        (function () { try {
            var t = localStorage.getItem('erp.theme'); if (t) document.documentElement.dataset.theme = t;
            if (localStorage.getItem('erp.sidebar') === 'collapsed') document.documentElement.classList.add('sb-collapsed');
        } catch (e) {} })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    @stack('head')
</head>
<body>
@php
    $u = auth()->user();
    $quick = array_filter([
        [__('Customer'), 'customers.create', 'customers.manage', 'users'],
        [__('Enquiry'), 'enquiries.create', 'customers.manage', 'funnel'],
        [__('Quotation'), 'quotations.create', 'sales.manage', 'document'],
        [__('Invoice'), 'invoices.create', 'sales.manage', 'receipt'],
        [__('Project'), 'projects.create', 'projects.manage', 'briefcase'],
        [__('Service request'), 'service-requests.create', 'service.manage', 'wrench'],
        [__('Purchase order'), 'purchase-orders.create', 'inventory.manage', 'truck'],
    ], fn ($q) => $u->can($q[2]) && Route::has($q[1]));
    $otherLocale = app()->getLocale() === 'zh_CN' ? 'en' : 'zh_CN';
@endphp
<div class="app">
    <aside class="sidebar" aria-label="{{ __('Main navigation') }}">
        <a href="{{ route('dashboard') }}" class="sidebar-brand">
            <span class="brand-mark">ERP</span>
            <span class="brand-text"><b>ERP <span>System</span></b><small>v2</small></span>
        </a>
        <nav class="sidebar-nav">
            @foreach($navMenu as $entry)
                @if(isset($entry['route']))
                    <a href="{{ $entry['url'] }}" class="nav-link {{ $entry['active'] ? 'active' : '' }}" title="{{ $entry['label'] }}">
                        <x-icon :name="$entry['icon']" /><span class="nav-label">{{ $entry['label'] }}</span>
                    </a>
                @else
                    <details class="nav-group {{ $entry['active'] ? 'has-active' : '' }}" @if($entry['active']) open @endif>
                        <summary title="{{ $entry['group'] }}"><x-icon :name="$entry['icon']" /><span class="nav-label">{{ $entry['group'] }}</span><x-icon name="chevron-right" class="ico ico-sm chev" /></summary>
                        <div class="nav-sub">
                            @foreach($entry['items'] as $item)
                                <a href="{{ $item['url'] }}" class="{{ $item['active'] ? 'active' : '' }}">{{ $item['label'] }}</a>
                            @endforeach
                        </div>
                    </details>
                @endif
            @endforeach
        </nav>
        <div class="sidebar-foot">
            <button type="button" class="btn btn-sm" data-sidebar-collapse><x-icon name="chevrons-left" class="ico collapse-ico" /><span class="nav-label">{{ __('Collapse') }}</span></button>
        </div>
    </aside>
    <div class="scrim"></div>

    <div class="main">
        <header class="topbar">
            <button type="button" class="icon-btn menu-btn" data-sidebar-open aria-label="{{ __('Open menu') }}"><x-icon name="menu" /></button>
            <div class="crumbs">
                <span>{{ $u->branch->code ?? 'HQ' }}</span><x-icon name="chevron-right" class="ico ico-sm" />
                <b>@yield('crumb', View::yieldContent('title', __('Dashboard')))</b>
            </div>
            <div class="topbar-spacer"></div>
            <button type="button" class="search-trigger" data-palette><x-icon name="search" class="ico ico-sm" /><span>{{ __('Search customers, projects, invoices…') }}</span><kbd>Ctrl K</kbd></button>

            @if($quick)
                <div class="dropdown">
                    <button type="button" class="btn btn-primary btn-sm" data-dropdown><x-icon name="plus" class="ico ico-sm" /><span class="hide-sm">{{ __('New') }}</span></button>
                    <div class="dropdown-menu">
                        <div class="head">{{ __('Quick create') }}</div>
                        @foreach($quick as [$label, $route, $perm, $icon])
                            <a href="{{ route($route) }}"><x-icon :name="$icon" class="ico ico-sm" />{{ $label }}</a>
                        @endforeach
                    </div>
                </div>
            @endif

            <form method="post" action="{{ route('locale', $otherLocale) }}">@csrf
                <button type="submit" class="icon-btn lang-btn" title="{{ $otherLocale === 'zh_CN' ? '切换到中文' : 'Switch to English' }}">{{ $otherLocale === 'zh_CN' ? '中' : 'EN' }}</button>
            </form>
            <button type="button" class="icon-btn" data-theme-toggle title="{{ __('Light / dark') }}"><x-icon name="moon" /></button>

            <div class="dropdown">
                <button type="button" class="icon-btn" data-dropdown aria-label="{{ __('Notifications') }}">
                    <x-icon name="bell" />@if(count($notifications))<span class="dot">{{ count($notifications) }}</span>@endif
                </button>
                <div class="dropdown-menu" style="width:340px">
                    <div class="head">{{ __('Needs your attention') }}</div>
                    @forelse($notifications as $n)
                        <a href="{{ $n['url'] }}" class="notif-item"><x-icon :name="$n['icon']" class="ico text-{{ $n['tone'] }}" /><span>{{ $n['title'] }}<small>{{ $n['text'] }}</small></span></a>
                    @empty
                        <div class="empty" style="padding:18px"><p>{{ __("You're all caught up.") }}</p></div>
                    @endforelse
                </div>
            </div>

            <div class="dropdown">
                <button type="button" class="user-chip" data-dropdown>
                    <span class="avatar">{{ $u->initials }}</span>
                    <span class="who">{{ $u->name }}<small>{{ __(\App\Models\User::TYPES[$u->user_type] ?? $u->user_type) }}{{ $u->getRoleNames()->first() ? ' · '.__($u->getRoleNames()->first()) : '' }}</small></span>
                </button>
                <div class="dropdown-menu">
                    <div class="head">{{ $u->email }}</div>
                    <a href="{{ route('profile.edit') }}"><x-icon name="user" class="ico ico-sm" />{{ __('My profile') }}</a>
                    <a href="{{ route('profile.edit') }}#password"><x-icon name="key" class="ico ico-sm" />{{ __('Change password') }}</a>
                    <a href="{{ route('attendance.index') }}"><x-icon name="clock" class="ico ico-sm" />{{ __('My Attendance') }}</a>
                    <a href="{{ route('leave.index') }}"><x-icon name="calendar" class="ico ico-sm" />{{ __('My Leave') }}</a>
                    <div class="sep"></div>
                    <form method="post" action="{{ route('logout') }}">@csrf<button type="submit"><x-icon name="logout" class="ico ico-sm" />{{ __('Sign out') }}</button></form>
                </div>
            </div>
        </header>

        <main class="content">
            @if($errors->any() && ! View::hasSection('suppress-errors'))
                <div class="alert alert-danger"><x-icon name="exclamation" /><div><b>{{ __('Please check the form.') }}</b><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>
            @endif
            @yield('content')
        </main>
        <footer class="footer">© 2019 – {{ date('Y') }} ERP System v2.0</footer>
    </div>
</div>

<div class="toasts">
    @if(session('ok'))<div class="toast"><x-icon name="check" />{{ session('ok') }}</div>@endif
    @if(session('error'))<div class="toast error"><x-icon name="x" />{{ session('error') }}</div>@endif
</div>

<dialog id="palette" data-search-url="{{ route('search') }}">
    <div class="palette-input"><x-icon name="search" /><input type="text" placeholder="{{ __('Search pages, customers, projects, invoices…') }}" autocomplete="off"><kbd>Esc</kbd></div>
    <div class="palette-results"></div>
    <div class="palette-foot"><span>↑↓ {{ __('navigate') }}</span><span>↵ {{ __('open') }}</span><span>/ {{ __('or') }} Ctrl K {{ __('to open') }}</span></div>
</dialog>
<script type="application/json" id="palette-pages">@json($palettePages)</script>
<script>window.ERP_I18N = {!! json_encode([
    'goTo' => __('Go to'), 'noResults' => __('No results for'), 'movedTo' => __('Moved to'), 'updateFailed' => __('Update failed'),
    'Customers' => __('Customers'), 'Enquiries' => __('Enquiries'), 'Projects' => __('Projects'), 'Quotations' => __('Quotations'),
    'Invoices' => __('Invoices'), 'Stock items' => __('Stock items'), 'Employees' => __('Employees'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!};</script>
<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
@stack('scripts')
</body>
</html>
