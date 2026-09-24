<x-app-layout>
    <x-slot:title>مهامي</x-slot:title>

    <x-page-header title="مهامي" description="ما أُسند إليك في كل المشاريع، وما أنجزته في الأسبوعين الأخيرين." />

    @php($groups = [
        ['title' => 'متأخرة', 'tasks' => $overdue, 'tone' => 'red'],
        ['title' => 'جارية', 'tasks' => $active, 'tone' => 'brand'],
        ['title' => 'لم تبدأ بعد', 'tasks' => $todo, 'tone' => 'gray'],
        ['title' => 'أُنجزت مؤخراً', 'tasks' => $done, 'tone' => 'green'],
    ])

    @if (collect($groups)->every(fn ($group) => $group['tasks']->isEmpty()))
        <x-empty-state title="لا مهام مسندة إليك الآن." />
    @else
        <div class="space-y-6">
            @foreach ($groups as $group)
                @continue($group['tasks']->isEmpty())
                <x-card :padding="false">
                    <x-slot:title>
                        {{ $group['title'] }} <x-badge :color="$group['tone']">{{ $group['tasks']->count() }}</x-badge>
                    </x-slot:title>
                    <ul class="divide-y divide-gray-100" role="list">
                        @foreach ($group['tasks'] as $task)
                            <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                                <div class="min-w-0">
                                    <a href="{{ route('tasks.show', $task) }}" class="font-medium text-brand-800 hover:underline">{{ $task->title }}</a>
                                    <p class="text-xs text-gray-500">
                                        {{ $task->project->name }}{{ $task->milestone ? ' — '.$task->milestone->title : '' }}
                                        — الموعد: <x-date :value="$task->due_date" empty="غير محدد" />
                                    </p>
                                </div>
                                @can('move', $task)
                                    <form method="POST" action="{{ route('tasks.move', $task) }}" class="flex items-center gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <label for="status-{{ $task->id }}" class="sr-only">حالة «{{ $task->title }}»</label>
                                        <select id="status-{{ $task->id }}" name="status" class="rounded-lg border-gray-300 py-1 text-sm focus:border-brand-600 focus:ring-brand-600">
                                            @foreach (\App\Enums\TaskStatus::cases() as $option)
                                                <option value="{{ $option->value }}" @selected($option === $task->status)>{{ $option->label() }}</option>
                                            @endforeach
                                        </select>
                                        <x-button type="submit" variant="secondary" size="sm">تحديث</x-button>
                                    </form>
                                @else
                                    <x-badge :color="$task->status->color()">{{ $task->status->label() }}</x-badge>
                                @endcan
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endforeach
        </div>
    @endif
</x-app-layout>
