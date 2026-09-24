@props(['project', 'active' => 'overview'])

@php
$sections = [
    'overview' => ['label' => 'نظرة عامة', 'url' => route('projects.show', $project)],
];

if (auth()->user()->can('viewInternals', $project)) {
    $sections += [
        'tasks' => ['label' => 'المهام', 'url' => route('projects.tasks.index', $project)],
        'milestones' => ['label' => 'المراحل والمعالم', 'url' => route('projects.milestones.index', $project)],
        'members' => ['label' => 'الفريق', 'url' => route('projects.members.index', $project)],
        'files' => ['label' => 'الملفات', 'url' => route('projects.files.index', $project)],
    ];
}
@endphp

<div class="mb-6">
    <x-page-header :title="$project->name" class="mb-4">
        <x-slot:breadcrumb>
            <a href="{{ route('projects.index') }}" class="hover:underline">المشاريع</a>
        </x-slot:breadcrumb>
        <x-slot:actions>
            <x-badge :color="$project->status->color()">{{ $project->status->label() }}</x-badge>
            @if ($project->isOverdue())
                <x-badge color="red">متأخر عن موعده</x-badge>
            @endif
            {{ $slot }}
        </x-slot:actions>
    </x-page-header>

    @if (count($sections) > 1)
        <nav aria-label="أقسام المشروع" class="border-b border-gray-200">
            <ul class="-mb-px flex gap-1 overflow-x-auto" role="list">
                @foreach ($sections as $key => $section)
                    <li class="shrink-0">
                        <a href="{{ $section['url'] }}" @if ($key === $active) aria-current="page" @endif
                           @class([
                               'inline-flex border-b-2 px-3 py-2 text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-600',
                               'border-brand-800 text-brand-800' => $key === $active,
                               'border-transparent text-gray-500 hover:text-gray-800' => $key !== $active,
                           ])>{{ $section['label'] }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>
    @endif
</div>
