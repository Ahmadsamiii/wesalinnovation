<x-app-layout>
    <x-slot:title>تعديل {{ $content->title }}</x-slot:title>

    <x-page-header :title="'تعديل: '.$content->title" :description="$content->isPublished() ? 'التعديل يُحفظ مسودة؛ النسخة المعتمدة تبقى منشورة حتى يُعتمد التعديل.' : null">
        <x-slot:breadcrumb><a href="{{ route('content.show', $content) }}" class="hover:underline">→ {{ $content->title }}</a></x-slot:breadcrumb>
    </x-page-header>

    <x-card class="max-w-4xl">
        <form method="POST" action="{{ route('content.update', $content) }}" class="space-y-6">
            @csrf
            @method('PUT')
            @include('content._form', ['content' => $content])
            <x-button type="submit">حفظ</x-button>
        </form>
    </x-card>
</x-app-layout>
