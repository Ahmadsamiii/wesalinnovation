<x-app-layout>
    <x-slot:title>تقرير مهامي</x-slot:title>

    <x-page-header title="تقرير مهامي" description="ما أنجزته في الفترة، والتزامك بالمواعيد، وما زال مفتوحاً عليك.">
        <x-slot:actions>
            <x-button variant="secondary" :href="route('reports.mine', ['export' => 'tasks', 'months' => $period['months']])">تصدير منجزات الفترة (CSV)</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-report-period :period="$period" route="reports.mine" class="mb-6" />

    <section aria-label="المؤشرات" class="mb-6 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <x-stat label="مهام مفتوحة" :value="$kpis['open']" :href="route('tasks.mine')" />
        <x-stat label="مهام متأخرة" :value="$kpis['overdue']" :href="route('tasks.mine')" />
        <x-stat label="أنجزتها في الفترة" :value="$kpis['completed']" />
        <x-stat label="الإنجاز في الموعد" :value="$kpis['onTimeRate'] === null ? '—' : $kpis['onTimeRate'].'٪'" hint="من المنجزات التي لها موعد استحقاق" />
    </section>

    <div class="mb-6 grid gap-6 lg:grid-cols-2">
        <x-chart.columns title="منجزاتي شهرياً" :labels="$period['labels']" :titles="$period['titles']"
                         :series="[['name' => 'مهام منجزة', 'values' => $completedSeries]]" />
        <x-chart.bars title="منجزات الفترة حسب المشروع" :rows="$byProject" empty="لم تُنجز مهام في هذه الفترة." />
    </div>

    <x-card title="آخر ما أنجزت" :padding="false">
        @if ($recent->isEmpty())
            <p class="p-5 text-sm text-gray-500">لم تُنجز مهام في هذه الفترة.</p>
        @else
            <x-table caption="آخر ما أنجزت">
                <thead class="bg-gray-50 text-xs font-semibold text-gray-600">
                    <tr>
                        <th scope="col" class="px-5 py-3 text-start">المهمة</th>
                        <th scope="col" class="px-5 py-3 text-start">المشروع</th>
                        <th scope="col" class="px-5 py-3 text-start">الاستحقاق</th>
                        <th scope="col" class="px-5 py-3 text-start">أُنجزت</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach ($recent as $task)
                        <tr>
                            <td class="px-5 py-3 font-medium">{{ $task->title }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $task->project->name }}</td>
                            <td class="px-5 py-3"><x-date :value="$task->due_date" /></td>
                            <td class="px-5 py-3">
                                <x-date :value="$task->completed_at" />
                                @if ($task->due_date && $task->completed_at->toDateString() > $task->due_date->toDateString())
                                    <x-badge color="orange" class="ms-1">بعد الموعد</x-badge>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        @endif
    </x-card>
</x-app-layout>
