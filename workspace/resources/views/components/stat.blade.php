@props(['label', 'value', 'hint' => null, 'href' => null, 'money' => false])

@php
/*
 * رقم رئيسي واحد. المبلغ يُعرض بالريال الصحيح والعملة أصغر منه؛ القيمة
 * الدقيقة بالهللات في التلميح وفي الجداول والتصدير.
 */
$display = $money ? number_format(round((float) $value)) : $value;
@endphp

<div {{ $attributes->merge(['class' => 'rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5']) }}>
    <p class="text-sm text-gray-500">{{ $label }}</p>
    <p class="mt-2 text-2xl font-bold text-ink sm:text-3xl" @if ($money) title="{{ number_format((float) $value, 2) }} ر.س" @endif>
        @if ($href)
            <a href="{{ $href }}" class="rounded hover:text-brand-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-600">{{ $display }}@if ($money) <span class="text-base font-medium text-gray-500">ر.س</span>@endif</a>
        @else
            {{ $display }}@if ($money) <span class="text-base font-medium text-gray-500">ر.س</span>@endif
        @endif
    </p>
    @if ($hint)
        <p class="mt-1 text-xs text-gray-500">{{ $hint }}</p>
    @endif
</div>
