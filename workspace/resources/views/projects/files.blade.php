<x-app-layout>
    <x-slot:title>ملفات {{ $project->name }}</x-slot:title>

    <x-project-header :project="$project" active="files" />

    <div class="grid [&>*]:min-w-0 gap-6 lg:grid-cols-3">
        <x-card title="ملفات المشروع ومهامه" class="lg:col-span-2">
            <x-attachment-list :attachments="$attachments" show-source />
        </x-card>

        @can('upload', $project)
            <x-card title="رفع على المشروع">
                <x-upload-form :action="route('projects.files.store', $project)" />
            </x-card>
        @endcan
    </div>
</x-app-layout>
