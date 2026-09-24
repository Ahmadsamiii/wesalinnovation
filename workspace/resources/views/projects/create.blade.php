<x-app-layout>
    <x-slot:title>مشروع جديد</x-slot:title>

    <x-page-header title="مشروع جديد" description="يُحفظ مسودة؛ تقدّمه للاعتماد التنفيذي بعد إضافة المعالم والفريق.">
        <x-slot:breadcrumb><a href="{{ route('projects.index') }}" class="hover:underline">المشاريع</a></x-slot:breadcrumb>
    </x-page-header>

    <x-card>
        <form method="POST" action="{{ route('projects.store') }}" class="space-y-6">
            @csrf
            @include('projects._form')

            <div class="flex gap-2">
                <x-button type="submit">حفظ المسودة</x-button>
                <x-button variant="secondary" :href="route('projects.index')">إلغاء</x-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
