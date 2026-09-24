<x-app-layout>
    <x-slot:title>مهام {{ $project->name }}</x-slot:title>

    <x-project-header :project="$project" active="tasks">
        @can('manageTasks', $project)
            <x-button size="sm" :href="route('projects.tasks.create', $project)">مهمة جديدة</x-button>
        @endcan
    </x-project-header>

    <div class="mb-4 flex flex-wrap items-end justify-between gap-4">
        <nav aria-label="طريقة العرض" class="inline-flex rounded-lg border border-gray-200 bg-white p-1 text-sm">
            @foreach (['board' => 'كانبان', 'list' => 'قائمة'] as $key => $label)
                <a href="{{ route('projects.tasks.index', [$project, ...array_filter([...$filters, 'view' => $key])]) }}"
                   @if ($view === $key) aria-current="true" @endif
                   @class(['rounded-md px-3 py-1.5 font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-600',
                           'bg-brand-800 text-white' => $view === $key, 'text-gray-600 hover:bg-gray-50' => $view !== $key])>{{ $label }}</a>
            @endforeach
        </nav>

        <form method="GET" action="{{ route('projects.tasks.index', $project) }}" class="flex flex-wrap items-end gap-3" role="search">
            <input type="hidden" name="view" value="{{ $view }}">
            <x-form.select name="assignee" label="المسند إليه" :options="$assignees" :value="$filters['assignee'] ?? null" placeholder="الكل" />
            <x-form.select name="milestone" label="المعلم" :options="$milestones" :value="$filters['milestone'] ?? null" placeholder="الكل" />
            @if ($view === 'list')
                <x-form.select name="status" label="الحالة" placeholder="الكل" :value="$filters['status'] ?? null"
                               :options="collect(\App\Enums\TaskStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()" />
            @endif
            <x-button type="submit" variant="secondary">تصفية</x-button>
        </form>
    </div>

    @if ($view === 'board')
        <div x-data="kanban" class="relative">
            <p class="sr-only" role="status" aria-live="polite" x-text="message"></p>
            <p x-show="error" x-cloak x-text="error" role="alert" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-800"></p>

            <div class="relative flex gap-4 overflow-x-auto pb-4">
                @foreach (\App\Enums\TaskStatus::cases() as $status)
                    @php($columnTasks = $columns[$status->value] ?? collect())
                    <section class="flex min-w-56 flex-1 shrink-0 flex-col rounded-xl bg-gray-100/80" aria-labelledby="column-{{ $status->value }}"
                             data-column="{{ $status->value }}"
                             @dragover.prevent="over = '{{ $status->value }}'" @dragleave="over = null"
                             @drop.prevent="drop($event, '{{ $status->value }}')"
                             :class="over === '{{ $status->value }}' && 'ring-2 ring-brand-400'">
                        <h2 id="column-{{ $status->value }}" class="flex items-center justify-between px-3 py-2 text-sm font-semibold text-gray-700">
                            <span class="flex items-center gap-2"><x-badge :color="$status->color()">{{ $status->label() }}</x-badge></span>
                            <span class="text-xs text-gray-500" data-count>{{ $columnTasks->count() }}</span>
                        </h2>
                        <ol class="flex min-h-24 flex-1 flex-col gap-2 px-2 pb-2" data-cards role="list">
                            @foreach ($columnTasks as $task)
                                @php($canMove = auth()->user()->can('move', $task))
                                <li id="task-{{ $task->id }}" data-task="{{ $task->id }}" data-move-url="{{ route('tasks.move', $task) }}"
                                    @if ($canMove) draggable="true" @dragstart="start($event, {{ $task->id }})" @dragend="over = null" @endif
                                    @class(['rounded-lg border bg-white p-3 text-sm shadow-sm', 'cursor-grab' => $canMove,
                                            'border-red-300' => $task->isOverdue(), 'border-gray-200' => ! $task->isOverdue()])>
                                    <a href="{{ route('tasks.show', $task) }}" class="font-medium text-ink hover:text-brand-800 hover:underline">{{ $task->title }}</a>
                                    <div class="mt-2 flex flex-wrap items-center gap-1 text-xs text-gray-500">
                                        @if ($task->priority !== \App\Enums\Priority::Normal)
                                            <x-badge :color="$task->priority->color()">{{ $task->priority->label() }}</x-badge>
                                        @endif
                                        <span>{{ $task->assignee?->name ?? 'بلا إسناد' }}</span>
                                        @if ($task->due_date)
                                            <span @class(['text-red-700 font-semibold' => $task->isOverdue()])>— <x-date :value="$task->due_date" /></span>
                                        @endif
                                        @if ($task->comments_count)
                                            <span>— {{ $task->comments_count }} تعليق</span>
                                        @endif
                                    </div>
                                    @if ($canMove)
                                        {{-- البديل الذي لا يحتاج سحباً: لوحة المفاتيح وقارئ الشاشة واللمس --}}
                                        <form method="POST" action="{{ route('tasks.move', $task) }}" class="mt-2 flex items-center gap-1" @submit.prevent="submitMove($event)">
                                            @csrf
                                            @method('PATCH')
                                            <label for="move-{{ $task->id }}" class="sr-only">نقل «{{ $task->title }}» إلى</label>
                                            <select id="move-{{ $task->id }}" name="status" class="flex-1 rounded-md border-gray-200 py-1 text-xs focus:border-brand-600 focus:ring-brand-600">
                                                @foreach (\App\Enums\TaskStatus::cases() as $option)
                                                    <option value="{{ $option->value }}" @selected($option === $task->status)>{{ $option->label() }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="rounded-md border border-gray-200 px-2 py-1 text-xs text-gray-600 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-600">نقل</button>
                                        </form>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    </section>
                @endforeach
            </div>
        </div>
    @else
        <x-card :padding="false">
            @if ($tasks->isEmpty())
                <div class="p-5"><x-empty-state title="لا مهام مطابقة." /></div>
            @else
                <x-table caption="مهام المشروع">
                    <thead class="bg-gray-50 text-xs font-semibold text-gray-600">
                        <tr>
                            <th scope="col" class="px-5 py-3 text-start">المهمة</th>
                            <th scope="col" class="px-5 py-3 text-start">الحالة</th>
                            <th scope="col" class="px-5 py-3 text-start">الأولوية</th>
                            <th scope="col" class="px-5 py-3 text-start">المسند إليه</th>
                            <th scope="col" class="px-5 py-3 text-start">المعلم</th>
                            <th scope="col" class="px-5 py-3 text-start">الموعد</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @foreach ($tasks as $task)
                            <tr>
                                <td class="px-5 py-3"><a href="{{ route('tasks.show', $task) }}" class="font-medium text-brand-800 hover:underline">{{ $task->title }}</a></td>
                                <td class="px-5 py-3"><x-badge :color="$task->status->color()">{{ $task->status->label() }}</x-badge></td>
                                <td class="px-5 py-3"><x-badge :color="$task->priority->color()">{{ $task->priority->label() }}</x-badge></td>
                                <td class="px-5 py-3">{{ $task->assignee?->name ?? '—' }}</td>
                                <td class="px-5 py-3 text-gray-600">{{ $task->milestone?->title ?? '—' }}</td>
                                <td @class(['px-5 py-3', 'font-semibold text-red-700' => $task->isOverdue()])><x-date :value="$task->due_date" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-table>
            @endif
        </x-card>
    @endif
</x-app-layout>
