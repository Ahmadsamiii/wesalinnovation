<x-app-layout>
    <x-slot:title>التقارير التقنية</x-slot:title>

    <x-page-header title="التقارير التقنية" description="الدخول ومحاولاته الفاشلة، ومن يستخدم النظام فعلاً، والحسابات الخاملة." />

    <x-report-period :period="$period" route="reports.technical" class="mb-6" />

    <section aria-label="المؤشرات" class="mb-6 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-5">
        <x-stat label="حسابات قادرة على الدخول" :value="$kpis['accounts']" :href="route('users.index', ['status' => 'active'])" />
        <x-stat label="دخلت خلال ٣٠ يوماً" :value="$kpis['activeLast30']" />
        <x-stat label="محاولات دخول فاشلة" :value="$kpis['failedLogins']" hint="في الفترة" :href="route('audit.index', ['action' => 'auth.failed'])" />
        <x-stat label="دعوات لم تُقبل" :value="$kpis['pendingInvitations']" :href="route('users.index', ['status' => 'pending'])" />
        <x-stat label="حسابات موقوفة" :value="$kpis['deactivated']" :href="route('users.index', ['status' => 'deactivated'])" />
    </section>

    <div class="mb-6 grid [&>*]:min-w-0 gap-6 lg:grid-cols-2">
        <x-chart.columns title="الدخول شهرياً" description="الناجح والفاشل" :labels="$period['labels']" :titles="$period['titles']"
                         :series="[['name' => 'دخول ناجح', 'values' => $loginSeries], ['name' => 'محاولة فاشلة', 'values' => $failedSeries]]" />
        <x-chart.bars title="الحسابات حسب الدور" description="القادرة على الدخول" :rows="$byRole" />
    </div>

    <div class="mb-6 grid [&>*]:min-w-0 gap-6 lg:grid-cols-2">
        <x-chart.bars title="النشاط حسب الفئة" description="أحداث سجل التدقيق في الفترة" :rows="$activity" empty="لا نشاط في الفترة." />

        <x-card :title="'حسابات خاملة (لا دخول منذ '.$dormantDays.' يوماً)'" :padding="false">
            @if ($dormant->isEmpty())
                <p class="p-5 text-sm text-gray-500">لا حسابات خاملة.</p>
            @else
                <ul class="divide-y divide-gray-100 text-sm" role="list">
                    @foreach ($dormant as $row)
                        <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-3">
                            <span>
                                <a href="{{ route('users.edit', $row['user']) }}" class="font-medium text-brand-800 hover:underline">{{ $row['user']->name }}</a>
                                <span class="text-gray-500">— {{ $row['user']->roleLabel() ?? 'بلا دور' }}</span>
                            </span>
                            <span class="text-xs text-gray-500">
                                @if ($row['lastLogin'])
                                    آخر دخول <x-date :value="$row['lastLogin']" />
                                @else
                                    لم يدخل منذ قبول الدعوة
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
                <p class="border-t border-gray-100 px-5 py-3 text-xs text-gray-500">حساب لا يُستخدم باب مفتوح بلا حاجة: أوقفه إن لم يعد صاحبه يحتاجه.</p>
            @endif
        </x-card>
    </div>
</x-app-layout>
