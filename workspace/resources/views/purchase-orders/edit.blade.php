<x-app-layout>
    <x-slot:title>تعديل {{ $order->number }}</x-slot:title>

    <x-page-header :title="'تعديل أمر الشراء '.$order->number">
        <x-slot:breadcrumb><a href="{{ route('purchase-orders.show', $order) }}" class="hover:underline" dir="ltr">{{ $order->number }}</a></x-slot:breadcrumb>
    </x-page-header>

    <x-card>
        <form method="POST" action="{{ route('purchase-orders.update', $order) }}" class="space-y-6">
            @csrf
            @method('PUT')
            @include('purchase-orders._form', ['order' => $order])
            <div class="flex gap-2">
                <x-button type="submit">حفظ</x-button>
                <x-button variant="secondary" :href="route('purchase-orders.show', $order)">إلغاء</x-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
