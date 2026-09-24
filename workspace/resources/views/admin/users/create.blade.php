<x-app-layout>
    <x-slot:title>إضافة حساب</x-slot:title>

    <x-page-header title="إضافة حساب" description="يُرسل للبريد رابط لتعيين كلمة المرور، صالح لمدة {{ \App\Models\User::INVITATION_VALID_DAYS }} أيام.">
        <x-slot:breadcrumb><a href="{{ route('users.index') }}" class="hover:underline">الأدوار والصلاحيات</a></x-slot:breadcrumb>
    </x-page-header>

    <x-card>
        <form method="POST" action="{{ route('users.store') }}" class="space-y-6">
            @csrf
            @include('admin.users._fields', ['user' => null])

            <div class="flex gap-2">
                <x-button type="submit">إنشاء الحساب وإرسال الدعوة</x-button>
                <x-button variant="secondary" :href="route('users.index')">إلغاء</x-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
