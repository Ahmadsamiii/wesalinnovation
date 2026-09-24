@props(['amount'])

{{-- الملف بلا سطر جديد في آخره (انظر .editorconfig) حتى تلتصق بالمبلغ علامة الترقيم التي تليه. --}}
<span {{ $attributes->merge(['class' => 'whitespace-nowrap tabular-nums']) }}>{{ number_format((float) $amount, 2) }} ر.س</span>