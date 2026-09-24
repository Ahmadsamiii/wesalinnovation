<x-app-layout>
    @php($pageTitle = $isClient ? 'عقودي' : 'العقود')
    <x-slot:title>{{ $pageTitle }}</x-slot:title>

    <x-page-header :title="$pageTitle" :description="$isClient ? 'عقودك الموقّعة مع وصال الابتكار ونسخها.' : null">
        <x-slot:actions>
            @can('create', \App\Models\Contract::class)
                <x-button :href="route('contracts.create')">عقد جديد</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @unless ($isClient)
        <x-finance-nav active="contracts" />

        <form method="GET" action="{{ route('contracts.index') }}" class="mb-6 grid gap-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:grid-cols-3" role="search">
            <x-form.select name="project" label="المشروع" :options="$projects" :value="$filters['project'] ?? null" placeholder="كل المشاريع" />
            <x-form.select name="status" label="الحالة" placeholder="كل الحالات" :value="$filters['status'] ?? null"
                           :options="collect(\App\Enums\ContractStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()" />
            <div class="flex items-end gap-2">
                <x-button type="submit">تصفية</x-button>
                @if (array_filter($filters))
                    <x-button variant="ghost" :href="route('contracts.index')">مسح</x-button>
                @endif
            </div>
        </form>
    @endunless

    @if ($contracts->isEmpty())
        <x-empty-state :title="$isClient ? 'لا عقود موقّعة بعد.' : 'لا عقود مطابقة.'" />
    @else
        <x-card :padding="false">
            <x-table caption="العقود">
                <thead class="bg-gray-50 text-xs font-semibold text-gray-600">
                    <tr>
                        <th scope="col" class="px-5 py-3 text-start">الرقم</th>
                        <th scope="col" class="px-5 py-3 text-start">العنوان</th>
                        <th scope="col" class="px-5 py-3 text-start">المشروع</th>
                        @unless ($isClient)
                            <th scope="col" class="px-5 py-3 text-start">العميل</th>
                        @endunless
                        <th scope="col" class="px-5 py-3 text-start">القيمة</th>
                        <th scope="col" class="px-5 py-3 text-start">المدة</th>
                        <th scope="col" class="px-5 py-3 text-start">الحالة</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach ($contracts as $contract)
                        <tr>
                            <td class="whitespace-nowrap px-5 py-3"><a href="{{ route('contracts.show', $contract) }}" class="font-medium text-brand-800 hover:underline" dir="ltr">{{ $contract->number }}</a></td>
                            <td class="px-5 py-3">{{ $contract->title }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $contract->project->name }}</td>
                            @unless ($isClient)
                                <td class="px-5 py-3 text-gray-600">{{ $contract->client?->name ?? '—' }}</td>
                            @endunless
                            <td class="px-5 py-3"><x-money :amount="$contract->value" /></td>
                            <td class="px-5 py-3 text-gray-600"><x-date :value="$contract->start_date" /> ← <x-date :value="$contract->end_date" /></td>
                            <td class="px-5 py-3"><x-badge :color="$contract->status->color()">{{ $contract->status->label() }}</x-badge></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        </x-card>
        @if ($contracts->hasPages())
            <div class="mt-6">{{ $contracts->links() }}</div>
        @endif
    @endif
</x-app-layout>
