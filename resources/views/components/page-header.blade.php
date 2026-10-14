{{-- Navy page header.
     <x-page-header eyebrow="Customers · Profiles" title="Customer Profile" :count="$rows->total()" sub="...">
         <x-slot:actions> buttons </x-slot:actions>
     </x-page-header> --}}
@props(['eyebrow' => null, 'title', 'count' => null, 'sub' => null])
<div {{ $attributes->merge(['class' => 'page-head']) }}>
    <div>
        @if($eyebrow)
            <div class="eyebrow"><svg width="9" height="9" viewBox="0 0 10 10"><rect x="1" y="1" width="8" height="8" transform="rotate(45 5 5)" fill="none" stroke="currentColor" stroke-width="1.4"/></svg>{{ $eyebrow }}</div>
        @endif
        <h1>{{ $title }} @if(!is_null($count))<span class="count-chip">{{ is_numeric($count) ? number_format($count) : $count }}</span>@endif {{ $badge ?? '' }}</h1>
        @if($sub)<div class="sub">{{ $sub }}</div>@endif
    </div>
    @isset($actions)
        <div class="page-actions">{{ $actions }}</div>
    @endisset
</div>
