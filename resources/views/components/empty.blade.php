{{-- Empty state: <x-empty icon="inbox" title="No customers yet">Optional text + buttons</x-empty> --}}
@props(['icon' => 'inbox', 'title' => null])
<div {{ $attributes->merge(['class' => 'empty']) }}>
    <x-icon :name="$icon" />
    <h4>{{ $title ?? __('Nothing here yet') }}</h4>
    @if(trim($slot))<div>{{ $slot }}</div>@endif
</div>
