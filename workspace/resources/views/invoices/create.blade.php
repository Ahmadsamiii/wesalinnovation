<x-app-layout>
    <x-slot:title>فاتورة جديدة</x-slot:title>

    <x-page-header title="فاتورة جديدة" description="تُحفظ مسودة بلا رقم؛ الرقم التسلسلي يُسند عند الإصدار.">
        <x-slot:breadcrumb><a href="{{ route('invoices.index') }}" class="hover:underline">الفواتير</a></x-slot:breadcrumb>
    </x-page-header>

    <x-card>
        @if ($projects === [])
            <x-empty-state title="لا مشاريع قابلة للفوترة." description="الفوترة للمشاريع المعتمدة أو الجارية أو المنجزة." />
        @else
            <form method="POST" action="{{ route('invoices.store') }}" class="space-y-6">
                @csrf
                @include('invoices._form', ['selectedProject' => $selectedProject, 'selectedContract' => $selectedContract])
                <div class="flex gap-2">
                    <x-button type="submit">حفظ المسودة</x-button>
                    <x-button variant="secondary" :href="route('invoices.index')">إلغاء</x-button>
                </div>
            </form>
        @endif
    </x-card>
</x-app-layout>
