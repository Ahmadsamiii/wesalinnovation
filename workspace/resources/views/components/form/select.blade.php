@props(['name', 'label', 'options' => [], 'value' => null, 'placeholder' => null, 'hint' => null, 'required' => false, 'bag' => 'default'])

@php
$key = str_replace(['[', ']'], ['.', ''], $name);
$id = $attributes->get('id', 'field-'.str_replace('.', '-', $key));
$error = $errors->getBag($bag)->first($key);
$describedBy = trim(($hint ? "{$id}-hint " : '').($error ? "{$id}-error" : ''));
$current = (string) old($key, $value instanceof \BackedEnum ? $value->value : $value);
@endphp

<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" class="block text-sm font-medium text-gray-700">
        {{ $label }}
        @if ($required)
            <span class="text-red-600" aria-hidden="true">*</span>
        @endif
    </label>
    <select id="{{ $id }}" name="{{ $name }}"
            @required($required)
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @if ($error) aria-invalid="true" @endif
            {{ $attributes->except(['class', 'id']) }}
            @class([
                'mt-1 block w-full rounded-lg shadow-sm focus:border-brand-600 focus:ring-brand-600',
                'border-red-500' => $error,
                'border-gray-300' => ! $error,
            ])>
        @if (! is_null($placeholder))
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected($current === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @if ($hint)
        <p id="{{ $id }}-hint" class="mt-1 text-xs text-gray-500">{{ $hint }}</p>
    @endif
    @if ($error)
        <p id="{{ $id }}-error" class="mt-1 text-sm text-red-600">{{ $error }}</p>
    @endif
</div>
