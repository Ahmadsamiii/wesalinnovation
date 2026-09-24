<x-app-layout>
    <x-slot:title>التقارير</x-slot:title>

    <x-page-header title="التقارير" description="مشاريعك قيد التسليم، ووتيرة الإنجاز، وحمل فريقك.">
        <x-slot:actions>
            <x-button variant="secondary" :href="route('reports.pm', ['export' => 'projects'])">تصدير المشاريع (CSV)</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-report-period :period="$period" route="reports.pm" class="mb-6" />

    <section aria-label="المؤشرات" class="mb-6 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <x-stat label="مشاريع قيد التسليم" :value="$kpis['delivering']" hint="معتمدة أو جارية أو متوقفة مؤقتاً" />
        <x-stat label="مهام مفتوحة" :value="$kpis['openTasks']" :href="route('tasks.index')" />
        <x-stat label="مهام متأخرة" :value="$kpis['overdueTasks']" :href="route('tasks.index', ['overdue' => 1])" />
        <x-stat label="معالم تستحق خلال ٣٠ يوماً" :value="$kpis['milestonesDue']" />
    </section>

    <div class="mb-6 grid gap-6 lg:grid-cols-2">
        <x-chart.columns title="المهام المنجزة شهرياً" description="في كل مشاريعك" :labels="$period['labels']" :titles="$period['titles']"
                         :series="[['name' => 'مهام منجزة', 'values' => $completedTasks]]" />
        <x-chart.bars title="حمل الفريق" description="المهام المفتوحة المسندة لكل عضو في مشاريعك قيد التسليم" :rows="$workloadRows" empty="لا مهام مفتوحة مسندة." />
    </div>

    <x-card title="المشاريع قيد التسليم" :padding="false">
        @if ($projects->isEmpty())
            <p class="p-5 text-sm text-gray-500">لا مشاريع معتمدة أو جارية.</p>
        @else
            <x-table caption="المشاريع قيد التسليم">
                <thead class="bg-gray-50 text-xs font-semibold text-gray-600">
                    <tr>
                        <th scope="col" class="px-5 py-3 text-start">المشروع</th>
                        <th scope="col" class="px-5 py-3 text-start">الإنجاز</th>
                        <th scope="col" class="px-5 py-3 text-start">مهام مفتوحة</th>
                        <th scope="col" class="px-5 py-3 text-start">المعالم المبلوغة</th>
                        <th scope="col" class="px-5 py-3 text-start">الميزانية</th>
                        <th scope="col" class="px-5 py-3 text-start">الملتزَم به</th>
                        <th scope="col" class="px-5 py-3 text-start">النهاية</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white tabular-nums">
                    @foreach ($projects as $project)
                        <tr>
                            <td class="px-5 py-3">
                                <a href="{{ route('projects.show', $project) }}" class="font-medium text-brand-800 hover:underline">{{ $project->name }}</a>
                                <div class="text-xs text-gray-500">{{ $project->status->label() }} — {{ $project->client?->name ?? 'داخلي' }}</div>
                            </td>
                            <td class="w-44 px-5 py-3"><x-progress :value="$project->progress()" /></td>
                            <td class="px-5 py-3">
                                <a href="{{ route('tasks.index', ['project' => $project->id]) }}" class="hover:underline">{{ $project->open_tasks_count }}</a>
                                @if ($project->overdue_tasks_count > 0)
                                    <span class="ms-1 text-xs font-semibold text-red-700">({{ $project->overdue_tasks_count }} متأخرة)</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">{{ $project->reached_milestones_count }} من {{ $project->milestones_count }}</td>
                            <td class="px-5 py-3">@if ($project->budget !== null)<x-money :amount="$project->budget" />@else — @endif</td>
                            <td @class(['px-5 py-3', 'font-semibold text-red-700' => $project->budget !== null && (float) $project->committed_sum > (float) $project->budget])><x-money :amount="$project->committed_sum ?? 0" /></td>
                            <td @class(['px-5 py-3', 'font-semibold text-red-700' => $project->isOverdue()])><x-date :value="$project->end_date" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        @endif
    </x-card>
</x-app-layout>
