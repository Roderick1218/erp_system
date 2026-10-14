{{-- Filter bar shared by list and board. $keepView = 'list'|'board' --}}
<form method="get" class="filters">
    <input type="hidden" name="view" value="{{ $keepView }}">
    @if($keepView === 'list')<input type="hidden" name="status" value="{{ request('status') }}">@endif
    <div class="filters-title"><x-icon name="funnel" class="ico ico-sm" />{{ __('Filters') }}</div>
    <div class="filter-row">
        <div class="field grow"><label>{{ __('Search') }}</label><input name="q" value="{{ request('q') }}" placeholder="{{ __('Project code, name, site, customer, phone…') }}"></div>
        <div class="field"><label>{{ __('Start from') }}</label><input type="date" name="from" value="{{ request('from') }}"></div>
        <div class="field"><label>{{ __('Start to') }}</label><input type="date" name="to" value="{{ request('to') }}"></div>
        <div class="field"><label>{{ __('Branch') }}</label><select name="branch"><option value="">{{ __('All') }}</option>@foreach($branches as $id => $name)<option value="{{ $id }}" @selected(request('branch') == $id)>{{ $name }}</option>@endforeach</select></div>
        <div class="field"><label>{{ __('Category') }}</label><select name="category"><option value="">{{ __('All') }}</option>@foreach(['normal' => 'Normal', 'temporary' => 'Temporary', 'aftercare' => 'Aftercare'] as $k => $v)<option value="{{ $k }}" @selected(request('category') === $k)>{{ __($v) }}</option>@endforeach</select></div>
        <div class="field"><label>{{ __('Manager') }}</label><select name="manager"><option value="">{{ __('All') }}</option>@foreach($managers as $id => $name)<option value="{{ $id }}" @selected(request('manager') == $id)>{{ $name }}</option>@endforeach</select></div>
        <div class="field"><label>{{ __('State') }}</label><select name="state"><option value="">{{ __('All') }}</option>@foreach(config('erp.states') as $s)<option value="{{ $s }}" @selected(request('state') === $s)>{{ __($s) }}</option>@endforeach</select></div>
        <div class="actions">
            @if(request()->except('page', 'status', 'view'))<a class="btn btn-ghost" href="{{ route('projects.index', ['view' => $keepView]) }}">{{ __('Reset') }}</a>@endif
            <button class="btn btn-primary"><x-icon name="search" class="ico ico-sm" />{{ __('Search') }}</button>
        </div>
    </div>
</form>
