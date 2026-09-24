<table class="min-w-full divide-y divide-gray-200 text-sm">
    <caption class="sr-only">بنود الفاتورة</caption>
    <thead class="bg-gray-50 text-xs font-semibold text-gray-600">
        <tr>
            <th scope="col" class="px-5 py-3 text-start">#</th>
            <th scope="col" class="px-5 py-3 text-start">الوصف</th>
            <th scope="col" class="px-5 py-3 text-start">الكمية</th>
            <th scope="col" class="px-5 py-3 text-start">سعر الوحدة</th>
            <th scope="col" class="px-5 py-3 text-start">الإجمالي</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-gray-100 bg-white">
        @foreach ($invoice->items as $item)
            <tr>
                <td class="px-5 py-3 text-gray-500">{{ $loop->iteration }}</td>
                <td class="px-5 py-3">{{ $item->description }}</td>
                <td class="px-5 py-3 tabular-nums">{{ rtrim(rtrim($item->quantity, '0'), '.') }}</td>
                <td class="px-5 py-3"><x-money :amount="$item->unit_price" /></td>
                <td class="px-5 py-3"><x-money :amount="$item->total" /></td>
            </tr>
        @endforeach
    </tbody>
    <tfoot class="bg-gray-50">
        <tr><th scope="row" colspan="4" class="px-5 py-2 text-start font-normal text-gray-600">المجموع قبل الضريبة</th><td class="px-5 py-2"><x-money :amount="$invoice->subtotal" /></td></tr>
        <tr><th scope="row" colspan="4" class="px-5 py-2 text-start font-normal text-gray-600">ضريبة القيمة المضافة ({{ (float) $invoice->vat_rate }}٪)</th><td class="px-5 py-2"><x-money :amount="$invoice->vat_amount" /></td></tr>
        <tr><th scope="row" colspan="4" class="px-5 py-2 text-start">الإجمالي شامل الضريبة</th><td class="px-5 py-2 font-bold"><x-money :amount="$invoice->total" /></td></tr>
    </tfoot>
</table>
