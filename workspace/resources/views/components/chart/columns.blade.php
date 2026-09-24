@props([
    'title',
    'description' => null,
    'labels' => [],
    'titles' => null,
    'series' => [],
    'money' => false,
    'plotHeight' => 180,
    'empty' => 'لا شيء في هذه الفترة.',
])

@php
/*
 * أعمدة زمنية بسلسلة أو سلسلتين (الرسم اتجاهه اتجاه الصفحة: الأقدم يميناً).
 * مواصفات الرسم: عمود لا يتجاوز ٢٤px، طرفه العلوي مدوّر ٤px وقاعدته مستقيمة،
 * فجوة ٢px بين أعمدة المجموعة، خطوط شبكة شعرية صلبة، وتلميح لكل فترة يعرض كل
 * السلاسل (القيمة أولاً). القيم كلها في «عرض كجدول» فلا يحجبها التلميح.
 *
 * labels أسماء قصيرة للمحور، وtitles الاسم الكامل (بالسنة) للتلميح والجدول.
 */
$titles ??= $labels;
$all = collect($series)->flatMap(fn (array $item) => $item['values'])->map(fn ($value): float => (float) $value);
$max = (float) $all->max();

if (! $money && $max <= 4) {
    // أعداد صغيرة: درجة لكل واحد، بلا كسور.
    $step = 1;
    $intervals = max(2, (int) ceil($max));
} else {
    $intervals = 4;
    $rawStep = max($max, 1) / $intervals;
    $magnitude = 10 ** floor(log10($rawStep));
    $step = collect($money ? [1, 2, 2.5, 5, 10] : [1, 2, 5, 10])
        ->map(fn ($multiplier) => $multiplier * $magnitude)
        ->first(fn ($candidate) => $candidate >= $rawStep);
}
$top = $step * $intervals;
$ticks = array_map(fn (int $i) => $i * $step, range(0, $intervals));

// وحدة واحدة لكل أرقام المحور (لا خلط بين «٧٬٥٠٠» و«١٠ ألف»).
[$divisor, $unit] = match (true) {
    $top >= 1_000_000 => [1_000_000, ' مليون'],
    $top >= 10_000 => [1_000, ' ألف'],
    default => [1, ''],
};
$compact = fn (float $value): string => rtrim(rtrim(number_format($value / $divisor, 1), '0'), '.').$unit;
$full = fn (float $value): string => $money ? number_format($value, 2).' ر.س' : number_format($value);

$count = count($labels);
$lastIndex = $count - 1;
@endphp

