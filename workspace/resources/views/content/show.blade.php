<x-app-layout>
    <x-slot:title>{{ $content->title }}</x-slot:title>

    <x-page-header :title="$content->title">
        <x-slot:breadcrumb><a href="{{ route('content.index') }}" class="hover:underline">إدارة المحتوى</a> / {{ $content->category->label() }}</x-slot:breadcrumb>
        <x-slot:actions>
            <x-badge :color="$content->status->color()">{{ $content->status->label() }}</x-badge>
            @can('update', $content)
                <x-button variant="secondary" :href="route('content.edit', $content)">تعديل</x-button>
            @endcan
            @can('submit', $content)
                <form method="POST" action="{{ route('content.submit', $content) }}">
                    @csrf
                    <x-button type="submit">تقديم للمراجعة الطبية</x-button>
                </form>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if ($content->status === \App\Enums\HealthContentStatus::InReview)
        <p class="mb-6 rounded-xl border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-800" role="note">
            النسخة {{ $content->version }} عند المدير الطبي منذ <x-date :value="$content->submitted_at" relative />، والتعديل مقفل حتى يقرّر.
        </p>
    @elseif ($content->hasUnpublishedChanges())
        <p class="mb-6 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-800" role="note">
            المنشور الآن هو النسخة {{ $content->published_version }} المعتمدة؛ ما تراه أدناه تعديل لم يُعتمد بعد.
        </p>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card title="النص قيد التحرير" class="min-w-0 lg:col-span-2">
            @if ($content->summary)
                <p class="mb-4 text-sm font-medium text-gray-700">{{ $content->summary }}</p>
            @endif
            <x-prose :text="$content->body" class="text-sm leading-8 text-gray-800" />
            @if ($content->source_url)
                <p class="mt-4 text-xs text-gray-500">المرجع: <a href="{{ $content->source_url }}" class="text-brand-800 hover:underline" dir="ltr" rel="noopener noreferrer" target="_blank">{{ $content->source_url }}</a></p>
            @endif
        </x-card>

        <div class="min-w-0 space-y-6">
            <x-card title="النشر">
                @if ($content->isPublished())
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-2"><dt class="text-gray-500">منشور منذ</dt><dd><x-date :value="$content->published_at" /></dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-gray-500">النسخة المنشورة</dt><dd>{{ $content->published_version }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-gray-500">المراجعة الدورية</dt><dd @class(['font-semibold text-red-700' => $content->isReviewOverdue()])><x-date :value="$content->review_due_on" /></dd></div>
                    </dl>
                    <x-button variant="secondary" size="sm" class="mt-4 w-full" :href="route('kb.show', $content)" target="_blank">الصفحة العامة ↗</x-button>
                    @can('withdraw', $content)
                        <details class="mt-4" @if ($errors->has('reason')) open @endif>
                            <summary class="cursor-pointer text-sm text-red-700">سحب من النشر</summary>
                            <form method="POST" action="{{ route('content.withdraw', $content) }}" class="mt-2 space-y-2">
                                @csrf
                                <x-form.textarea name="reason" label="السبب" rows="2" required />
                                <x-button type="submit" variant="danger" size="sm">سحب</x-button>
                            </form>
                        </details>
                    @endcan
                @else
                    <p class="text-sm text-gray-500">غير منشور. يُنشر حين يعتمده المدير الطبي.</p>
                @endif
            </x-card>

            <x-card title="القرارات الطبية" :padding="false">
                @if ($content->reviews->isEmpty())
                    <p class="p-5 text-sm text-gray-500">لم يُراجَع بعد.</p>
                @else
                    <ol class="divide-y divide-gray-100 text-sm" role="list">
                        @foreach ($content->reviews as $review)
                            <li class="px-5 py-3">
                                <p><x-badge :color="$review->decision->color()">{{ $review->decision->label() }}</x-badge> النسخة {{ $review->version }}</p>
                                <p class="mt-1 text-xs text-gray-500">{{ $review->reviewer->name }} — <x-date :value="$review->created_at" /></p>
                                @if ($review->note)
                                    <p class="mt-1 whitespace-pre-line rounded-lg bg-surface p-2 text-xs text-gray-700">{{ $review->note }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-card>

            @can('delete', $content)
                <form method="POST" action="{{ route('content.destroy', $content) }}" onsubmit="return confirm(@js('حذف المسودة «'.$content->title.'» نهائياً؟'))">
                    @csrf
                    @method('DELETE')
                    <x-button type="submit" variant="ghost" size="sm" class="text-red-700">حذف المسودة</x-button>
                </form>
            @endcan
        </div>
    </div>
</x-app-layout>
