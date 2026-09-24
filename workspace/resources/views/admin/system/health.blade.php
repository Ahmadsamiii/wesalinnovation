<x-app-layout>
    <x-slot:title>صحة النظام</x-slot:title>

    <x-page-header title="صحة النظام" description="فحوص حيّة لحظة فتح الصفحة؛ كل ما ليس سليماً يذكر ما يُفعل.">
        <x-slot:actions>
            <x-button variant="secondary" :href="route('system.health')">إعادة الفحص</x-button>
        </x-slot:actions>
    </x-page-header>

    @php
        $statuses = [
            'ok' => ['label' => 'سليم', 'color' => 'green'],
            'warn' => ['label' => 'تنبيه', 'color' => 'yellow'],
            'fail' => ['label' => 'عطل', 'color' => 'red'],
            'off' => ['label' => 'غير مفعّل', 'color' => 'gray'],
        ];
        $failing = collect($checks)->where('status', 'fail')->count();
        $warning = collect($checks)->where('status', 'warn')->count();
    @endphp

    <p @class(['mb-6 rounded-xl border p-4 text-sm font-medium',
               'border-green-200 bg-green-50 text-green-800' => $failing + $warning === 0,
               'border-yellow-200 bg-yellow-50 text-yellow-800' => $failing === 0 && $warning > 0,
               'border-red-200 bg-red-50 text-red-800' => $failing > 0]) role="status">
        @if ($failing + $warning === 0)
            كل الفحوص سليمة.
        @else
            {{ collect(['أعطال' => $failing, 'تنبيهات' => $warning])->filter()->map(fn ($count, $label) => $label.': '.$count)->implode('، ') }} — التفاصيل أدناه.
        @endif
    </p>

    <x-card :padding="false" class="mb-6">
        <ul class="divide-y divide-gray-100" role="list">
            @foreach ($checks as $check)
                <li class="flex flex-wrap items-start gap-3 px-5 py-4 sm:flex-nowrap">
                    <x-badge :color="$statuses[$check['status']]['color']" class="shrink-0">{{ $statuses[$check['status']]['label'] }}</x-badge>
                    <div class="min-w-0">
                        <p class="font-medium text-ink">{{ $check['label'] }}</p>
                        <p class="mt-0.5 text-sm text-gray-600">{{ $check['detail'] }}</p>
                    </div>
                </li>
            @endforeach
        </ul>
    </x-card>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card title="البيئة" class="min-w-0">
            <dl class="space-y-2 text-sm">
                @foreach ($environment as $label => $value)
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">{{ $label }}</dt><dd class="font-medium" dir="auto">{{ $value }}</dd></div>
                @endforeach
            </dl>
        </x-card>

        <x-card title="آخر الأخطاء المسجّلة" :padding="false" class="min-w-0 lg:col-span-2">
            @if ($recentErrors === [])
                <p class="p-5 text-sm text-gray-500">لا أخطاء في آخر السجل.</p>
            @else
                <ul class="divide-y divide-gray-100 text-sm" role="list">
                    @foreach ($recentErrors as $error)
                        <li class="px-5 py-3">
                            <p class="text-xs text-gray-500" dir="ltr">{{ $error['time'] }}</p>
                            <p class="mt-0.5 break-all font-mono text-xs text-gray-800" dir="ltr">{{ $error['message'] }}</p>
                        </li>
                    @endforeach
                </ul>
                <p class="border-t border-gray-100 px-5 py-3 text-xs text-gray-500">السطر الأول من كل خطأ؛ التفاصيل الكاملة في storage/logs على الخادم.</p>
            @endif
        </x-card>
    </div>
</x-app-layout>
