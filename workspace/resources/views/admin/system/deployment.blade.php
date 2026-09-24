<x-app-layout>
    <x-slot:title>النطاقات والنشر</x-slot:title>

    <x-page-header title="النطاقات والنشر" description="أين تعمل مساحة العمل، وأي إصدار منشور، وما يحتاجه الإنتاج." />

    <div class="grid [&>*]:min-w-0 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="قائمة جاهزية الإنتاج" :padding="false">
                <ul class="divide-y divide-gray-100 text-sm" role="list">
                    @foreach ($checklist as $item)
                        <li class="flex items-center gap-3 px-5 py-3">
                            @if ($item['done'])
                                <x-badge color="green">تم</x-badge>
                            @else
                                <x-badge color="yellow">لم يتم</x-badge>
                            @endif
                            <span>{{ $item['label'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            <x-card title="طريقة النشر">
                <p class="text-sm leading-7 text-gray-700">
                    مساحة العمل تطبيق مستقل عن الموقع العام، ولا ينشرها سكربت الموقع (deploy.sh). تُنشر بسكربتها
                    <span dir="ltr" class="font-mono">workspace/deploy.sh</span> على الخادم، وخطوات تجهيز الخادم أول مرة (النطاق الفرعي، قاعدة البيانات، ملف ‎.env، البريد)
                    في <span dir="ltr" class="font-mono">workspace/DEPLOY.md</span>.
                </p>
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="النطاق">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">الرابط الأساسي</dt><dd class="font-medium" dir="ltr">{{ $appUrl }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">المضيف الحالي</dt><dd class="font-medium" dir="ltr">{{ $host }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">الاتصال</dt><dd class="font-medium">{{ $secure ? 'مشفّر (https)' : 'غير مشفّر (http)' }}</dd></div>
                </dl>
            </x-card>

            <x-card title="الإصدار المنشور">
                @if ($release)
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-2"><dt class="text-gray-500">الإيداع</dt><dd class="font-mono font-medium" dir="ltr">{{ $release['commit'] ?? '-' }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-gray-500">الفرع</dt><dd class="font-medium" dir="ltr">{{ $release['branch'] ?? '-' }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-gray-500">وقت النشر</dt><dd class="font-medium"><x-date :value="$release['deployed_at']" time /></dd></div>
                    </dl>
                @else
                    <p class="text-sm text-gray-500">لا سجل نشر: هذه النسخة لم تُنشر بسكربت النشر (نسخة تطوير محلية على الأرجح).</p>
                @endif
            </x-card>
        </div>
    </div>
</x-app-layout>
