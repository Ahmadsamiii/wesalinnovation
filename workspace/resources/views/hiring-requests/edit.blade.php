<x-app-layout>
    <x-slot:title>تعديل {{ $hiringRequest->number }}</x-slot:title>

    <x-page-header :title="'تعديل '.$hiringRequest->number" description="يُعدَّل الطلب ما دام بانتظار القرار.">
        <x-slot:breadcrumb><a href="{{ route('hiring-requests.show', $hiringRequest) }}" class="hover:underline">→ {{ $hiringRequest->title }}</a></x-slot:breadcrumb>
    </x-page-header>

    <x-card class="max-w-3xl">
        <form method="POST" action="{{ route('hiring-requests.update', $hiringRequest) }}" class="space-y-6">
            @csrf
            @method('PUT')
            @include('hiring-requests._form', ['hiringRequest' => $hiringRequest, 'projects' => $projects])
            <x-button type="submit">حفظ</x-button>
        </form>
    </x-card>
</x-app-layout>
