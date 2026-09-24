<x-app-layout>
    <x-slot:title>أمر شراء جديد</x-slot:title>

    <x-page-header title="أمر شراء جديد" description="يُحفظ مسودة؛ تقدّمه لمراجعة المالية حين يكتمل.">
        <x-slot:breadcrumb><a href="{{ route('purchase-orders.index') }}" class="hover:underline">أوامر الشراء</a></x-slot:breadcrumb>
    </x-page-header>

    <x-card>
        @if ($projects === [])
            <x-empty-state title="لا مشاريع معتمدة لديك." description="الشراء يتبع اعتماد المشروع؛ لا أوامر شراء على مسودة أو مشروع مغلق." />
        @else
            <form method="POST" action="{{ route('purchase-orders.store') }}" class="space-y-6">
                @csrf
                @include('purchase-orders._form', ['selectedProject' => $selectedProject])
                <div class="flex gap-2">
                    <x-button type="submit">حفظ المسودة</x-button>
                    <x-button variant="secondary" :href="route('purchase-orders.index')">إلغاء</x-button>
                </div>
            </form>
        @endif
    </x-card>
</x-app-layout>
