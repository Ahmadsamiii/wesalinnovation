<x-app-layout>
    <x-slot:title>تعديل {{ $project->name }}</x-slot:title>

    <x-page-header :title="'تعديل '.$project->name">
        <x-slot:breadcrumb><a href="{{ route('projects.show', $project) }}" class="hover:underline">{{ $project->name }}</a></x-slot:breadcrumb>
    </x-page-header>

    <x-card>
        <form method="POST" action="{{ route('projects.update', $project) }}" class="space-y-6">
            @csrf
            @method('PUT')
            @include('projects._form', ['project' => $project])

            <div class="flex gap-2">
                <x-button type="submit">حفظ</x-button>
                <x-button variant="secondary" :href="route('projects.show', $project)">إلغاء</x-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
