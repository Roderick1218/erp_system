{{-- Status pill: <x-badge :status="$row->status" /> — colour comes from App\Support\Status. --}}
@props(['status', 'label' => null, 'tone' => null])
<span {{ $attributes->merge(['class' => 'badge badge-'.($tone ?? status_tone($status))]) }}>{{ $label ?? \App\Support\Status::label($status) }}</span>
