<x-app-layout>
    <x-slot:title>مهمة جديدة</x-slot:title>

    <x-page-header title="مهمة جديدة">
        <x-slot:breadcrumb><a href="{{ route('projects.tasks.index', $project) }}" class="hover:underline">{{ $project->name }}</a></x-slot:breadcrumb>
    </x-page-header>

    <x-card>
        <form method="POST" action="{{ route('projects.tasks.store', $project) }}" class="space-y-6">
            @csrf
            @include('tasks._form', ['defaultMilestone' => $defaultMilestone])
            <div class="flex gap-2">
                <x-button type="submit">إضافة المهمة</x-button>
                <x-button variant="secondary" :href="route('projects.tasks.index', $project)">إلغاء</x-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
