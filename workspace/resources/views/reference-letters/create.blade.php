<x-app-layout>
    <x-slot:title>طلب إفادة</x-slot:title>

    <x-page-header title="طلب إفادة" description="تُكتب الإفادة من بياناتك الوظيفية المسجلة، ويعتمدها المدير التنفيذي.">
        <x-slot:breadcrumb><a href="{{ route('reference-letters.index') }}" class="hover:underline">طلب إفادة</a></x-slot:breadcrumb>
    </x-page-header>

    <div class="grid [&>*]:min-w-0 gap-6 lg:grid-cols-3">
        <x-card class="lg:col-span-2">
            <form method="POST" action="{{ route('reference-letters.store') }}" class="space-y-4">
                @csrf
                <x-form.input name="purpose" label="الجهة أو الغرض" required hint="مثل: بنك، سفارة، جهة تعليمية." />
                <x-form.input name="addressee" label="موجّهة إلى" hint="إن تُركت: «من يهمه الأمر»." />
                <x-form.textarea name="notes" label="ملاحظات للمعتمِد" rows="3" />
                <x-button type="submit">إرسال الطلب</x-button>
            </form>
        </x-card>

        <x-card title="ما ستتضمنه الإفادة">
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-2"><dt class="text-gray-500">الاسم</dt><dd>{{ $user->name }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-gray-500">المسمى الوظيفي</dt><dd>{{ $user->job_title ?? '—' }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-gray-500">القسم</dt><dd>{{ $user->department ?? '—' }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-gray-500">تاريخ الالتحاق</dt><dd><x-date :value="$user->joined_at" /></dd></div>
            </dl>
            <p class="mt-4 text-xs text-gray-500">بيانات ناقصة أو خاطئة؟ يصححها مدير النظام قبل الاعتماد.</p>
        </x-card>
    </div>
</x-app-layout>
