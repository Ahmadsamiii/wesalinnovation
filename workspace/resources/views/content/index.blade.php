<x-app-layout>
    <x-slot:title>إدارة المحتوى</x-slot:title>

    <x-page-header title="إدارة المحتوى" description="مكتبة المحتوى الصحي: يُحرَّر هنا، ولا يُنشر إلا باعتماد المدير الطبي.">
        <x-slot:actions>
            @can('create', \App\Models\HealthContent::class)
                <x-button :href="route('content.create')">محتوى جديد</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <section aria-label="المؤشرات" class="mb-6 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <x-stat label="منشور" :value="$publishedCount" />
        <x-stat label="قيد المراجعة الطبية" :value="$counts['in_review'] ?? 0" :href="route('content.index', ['status' => 'in_review'])" />
        <x-stat label="مرفوض يحتاج تعديلاً" :value="$counts['rejected'] ?? 0" :href="route('content.index', ['status' => 'rejected'])" />
        <x-stat label="مسودات" :value="$counts['draft'] ?? 0" :href="route('content.index', ['status' => 'draft'])" />
    </section>

    <form method="GET" action="{{ route('content.index') }}" class="mb-6 flex flex-wrap items-end gap-3" role="search">
        <x-form.input name="q" label="بحث في العناوين" :value="$filters['q'] ?? null" class="w-full sm:w-64" />
        <x-form.select name="status" label="الحالة" placeholder="كل الحالات" :value="$filters['status'] ?? null"
                       :options="collect(\App\Enums\HealthContentStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()" />
        <x-form.select name="category" label="الفئة" placeholder="كل الفئات" :value="$filters['category'] ?? null"
                       :options="collect(\App\Enums\HealthContentCategory::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all()" />
        <x-button type="submit" variant="secondary">تصفية</x-button>
        @if (array_filter($filters))
            <x-button variant="ghost" :href="route('content.index')">مسح</x-button>
        @endif
    </form>

    @if ($contents->isEmpty())
        <x-empty-state :title="array_filter($filters) ? 'لا محتوى مطابق.' : 'المكتبة فارغة.'" description="المحتوى المعتمد يُنشر بصفحة عامة ويُصدَّر لقاعدة معرفة مساعد المنصة." />
    @else
        <x-card :padding="false">
            <x-table caption="المحتوى الصحي">
                <thead class="bg-gray-50 text-xs font-semibold text-gray-600">
                    <tr>
                        <th scope="col" class="px-5 py-3 text-start">العنوان</th>
                        <th scope="col" class="px-5 py-3 text-start">الفئة</th>
                        <th scope="col" class="px-5 py-3 text-start">الحالة</th>
                        <th scope="col" class="px-5 py-3 text-start">آخر تعديل</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach ($contents as $content)
                        <tr>
                            <td class="px-5 py-3">
                                <a href="{{ route('content.show', $content) }}" class="font-medium text-brand-800 hover:underline">{{ $content->title }}</a>
                                @if ($content->hasUnpublishedChanges())
                                    <div class="text-xs text-gray-500">منشور بنسخة سابقة؛ التعديل لم يُعتمد بعد</div>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-gray-600">{{ $content->category->label() }}</td>
                            <td class="px-5 py-3">
                                <x-badge :color="$content->status->color()">{{ $content->status->label() }}</x-badge>
                                @if ($content->isReviewOverdue())
                                    <x-badge color="orange">تجاوز موعد المراجعة الدورية</x-badge>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-gray-600"><x-date :value="$content->updated_at" relative /></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        </x-card>

        @if ($contents->hasPages())
            <div class="mt-6">{{ $contents->links() }}</div>
        @endif
    @endif

    <x-card title="النشر في مساعد المنصة" class="mt-6">
        <div class="space-y-2 text-sm leading-7 text-gray-700">
            <p>كل محتوى معتمد له صفحة عامة يستشهد بها المساعد. لإدخال المعتمد في قاعدة معرفته شغّل على الخادم:</p>
            <pre class="overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs leading-6 text-gray-100" dir="ltr">php artisan content:export-kb /tmp/wesal-kb
php tools/rag/ingest-kb.php /tmp/wesal-kb</pre>
            <p class="text-xs text-gray-500">الأول من مجلد مساحة العمل والثاني من مجلد الموقع العام. إعادة التشغيل تستبدل نسخة كل محتوى ولا تكررها، والأمر الأول يطبع طريقة حذف المسحوب.</p>
        </div>
    </x-card>
</x-app-layout>
