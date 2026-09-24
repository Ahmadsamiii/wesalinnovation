<x-app-layout>
    <x-slot:title>سجل القرارات</x-slot:title>

    <x-page-header title="سجل القرارات" description="كل قرار تنفيذي ومالي بتعليله ومتخذه وتاريخه. لا يُعدَّل ولا يُحذف." />

    <nav aria-label="نوع القرارات" class="mb-6 inline-flex rounded-lg border border-gray-200 bg-white p-1 text-sm">
        @foreach (['projects' => 'قرارات المشاريع', 'finance' => 'القرارات المالية'] as $key => $label)
            <a href="{{ route('decisions.index', ['kind' => $key]) }}" @if ($kind === $key) aria-current="page" @endif
               @class(['rounded-md px-3 py-1.5 font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-600',
                       'bg-brand-800 text-white' => $kind === $key, 'text-gray-600 hover:bg-gray-50' => $kind !== $key])>{{ $label }}</a>
        @endforeach
    </nav>

    <form method="GET" action="{{ route('decisions.index') }}" class="mb-6 grid gap-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:grid-cols-5" role="search">
        <input type="hidden" name="kind" value="{{ $kind }}">
        <x-form.input name="q" :label="$kind === 'finance' ? 'رقم الأمر أو المورّد' : 'اسم المشروع'" type="search" :value="$filters['q'] ?? null" />
        <x-form.select name="type" label="القرار" :options="$types" :value="$filters['type'] ?? null" placeholder="الكل" />
        <x-form.input name="from" label="من" type="date" :value="$filters['from'] ?? null" />
        <x-form.input name="to" label="إلى" type="date" :value="$filters['to'] ?? null" />
        <div class="flex items-end gap-2">
            <x-button type="submit">تصفية</x-button>
            @if (array_filter(\Illuminate\Support\Arr::except($filters, 'kind')))
                <x-button variant="ghost" :href="route('decisions.index', ['kind' => $kind])">مسح</x-button>
            @endif
        </div>
    </form>

    @if ($kind === 'finance')
        @include('executive._financial-decisions', ['approvals' => $records])
    @else
        @include('executive._decisions', ['decisions' => $records])
    @endif

    @if ($records->hasPages())
        <div class="mt-6">{{ $records->links() }}</div>
    @endif
</x-app-layout>
