<x-app-layout>
    <x-slot:title>تعديل مسودة فاتورة</x-slot:title>

    <x-page-header title="تعديل مسودة الفاتورة">
        <x-slot:breadcrumb><a href="{{ route('invoices.show', $invoice) }}" class="hover:underline">الفاتورة</a></x-slot:breadcrumb>
    </x-page-header>

    <x-card>
        <form method="POST" action="{{ route('invoices.update', $invoice) }}" class="space-y-6">
            @csrf
            @method('PUT')
            @include('invoices._form', ['invoice' => $invoice])
            <div class="flex gap-2">
                <x-button type="submit">حفظ</x-button>
                <x-button variant="secondary" :href="route('invoices.show', $invoice)">إلغاء</x-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
