@extends('layouts.app')
@section('title', __('Attendance Report'))
@push('head')
<style>
    .att-matrix { border-collapse: collapse; font-size: 11.5px; width: 100%; }
    .att-matrix th, .att-matrix td { border-bottom: 1px solid var(--border); border-right: 1px solid var(--border); text-align: center; padding: 5px 2px; min-width: 26px; }
    .att-matrix th { background: var(--surface-3); font-family: var(--mono); font-size: 10px; color: var(--muted); position: sticky; top: 0; }
    .att-matrix th.we, .att-matrix td.we { background: var(--surface-2); }
    .att-matrix .emp { position: sticky; left: 0; z-index: 2; background: var(--surface); text-align: left; min-width: 210px; padding: 5px 10px; white-space: nowrap; }
    .att-matrix th.emp { z-index: 3; background: var(--surface-3); }
    .att-matrix .tot { font-weight: 700; font-variant-numeric: tabular-nums; min-width: 44px; }
    .c { display: inline-block; min-width: 20px; padding: 1px 0; border-radius: 4px; font-weight: 700; font-size: 10.5px; }
    .c-P { background: var(--success-soft); color: var(--success); }
    .c-L { background: var(--warning-soft); color: var(--warning); }
    .c-A { background: var(--danger-soft); color: var(--danger); }
    .c-LV { background: var(--info-soft); color: var(--info); }
    .c-H, .c-S { color: var(--muted); }
</style>
@endpush
@section('content')
<x-page-header :eyebrow="__('HR').' · '.__('Attendance')" :title="__('Monthly attendance report')" :sub="$start->translatedFormat(app()->getLocale() === 'zh_CN' ? 'Y年n月' : 'F Y').' · '.__(':n active employees', ['n' => $matrix->count()])">
    <x-slot:actions>
        <a class="btn btn-ghost" href="{{ route('attendance.board') }}"><x-icon name="identification" class="ico ico-sm" />{{ __('Daily board') }}</a>
        <a class="btn btn-ghost" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}"><x-icon name="download" class="ico ico-sm" />{{ __('Export CSV') }}</a>
    </x-slot:actions>
</x-page-header>

<div class="panel">
    <form method="get" class="filters">
        <div class="filter-row">
            <div class="field"><label>{{ __('Month') }}</label><input type="month" name="month" value="{{ $month }}" data-autosubmit></div>
            <div class="field grow"><label>{{ __('Search') }}</label><input name="q" value="{{ request('q') }}" placeholder="{{ __('Name or emp no…') }}"></div>
            <div class="field"><label>{{ __('Branch') }}</label><select name="branch" data-autosubmit><option value="">{{ __('All') }}</option>@foreach($branches as $id => $n)<option value="{{ $id }}" @selected(request('branch') == $id)>{{ $n }}</option>@endforeach</select></div>
            <div class="field"><label>{{ __('Department') }}</label><select name="department" data-autosubmit><option value="">{{ __('All') }}</option>@foreach($departments as $id => $n)<option value="{{ $id }}" @selected(request('department') == $id)>{{ __($n) }}</option>@endforeach</select></div>
            <div class="actions"><button class="btn btn-primary"><x-icon name="search" class="ico ico-sm" />{{ __('Apply') }}</button></div>
        </div>
        <div class="row small muted" style="margin-top:10px;gap:14px">
            <span><span class="c c-P">P</span> {{ __('Present') }}</span><span><span class="c c-L">L</span> {{ __('Late') }}</span><span><span class="c c-A">A</span> {{ __('Absent') }}</span>
            <span><span class="c c-LV">LV</span> {{ __('Leave') }}</span><span><span class="c c-H">H</span> {{ __('Holiday') }}</span><span><span class="c c-S">S</span> {{ __('Sunday') }}</span>
        </div>
    </form>
    @if($matrix->count())
        <div class="table-wrap" style="max-height:calc(100vh - 300px)">
            <table class="att-matrix">
                <thead><tr>
                    <th class="emp">{{ __('Employee') }}</th>
                    @foreach($days as $d)<th class="{{ $d->isSunday() ? 'we' : '' }}" title="{{ d($d, 'D, d M') }}">{{ $d->format('d') }}<br>{{ app()->getLocale() === 'zh_CN' ? mb_substr($d->translatedFormat('D'), -1) : substr($d->format('D'), 0, 2) }}</th>@endforeach
                    <th class="tot">{{ __('Pres.') }}</th><th class="tot">{{ __('Late') }}</th><th class="tot">{{ __('Abs.') }}</th><th class="tot">{{ __('Leave') }}</th><th class="tot">{{ __('Hours') }}</th>
                </tr></thead>
                <tbody>
                @foreach($matrix as $r)
                    <tr>
                        <td class="emp"><a href="{{ route('employees.show', $r['e']) }}">{{ $r['e']->display_name }}</a><div class="small muted">{{ $r['e']->emp_no }}</div></td>
                        @foreach($r['cells'] as $date => $cell)
                            <td class="{{ \Illuminate\Support\Carbon::parse($date)->isSunday() ? 'we' : '' }}" title="{{ $date }}{{ $cell['a'] ? ' · '.$cell['a']->clock_in?->format('H:i').'–'.($cell['a']->clock_out?->format('H:i') ?? '?') : '' }}">
                                @if($cell['code'] !== '')<span class="c c-{{ $cell['code'] }}">{{ $cell['code'] }}</span>@endif
                            </td>
                        @endforeach
                        <td class="tot">{{ $r['tot']['P'] + $r['tot']['L'] }}</td>
                        <td class="tot {{ $r['tot']['L'] ? 'text-warning' : '' }}">{{ $r['tot']['L'] }}</td>
                        <td class="tot {{ $r['tot']['A'] ? 'text-danger' : '' }}">{{ $r['tot']['A'] }}</td>
                        <td class="tot">{{ $r['tot']['LV'] }}</td>
                        <td class="tot">{{ qty($r['tot']['hours']) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @else
        <x-empty icon="table" :title="__('No employees match these filters')" />
    @endif
</div>
@endsection
