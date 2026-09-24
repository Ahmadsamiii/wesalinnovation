<x-app-layout>
    <x-slot:title>تقارير المراجعة</x-slot:title>

    <x-page-header title="تقارير المراجعة" description="وتيرة القرارات الطبية وزمنها، وما نُشر، ونتائج مراجعة الأسئلة الحساسة." />

    <x-report-period :period="$period" route="reports.medical" class="mb-6" />

    <section aria-label="المؤشرات" class="mb-6 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3">
        <x-stat label="بانتظار مراجعتك الآن" :value="$kpis['inReview']" :href="route('medical.review')" />
        <x-stat label="اعتمادات في الفترة" :value="$kpis['approved']" />
        <x-stat label="إعادات للتعديل في الفترة" :value="$kpis['rejected']" />
        <x-stat label="زمن القرار (الوسيط)" :value="$kpis['medianHours'] === null ? '—' : ($kpis['medianHours'] < 48 ? number_format($kpis['medianHours'], 1).' ساعة' : number_format($kpis['medianHours'] / 24, 1).' يوم')" hint="من التقديم إلى القرار" />
        <x-stat label="محتوى منشور" :value="$kpis['published']" :href="route('content.index', ['status' => 'approved'])" />
        <x-stat label="مراجعة دورية خلال ٣٠ يوماً" :value="$kpis['dueSoon']" :href="route('medical.review')" />
    </section>

    <div class="mb-6 grid gap-6 lg:grid-cols-2">
        <x-chart.columns title="القرارات شهرياً" :labels="$period['labels']" :titles="$period['titles']"
                         :series="[['name' => 'اعتماد', 'values' => $approvedSeries], ['name' => 'إعادة للتعديل', 'values' => $rejectedSeries]]" />
        <x-chart.bars title="المنشور حسب الفئة" :rows="$byCategory" empty="لا محتوى منشور بعد." />
    </div>

    <x-chart.bars title="نتائج مراجعة الأسئلة الحساسة" description="ما سجّلته في الفترة" :rows="$alertOutcomes" empty="لم تُراجَع أسئلة في الفترة." class="lg:w-1/2" />
</x-app-layout>
