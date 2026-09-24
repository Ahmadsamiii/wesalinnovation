<x-app-layout>
    <x-slot:title>طلب توظيف جديد</x-slot:title>

    <x-page-header title="طلب توظيف جديد" description="يصل للمدير التنفيذي ليقرّره، وتُتابع حالته من هنا.">
        <x-slot:breadcrumb><a href="{{ route('hiring-requests.index') }}" class="hover:underline">طلبات التوظيف</a></x-slot:breadcrumb>
    </x-page-header>

    <x-card class="max-w-3xl">
        <form method="POST" action="{{ route('hiring-requests.store') }}" class="space-y-6">
            @csrf
            @include('hiring-requests._form', ['hiringRequest' => null, 'projects' => $projects])
            <x-button type="submit">إرسال الطلب</x-button>
        </form>
    </x-card>
</x-app-layout>
