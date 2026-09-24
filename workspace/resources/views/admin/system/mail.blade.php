<x-app-layout>
    <x-slot:title>تكامل البريد الإلكتروني</x-slot:title>

    <x-page-header title="تكامل البريد الإلكتروني" description="الإعداد الفعلي كما يقرؤه التطبيق، ورسالة اختبار تثبت الوصول." />

    @unless ($delivers)
        <p class="mb-6 rounded-xl border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-800" role="note">
            طريقة الإرسال الحالية «{{ $mailer }}» تكتب الرسائل في سجل التطبيق ولا ترسلها: دعوات الحسابات وروابط استعادة كلمة المرور لن تصل لأحد.
            اضبط في ملف ‎.env على الخادم: MAIL_MAILER=smtp ومعه MAIL_HOST وMAIL_PORT وMAIL_USERNAME وMAIL_PASSWORD وMAIL_FROM_ADDRESS، ثم php artisan config:cache.
        </p>
    @endunless

    <div class="grid [&>*]:min-w-0 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="الإعداد الحالي">
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    @foreach ($settings as $label => $value)
                        <div><dt class="text-gray-500">{{ $label }}</dt><dd class="mt-0.5 font-medium" dir="auto">{{ $value }}</dd></div>
                    @endforeach
                </dl>
                <p class="mt-4 text-xs text-gray-500">كلمة المرور لا تُعرض هنا أبداً، والتعديل من ملف ‎.env على الخادم لا من الواجهة: أسرار البريد لا تُخزَّن في قاعدة البيانات.</p>
            </x-card>

            <x-card title="آخر رسائل الاختبار" :padding="false">
                @if ($recentTests->isEmpty())
                    <p class="p-5 text-sm text-gray-500">لم تُرسل رسالة اختبار بعد.</p>
                @else
                    <ul class="divide-y divide-gray-100 text-sm" role="list">
                        @foreach ($recentTests as $test)
                            <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-3">
                                <span>
                                    @if ($test->properties['delivered'] ?? false)
                                        <x-badge color="green">سُلّمت</x-badge>
                                    @else
                                        <x-badge color="red">فشلت</x-badge>
                                    @endif
                                    <span class="ms-1" dir="ltr">{{ $test->properties['to'] ?? '' }}</span>
                                    @if (! ($test->properties['delivered'] ?? false) && isset($test->properties['error']))
                                        <span class="block text-xs text-red-700" dir="ltr">{{ $test->properties['error'] }}</span>
                                    @endif
                                </span>
                                <span class="text-xs text-gray-500">{{ $test->user?->name }}، <x-date :value="$test->created_at" relative /></span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="رسالة اختبار">
                <form method="POST" action="{{ route('system.mail.test') }}" class="space-y-3">
                    @csrf
                    <x-form.input name="to" label="إلى" type="email" :value="auth()->user()->email" required dir="ltr" />
                    <x-button type="submit" class="w-full">إرسال رسالة اختبار</x-button>
                </form>
            </x-card>

            <x-card title="ما يرسله النظام">
                <ul class="list-disc space-y-1 ps-5 text-sm text-gray-700">
                    <li>دعوة الحساب الجديد (صالحة ٧ أيام)</li>
                    <li>رابط استعادة كلمة المرور</li>
                </ul>
            </x-card>
        </div>
    </div>
</x-app-layout>
