@props(['text'])

@php
/*
 * نص كتبه المحرّر كما هو: الفقرات يفصلها سطر فارغ، والأسطر التي تبدأ بشرطة
 * أو نقطة تصير قائمة حقيقية تقرؤها قارئات الشاشة قائمةً. لا HTML من المُدخل.
 */
$blocks = [];

foreach (preg_split('/\R{2,}/u', trim((string) $text)) as $paragraph) {
    $lines = [];
    $items = [];

    foreach (preg_split('/\R/u', $paragraph) as $line) {
        if (preg_match('/^\s*[-•]\s+(.+)$/u', $line, $match)) {
            if ($lines !== []) {
                $blocks[] = ['type' => 'p', 'lines' => $lines];
                $lines = [];
            }
            $items[] = $match[1];
        } else {
            if ($items !== []) {
                $blocks[] = ['type' => 'ul', 'lines' => $items];
                $items = [];
            }
            $lines[] = $line;
        }
    }

    if ($lines !== []) {
        $blocks[] = ['type' => 'p', 'lines' => $lines];
    }
    if ($items !== []) {
        $blocks[] = ['type' => 'ul', 'lines' => $items];
    }
}
@endphp

<div {{ $attributes->merge(['class' => 'space-y-4']) }}>
    @foreach ($blocks as $block)
        @if ($block['type'] === 'ul')
            <ul class="list-disc space-y-1 ps-6" role="list">
                @foreach ($block['lines'] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        @else
            <p>
                @foreach ($block['lines'] as $line)
                    {{ $line }}@unless ($loop->last)<br>@endunless
                @endforeach
            </p>
        @endif
    @endforeach
</div>
