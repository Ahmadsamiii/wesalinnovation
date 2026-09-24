<x-app-layout>
    <x-slot:title>عقد جديد</x-slot:title>

    <x-page-header title="عقد جديد" description="يُحفظ مسودة لا يراها العميل، ويُفعَّل عند التوقيع.">
        <x-slot:breadcrumb><a href="{{ route('contracts.index') }}" class="hover:underline">العقود</a></x-slot:breadcrumb>
    </x-page-header>

    <x-card>
        @if ($projects === [])
            <x-empty-state title="لا مشاريع متاحة للتعاقد." description="العقود تُربط بمشروع؛ أنشئ المشروع أولاً." />
        @else
            <form method="POST" action="{{ route('contracts.store') }}" class="space-y-6">
                @csrf
                @include('contracts._form', ['selectedProject' => $selectedProject])
                <div class="flex gap-2">
                    <x-button type="submit">حفظ المسودة</x-button>
                    <x-button variant="secondary" :href="route('contracts.index')">إلغاء</x-button>
                </div>
            </form>
        @endif
    </x-card>
</x-app-layout>
