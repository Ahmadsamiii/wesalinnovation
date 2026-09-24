<x-app-layout>
    <x-slot:title>محتوى جديد</x-slot:title>

    <x-page-header title="محتوى صحي جديد" description="يُحفظ مسودة، ثم تقدّمه للمراجعة الطبية.">
        <x-slot:breadcrumb><a href="{{ route('content.index') }}" class="hover:underline">إدارة المحتوى</a></x-slot:breadcrumb>
    </x-page-header>

    <x-card class="max-w-4xl">
        <form method="POST" action="{{ route('content.store') }}" class="space-y-6">
            @csrf
            @include('content._form', ['content' => null])
            <x-button type="submit">حفظ المسودة</x-button>
        </form>
    </x-card>
</x-app-layout>
