{{-- KPI tile: <x-stat label="Open enquiries" :value="12" icon="funnel" tone="info" hint="3 new this week" :href="route(...)" /> --}}
@props(['label', 'value', 'icon' => 'chart', 'tone' => 'info', 'hint' => null, 'href' => null])
@php($tag = $href ? 'a' : 'div')
<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'stat']) }}>
    <div class="stat-ico tone-{{ $tone }}"><x-icon :name="$icon" /></div>
    <div style="min-width:0">
        <div class="stat-label">{{ $label }}</div>
        <div class="stat-value">{{ $value }}</div>
        @if($hint)<div class="stat-hint">{{ $hint }}</div>@endif
    </div>
</{{ $tag }}>
