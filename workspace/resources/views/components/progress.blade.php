@props(['value', 'label' => 'نسبة الإنجاز', 'showValue' => true])

<div {{ $attributes->merge(['class' => 'flex items-center gap-3']) }}>
    <div class="h-2 flex-1 overflow-hidden rounded-full bg-brand-100" role="progressbar" aria-label="{{ $label }}"
         aria-valuenow="{{ $value }}" aria-valuemin="0" aria-valuemax="100">
        <div @class(['h-full rounded-full', 'bg-green-500' => $value >= 100, 'bg-brand-600' => $value < 100]) style="width: {{ $value }}%"></div>
    </div>
    @if ($showValue)
        <span class="w-10 text-end text-xs font-semibold text-gray-600">{{ $value }}٪</span>
    @endif
</div>
