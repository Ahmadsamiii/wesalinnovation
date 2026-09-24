<x-app-layout>
    <x-slot:title>التقارير المالية</x-slot:title>

    <x-page-header title="التقارير المالية" description="الفوترة والتحصيل، والذمم، والتزامات الشراء على الميزانيات.">
        <x-slot:actions>
            <x-button variant="secondary" :href="route('reports.finance', ['export' => 'invoices', 'months' => $period['months']])">تصدير فواتير الفترة (CSV)</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-report-period :period="$period" route="reports.finance" class="mb-6" />

    <section aria-label="المؤشرات" class="mb-6 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-5">
        <x-stat label="المفوتر في الفترة" :value="$kpis['invoiced']" money hint="الفواتير المصدرة شاملة الضريبة" />
        <x-stat label="المحصّل في الفترة" :value="$kpis['collected']" money />
        <x-stat label="أوامر شراء بانتظار الاعتماد" :value="$kpis['awaitingApprovalCount']" :href="route('purchase-orders.index')"
                :hint="'بقيمة '.number_format((float) $kpis['awaitingApprovalAmount'], 2).' ر.س'" />
        <x-stat label="ذمم مستحقة على العملاء" :value="$kpis['outstanding']" money :href="route('invoices.index', ['status' => 'issued'])" />
        <x-stat label="منها متأخرة السداد" :value="$kpis['overdue']" money :href="route('invoices.index', ['status' => 'overdue'])" />
    </section>

    <div class="mb-6 grid gap-6 lg:grid-cols-2">
        <x-chart.columns title="الفوترة والتحصيل شهرياً" description="بالريال: الفواتير بتاريخ إصدارها، والتحصيل بتاريخ الدفع" :labels="$period['labels']" :titles="$period['titles']" money
                         :series="[['name' => 'المفوتر', 'values' => $invoicedSeries], ['name' => 'المحصّل', 'values' => $collectedSeries]]" />
        <x-chart.bars title="أعمار الذمم" description="المتبقي على الفواتير المصدرة حسب التأخر عن الاستحقاق" :rows="$aging" money />
    </div>

    <div class="mb-6 grid gap-6 lg:grid-cols-2">
        <x-chart.bars title="الذمم حسب العميل" description="المتبقي على كل عميل من فواتيره المصدرة" :rows="$byClient" money empty="لا ذمم قائمة." />

        <x-card title="تغطية العقود السارية" :padding="false">
            @if ($contracts->isEmpty())
                <p class="p-5 text-sm text-gray-500">لا عقود سارية.</p>
            @else
                <x-table caption="ما فُوتر من كل عقد ساري">
                    <thead class="bg-gray-50 text-xs font-semibold text-gray-600">
                        <tr>
                            <th scope="col" class="px-5 py-3 text-start">العقد</th>
                            <th scope="col" class="px-5 py-3 text-start">القيمة</th>
                            <th scope="col" class="px-5 py-3 text-start">فُوتر منها</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white tabular-nums">
                        @foreach ($contracts as $contract)
                            @php
                                $share = (float) $contract->value > 0 ? (int) round((float) $contract->invoiced_sum * 100 / (float) $contract->value) : 0;
                            @endphp
                            <tr>
                                <td class="px-5 py-3">
                                    <a href="{{ route('contracts.show', $contract) }}" class="whitespace-nowrap font-medium text-brand-800 hover:underline">{{ $contract->number }}</a>
                                    <div class="text-xs text-gray-500">{{ $contract->project->name }}</div>
                                </td>
                                <td class="px-5 py-3"><x-money :amount="$contract->value" /></td>
                                <td class="w-48 px-5 py-3">
                                    <x-progress :value="min(100, $share)" label="نسبة المفوتر من العقد" />
                                    <div @class(['mt-1 text-xs', 'font-semibold text-red-700' => $share > 100, 'text-gray-500' => $share <= 100])>
                                        <x-money :amount="$contract->invoiced_sum ?? 0" /> @if ($share > 100) — تجاوز قيمة العقد @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-table>
                <p class="border-t border-gray-100 px-5 py-3 text-xs text-gray-500">القيم قبل الضريبة في الجهتين.</p>
            @endif
        </x-card>
    </div>

    <x-card title="الميزانيات والالتزامات" :padding="false">
        @if ($budgets->isEmpty())
            <p class="p-5 text-sm text-gray-500">لا مشاريع جارية لها ميزانية.</p>
        @else
            <x-table caption="الميزانيات والالتزامات">
                <thead class="bg-gray-50 text-xs font-semibold text-gray-600">
                    <tr>
                        <th scope="col" class="px-5 py-3 text-start">المشروع</th>
                        <th scope="col" class="px-5 py-3 text-start">الميزانية</th>
                        <th scope="col" class="px-5 py-3 text-start">الملتزَم به</th>
                        <th scope="col" class="px-5 py-3 text-start">المتبقي</th>
                        <th scope="col" class="px-5 py-3 text-start">المستخدم</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white tabular-nums">
                    @foreach ($budgets as $project)
                        @php
                            $committed = (float) $project->committed_sum;
                            $used = (float) $project->budget > 0 ? (int) round($committed * 100 / (float) $project->budget) : 0;
                        @endphp
                        <tr>
                            <td class="px-5 py-3"><a href="{{ route('projects.show', $project) }}" class="font-medium text-brand-800 hover:underline">{{ $project->name }}</a></td>
                            <td class="px-5 py-3"><x-money :amount="$project->budget" /></td>
                            <td class="px-5 py-3"><a href="{{ route('purchase-orders.index', ['project' => $project->id]) }}" class="hover:underline"><x-money :amount="$committed" /></a></td>
                            <td @class(['px-5 py-3', 'font-semibold text-red-700' => $used > 100])><x-money :amount="(float) $project->budget - $committed" /></td>
                            <td @class(['px-5 py-3', 'font-semibold text-red-700' => $used > 100, 'text-orange-700' => $used >= 90 && $used <= 100])>{{ $used }}٪</td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        @endif
    </x-card>
</x-app-layout>
