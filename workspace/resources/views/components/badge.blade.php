@props(['color' => 'gray'])

@php
$classes = match ($color) {
    'green' => 'bg-green-50 text-green-700 ring-green-600/20',
    'red' => 'bg-red-50 text-red-700 ring-red-600/20',
    'yellow' => 'bg-yellow-50 text-yellow-800 ring-yellow-600/20',
    'orange' => 'bg-orange-50 text-orange-700 ring-orange-600/20',
    'blue' => 'bg-sky-50 text-sky-700 ring-sky-600/20',
    'brand' => 'bg-brand-50 text-brand-800 ring-brand-600/20',
    'purple' => 'bg-purple-50 text-purple-700 ring-purple-600/20',
    default => 'bg-gray-50 text-gray-700 ring-gray-500/20',
};
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset {$classes}"]) }}>{{ $slot }}</span>
