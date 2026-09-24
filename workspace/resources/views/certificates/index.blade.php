<x-app-layout>
    @php($pageTitle = $isRecipientView ? (auth()->user()->hasRole('client') ? 'شهادة إنجازي' : 'شهاداتي') : 'شهادات الإنجاز')
    <x-slot:title>{{ $pageTitle }}</x-slot:title>

    <x-page-header :title="$pageTitle" :description="$isRecipientView ? 'الشهادات الصادرة لك، برمز تحقق يستطيع أي طرف التأكد منه.' : 'شهادات إنجاز المشاريع للعملاء وشهادات المشاركة لأعضاء الفرق.'">
        <x-slot:actions>
            @can('create', \App\Models\Certificate::class)
                <x-button :href="route('certificates.create')">إصدار شهادة</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if ($awaiting->isNotEmpty())
        <x-card title="مشاريع منجزة بلا شهادة إنجاز" class="mb-6" :padding="false">
            <ul class="divide-y divide-gray-100 text-sm" role="list">
                @foreach ($awaiting as $project)
                    <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-3">
                        <span><span class="font-medium">{{ $project->name }}</span><span class="text-gray-500">، أُنجز في <x-date :value="$project->actual_end_date" /></span></span>
                        <x-button size="sm" :href="route('certificates.create', ['project' => $project->id, 'type' => 'completion'])">إصدار شهادة الإنجاز</x-button>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif

    @if ($certificates->isEmpty())
        <x-empty-state title="لا شهادات بعد." :description="$isRecipientView ? 'تصدر شهادة الإنجاز حين يُنجز مشروعك.' : null" />
    @else
        <x-card :padding="false">
            <x-table caption="الشهادات">
                <thead class="bg-gray-50 text-xs font-semibold text-gray-600">
                    <tr>
                        <th scope="col" class="px-5 py-3 text-start">الرقم</th>
                        <th scope="col" class="px-5 py-3 text-start">النوع</th>
                        <th scope="col" class="px-5 py-3 text-start">المستلم</th>
                        <th scope="col" class="px-5 py-3 text-start">المشروع</th>
                        <th scope="col" class="px-5 py-3 text-start">الإصدار</th>
                        <th scope="col" class="px-5 py-3 text-start">الحالة</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach ($certificates as $certificate)
                        <tr>
                            <td class="whitespace-nowrap px-5 py-3"><a href="{{ route('certificates.show', $certificate) }}" class="font-medium text-brand-800 hover:underline" dir="ltr">{{ $certificate->number }}</a></td>
                            <td class="px-5 py-3">{{ $certificate->type->label() }}</td>
                            <td class="px-5 py-3">{{ $certificate->recipient->name }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $certificate->project->name }}</td>
                            <td class="px-5 py-3 text-gray-600"><x-date :value="$certificate->issued_at" /></td>
                            <td class="px-5 py-3">
                                @if ($certificate->isRevoked())
                                    <x-badge color="red">ملغاة</x-badge>
                                @else
                                    <x-badge color="green">سارية</x-badge>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        </x-card>
        @if ($certificates->hasPages())
            <div class="mt-6">{{ $certificates->links() }}</div>
        @endif
    @endif
</x-app-layout>
