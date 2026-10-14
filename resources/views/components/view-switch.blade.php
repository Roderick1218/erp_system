{{-- List / Board / Calendar switcher that keeps the current filters.
     <x-view-switch :current="$view" :views="['list' => ['List', 'table'], 'board' => ['Board', 'columns']]" /> --}}
@props(['current', 'views', 'param' => 'view'])
<div class="segmented" role="tablist">
    @foreach($views as $key => [$label, $icon])
        @php($url = is_string($key) && str_starts_with($key, 'http') ? $key : request()->fullUrlWithQuery([$param => $key, 'page' => null]))
        <a href="{{ $url }}" class="{{ $current === $key ? 'active' : '' }}"><x-icon :name="$icon" class="ico ico-sm" />{{ $label }}</a>
    @endforeach
</div>
