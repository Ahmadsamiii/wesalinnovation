<x-app-layout>
    @php($pageTitle = $isApprover ? 'طلبات الإفادة' : 'طلب إفادة')
    <x-slot:title>{{ $pageTitle }}</x-slot:title>

    <x-page-header :title="$pageTitle" :description="$isApprover ? 'إفادات منسوبي المنشأة: ما ينتظر اعتمادك أولاً.' : 'إفادة وظيفية تثبت عملك في وصال الابتكار، برمز تحقق تتأكد منه الجهة المقدَّمة إليها.'">
        <x-slot:actions>
            @unless ($isApprover)
                <x-button :href="route('reference-letters.create')">طلب إفادة جديدة</x-button>
            @endunless
        </x-slot:actions>
    </x-page-header>

    @if ($missingProfile && ! $isApprover)
        <p class="mb-6 rounded-xl border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-800" role="note">
            مسماك الوظيفي أو تاريخ انضمامك غير مسجّل؛ الإفادة تُكتب منهما. اطلب من مدير النظام استكمالهما قبل الطلب.
        </p>
    @endif

    @forelse ($letters as $letter)
        <x-card class="mb-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0 flex-1 text-sm">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-badge :color="$letter->status->color()">{{ $letter->status->label() }}</x-badge>
                        @if ($isApprover)
                            <span class="font-semibold">{{ $letter->requester->name }}</span>
                            <span class="text-gray-500">— {{ $letter->requester->job_title ?? 'بلا مسمى' }}</span>
                        @endif
                        @if ($letter->number)
                            <span class="text-gray-500" dir="ltr">{{ $letter->number }}</span>
                        @endif
                    </div>
                    <p class="mt-2"><span class="text-gray-500">الغرض:</span> {{ $letter->purpose }}</p>
                    <p><span class="text-gray-500">إلى:</span> {{ $letter->addressee ?? 'من يهمه الأمر' }}</p>
                    @if ($letter->notes)
                        <p class="mt-1 text-gray-600">{{ $letter->notes }}</p>
                    @endif
                    <p class="mt-2 text-xs text-gray-500">طُلبت <x-date :value="$letter->created_at" relative />
                        @if ($letter->decider) — قرّرها {{ $letter->decider->name }} <x-date :value="$letter->decided_at" /> @endif
                    </p>
                    @if ($letter->decision_note)
                        <p @class(['mt-2 rounded-lg p-3 text-xs', 'bg-red-50 text-red-800' => $letter->status === \App\Enums\ReferenceLetterStatus::Rejected, 'bg-surface text-gray-700' => $letter->status !== \App\Enums\ReferenceLetterStatus::Rejected])>{{ $letter->decision_note }}</p>
                    @endif
                </div>

                <div class="flex w-full flex-col gap-2 sm:w-72">
                    @can('print', $letter)
                        <x-button :href="route('reference-letters.show', $letter)">عرض الإفادة وطباعتها</x-button>
                    @endcan
                    @can('decide', $letter)
                        <form method="POST" action="{{ route('reference-letters.approve', $letter) }}">
                            @csrf
                            <x-button type="submit" variant="success" class="w-full">اعتماد</x-button>
                        </form>
                        <details>
                            <summary class="cursor-pointer text-sm text-red-700">رفض بتعليل</summary>
                            <form method="POST" action="{{ route('reference-letters.reject', $letter) }}" class="mt-2 space-y-2">
                                @csrf
                                <x-form.textarea name="note" label="التعليل" rows="2" :id="'reject-'.$letter->id" required />
                                <x-button type="submit" variant="danger" size="sm">رفض</x-button>
                            </form>
                        </details>
                    @endcan
                </div>
            </div>
        </x-card>
    @empty
        <x-empty-state :title="$isApprover ? 'لا طلبات إفادة.' : 'لم تطلب إفادة بعد.'" />
    @endforelse

    @if ($letters->hasPages())
        <div class="mt-6">{{ $letters->links() }}</div>
    @endif
</x-app-layout>
