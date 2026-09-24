<x-app-layout>
    <x-slot:title>مراجعة: {{ $content->title }}</x-slot:title>

    <x-page-header :title="$content->title">
        <x-slot:breadcrumb><a href="{{ route('medical.review') }}" class="hover:underline">قائمة المراجعة</a> / {{ $content->category->label() }}</x-slot:breadcrumb>
        <x-slot:actions>
            <x-badge :color="$content->status->color()">{{ $content->status->label() }}</x-badge>
        </x-slot:actions>
    </x-page-header>

    <div class="grid [&>*]:min-w-0 gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            <x-card :title="'النسخة '.$content->version.' كما قُدّمت'">
                @if ($content->summary)
                    <p class="mb-4 text-sm font-medium text-gray-700">{{ $content->summary }}</p>
                @endif
                <x-prose :text="$content->body" class="text-base leading-8 text-gray-900" />
                @if ($content->source_url)
                    <p class="mt-4 text-sm text-gray-600">المرجع: <a href="{{ $content->source_url }}" class="text-brand-800 hover:underline" dir="ltr" rel="noopener noreferrer" target="_blank">{{ $content->source_url }}</a></p>
                @endif
            </x-card>

            @if ($content->isPublished() && $content->hasUnpublishedChanges())
                <details class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <summary class="cursor-pointer text-sm font-semibold text-brand-800">النسخة {{ $content->published_version }} المنشورة الآن (للمقارنة)</summary>
                    <x-prose :text="$content->published_body" class="mt-4 text-sm leading-8 text-gray-700" />
                </details>
            @endif
        </div>

        <div class="min-w-0 space-y-6">
            @can('review', $content)
                <x-card title="قرارك">
                    <form method="POST" action="{{ route('medical.review.approve', $content) }}" class="space-y-3">
                        @csrf
                        <x-form.textarea name="note" label="ملاحظة مع الاعتماد" rows="2" />
                        <x-button type="submit" variant="success" class="w-full">اعتماد ونشر</x-button>
                    </form>
                    <details class="mt-4" @if ($errors->has('reason')) open @endif>
                        <summary class="cursor-pointer text-sm text-red-700">إعادة للتعديل بملاحظات</summary>
                        <form method="POST" action="{{ route('medical.review.reject', $content) }}" class="mt-2 space-y-2">
                            @csrf
                            <x-form.textarea name="reason" label="ما يجب تعديله" rows="4" required />
                            <x-button type="submit" variant="danger" size="sm">إعادة للتعديل</x-button>
                        </form>
                    </details>
                    <p class="mt-4 text-xs text-gray-500">الاعتماد ينشر هذا النص كما هو، وتبقى صلاحيته {{ \App\Models\HealthContent::REVIEW_VALID_MONTHS }} شهراً ثم يعود لقائمتك للمراجعة الدورية.</p>
                </x-card>
            @endcan

            @can('renew', $content)
                <x-card title="المراجعة الدورية">
                    <p class="text-sm text-gray-600">منشور حتى مراجعة <x-date :value="$content->review_due_on" />.</p>
                    <form method="POST" action="{{ route('medical.review.renew', $content) }}" class="mt-3 space-y-3">
                        @csrf
                        <x-form.textarea name="note" label="ملاحظة" rows="2" />
                        <x-button type="submit" variant="secondary" class="w-full">راجعته — جدّد الاعتماد</x-button>
                    </form>
                </x-card>
            @endcan

            @can('withdraw', $content)
                <x-card>
                    <details @if ($errors->has('reason') && ! auth()->user()->can('review', $content)) open @endif>
                        <summary class="cursor-pointer text-sm text-red-700">سحب من النشر فوراً</summary>
                        <form method="POST" action="{{ route('content.withdraw', $content) }}" class="mt-2 space-y-2">
                            @csrf
                            <x-form.textarea name="reason" label="السبب" rows="2" required id="withdraw-reason" />
                            <x-button type="submit" variant="danger" size="sm">سحب</x-button>
                        </form>
                    </details>
                </x-card>
            @endcan

            <x-card title="القرارات السابقة" :padding="false">
                @if ($content->reviews->isEmpty())
                    <p class="p-5 text-sm text-gray-500">أول مراجعة لهذا المحتوى.</p>
                @else
                    <ol class="divide-y divide-gray-100 text-sm" role="list">
                        @foreach ($content->reviews as $review)
                            <li class="px-5 py-3">
                                <p><x-badge :color="$review->decision->color()">{{ $review->decision->label() }}</x-badge> النسخة {{ $review->version }}</p>
                                <p class="mt-1 text-xs text-gray-500">{{ $review->reviewer->name }} — <x-date :value="$review->created_at" /></p>
                                @if ($review->note)
                                    <p class="mt-1 whitespace-pre-line text-xs text-gray-700">{{ $review->note }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-card>
        </div>
    </div>
</x-app-layout>