<figure {{ $attributes->merge(['class' => 'rounded-xl border border-gray-200 bg-white p-5 shadow-sm']) }}>
    <figcaption class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h3 class="text-base font-semibold text-ink">{{ $title }}</h3>
            @if ($description)
                <p class="mt-0.5 text-xs text-gray-500">{{ $description }}</p>
            @endif
        </div>
        @if (count($series) > 1)
            <ul class="flex flex-wrap gap-4 text-xs text-gray-600" role="list" aria-label="مفتاح الرسم">
                @foreach ($series as $index => $item)
                    <li class="flex items-center gap-1.5">
                        <span class="inline-block h-2.5 w-2.5 rounded-sm" style="background: var(--series-{{ $index + 1 }})" aria-hidden="true"></span>
                        {{ $item['name'] }}
                    </li>
                @endforeach
            </ul>
        @endif
    </figcaption>

    @if ($max <= 0)
        <p class="text-sm text-gray-500">{{ $empty }}</p>
    @else
        <div class="flex gap-2">
            {{-- محور القيم على جانب البداية (اليمين في العربية) --}}
            <div class="relative w-14 shrink-0 text-[11px] tabular-nums text-gray-500" style="height: {{ $plotHeight }}px" aria-hidden="true">
                @foreach ($ticks as $tick)
                    <span class="absolute start-0 whitespace-nowrap leading-none" style="bottom: calc({{ $tick / $top * 100 }}% - 0.35em)">{{ $compact($tick) }}</span>
                @endforeach
            </div>

            <div class="min-w-0 flex-1">
                <div class="relative" style="height: {{ $plotHeight }}px">
                    @foreach ($ticks as $tick)
                        <div class="pointer-events-none absolute inset-x-0 h-px" style="bottom: {{ $tick / $top * 100 }}%; background: {{ $loop->first ? 'var(--chart-axis)' : 'var(--chart-grid)' }}" aria-hidden="true"></div>
                    @endforeach

                    <ol class="relative flex h-full items-end" role="list" aria-label="{{ $title }}">
                        @foreach ($labels as $i => $label)
                            @php
                                $readout = collect($series)->map(fn (array $item): string => $item['name'].': '.$full((float) $item['values'][$i]))->implode('، ');
                                // التلميح يمتد نحو داخل الرسم عند الطرفين كي لا يخرج من الصفحة.
                                $tipPosition = match (true) {
                                    $i < $count / 3 => 'start-0',
                                    $i >= $count * 2 / 3 => 'end-0',
                                    default => 'left-1/2 -translate-x-1/2',
                                };
                            @endphp
                            <li class="group relative flex h-full flex-1 items-end justify-center gap-[2px] rounded-sm outline-none hover:bg-gray-50/80 focus-visible:bg-brand-50 focus-visible:ring-2 focus-visible:ring-brand-600"
                                tabindex="0" aria-label="{{ $titles[$i] }} — {{ $readout }}">
                                @foreach ($series as $index => $item)
                                    @php
                                        $value = (float) $item['values'][$i];
                                    @endphp
                                    <span class="relative block w-full max-w-[24px] rounded-t-[4px] transition-opacity group-hover:opacity-85"
                                          style="height: {{ $value > 0 ? max(1, $value / $top * 100) : 0 }}%; background: var(--series-{{ $index + 1 }})" aria-hidden="true">
                                        {{-- قيمة واحدة ظاهرة فقط: آخر فترة، فوق عمودها --}}
                                        @if ($i === $lastIndex && count($series) === 1 && $value > 0)
                                            <span class="absolute bottom-full left-1/2 mb-1 -translate-x-1/2 whitespace-nowrap text-[11px] font-semibold text-gray-700">{{ $compact($value) }}</span>
                                        @endif
                                    </span>
                                @endforeach

                                {{-- التلميح: كل السلاسل لهذه الفترة، القيمة أولاً ثم الاسم --}}
                                <span class="pointer-events-none absolute bottom-full z-10 mb-2 hidden min-w-max rounded-lg bg-ink px-3 py-2 text-xs text-white shadow-lg group-hover:block group-focus-visible:block {{ $tipPosition }}" aria-hidden="true">
                                    <span class="block text-[11px] text-white/70">{{ $titles[$i] }}</span>
                                    @foreach ($series as $index => $item)
                                        <span class="mt-1 flex items-center gap-2">
                                            <span class="inline-block h-0.5 w-3" style="background: var(--series-{{ $index + 1 }})"></span>
                                            <strong class="tabular-nums">{{ $full((float) $item['values'][$i]) }}</strong>
                                            <span class="text-white/70">{{ $item['name'] }}</span>
                                        </span>
                                    @endforeach
                                </span>
                            </li>
                        @endforeach
                    </ol>
                </div>

                {{-- أسماء الفترات: كلها حتى ستة، وإلا كل ثالث بدءاً من الأحدث، ويمتد
                     الاسم فوق جيرانه الفارغين بدل أن يُقصّ --}}
                <ol class="mt-2 flex text-center text-[11px] text-gray-500" role="list" aria-hidden="true">
                    @foreach ($labels as $i => $label)
                        <li class="flex min-w-0 flex-1 justify-center whitespace-nowrap">{{ $count <= 6 || ($lastIndex - $i) % 3 === 0 ? $label : '' }}</li>
                    @endforeach
                </ol>
            </div>
        </div>

        <details class="mt-4 text-sm">
            <summary class="cursor-pointer text-xs font-medium text-brand-800">عرض كجدول</summary>
            <div class="mt-2 overflow-x-auto">
                <table class="min-w-full text-xs">
                    <caption class="sr-only">{{ $title }}</caption>
                    <thead class="text-gray-500">
                        <tr>
                            <th scope="col" class="py-1 text-start font-medium">الفترة</th>
                            @foreach ($series as $item)
                                <th scope="col" class="py-1 text-start font-medium">{{ $item['name'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 tabular-nums">
                        @foreach ($labels as $i => $label)
                            <tr>
                                <th scope="row" class="py-1 text-start font-normal">{{ $titles[$i] }}</th>
                                @foreach ($series as $item)
                                    <td class="py-1">{{ $full((float) $item['values'][$i]) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endif
</figure>
