<x-app-layout>
    <x-slot:title>سجل المحتوى المعتمد والمرفوض</x-slot:title>

    <x-page-header title="سجل المحتوى المعتمد والمرفوض" description="كل قرار طبي بنسخته وملاحظته، ولا يُعدَّل." />

    <nav aria-label="تصفية حسب القرار" class="mb-6 flex flex-wrap gap-2 text-sm">
        @foreach (['' => 'الكل', 'approved' => 'المعتمد', 'rejected' => 'المُعاد للتعديل'] as $value => $label)
            @php($active = ($filters['decision'] ?? '') === $value)
            <a href="{{ route('medical.log', array_filter(['decision' => $value])) }}" @if ($active) aria-current="true" @endif
               @class(['rounded-full border px-3 py-1.5 font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-600',
                       'border-brand-800 bg-brand-800 text-white' => $active, 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50' => ! $active])>{{ $label }}</a>
        @endforeach
    </nav>

    @if ($reviews->isEmpty())
        <x-empty-state title="لا قرارات بعد." />
    @else
        <x-card :padding="false">
            <x-table caption="القرارات الطبية">
                <thead class="bg-gray-50 text-xs font-semibold text-gray-600">
                    <tr>
                        <th scope="col" class="px-5 py-3 text-start">القرار</th>
                        <th scope="col" class="px-5 py-3 text-start">المحتوى</th>
                        <th scope="col" class="px-5 py-3 text-start">الملاحظة</th>
                        <th scope="col" class="px-5 py-3 text-start">المراجِع والتاريخ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white align-top">
                    @foreach ($reviews as $review)
                        <tr>
                            <td class="whitespace-nowrap px-5 py-3"><x-badge :color="$review->decision->color()">{{ $review->decision->label() }}</x-badge></td>
                            <td class="px-5 py-3">
                                <a href="{{ route('medical.review.show', $review->content) }}" class="font-medium text-brand-800 hover:underline">{{ $review->reviewed_title }}</a>
                                <div class="text-xs text-gray-500">النسخة {{ $review->version }}، {{ $review->content->category->label() }}</div>
                            </td>
                            <td class="max-w-md px-5 py-3 text-gray-700">{{ $review->note ?? '-' }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-gray-600">{{ $review->reviewer->name }}<div class="text-xs"><x-date :value="$review->created_at" /></div></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        </x-card>

        @if ($reviews->hasPages())
            <div class="mt-6">{{ $reviews->links() }}</div>
        @endif
    @endif
</x-app-layout>
