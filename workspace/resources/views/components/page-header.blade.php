@props(['title', 'description' => null])

<div {{ $attributes->merge(['class' => 'mb-6 flex flex-wrap items-start justify-between gap-4']) }}>
    <div class="min-w-0">
        @isset($breadcrumb)
            <div class="mb-1 text-sm text-gray-500">{{ $breadcrumb }}</div>
        @endisset
        <h1 class="text-2xl font-bold text-ink">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1 text-sm text-gray-600">{{ $description }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
