@props(['caption' => null])

<div {{ $attributes->merge(['class' => 'relative overflow-x-auto']) }}>
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        @if ($caption)
            <caption class="sr-only">{{ $caption }}</caption>
        @endif
        {{ $slot }}
    </table>
</div>
