<x-app-layout>
    <x-slot:title>قائمة مراجعة المحتوى الصحي</x-slot:title>

    <x-page-header title="قائمة مراجعة المحتوى الصحي" description="لا يُنشر محتوى صحي إلا بقرارك. الأقدم تقديماً أولاً." />

    @if ($queue->isEmpty())
        <x-empty-state title="لا محتوى ينتظر مراجعتك." class="mb-6" />
    @else
        <div class="mb-8 space-y-4">
            @foreach ($queue as $content)
                <x-card>
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0 flex-1 text-sm">
                            <p class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('medical.review.show', $content) }}" class="text-base font-semibold text-brand-800 hover:underline">{{ $content->title }}</a>
                                <x-badge>{{ $content->category->label() }}</x-badge>
                                @if ($content->isPublished())
                                    <x-badge color="blue">تعديل على منشور</x-badge>
                                @endif
                            </p>
                            @if ($content->summary)
                                <p class="mt-1 text-gray-600">{{ $content->summary }}</p>
                            @endif
                            <p class="mt-2 text-xs text-gray-500">النسخة {{ $content->version }}، مقدَّمة من {{ $content->author->name }} <x-date :value="$content->submitted_at" relative /></p>
                        </div>
                        <x-button :href="route('medical.review.show', $content)">مراجعة</x-button>
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif

    <x-card title="مراجعة دورية مستحقة خلال ٣٠ يوماً" :padding="false">
        @if ($due->isEmpty())
            <p class="p-5 text-sm text-gray-500">لا منشور يستحق المراجعة الدورية قريباً.</p>
        @else
            <ul class="divide-y divide-gray-100 text-sm" role="list">
                @foreach ($due as $content)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                        <span>
                            <a href="{{ route('medical.review.show', $content) }}" class="font-medium text-brand-800 hover:underline">{{ $content->title }}</a>
                            <span @class(['ms-1 text-xs', 'font-semibold text-red-700' => $content->isReviewOverdue(), 'text-gray-500' => ! $content->isReviewOverdue()])>
                                {{ $content->isReviewOverdue() ? 'تجاوز موعده' : 'يستحق' }} <x-date :value="$content->review_due_on" />
                            </span>
                        </span>
                        @can('renew', $content)
                            <form method="POST" action="{{ route('medical.review.renew', $content) }}">
                                @csrf
                                <x-button type="submit" variant="secondary" size="sm">تأكيد المراجعة وتجديد الاعتماد</x-button>
                            </form>
                        @endcan
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
</x-app-layout>
