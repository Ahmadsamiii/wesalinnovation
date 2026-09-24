<x-app-layout>
    <x-slot:title>سجل القرارات</x-slot:title>

    <x-page-header title="سجل القرارات" description="كل قرار اعتماد أو رفض أو إيقاف أو استئناف أو إلغاء، بتعليله ومتخذه وتاريخه. لا يُعدَّل ولا يُحذف." />

    <form method="GET" action="{{ route('decisions.index') }}" class="mb-6 grid gap-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:grid-cols-5" role="search">
        <x-form.input name="q" label="اسم المشروع" type="search" :value="$filters['q'] ?? null" />
        <x-form.select name="type" label="نوع القرار" :options="$types" :value="$filters['type'] ?? null" placeholder="الكل" />
        <x-form.input name="from" label="من" type="date" :value="$filters['from'] ?? null" />
        <x-form.input name="to" label="إلى" type="date" :value="$filters['to'] ?? null" />
        <div class="flex items-end gap-2">
            <x-button type="submit">تصفية</x-button>
            @if (array_filter($filters))
                <x-button variant="ghost" :href="route('decisions.index')">مسح</x-button>
            @endif
        </div>
    </form>

    @include('executive._decisions', ['decisions' => $decisions])

    @if ($decisions->hasPages())
        <div class="mt-6">{{ $decisions->links() }}</div>
    @endif
</x-app-layout>
