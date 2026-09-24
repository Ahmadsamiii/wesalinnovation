@props(['period', 'route', 'params' => []])

{{-- الفترة أول ما يُضبط وتنطبق على كل ما تحتها من أرقام ورسوم (صف واحد فوقها). --}}
<nav aria-label="فترة التقرير" {{ $attributes->merge(['class' => 'inline-flex rounded-lg border border-gray-200 bg-white p-1 text-sm']) }}>
    @foreach ([3 => 'آخر ٣ أشهر', 6 => 'آخر ٦ أشهر', 12 => 'آخر ١٢ شهراً'] as $months => $label)
        <a href="{{ route($route, [...$params, 'months' => $months]) }}" @if ($period['months'] === $months) aria-current="true" @endif
           @class(['rounded-md px-3 py-1.5 font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-600',
                   'bg-brand-800 text-white' => $period['months'] === $months, 'text-gray-600 hover:bg-gray-50' => $period['months'] !== $months])>{{ $label }}</a>
    @endforeach
</nav>
