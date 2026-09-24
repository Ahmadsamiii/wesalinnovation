<x-app-layout>
    <x-slot:title>تكامل الذكاء الاصطناعي</x-slot:title>

    <x-page-header title="تكامل الذكاء الاصطناعي" description="كيف يعمل مساعد المنصة العامة: أي مزوّد يجيب، وبأي سرعة، وما انقطع أو بقي بلا جواب." />

    @if (! $connected)
        <x-card title="قاعدة المنصة العامة غير مربوطة">
            <div class="space-y-3 text-sm leading-7 text-gray-700">
                <p>الإحصاءات هنا تُقرأ من سجل أسئلة المساعد في قاعدة بيانات المنصة العامة، ومساحة العمل لا تكتب فيها شيئاً. لربطها أضف إلى ملف ‎.env على الخادم ثم شغّل <span dir="ltr" class="font-mono">php artisan config:cache</span>:</p>
                <pre class="overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs leading-6 text-gray-100" dir="ltr">PLATFORM_DB_HOST=localhost
PLATFORM_DB_DATABASE=اسم_قاعدة_المنصة
PLATFORM_DB_USERNAME=مستخدم_للقراءة_فقط
PLATFORM_DB_PASSWORD=…</pre>
                <p>يُفضَّل مستخدم MySQL بصلاحية SELECT وحدها على قاعدة المنصة.</p>
            </div>
        </x-card>
    @elseif ($error ?? null)
        <p class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">الربط مضبوط لكن تعذّرت القراءة: <span dir="ltr">{{ $error }}</span></p>
    @else
        <section aria-label="المؤشرات" class="mb-6 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
            <x-stat label="أسئلة آخر ٣٠ يوماً" :value="number_format($total)" />
            <x-stat label="بلا جواب" :value="number_format($unanswered)" hint="فشل المزوّد وبديله معاً" />
            <x-stat label="انقطعت أثناء البث" :value="number_format($aborted)" />
            <x-stat label="زمن أول كلمة (الوسيط)" :value="$medianTtfb === null ? '-' : number_format($medianTtfb / 1000, 1).' ث'" hint="في الإجابات المبثوثة" />
        </section>

        @if ($latest)
            <p class="mb-6 text-sm text-gray-600">جاء آخر جواب <x-date :value="$latest->created_at" relative /> من <strong dir="ltr">{{ $latest->provider ?? 'غير معروف' }}</strong>@if ($latest->model) (<span dir="ltr">{{ $latest->model }}</span>)@endif.</p>
        @endif

        <div class="mb-6 grid [&>*]:min-w-0 gap-6 lg:grid-cols-2">
            <x-chart.columns title="الأسئلة يومياً" description="آخر ٣٠ يوماً" :labels="$dayLabels" :titles="$dayTitles"
                             :series="[['name' => 'أسئلة', 'values' => $daySeries]]" />

            <x-card title="حسب المزوّد" :padding="false">
                @if ($providers === [])
                    <p class="p-5 text-sm text-gray-500">لا أسئلة في آخر ٣٠ يوماً.</p>
                @else
                    <x-table caption="الأداء حسب المزوّد">
                        <thead class="bg-gray-50 text-xs font-semibold text-gray-600">
                            <tr>
                                <th scope="col" class="px-5 py-3 text-start">المزوّد</th>
                                <th scope="col" class="px-5 py-3 text-start">الحصة</th>
                                <th scope="col" class="px-5 py-3 text-start">الزمن الكلي (الوسيط / ٩٠٪)</th>
                                <th scope="col" class="px-5 py-3 text-start">انقطاع</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white tabular-nums">
                            @foreach ($providers as $provider)
                                <tr>
                                    <td class="px-5 py-3">
                                        <span class="font-medium" dir="ltr">{{ $provider['provider'] }}</span>
                                        <div class="text-xs text-gray-500" dir="ltr">{{ implode('، ', $provider['models']) }}</div>
                                    </td>
                                    <td class="px-5 py-3">{{ $provider['share'] }}٪ <span class="text-xs text-gray-500">({{ number_format($provider['count']) }})</span></td>
                                    <td class="px-5 py-3">
                                        {{ $provider['median'] === null ? '-' : number_format($provider['median'] / 1000, 1).' ث' }}
                                        /
                                        {{ $provider['p90'] === null ? '-' : number_format($provider['p90'] / 1000, 1).' ث' }}
                                    </td>
                                    <td class="px-5 py-3">{{ $provider['aborted'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-card>
        </div>
    @endif

    <x-card title="أين يُضبط المزوّد" class="mt-6">
        <p class="text-sm leading-7 text-gray-700">
            اختيار المزوّد (gemini أو openai أو claude أو kimi) ومفاتيحه في إعدادات خادم المنصة
            <span dir="ltr" class="font-mono">api/config.php</span> (الثابت AI_PROVIDER)، وللمزوّد النشط بديل تلقائي عند الفشل.
            لا تُعرض المفاتيح ولا تُعدَّل من هنا عمداً: الأسرار تبقى على الخادم خارج قاعدة مساحة العمل.
        </p>
    </x-card>
</x-app-layout>
