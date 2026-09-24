<x-app-layout>
    <x-slot:title>المهام والفريق</x-slot:title>

    <x-page-header title="المهام والفريق" description="المهام المفتوحة في كل مشاريعك الجارية، وحمل كل عضو منها." />

    @if ($people->isNotEmpty())
        <x-card title="حمل الفريق" :padding="false" class="mb-6">
            <x-table caption="حمل الفريق">
                <thead class="bg-gray-50 text-xs font-semibold text-gray-600">
                    <tr>
                        <th scope="col" class="px-5 py-3 text-start">العضو</th>
                        <th scope="col" class="px-5 py-3 text-start">مفتوحة</th>
                        <th scope="col" class="px-5 py-3 text-start">متأخرة</th>
                        <th scope="col" class="px-5 py-3 text-start">أُنجزت آخر ٣٠ يوماً</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach ($people as $person)
                        @php($load = $workload[$person->id])
                        <tr>
                            <td class="px-5 py-3">
                                <a href="{{ route('tasks.index', ['assignee' => $person->id]) }}" class="font-medium text-brand-800 hover:underline">{{ $person->name }}</a>
                            </td>
                            <td class="px-5 py-3">{{ (int) $load->open_count }}</td>
                            <td @class(['px-5 py-3', 'font-semibold text-red-700' => $load->overdue_count > 0])>{{ (int) $load->overdue_count }}</td>
                            <td class="px-5 py-3">{{ (int) $load->done_recently }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        </x-card>
    @endif

    <x-card :padding="false">
        <form method="GET" action="{{ route('tasks.index') }}" class="grid gap-4 border-b border-gray-100 p-5 sm:grid-cols-5" role="search">
            <x-form.select name="project" label="المشروع" :options="$projects" :value="$filters['project'] ?? null" placeholder="كل المشاريع" />
            <x-form.select name="assignee" label="المسند إليه" :options="$people->pluck('name', 'id')->all()" :value="$filters['assignee'] ?? null" placeholder="الكل" />
            <x-form.select name="status" label="الحالة" placeholder="المفتوحة فقط" :value="$filters['status'] ?? null"
                           :options="collect(\App\Enums\TaskStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()" />
            <label class="flex items-end gap-2 pb-2 text-sm text-gray-700">
                <input type="checkbox" name="overdue" value="1" @checked($filters['overdue'] ?? false) class="rounded border-gray-300 text-brand-800 focus:ring-brand-600">
                المتأخرة فقط
            </label>
            <div class="flex items-end gap-2">
                <x-button type="submit">تصفية</x-button>
                @if (array_filter($filters))
                    <x-button variant="ghost" :href="route('tasks.index')">مسح</x-button>
                @endif
            </div>
        </form>

        @if ($tasks->isEmpty())
            <div class="p-5"><x-empty-state title="لا مهام مطابقة." /></div>
        @else
            <x-table caption="المهام">
                <thead class="bg-gray-50 text-xs font-semibold text-gray-600">
                    <tr>
                        <th scope="col" class="px-5 py-3 text-start">المهمة</th>
                        <th scope="col" class="px-5 py-3 text-start">المشروع</th>
                        <th scope="col" class="px-5 py-3 text-start">الحالة</th>
                        <th scope="col" class="px-5 py-3 text-start">المسند إليه</th>
                        <th scope="col" class="px-5 py-3 text-start">الموعد</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach ($tasks as $task)
                        <tr>
                            <td class="px-5 py-3"><a href="{{ route('tasks.show', $task) }}" class="font-medium text-brand-800 hover:underline">{{ $task->title }}</a></td>
                            <td class="px-5 py-3 text-gray-600">{{ $task->project->name }}</td>
                            <td class="px-5 py-3"><x-badge :color="$task->status->color()">{{ $task->status->label() }}</x-badge></td>
                            <td class="px-5 py-3">{{ $task->assignee?->name ?? 'بلا إسناد' }}</td>
                            <td @class(['px-5 py-3', 'font-semibold text-red-700' => $task->isOverdue()])><x-date :value="$task->due_date" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
            @if ($tasks->hasPages())
                <div class="border-t border-gray-100 px-5 py-3">{{ $tasks->links() }}</div>
            @endif
        @endif
    </x-card>
</x-app-layout>
