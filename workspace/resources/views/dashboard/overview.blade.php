<x-app-layout>
    <x-slot:title>نظرة عامة</x-slot:title>

    <x-page-header title="نظرة عامة" :description="'مرحباً '.auth()->user()->name.'.'">
        @isset($queues)
            <x-slot:actions>
                <x-button variant="ghost" :href="route('reports.executive')">التقارير الشاملة ←</x-button>
            </x-slot:actions>
        @endisset
    </x-page-header>

    @isset($queues)
        <section aria-labelledby="queues-title" class="mb-8">
            <h2 id="queues-title" class="mb-3 text-base font-semibold text-ink">بانتظار قرارك</h2>
            <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                @foreach ($queues as $queue)
                    <a href="{{ $queue['url'] }}"
                       @class(['rounded-xl border bg-white p-4 shadow-sm transition hover:shadow focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-600 sm:p-5',
                               'border-brand-300' => $queue['count'] > 0, 'border-gray-200' => $queue['count'] === 0])>
                        <span class="block text-sm text-gray-500">{{ $queue['label'] }}</span>
                        <span @class(['mt-2 block text-2xl font-bold sm:text-3xl', 'text-brand-800' => $queue['count'] > 0, 'text-gray-400' => $queue['count'] === 0])>{{ $queue['count'] }}</span>
                        @if ($queue['count'] === 0)
                            <span class="mt-1 block text-xs text-gray-500">لا شيء ينتظرك</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </section>

        <section aria-label="لمحة" class="mb-8 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
            <x-stat label="مشاريع جارية" :value="$kpis['active']" :href="route('projects.index', ['status' => 'in_progress'])" />
            <x-stat label="مشاريع تجاوزت موعدها" :value="$kpis['overdueProjects']" />
            <x-stat label="المحصّل هذا الشهر" :value="$kpis['collectedThisMonth']" money />
            <x-stat label="ذمم متأخرة السداد" :value="$kpis['overdueReceivables']" money :href="route('invoices.index', ['status' => 'overdue'])" />
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            <x-card title="يحتاج انتباهك" :padding="false">
                @if ($overdueProjects->isEmpty() && $overBudget->isEmpty() && $overdueInvoices->isEmpty())
                    <p class="p-5 text-sm text-gray-500">لا مشاريع متأخرة ولا تجاوز للميزانيات ولا فواتير متأخرة.</p>
                @else
                    <ul class="divide-y divide-gray-100 text-sm" role="list">
                        @foreach ($overdueProjects as $project)
                            <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-3">
                                <span>
                                    <x-badge color="orange">تجاوز موعده</x-badge>
                                    <a href="{{ route('projects.show', $project) }}" class="ms-1 font-medium text-brand-800 hover:underline">{{ $project->name }}</a>
                                </span>
                                <span class="text-xs text-gray-500">كان مقرراً <x-date :value="$project->end_date" /> — {{ $project->pm->name }}</span>
                            </li>
                        @endforeach
                        @foreach ($overBudget as $project)
                            <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-3">
                                <span>
                                    <x-badge color="red">تجاوز الميزانية</x-badge>
                                    <a href="{{ route('projects.show', $project) }}" class="ms-1 font-medium text-brand-800 hover:underline">{{ $project->name }}</a>
                                </span>
                                <span class="text-xs text-gray-500">ملتزَم <x-money :amount="$project->committed_sum" /> من <x-money :amount="$project->budget" /></span>
                            </li>
                        @endforeach
                        @foreach ($overdueInvoices as $invoice)
                            <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-3">
                                <span>
                                    <x-badge color="red">فاتورة متأخرة</x-badge>
                                    <a href="{{ route('invoices.show', $invoice) }}" class="ms-1 font-medium text-brand-800 hover:underline" dir="ltr">{{ $invoice->number }}</a>
                                    <span class="text-gray-600">— {{ $invoice->client?->name ?? $invoice->project->name }}</span>
                                </span>
                                <span class="text-xs text-gray-500">متبقٍ <x-money :amount="$invoice->balance()" />، استحقت <x-date :value="$invoice->due_date" /></span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            <x-card title="مراحل تستحق خلال أسبوعين" :padding="false">
                @if ($upcomingMilestones->isEmpty())
                    <p class="p-5 text-sm text-gray-500">لا مراحل مستحقة في الأسبوعين القادمين.</p>
                @else
                    <ul class="divide-y divide-gray-100 text-sm" role="list">
                        @foreach ($upcomingMilestones as $milestone)
                            <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-3">
                                <span>
                                    <span class="font-medium">{{ $milestone->title }}</span>
                                    <a href="{{ route('projects.show', $milestone->project) }}" class="text-gray-600 hover:underline">— {{ $milestone->project->name }}</a>
                                </span>
                                <x-date :value="$milestone->due_date" class="text-xs text-gray-500" />
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach (auth()->user()->roleTabs() as $key => $tab)
                @continue($tab['route'] === 'dashboard')
                <a href="{{ Route::has($tab['route']) ? route($tab['route']) : route('sections.show', $key) }}"
                   class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-brand-300 hover:shadow focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-600">
                    <span class="font-semibold text-ink">{{ $tab['label'] }}</span>
                </a>
            @endforeach
        </div>
    @endisset
</x-app-layout>
