<x-app-layout>
    <x-slot:title>التقارير الشاملة</x-slot:title>

    <x-page-header title="التقارير الشاملة" description="المحفظة والتسليم والمال على الفترة نفسها.">
        <x-slot:actions>
            <x-button variant="ghost" :href="route('reports.finance', ['months' => $period['months']])">التقارير المالية التفصيلية ←</x-button>
            <x-button variant="secondary" :href="route('reports.executive', ['export' => 'projects'])">تصدير المشاريع الجارية (CSV)</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-report-period :period="$period" route="reports.executive" class="mb-6" />

    <section aria-label="المؤشرات" class="mb-6 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <x-stat label="مشاريع جارية" :value="$kpis['active']" :href="route('projects.index', ['status' => 'in_progress'])" />
        <x-stat label="بانتظار الاعتماد" :value="$kpis['pending']" :href="route('approvals.projects')" />
        <x-stat label="مشاريع تجاوزت موعدها" :value="$kpis['overdue']" hint="من المشاريع الجارية والمعلّقة" />
        <x-stat label="أوامر شراء قائمة" :value="$kpis['committed']" money :href="route('purchase-orders.index')" hint="معتمدة لم تُستلم أو بانتظار الاعتماد" />
        <x-stat label="المفوتر في الفترة" :value="$kpis['invoiced']" money />
        <x-stat label="المحصّل في الفترة" :value="$kpis['collected']" money />
        <x-stat label="ذمم مستحقة على العملاء" :value="$kpis['outstanding']" money :href="route('invoices.index', ['status' => 'issued'])" />
        <x-stat label="منها متأخرة السداد" :value="$kpis['overdueReceivables']" money :href="route('invoices.index', ['status' => 'overdue'])" />
    </section>

    <div class="mb-6 grid gap-6 lg:grid-cols-2">
        <x-chart.columns title="المهام المنجزة شهرياً" description="كل المشاريع" :labels="$period['labels']" :titles="$period['titles']"
                         :series="[['name' => 'مهام منجزة', 'values' => $completedTasks]]" />
        <x-chart.columns title="الفوترة والتحصيل شهرياً" description="بالريال: الفواتير بتاريخ إصدارها، والتحصيل بتاريخ الدفع" :labels="$period['labels']" :titles="$period['titles']" money
                         :series="[['name' => 'المفوتر', 'values' => $invoicedSeries], ['name' => 'المحصّل', 'values' => $collectedSeries]]" />
    </div>

    <div class="mb-6 grid gap-6 lg:grid-cols-2">
        <x-chart.bars title="المشاريع حسب الحالة" :rows="$statusRows" />
        <x-chart.bars title="أعمار الذمم" description="المتبقي على الفواتير المصدرة حسب التأخر عن الاستحقاق" :rows="$aging" money />
    </div>

    <x-card title="المشاريع الجارية" :padding="false">
        @if ($projects->isEmpty())
            <p class="p-5 text-sm text-gray-500">لا مشاريع جارية.</p>
        @else
            <x-table caption="المشاريع الجارية">
                <thead class="bg-gray-50 text-xs font-semibold text-gray-600">
                    <tr>
                        <th scope="col" class="px-5 py-3 text-start">المشروع</th>
                        <th scope="col" class="px-5 py-3 text-start">الإنجاز</th>
                        <th scope="col" class="px-5 py-3 text-start">الميزانية</th>
                        <th scope="col" class="px-5 py-3 text-start">الملتزَم به</th>
                        <th scope="col" class="px-5 py-3 text-start">المفوتر</th>
                        <th scope="col" class="px-5 py-3 text-start">النهاية</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white tabular-nums">
                    @foreach ($projects as $project)
                        <tr>
                            <td class="px-5 py-3">
                                <a href="{{ route('projects.show', $project) }}" class="font-medium text-brand-800 hover:underline">{{ $project->name }}</a>
                                <div class="text-xs text-gray-500">{{ $project->pm->name }} — {{ $project->client?->name ?? 'داخلي' }}</div>
                            </td>
                            <td class="w-48 px-5 py-3"><x-progress :value="$project->progress()" /></td>
                            <td class="px-5 py-3">@if ($project->budget !== null)<x-money :amount="$project->budget" />@else — @endif</td>
                            <td @class(['px-5 py-3', 'font-semibold text-red-700' => $project->budget !== null && (float) $project->committed_sum > (float) $project->budget])><x-money :amount="$project->committed_sum ?? 0" /></td>
                            <td class="px-5 py-3"><x-money :amount="$project->invoiced_sum ?? 0" /></td>
                            <td @class(['px-5 py-3', 'font-semibold text-red-700' => $project->isOverdue()])><x-date :value="$project->end_date" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        @endif
    </x-card>
</x-app-layout>
