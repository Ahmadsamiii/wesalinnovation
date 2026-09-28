<x-app-layout>
    <x-slot:title>{{ $employee->name }}</x-slot:title>

    <x-page-header :title="$employee->name">
        <x-slot:breadcrumb><a href="{{ route('hr.employees') }}" class="hover:underline">الموظفون</a> / <span dir="ltr">{{ $employee->employeeNumber() }}</span></x-slot:breadcrumb>
        <x-slot:actions>
            <x-badge :color="$employee->status()->color()">{{ $employee->status()->label() }}</x-badge>
        </x-slot:actions>
    </x-page-header>

    <div class="grid [&>*]:min-w-0 gap-6 lg:grid-cols-3">
        <x-card title="البيانات الوظيفية" class="lg:col-span-2">
            <form method="POST" action="{{ route('hr.employees.update', $employee) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form.input name="department" label="القسم" :value="$employee->department" />
                    <x-form.input name="job_title" label="المسمى الوظيفي" :value="$employee->job_title" hint="يظهر في البطاقة الرقمية والإفادات." />
                    <x-form.input name="joined_at" label="تاريخ الانضمام" type="date" :value="$employee->joined_at?->format('Y-m-d')" />
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <x-button type="submit">حفظ التعديلات</x-button>
                    <x-button variant="ghost" :href="route('hr.employees')">رجوع</x-button>
                </div>
            </form>
        </x-card>

        <x-card title="بيانات الحساب">
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-2"><dt class="text-gray-500">الاسم</dt><dd>{{ $employee->name }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-gray-500">البريد الإلكتروني</dt><dd dir="ltr">{{ $employee->email }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-gray-500">رقم الجوال</dt><dd dir="ltr">{{ $employee->phone ?? '-' }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-gray-500">الدور</dt><dd>{{ $employee->roleLabel() ?? '-' }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-gray-500">الحالة</dt><dd>{{ $employee->status()->label() }}</dd></div>
            </dl>
            <p class="mt-4 text-xs text-gray-500" role="note">الأدوار وحالة الحساب تُدار من صفحة المستخدمين في المنصة، ولا تُعدَّل من هنا.</p>
        </x-card>
    </div>
</x-app-layout>
