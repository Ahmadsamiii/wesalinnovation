<x-app-layout>
    <x-slot:title>تعديل {{ $task->title }}</x-slot:title>

    <x-page-header :title="'تعديل: '.$task->title">
        <x-slot:breadcrumb><a href="{{ route('tasks.show', $task) }}" class="hover:underline">{{ $project->name }}</a></x-slot:breadcrumb>
    </x-page-header>

    <x-card>
        <form method="POST" action="{{ route('tasks.update', $task) }}" class="space-y-6">
            @csrf
            @method('PUT')
            @include('tasks._form', ['task' => $task])
            <div class="flex gap-2">
                <x-button type="submit">حفظ</x-button>
                <x-button variant="secondary" :href="route('tasks.show', $task)">إلغاء</x-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
