<x-app-layout>
    <x-slot:title>نظرة عامة</x-slot:title>

    <x-page-header title="نظرة عامة" :description="'مرحباً '.auth()->user()->name.'.'" />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach (auth()->user()->roleTabs() as $key => $tab)
            @continue($tab['route'] === 'dashboard')
            <a href="{{ Route::has($tab['route']) ? route($tab['route']) : route('sections.show', $key) }}"
               class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-brand-300 hover:shadow focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-600">
                <span class="font-semibold text-ink">{{ $tab['label'] }}</span>
            </a>
        @endforeach
    </div>
</x-app-layout>
