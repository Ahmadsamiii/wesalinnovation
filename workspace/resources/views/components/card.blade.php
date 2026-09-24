@props(['title' => null, 'padding' => true])

<section {{ $attributes->merge(['class' => 'bg-white rounded-xl border border-gray-200 shadow-sm']) }}>
    @if ($title || isset($actions))
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4">
            @if ($title)
                <h2 class="text-base font-semibold text-ink">{{ $title }}</h2>
            @endif
            @isset($actions)
                <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    <div @class(['p-5' => $padding])>
        {{ $slot }}
    </div>
</section>
