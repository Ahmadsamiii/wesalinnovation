<x-app-layout>
    @php($pageTitle = $isClient ? 'حالة مشروعي' : (auth()->user()->hasRole('pm') ? 'مشاريعي' : 'المشاريع'))
    <x-slot:title>{{ $pageTitle }}</x-slot:title>

    <x-page-header :title="$pageTitle" :description="$isClient ? 'مشاريعك مع وصال الابتكار ومدى تقدّمها.' : null">
        <x-slot:actions>
            @can('create', \App\Models\Project::class)
                <x-button :href="route('projects.create')">مشروع جديد</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @unless ($isClient)
        <form method="GET" action="{{ route('projects.index') }}" class="mb-6 grid gap-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:grid-cols-3" role="search">
            <x-form.input name="q" label="بحث باسم المشروع" type="search" :value="$filters['q'] ?? null" />
            <x-form.select name="status" label="الحالة" placeholder="كل الحالات" :value="$filters['status'] ?? null"
                           :options="collect(\App\Enums\ProjectStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()" />
            <div class="flex items-end gap-2">
                <x-button type="submit">تصفية</x-button>
                @if (array_filter($filters))
                    <x-button variant="ghost" :href="route('projects.index')">مسح</x-button>
                @endif
            </div>
        </form>
    @endunless

    @if ($projects->isEmpty())
        <x-empty-state :title="$isClient ? 'لا مشاريع مرتبطة بحسابك بعد.' : 'لا مشاريع هنا بعد.'">
            @can('create', \App\Models\Project::class)
                <x-button :href="route('projects.create')">أنشئ أول مشروع</x-button>
            @endcan
        </x-empty-state>
    @else
        <ul class="grid gap-4 md:grid-cols-2" role="list">
            @foreach ($projects as $project)
                <li>
                    <a href="{{ route('projects.show', $project) }}"
                       class="block h-full rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-brand-300 hover:shadow focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-600">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <h2 class="font-semibold text-ink">{{ $project->name }}</h2>
                            <div class="flex gap-1">
                                <x-badge :color="$project->status->color()">{{ $project->status->label() }}</x-badge>
                                @if ($project->isOverdue())
                                    <x-badge color="red">متأخر</x-badge>
                                @endif
                            </div>
                        </div>

                        <dl class="mt-3 grid grid-cols-2 gap-2 text-xs text-gray-600">
                            <div><dt class="inline text-gray-500">المدير:</dt> <dd class="inline">{{ $project->pm->name }}</dd></div>
                            @unless ($isClient)
                                <div><dt class="inline text-gray-500">العميل:</dt> <dd class="inline">{{ $project->client?->name ?? 'داخلي' }}</dd></div>
                            @endunless
                            <div><dt class="inline text-gray-500">البداية:</dt> <dd class="inline"><x-date :value="$project->start_date" /></dd></div>
                            <div><dt class="inline text-gray-500">النهاية:</dt> <dd class="inline"><x-date :value="$project->end_date" /></dd></div>
                        </dl>

                        <x-progress class="mt-4" :value="$project->progress()" />
                    </a>
                </li>
            @endforeach
        </ul>

        @if ($projects->hasPages())
            <div class="mt-6">{{ $projects->links() }}</div>
        @endif
    @endif
</x-app-layout>
