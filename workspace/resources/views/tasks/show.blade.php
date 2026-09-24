<x-app-layout>
    <x-slot:title>{{ $task->title }}</x-slot:title>

    <x-page-header :title="$task->title">
        <x-slot:breadcrumb>
            <a href="{{ route('projects.tasks.index', $task->project) }}" class="hover:underline">{{ $task->project->name }}</a>
        </x-slot:breadcrumb>
        <x-slot:actions>
            <x-badge :color="$task->status->color()">{{ $task->status->label() }}</x-badge>
            <x-badge :color="$task->priority->color()">أولوية {{ $task->priority->label() }}</x-badge>
            @if ($task->isOverdue())
                <x-badge color="red">متأخرة</x-badge>
            @endif
            @can('update', $task)
                <x-button variant="secondary" size="sm" :href="route('tasks.edit', $task)">تعديل</x-button>
            @endcan
            @can('delete', $task)
                <form method="POST" action="{{ route('tasks.destroy', $task) }}" onsubmit="return confirm(@js('حذف المهمة «'.$task->title.'» بتعليقاتها ومرفقاتها؟'))">
                    @csrf
                    @method('DELETE')
                    <x-button type="submit" variant="danger" size="sm">حذف</x-button>
                </form>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid [&>*]:min-w-0 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="الوصف">
                @if ($task->description)
                    <p class="whitespace-pre-line text-sm leading-7 text-gray-700">{{ $task->description }}</p>
                @else
                    <p class="text-sm text-gray-500">بلا وصف.</p>
                @endif
            </x-card>

            <x-card title="التعليقات" id="comments">
                @if ($task->comments->isEmpty())
                    <p class="text-sm text-gray-500">لا تعليقات بعد.</p>
                @else
                    <ol class="space-y-4" role="list">
                        @foreach ($task->comments as $comment)
                            <li class="rounded-lg bg-surface p-4">
                                <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-gray-500">
                                    <span class="font-semibold text-gray-800">{{ $comment->author->name }}</span>
                                    <span class="flex items-center gap-2">
                                        <x-date :value="$comment->created_at" time />
                                        @if ($comment->user_id === auth()->id() || auth()->user()->can('manageTasks', $task->project))
                                            <form method="POST" action="{{ route('comments.destroy', $comment) }}" onsubmit="return confirm('حذف التعليق؟')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-700 hover:underline">حذف</button>
                                            </form>
                                        @endif
                                    </span>
                                </div>
                                <p class="mt-2 whitespace-pre-line text-sm leading-7 text-gray-800">{{ $comment->body }}</p>
                            </li>
                        @endforeach
                    </ol>
                @endif

                @can('comment', $task)
                    <form method="POST" action="{{ route('tasks.comments.store', $task) }}" class="mt-6 space-y-3">
                        @csrf
                        <x-form.textarea name="body" label="تعليق جديد" rows="3" required />
                        <x-button type="submit" size="sm">إضافة التعليق</x-button>
                    </form>
                @endcan
            </x-card>

            <x-card title="المرفقات">
                <x-attachment-list :attachments="$task->attachments" />
                @can('upload', $task->project)
                    <x-upload-form :action="route('tasks.files.store', $task)" class="mt-4 border-t border-gray-100 pt-4 space-y-3" />
                @endcan
            </x-card>
        </div>

        <div class="space-y-6">
            @can('move', $task)
                <x-card title="تحديث الحالة">
                    <form method="POST" action="{{ route('tasks.move', $task) }}" class="space-y-3">
                        @csrf
                        @method('PATCH')
                        <x-form.select name="status" label="الحالة" :value="$task->status"
                                       :options="collect(\App\Enums\TaskStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()" />
                        <x-button type="submit" size="sm">تحديث</x-button>
                    </form>
                </x-card>
            @elseif ($task->assignee_id === auth()->id() && ! $task->project->status->allowsTaskProgress())
                <x-card>
                    <p class="text-sm text-gray-600">لا يمكن تحريك المهام والمشروع «{{ $task->project->status->label() }}».</p>
                </x-card>
            @endcan

            <x-card title="التفاصيل">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">المشروع</dt><dd><a href="{{ route('projects.show', $task->project) }}" class="text-brand-800 hover:underline">{{ $task->project->name }}</a></dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">المعلم</dt><dd>{{ $task->milestone?->title ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">المسند إليه</dt><dd>{{ $task->assignee?->name ?? 'بلا إسناد' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">الموعد</dt><dd><x-date :value="$task->due_date" /></dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">بدأت</dt><dd><x-date :value="$task->started_at" /></dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">أُنجزت</dt><dd><x-date :value="$task->completed_at" /></dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">أنشأها</dt><dd>{{ $task->creator->name }}</dd></div>
                </dl>
            </x-card>
        </div>
    </div>
</x-app-layout>
