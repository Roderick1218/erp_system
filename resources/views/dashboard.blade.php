@extends('layouts.app')
@section('title', __('Dashboard'))
@section('content')
@php($u = auth()->user())
<div class="grid g-side-main">
    {{-- ============ Left rail: me ============ --}}
    <div class="stack">
        <div class="hero">
            <div class="eyebrow" style="color:#8fd0b0"><span class="live-dot"></span>&nbsp; ERP System</div>
            <h2>{{ __('Welcome,') }} {{ $u->employee?->display_name ?? $u->name }}</h2>
            <div class="clock" data-clock>{{ now()->format('l, d M Y · H:i:s') }}</div>
            <div class="small" style="margin-top:10px;color:#b9cbe6">{{ __('Branch') }} <b style="color:#fff">{{ $u->branch->legal_name ?? '—' }}</b></div>
        </div>

        <div class="panel clock-card">
            <div class="panel-head"><h3><x-icon name="clock" />{{ __('Today\'s attendance') }}</h3><span class="panel-count">{{ d($today) }}</span></div>
            <div class="panel-body">
                @if($u->employee_id)
                    <div class="times">
                        <span>IN <b>{{ $attendance?->clock_in?->format('H:i:s') ?? '--:--' }}</b>@if($attendance?->is_late) <x-badge status="late" /> @endif</span>
                        <span>OUT <b>{{ $attendance?->clock_out?->format('H:i:s') ?? '--:--' }}</b></span>
                    </div>
                    @if(! $attendance?->clock_out)
                        <form method="post" action="{{ route('attendance.clock') }}">@csrf
                            <button class="btn btn-lg btn-block {{ $attendance ? 'btn-danger' : 'btn-success' }}">
                                <x-icon name="{{ $attendance ? 'logout' : 'clock' }}" />{{ $attendance ? __('Clock Out') : __('Clock In') }}
                            </button>
                        </form>
                        <div class="hint small muted" style="margin-top:8px">{{ __('Nothing is recorded until you press the button.') }}</div>
                    @else
                        <div class="alert alert-success" style="margin:0"><x-icon name="check" />{{ __('Done for today — :h h worked.', ['h' => qty($attendance->hours)]) }}</div>
                    @endif
                @else
                    <x-empty icon="user" :title="__('No employee profile linked')">{{ __('Ask HR to link your login to an employee record to use clock-in.') }}</x-empty>
                @endif
            </div>
        </div>

        <div class="panel">
            <div class="panel-head navy"><h3><x-icon name="flag" />{{ __('Upcoming holidays') }}</h3>@can('hr.manage')<a class="small" style="color:#b9cbe6" href="{{ route('holidays.index') }}">{{ __('Manage') }}</a>@endcan</div>
            <ul class="list">
                @forelse($holidays as $h)
                    <li><span>{{ __($h->name) }}</span><span class="date">{{ $h->date->translatedFormat('D, d M') }}</span></li>
                @empty
                    <li class="muted">{{ __('No upcoming holidays.') }}</li>
                @endforelse
            </ul>
        </div>
    </div>

    {{-- ============ Main: business ============ --}}
    <div class="stack">
        @if($kpis)
            <div class="kpis">
                @foreach($kpis as $k)<x-stat :label="$k['label']" :value="$k['value']" :icon="$k['icon']" :tone="$k['tone']" :hint="$k['hint']" :href="$k['href']" />@endforeach
            </div>
        @endif

        <div class="grid g-2">
            <div class="panel">
                <div class="panel-head navy">
                    <h3><x-icon name="megaphone" />{{ __('Announcements') }}</h3>
                    <form method="get"><select name="year" class="input" style="height:30px;width:auto" data-autosubmit>@foreach($announcementYears as $y)<option @selected($y == $year)>{{ $y }}</option>@endforeach</select></form>
                </div>
                <ul class="list scroll-box">
                    @forelse($announcements as $a)
                        <li style="display:block">
                            <div class="date">{{ $a->published_on->format('Y-m-d') }} @if($a->is_pinned)<span class="tag">{{ __('Pinned') }}</span>@endif</div>
                            <details><summary style="cursor:pointer;font-weight:500">{{ $a->title }}</summary><p class="small muted" style="margin:6px 0 0;white-space:pre-line">{{ $a->body }}</p></details>
                        </li>
                    @empty
                        <li><x-empty icon="megaphone" :title="__('No announcements in :year', ['year' => $year])" /></li>
                    @endforelse
                </ul>
            </div>

            <div class="panel">
                <div class="panel-head navy"><h3><x-icon name="inbox" />{{ __('Pending work') }}</h3><span class="panel-count">{{ __(':n open', ['n' => count($pending)]) }}</span></div>
                <ul class="list scroll-box">
                    @forelse($pending as $p)
                        <li><span style="display:flex;gap:10px;min-width:0"><x-icon :name="$p['icon']" class="ico text-{{ $p['tone'] }}" /><span style="min-width:0"><a class="li-link" href="{{ $p['url'] }}">{{ $p['title'] }}</a><div class="meta">{{ $p['meta'] }}</div></span></span></li>
                    @empty
                        <li><x-empty icon="check-badge" :title="__('Nothing pending')">{{ __('You\'re all caught up.') }}</x-empty></li>
                    @endforelse
                </ul>
            </div>
        </div>

        @if(isset($pipeline) || isset($revenue))
            <div class="grid g-2">
                @isset($pipeline)
                    @php($maxN = max(1, $pipeline->max('n')))
                    <div class="panel">
                        <div class="panel-head"><h3><x-icon name="funnel" />{{ __('Sales pipeline') }}</h3><span class="panel-count">{{ __('Win rate') }} {{ $winRate !== null ? $winRate.'%' : '—' }}</span></div>
                        <div class="panel-body">
                            <table class="table compact" aria-label="{{ __('Enquiries by stage') }}">
                                <tbody>
                                @foreach($pipeline as $key => $s)
                                    <tr title="{{ __($s['label']) }}: {{ $s['n'] }} · {{ money($s['amt']) }}">
                                        <td style="width:100px"><a href="{{ route('enquiries.index', ['stage' => $key]) }}">{{ __($s['label']) }}</a></td>
                                        <td><div class="progress" style="height:12px"><span style="width:{{ $s['n'] / $maxN * 100 }}%;border-radius:0 4px 4px 0;{{ $key === 'lost' ? 'background:var(--neutral)' : '' }}"></span></div></td>
                                        <td class="num" style="width:40px"><b>{{ $s['n'] }}</b></td>
                                        <td class="num muted small" style="width:110px">{{ money($s['amt']) }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endisset
                @isset($revenue)
                    @php($maxR = max(1, $revenue->max('amt')))
                    <div class="panel">
                        <div class="panel-head"><h3><x-icon name="trending" />{{ __('Invoiced — last 6 months') }}</h3><span class="panel-count">{{ __('Collected this month') }} {{ money($collected) }}</span></div>
                        <div class="panel-body">
                            <div style="display:flex;align-items:flex-end;gap:14px;height:170px;padding-top:18px;border-bottom:1px solid var(--border)" role="img" aria-label="{{ __('Invoiced amount per month') }}">
                                @foreach($revenue as $r)
                                    <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%" title="{{ $r['full'] }}: {{ money($r['amt']) }}">
                                        <span class="small muted" style="font-variant-numeric:tabular-nums;margin-bottom:4px">{{ $r['amt'] ? number_format($r['amt'] / 1000, 1).'k' : '' }}</span>
                                        <span style="display:block;width:100%;max-width:42px;height:{{ max(2, $r['amt'] / $maxR * 120) }}px;background:var(--accent);border-radius:4px 4px 0 0"></span>
                                    </div>
                                @endforeach
                            </div>
                            <div style="display:flex;gap:14px;margin-top:6px">@foreach($revenue as $r)<span class="small muted" style="flex:1;text-align:center">{{ $r['label'] }}</span>@endforeach</div>
                        </div>
                    </div>
                @endisset
            </div>
        @endif

        @isset($birthdays)
            <div class="panel">
                <div class="panel-head navy">
                    <h3><x-icon name="cake" />{{ __('Birthdays') }} <span class="count-chip">{{ $birthdays->count() }}</span></h3>
                    <form method="get"><input type="hidden" name="year" value="{{ $year }}">
                        <select name="month" class="input" style="height:30px;width:auto" data-autosubmit>@foreach(range(1, 12) as $m)<option value="{{ $m }}" @selected($m == $month)>{{ \Illuminate\Support\Carbon::create(null, $m, 1)->translatedFormat('F') }}</option>@endforeach</select>
                    </form>
                </div>
                <div class="table-wrap scroll-box">
                    <table class="table table-navy compact">
                        <thead><tr><th>{{ __('Employee') }}</th><th>{{ __('Position') }}</th><th class="num">{{ __('Date of birth') }}</th></tr></thead>
                        <tbody>
                        @forelse($birthdays as $e)
                            <tr data-href="{{ route('employees.show', $e) }}"><td class="primary">{{ $e->display_name }} <span class="muted small">({{ $e->gender === 'F' ? __('Female') : __('Male') }})</span><span class="sub">{{ $e->email }}</span></td><td>{{ __($e->position) }}</td><td class="num mono">{{ $e->dob->format('Y-m-d') }}</td></tr>
                        @empty
                            <tr><td colspan="3"><x-empty icon="cake" :title="__('No birthdays this month')" /></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid g-2" id="expiry">
                @foreach($expiry as $block)
                    <div class="panel">
                        <div class="panel-head navy"><h3><x-icon name="identification" />{{ __($block['title']) }} <span class="count-chip">{{ $block['rows']->count() }}</span></h3><span class="panel-count">{{ __('next 60 days') }}</span></div>
                        <div class="table-wrap">
                            <table class="table table-navy compact">
                                <thead><tr><th>{{ __('Employee') }}</th><th class="num">{{ __('Detail') }}</th></tr></thead>
                                <tbody>
                                @forelse($block['rows'] as $e)
                                    @php($exp = $e->{$block['col']})
                                    <tr data-href="{{ route('employees.show', $e) }}">
                                        <td class="primary">{{ $e->display_name }}<span class="sub">{{ __('Nationality') }}: {{ $e->nationality }}</span></td>
                                        <td class="num"><span class="mono small muted">{{ $e->{$block['numberCol']} ?? '—' }}</span><br>
                                            <b class="{{ $exp->isPast() ? 'text-danger' : (now()->diffInDays($exp) < 14 ? 'text-warning' : '') }}">{{ $exp->format('Y-m-d') }}</b>
                                            @if($exp->isPast())<x-badge status="expired" />@endif</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2" class="muted small" style="padding:16px">{{ __('Nothing expiring in the next 60 days.') }}</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            </div>
        @endisset
    </div>
</div>
@endsection
