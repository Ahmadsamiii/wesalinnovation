<x-app-layout>
    <x-slot:title>معالم {{ $project->name }}</x-slot:title>

    <x-project-header :project="$project" active="milestones" />

    <div class="grid [&>*]:min-w-0 gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            @forelse ($milestones as $milestone)
                <x-card>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="flex items-center gap-2 font-semibold text-ink">
                                <span class="text-gray-400">{{ $loop->iteration }}.</span> {{ $milestone->title }}
                                @if ($milestone->isReached())
                                    <x-badge color="green">بُلغ <x-date :value="$milestone->completed_at" /></x-badge>
                                @elseif ($milestone->isOverdue())
                                    <x-badge color="red">متأخر</x-badge>
                                @endif
                            </h2>
                            @if ($milestone->description)
                                <p class="mt-1 text-sm text-gray-600">{{ $milestone->description }}</p>
                            @endif
                            <p class="mt-2 text-xs text-gray-500">
                                الموعد: <x-date :value="$milestone->due_date" empty="غير محدد" /> —
                                {{ $milestone->done_tasks_count }} من {{ $milestone->tasks_count }} مهام منجزة
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <x-button variant="ghost" size="sm" :href="route('projects.tasks.index', [$project, 'milestone' => $milestone->id, 'view' => 'list'])">المهام</x-button>
                            @can('manage', $project)
                                <form method="POST" action="{{ route('projects.milestones.toggle', [$project, $milestone]) }}">
                                    @csrf
                                    <x-button type="submit" :variant="$milestone->isReached() ? 'secondary' : 'success'" size="sm">
                                        {{ $milestone->isReached() ? 'إلغاء البلوغ' : 'تسجيل البلوغ' }}
                                    </x-button>
                                </form>
                            @endcan
                        </div>
                    </div>

                    @can('manage', $project)
                        <details class="mt-4 border-t border-gray-100 pt-4">
                            <summary class="cursor-pointer text-sm font-medium text-brand-800">تعديل المعلم</summary>
                            <form method="POST" action="{{ route('projects.milestones.update', [$project, $milestone]) }}" class="mt-4 grid gap-4 sm:grid-cols-2">
                                @csrf
                                @method('PUT')
                                <x-form.input name="title" label="العنوان" :value="$milestone->title" required :id="'title-'.$milestone->id" />
                                <x-form.input name="due_date" label="الموعد" type="date" :value="$milestone->due_date?->format('Y-m-d')" :id="'due-'.$milestone->id" />
                                <x-form.textarea name="description" label="الوصف" :value="$milestone->description" rows="2" class="sm:col-span-2" :id="'desc-'.$milestone->id" />
                                <div class="flex gap-2 sm:col-span-2">
                                    <x-button type="submit" size="sm">حفظ</x-button>
                                </div>
                            </form>
                            <form method="POST" action="{{ route('projects.milestones.destroy', [$project, $milestone]) }}" class="mt-2"
                                  onsubmit="return confirm(@js('حذف المعلم «'.$milestone->title.'»؟ تبقى مهامه في المشروع بلا معلم.'))">
                                @csrf
                                @method('DELETE')
                                <x-button type="submit" variant="ghost" size="sm" class="text-red-700">حذف المعلم</x-button>
                            </form>
                        </details>
                    @endcan
                </x-card>
            @empty
                <x-empty-state title="لا معالم بعد." description="قسّم المشروع إلى مراحل بمواعيد يُقاس بها التقدّم." />
            @endforelse
        </div>

        @can('manage', $project)
            <x-card title="معلم جديد">
                <form method="POST" action="{{ route('projects.milestones.store', $project) }}" class="space-y-4">
                    @csrf
                    <x-form.input name="title" label="العنوان" required />
                    <x-form.input name="due_date" label="الموعد" type="date" />
                    <x-form.textarea name="description" label="الوصف" rows="3" />
                    <x-button type="submit">إضافة المعلم</x-button>
                </form>
            </x-card>
        @endcan
    </div>
</x-app-layout>
