@props(['variant' => 'primary', 'href' => null, 'type' => 'button', 'size' => 'md'])

@php
$base = 'inline-flex items-center justify-center gap-2 rounded-lg font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50';

$sizes = match ($size) {
    'sm' => 'px-3 py-1.5 text-xs',
    default => 'px-4 py-2 text-sm',
};

$variants = match ($variant) {
    'secondary' => 'border border-gray-300 bg-white text-gray-700 shadow-sm hover:bg-gray-50 focus-visible:ring-brand-600',
    'danger' => 'bg-red-600 text-white shadow-sm hover:bg-red-500 focus-visible:ring-red-600',
    'success' => 'bg-green-600 text-white shadow-sm hover:bg-green-500 focus-visible:ring-green-600',
    'ghost' => 'text-brand-800 hover:bg-brand-50 focus-visible:ring-brand-600',
    default => 'bg-brand-gradient text-white shadow-sm shadow-brand-tertiary/25 hover:brightness-110 active:brightness-95 focus-visible:ring-brand-600',
};

$classes = "{$base} {$sizes} {$variants}";
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
