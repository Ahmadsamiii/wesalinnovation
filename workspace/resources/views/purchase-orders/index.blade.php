<x-app-layout>
    <x-slot:title>أوامر الشراء</x-slot:title>

    <x-page-header title="أوامر الشراء">
        <x-slot:actions>
            @can('create', \App\Models\PurchaseOrder::class)
                <x-button :href="route('purchase-orders.create')">أمر شراء جديد</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-finance-nav active="purchase-orders" />

    <form method="GET" action="{{ route('purchase-orders.index') }}" class="mb-6 flex flex-wrap items-end gap-3" role="search">
        <x-form.select name="status" label="الحالة" placeholder="كل الحالات" :value="$filters['status'] ?? null"
                       :options="collect(\App\Enums\PurchaseOrderStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()" />
        <x-button type="submit" variant="secondary">تصفية</x-button>
        @if (array_filter($filters))
            <x-button variant="ghost" :href="route('purchase-orders.index')">مسح</x-button>
        @endif
    </form>

    @if ($orders->isEmpty())
        <x-empty-state title="لا أوامر شراء مطابقة." />
    @else
        <x-card :padding="false">
            <x-table caption="أوامر الشراء">
                <thead class="bg-gray-50 text-xs font-semibold text-gray-600">
                    <tr>
                        <th scope="col" class="px-5 py-3 text-start">الرقم</th>
                        <th scope="col" class="px-5 py-3 text-start">المورّد</th>
                        <th scope="col" class="px-5 py-3 text-start">المشروع</th>
                        <th scope="col" class="px-5 py-3 text-start">الطالب</th>
                        <th scope="col" class="px-5 py-3 text-start">الإجمالي</th>
                        <th scope="col" class="px-5 py-3 text-start">الحالة</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach ($orders as $order)
                        <tr @class(['bg-yellow-50/60' => $awaitingMe && $order->status === $awaitingMe])>
                            <td class="whitespace-nowrap px-5 py-3"><a href="{{ route('purchase-orders.show', $order) }}" class="font-medium text-brand-800 hover:underline" dir="ltr">{{ $order->number }}</a></td>
                            <td class="px-5 py-3">{{ $order->vendor_name }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $order->project->name }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $order->requester->name }}</td>
                            <td class="px-5 py-3"><x-money :amount="$order->total" /></td>
                            <td class="px-5 py-3">
                                <x-badge :color="$order->status->color()">{{ $order->status->label() }}</x-badge>
                                @if ($awaitingMe && $order->status === $awaitingMe)
                                    <span class="sr-only">(بانتظار مراجعتك)</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        </x-card>
        @if ($orders->hasPages())
            <div class="mt-6">{{ $orders->links() }}</div>
        @endif
    @endif
</x-app-layout>
