{{-- Form field with label, old() value and validation error.
     <x-field name="phone" label="Phone" :value="$row->phone" required />
     <x-field name="state" label="State" type="select" :options="$states" placeholder="— Select —" />
     <x-field name="notes" label="Notes" type="textarea" class="span-2" />
     Options may be a list (value = label) or an associative array (value => label). --}}
@props([
    'name', 'label' => null, 'type' => 'text', 'value' => null, 'options' => [], 'placeholder' => null,
    'hint' => null, 'required' => false, 'readonly' => false, 'step' => null, 'min' => null, 'max' => null,
])
@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $current = old($key, $value instanceof \DateTimeInterface ? $value->format($type === 'datetime-local' ? 'Y-m-d\TH:i' : 'Y-m-d') : $value);
    $isList = array_is_list($options);
    $id = 'f_'.str_replace(['[', ']', '.'], '_', $name);
@endphp
<div {{ $attributes->merge(['class' => 'field'.($errors->has($key) ? ' has-error' : '')]) }}>
    @if($label)<label for="{{ $id }}">{{ $label }} @if($required)<span class="req">*</span>@endif</label>@endif
    @if($type === 'select')
        <select id="{{ $id }}" name="{{ $name }}" @required($required) @disabled($readonly)>
            @if(!is_null($placeholder))<option value="">{{ $placeholder }}</option>@endif
            @foreach($options as $k => $v)
                @php($optValue = $isList ? $v : $k)
                <option value="{{ $optValue }}" @selected((string) $current === (string) $optValue)>{{ $v }}</option>
            @endforeach
        </select>
    @elseif($type === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" placeholder="{{ $placeholder }}" @required($required) @readonly($readonly)>{{ $current }}</textarea>
    @else
        <input id="{{ $id }}" type="{{ $type }}" name="{{ $name }}" value="{{ $current }}" placeholder="{{ $placeholder }}"
               @if($step) step="{{ $step }}" @endif @if(!is_null($min)) min="{{ $min }}" @endif @if(!is_null($max)) max="{{ $max }}" @endif
               @required($required) @readonly($readonly)>
    @endif
    @error($key)<div class="error">{{ $message }}</div>@enderror
    @if($hint)<div class="hint">{{ $hint }}</div>@endif
</div>
