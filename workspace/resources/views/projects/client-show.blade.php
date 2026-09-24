<x-app-layout>
    <x-slot:title>{{ $project->name }}</x-slot:title>

    <x-project-header :project="$project" />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="التقدّم">
                <x-progress :value="$project->progress()" />
                <p class="mt-3 text-sm text-gray-600">
                    @switch($project->status)
                        @case(\App\Enums\ProjectStatus::Draft)
                        @case(\App\Enums\ProjectStatus::PendingApproval)
                        @case(\App\Enums\ProjectStatus::Rejected)
                            المشروع في مرحلة التخطيط والاعتماد الداخلي.
                            @break
                        @case(\App\Enums\ProjectStatus::Approved)
                            اعتُمد المشروع ويستعد الفريق لبدء التنفيذ.
                            @break
                        @case(\App\Enums\ProjectStatus::InProgress)
                            المشروع قيد التنفيذ.
                            @break
                        @case(\App\Enums\ProjectStatus::OnHold)
                            المشروع متوقف مؤقتاً. تواصل مع مدير المشروع للتفاصيل.
                            @break
                        @case(\App\Enums\ProjectStatus::Completed)
                            أُنجز المشروع بتاريخ <x-date :value="$project->actual_end_date" />.
                            @break
                        @case(\App\Enums\ProjectStatus::Cancelled)
                            أُلغي المشروع.
                            @break
                    @endswitch
                </p>
            </x-card>

            <x-card title="المراحل" :padding="false">
                @if ($project->milestones->isEmpty())
                    <p class="p-5 text-sm text-gray-500">لم تُحدَّد المراحل بعد.</p>
                @else
                    <ol class="divide-y divide-gray-100" role="list">
                        @foreach ($project->milestones as $milestone)
                            <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3 text-sm">
                                <span class="flex items-center gap-2">
                                    @if ($milestone->isReached())
                                        <x-badge color="green">أُنجزت</x-badge>
                                    @else
                                        <x-badge>قادمة</x-badge>
                                    @endif
                                    <span class="font-medium">{{ $milestone->title }}</span>
                                </span>
                                <span class="text-gray-500">
                                    @if ($milestone->isReached())
                                        <x-date :value="$milestone->completed_at" />
                                    @else
                                        الموعد: <x-date :value="$milestone->due_date" empty="يُحدَّد لاحقاً" />
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-card>
        </div>

        <x-card title="بيانات المشروع">
            @if ($project->description)
                <p class="mb-4 whitespace-pre-line text-sm leading-7 text-gray-700">{{ $project->description }}</p>
            @endif
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-2"><dt class="text-gray-500">مدير المشروع</dt><dd>{{ $project->pm->name }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-gray-500">للتواصل</dt><dd dir="ltr"><a href="mailto:{{ $project->pm->email }}" class="text-brand-800 hover:underline">{{ $project->pm->email }}</a></dd></div>
                <div class="flex justify-between gap-2"><dt class="text-gray-500">البداية</dt><dd><x-date :value="$project->actual_start_date ?? $project->start_date" /></dd></div>
                <div class="flex justify-between gap-2"><dt class="text-gray-500">النهاية المتوقعة</dt><dd><x-date :value="$project->end_date" /></dd></div>
            </dl>
        </x-card>
    </div>
</x-app-layout>
