@props(['title', 'description' => null, 'rows' => [], 'money' => false, 'empty' => 'لا بيانات.'])

@php
/*
 * أشرطة أفقية لسلسلة واحدة (لون واحد لكل الأشرطة: الفئات هنا أسماء لا رتب).
 * الشريط ينمو من خط البداية باتجاه الصفحة والقيمة عند طرفه. طول كل شريط نسبة
 * من المسار نفسه في كل الصفوف، ومساحة القيمة محجوزة في حشوة نهاية المسار كي
 * لا يقصّ أطولُ شريط فتختلّ النسب. على الهاتف يعلو الاسمُ شريطَه ليأخذ الشريط
 * العرض كله.
 */
$max = max(1, (float) collect($rows)->max('value'));
$format = fn (float $value): string => $money ? number_format($value, 2).' ر.س' : number_format($value);
@endphp

<figure {{ $attributes->merge(['class' => 'rounded-xl border border-gray-200 bg-white p-5 shadow-sm']) }}>
    <figcaption class="mb-4">
        <h3 class="text-base font-semibold text-ink">{{ $title }}</h3>
        @if ($description)
            <p class="mt-0.5 text-xs text-gray-500">{{ $description }}</p>
        @endif
    </figcaption>

    @if (collect($rows)->isEmpty())
        <p class="text-sm text-gray-500">{{ $empty }}</p>
    @else
        <dl class="space-y-3 text-sm">
            @foreach ($rows as $row)
                @php
                    $value = (float) $row['value'];
                @endphp
                <div class="grid gap-1 sm:grid-cols-[minmax(0,9rem)_1fr] sm:items-center sm:gap-3">
                    <dt class="truncate text-gray-700" title="{{ $row['label'] }}">
                        @isset($row['url'])
                            <a href="{{ $row['url'] }}" class="hover:text-brand-800 hover:underline">{{ $row['label'] }}</a>
                        @else
                            {{ $row['label'] }}
                        @endisset
                    </dt>
                    <dd @class(['min-w-0', 'pe-28' => $money, 'pe-12' => ! $money])>
                        <div class="flex items-center gap-2">
                            <span class="block h-3 shrink-0 rounded-e-[4px]" style="width: {{ $value > 0 ? max(0.75, $value / $max * 100) : 0 }}%; background: var(--series-1)" aria-hidden="true"></span>
                            <span class="whitespace-nowrap text-xs font-semibold tabular-nums text-gray-700">{{ $format($value) }}</span>
                        </div>
                    </dd>
                </div>
            @endforeach
        </dl>
    @endif
</figure>
