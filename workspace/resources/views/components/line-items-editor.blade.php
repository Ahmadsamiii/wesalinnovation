@props(['items' => [], 'vatRate'])

@php
$initial = collect(old('items', collect($items)->map(fn ($item) => [
    'description' => $item->description,
    'quantity' => (string) $item->quantity,
    'unit_price' => (string) $item->unit_price,
])->all()))->values()->all();
$itemErrors = collect($errors->getMessages())->filter(fn ($messages, $key) => str_starts_with($key, 'items'))->all();
@endphp

<fieldset x-data="lineItems(@js($initial), @js((float) $vatRate), @js($itemErrors))" {{ $attributes }}>
    <legend class="text-sm font-medium text-gray-700">البنود</legend>
    @error('items')
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror

    <div class="mt-2 overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="text-xs text-gray-500">
                <tr>
                    <th scope="col" class="py-2 text-start font-medium">الوصف</th>
                    <th scope="col" class="w-28 py-2 text-start font-medium">الكمية</th>
                    <th scope="col" class="w-40 py-2 text-start font-medium">سعر الوحدة</th>
                    <th scope="col" class="w-36 py-2 text-start font-medium">الإجمالي</th>
                    <th scope="col" class="w-10"><span class="sr-only">حذف</span></th>
                </tr>
            </thead>
            <tbody>
                <template x-for="(item, index) in items" :key="index">
                    <tr class="align-top">
                        <td class="py-1 pe-2">
                            <label :for="`item-${index}-description`" class="sr-only" x-text="`وصف البند ${index + 1}`"></label>
                            <input type="text" :id="`item-${index}-description`" :name="`items[${index}][description]`" x-model="item.description" required maxlength="255"
                                   class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-600 focus:ring-brand-600"
                                   :aria-invalid="error(index, 'description') ? 'true' : null">
                            <p class="mt-1 text-xs text-red-600" x-show="error(index, 'description')" x-text="error(index, 'description')"></p>
                        </td>
                        <td class="py-1 pe-2">
                            <label :for="`item-${index}-quantity`" class="sr-only" x-text="`كمية البند ${index + 1}`"></label>
                            <input type="number" step="0.01" min="0.01" :id="`item-${index}-quantity`" :name="`items[${index}][quantity]`" x-model="item.quantity" required dir="ltr"
                                   class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-600 focus:ring-brand-600"
                                   :aria-invalid="error(index, 'quantity') ? 'true' : null">
                            <p class="mt-1 text-xs text-red-600" x-show="error(index, 'quantity')" x-text="error(index, 'quantity')"></p>
                        </td>
                        <td class="py-1 pe-2">
                            <label :for="`item-${index}-price`" class="sr-only" x-text="`سعر وحدة البند ${index + 1}`"></label>
                            <input type="number" step="0.01" min="0" :id="`item-${index}-price`" :name="`items[${index}][unit_price]`" x-model="item.unit_price" required dir="ltr"
                                   class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-600 focus:ring-brand-600"
                                   :aria-invalid="error(index, 'unit_price') ? 'true' : null">
                            <p class="mt-1 text-xs text-red-600" x-show="error(index, 'unit_price')" x-text="error(index, 'unit_price')"></p>
                        </td>
                        <td class="py-3 pe-2 tabular-nums text-gray-700" x-text="format(lineHalalas(item))"></td>
                        <td class="py-1">
                            <button type="button" @click="remove(index)" :disabled="items.length === 1"
                                    class="rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 disabled:opacity-30"
                                    :aria-label="`حذف البند ${index + 1}`">
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
                            </button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <x-button type="button" variant="ghost" size="sm" class="mt-2" x-on:click="add()">+ إضافة بند</x-button>

    <dl class="mt-4 ms-auto w-full max-w-xs space-y-1 rounded-lg bg-surface p-4 text-sm" aria-live="polite">
        <div class="flex justify-between"><dt class="text-gray-500">المجموع قبل الضريبة</dt><dd class="tabular-nums" x-text="format(subtotal)"></dd></div>
        <div class="flex justify-between"><dt class="text-gray-500">ضريبة القيمة المضافة (<span x-text="vatRate"></span>٪)</dt><dd class="tabular-nums" x-text="format(vat)"></dd></div>
        <div class="flex justify-between border-t border-gray-200 pt-1 font-semibold"><dt>الإجمالي</dt><dd class="tabular-nums" x-text="format(subtotal + vat)"></dd></div>
    </dl>
</fieldset>
