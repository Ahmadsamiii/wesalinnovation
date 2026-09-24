<x-app-layout>
    <x-slot:title>{{ $label }}</x-slot:title>

    <x-page-header :title="$label" />

    <x-empty-state title="هذا القسم قيد البناء." description="سيظهر هنا تلقائياً حين تكتمل وحدته." />
</x-app-layout>
