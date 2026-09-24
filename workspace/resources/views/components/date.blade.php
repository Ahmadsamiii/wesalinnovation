@props(['value', 'time' => false, 'relative' => false, 'empty' => '—'])

{{-- تواريخ بصيغة عربية مقروءة («24 سبتمبر 2026») بدل Y-m-d الذي ينقلب ترتيبه
     بصرياً داخل النص العربي. --}}
@if ($value)
    <time datetime="{{ $value->toIso8601String() }}"
          @if ($relative) title="{{ $value->translatedFormat('j F Y، H:i') }}" @endif
          {{ $attributes }}>{{ $relative
              ? $value->diffForHumans(['options' => \Carbon\CarbonInterface::JUST_NOW | \Carbon\CarbonInterface::ONE_DAY_WORDS])
              : $value->translatedFormat($time ? 'j F Y، H:i' : 'j F Y') }}</time>
@else
    {{ $empty }}
@endif
