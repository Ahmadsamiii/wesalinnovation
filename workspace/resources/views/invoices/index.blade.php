<x-app-layout>
    @php($pageTitle = $isClient ? 'فواتيري' : 'الفواتير')
    <x-slot:title>{{ $pageTitle }}</x-slot:title>

    <x-page-header :title="$pageTitle">
        <x-slot:actions>
            @can('create', \App\Models\Invoice::class)
                <x-button :href="route('invoices.create')">فاتورة جديدة</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @unless ($isClient)
        <x-finance-nav active="invoices" />
    @endunless

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <x-stat :label="$isClient ? 'المستحق عليك' : 'المستحق على العملاء'">
            <x-slot:value><x-money :amount="$outstanding" /></x-slot:value>
        </x-stat>
        <x-stat label="فواتير متأخرة" :value="$overdueCount" :href="route('invoices.index', ['status' => 'overdue'])" />
    </div>

    <form method="GET" action="{{ route('invoices.index') }}" class="mb-6 flex flex-wrap items-end gap-3" role="search">
        <x-form.select name="status" label="الحالة" placeholder="كل الحالات" :value="$filters['status'] ?? null"
                       :options="collect(\App\Enums\InvoiceStatus::cases())
                           ->reject(fn ($s) => $isClient && in_array($s, [\App\Enums\InvoiceStatus::Draft, \App\Enums\InvoiceStatus::Cancelled], true))
                           ->mapWithKeys(fn ($s) => [$s->value => $s->label()])->put('overdue', 'متأخرة')->all()" />
        <x-button type="submit" variant="secondary">تصفية</x-button>
        @if (array_filter($filters))
            <x-button variant="ghost" :href="route('invoices.index')">مسح</x-button>
        @endif
    </form>

    @if ($invoices->isEmpty())
        <x-empty-state title="لا فواتير مطابقة." />
    @else
        <x-card :padding="false">
            <x-table caption="الفواتير">
                <thead class="bg-gray-50 text-xs font-semibold text-gray-600">
                    <tr>
                        <th scope="col" class="px-5 py-3 text-start">الرقم</th>
                        <th scope="col" class="px-5 py-3 text-start">المشروع</th>
                        @unless ($isClient)
                            <th scope="col" class="px-5 py-3 text-start">العميل</th>
                        @endunless
                        <th scope="col" class="px-5 py-3 text-start">الإصدار</th>
                        <th scope="col" class="px-5 py-3 text-start">الاستحقاق</th>
                        <th scope="col" class="px-5 py-3 text-start">الإجمالي</th>
                        <th scope="col" class="px-5 py-3 text-start">المتبقي</th>
                        <th scope="col" class="px-5 py-3 text-start">الحالة</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach ($invoices as $invoice)
                        <tr>
                            <td class="whitespace-nowrap px-5 py-3"><a href="{{ route('invoices.show', $invoice) }}" class="font-medium text-brand-800 hover:underline" dir="ltr">{{ $invoice->number ?? 'مسودة #'.$invoice->id }}</a></td>
                            <td class="px-5 py-3 text-gray-600">{{ $invoice->project->name }}</td>
                            @unless ($isClient)
                                <td class="px-5 py-3 text-gray-600">{{ $invoice->client?->name ?? '-' }}</td>
                            @endunless
                            <td class="px-5 py-3 text-gray-600"><x-date :value="$invoice->issue_date" /></td>
                            <td @class(['px-5 py-3', 'font-semibold text-red-700' => $invoice->isOverdue(), 'text-gray-600' => ! $invoice->isOverdue()])><x-date :value="$invoice->due_date" /></td>
                            <td class="px-5 py-3"><x-money :amount="$invoice->total" /></td>
                            <td class="px-5 py-3"><x-money :amount="$invoice->balance()" /></td>
                            <td class="px-5 py-3">
                                <x-badge :color="$invoice->status->color()">{{ $invoice->status->label() }}</x-badge>
                                @if ($invoice->isOverdue())
                                    <x-badge color="red">متأخرة</x-badge>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        </x-card>
        @if ($invoices->hasPages())
            <div class="mt-6">{{ $invoices->links() }}</div>
        @endif
    @endif
</x-app-layout>
