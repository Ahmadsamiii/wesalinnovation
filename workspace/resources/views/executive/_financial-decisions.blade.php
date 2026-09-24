@if ($approvals->isEmpty())
    <x-empty-state title="لا قرارات مالية بعد." />
@else
    <x-card :padding="false">
        <x-table caption="القرارات المالية">
            <thead class="bg-gray-50 text-xs font-semibold text-gray-600">
                <tr>
                    <th scope="col" class="px-5 py-3 text-start">التاريخ</th>
                    <th scope="col" class="px-5 py-3 text-start">أمر الشراء</th>
                    <th scope="col" class="px-5 py-3 text-start">المرحلة</th>
                    <th scope="col" class="px-5 py-3 text-start">القرار</th>
                    <th scope="col" class="px-5 py-3 text-start">التعليل</th>
                    <th scope="col" class="px-5 py-3 text-start">متخذه</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                @foreach ($approvals as $approval)
                    <tr>
                        <td class="whitespace-nowrap px-5 py-3 text-gray-600"><x-date :value="$approval->decided_at" time /></td>
                        <td class="px-5 py-3">
                            <a href="{{ route('purchase-orders.show', $approval->purchaseOrder) }}" class="font-medium text-brand-800 hover:underline"><span dir="ltr">{{ $approval->purchaseOrder->number }}</span></a>
                            <div class="text-xs text-gray-500">{{ $approval->purchaseOrder->vendor_name }}، <x-money :amount="$approval->purchaseOrder->total" /></div>
                        </td>
                        <td class="px-5 py-3 text-gray-600">{{ $approval->stage->label() }}</td>
                        <td class="px-5 py-3"><x-badge :color="$approval->decision->color()">{{ $approval->decision->label() }}</x-badge></td>
                        <td class="px-5 py-3 text-gray-600">{{ $approval->note ?? '-' }}</td>
                        <td class="px-5 py-3">{{ $approval->decider->name }}</td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
    </x-card>
@endif
