@props(['label', 'value', 'hint' => null, 'href' => null])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-gray-200 bg-white p-5 shadow-sm']) }}>
    <p class="text-sm text-gray-500">{{ $label }}</p>
    <p class="mt-2 text-3xl font-bold text-ink">
        @if ($href)
            <a href="{{ $href }}" class="rounded hover:text-brand-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-600">{{ $value }}</a>
        @else
            {{ $value }}
        @endif
    </p>
    @if ($hint)
        <p class="mt-1 text-xs text-gray-500">{{ $hint }}</p>
    @endif
</div>
