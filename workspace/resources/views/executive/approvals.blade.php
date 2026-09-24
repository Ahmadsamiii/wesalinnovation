<x-app-layout>
    <x-slot:title>اعتماد المشاريع</x-slot:title>

    <x-page-header title="اعتماد المشاريع" description="ما ينتظر قرارك، ثم ما يحتاج انتباهك من المشاريع الجارية.">
        <x-slot:actions>
            <x-button variant="secondary" :href="route('projects.index')">كل المشاريع</x-button>
        </x-slot:actions>
    </x-page-header>

    <section aria-labelledby="pending-heading" class="mb-8">
        <h2 id="pending-heading" class="mb-3 flex items-center gap-2 text-lg font-semibold text-ink">
            بانتظار قرارك <x-badge color="yellow">{{ $pending->count() }}</x-badge>
        </h2>

        @forelse ($pending as $project)
            @php($previous = $project->decisions->first())
            <x-card class="mb-4">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <h3 class="text-base font-semibold"><a href="{{ route('projects.show', $project) }}" class="text-brand-800 hover:underline">{{ $project->name }}</a></h3>
                        <p class="mt-1 text-xs text-gray-500">
                            {{ $project->pm->name }} — {{ $project->client?->name ?? 'مشروع داخلي' }} — قُدّم <x-date :value="$project->submitted_at" relative />
                        </p>
                        @if ($project->description)
                            <p class="mt-3 line-clamp-3 text-sm leading-7 text-gray-700">{{ $project->description }}</p>
                        @endif
                        <dl class="mt-3 flex flex-wrap gap-x-6 gap-y-1 text-sm">
                            <div><dt class="inline text-gray-500">الميزانية:</dt> <dd class="inline font-medium">{{ $project->budget !== null ? number_format((float) $project->budget, 2).' ر.س' : '—' }}</dd></div>
                            <div><dt class="inline text-gray-500">المدة:</dt> <dd class="inline"><x-date :value="$project->start_date" /> ← <x-date :value="$project->end_date" /></dd></div>
                            <div><dt class="inline text-gray-500">الأولوية:</dt> <dd class="inline">{{ $project->priority->label() }}</dd></div>
                            <div><dt class="inline text-gray-500">المعالم:</dt> <dd class="inline">{{ $project->milestones_count }}</dd></div>
                            <div><dt class="inline text-gray-500">الفريق:</dt> <dd class="inline">{{ $project->members_count + 1 }}</dd></div>
                        </dl>
                        @if ($previous?->type === \App\Enums\ProjectDecisionType::Rejected)
                            <p class="mt-3 rounded-lg bg-orange-50 p-3 text-xs text-orange-800">إعادة تقديم بعد رفض سابق: {{ $previous->note }}</p>
                        @endif
                    </div>

                    <form method="POST" action="{{ route('projects.decide', $project) }}" class="w-full space-y-3 sm:w-80">
                        @csrf
                        <x-form.textarea name="note" label="التعليل" rows="2" :id="'note-'.$project->id" hint="مطلوب عند الرفض." />
                        <div class="flex gap-2">
                            <x-button type="submit" name="type" value="approved" variant="success">اعتماد</x-button>
                            <x-button type="submit" name="type" value="rejected" variant="danger">رفض</x-button>
                        </div>
                    </form>
                </div>
            </x-card>
        @empty
            <x-empty-state title="لا مشاريع بانتظار الاعتماد." />
        @endforelse
    </section>

    @if ($attention->isNotEmpty())
        <section aria-labelledby="attention-heading" class="mb-8">
            <h2 id="attention-heading" class="mb-3 text-lg font-semibold text-ink">تحتاج انتباهك</h2>
            <x-card :padding="false">
                <ul class="divide-y divide-gray-100" role="list">
                    @foreach ($attention as $project)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3 text-sm">
                            <span class="flex items-center gap-2">
                                <a href="{{ route('projects.show', $project) }}" class="font-medium text-brand-800 hover:underline">{{ $project->name }}</a>
                                <x-badge :color="$project->status->color()">{{ $project->status->label() }}</x-badge>
                                @if ($project->isOverdue())
                                    <x-badge color="red">تجاوز موعده <x-date :value="$project->end_date" /></x-badge>
                                @endif
                            </span>
                            <x-progress :value="$project->progress()" class="w-48" />
                        </li>
                    @endforeach
                </ul>
            </x-card>
        </section>
    @endif

    <section aria-labelledby="recent-heading">
        <div class="mb-3 flex items-center justify-between">
            <h2 id="recent-heading" class="text-lg font-semibold text-ink">آخر القرارات</h2>
            <x-button variant="ghost" size="sm" :href="route('decisions.index')">سجل القرارات كاملاً</x-button>
        </div>
        @include('executive._decisions', ['decisions' => $recent])
    </section>
</x-app-layout>
