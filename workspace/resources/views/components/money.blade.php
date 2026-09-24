@props(['amount'])

<span {{ $attributes->merge(['class' => 'whitespace-nowrap tabular-nums']) }}>{{ number_format((float) $amount, 2) }} ر.س</span>
