<x-app-layout>
    <x-slot:title>تعديل {{ $contract->number }}</x-slot:title>

    <x-page-header :title="'تعديل العقد '.$contract->number">
        <x-slot:breadcrumb><a href="{{ route('contracts.show', $contract) }}" class="hover:underline">{{ $contract->title }}</a></x-slot:breadcrumb>
    </x-page-header>

    <x-card>
        <form method="POST" action="{{ route('contracts.update', $contract) }}" class="space-y-6">
            @csrf
            @method('PUT')
            @include('contracts._form', ['contract' => $contract])
            <div class="flex gap-2">
                <x-button type="submit">حفظ</x-button>
                <x-button variant="secondary" :href="route('contracts.show', $contract)">إلغاء</x-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
