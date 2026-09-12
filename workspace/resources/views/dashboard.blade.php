<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $role['label'] ?? 'لوحة التحكم' }}
            </h2>
            @if ($roleName)
                <span class="text-sm text-gray-500">{{ auth()->user()->name }}</span>
            @endif
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @unless ($role)
                {{-- لا دور مُسنداً — لا مسار تسجيل ذاتي هنا، فهذه حالة تحتاج
                     تدخّل مدير النظام، لا افتراض دور افتراضي غير معتمَد. --}}
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 text-center">
                    <p class="text-gray-700 font-medium">لا يوجد دور مُسنَد لحسابك بعد.</p>
                    <p class="text-gray-500 text-sm mt-2">تواصل مع مدير النظام لتفعيل صلاحياتك.</p>
                </div>
            @else
                <div x-data="{ tab: '{{ array_key_first($role['tabs']) }}' }" class="bg-white overflow-hidden shadow-sm sm:rounded-lg">

                    {{-- شريط التبويبات --}}
                    <div class="border-b border-gray-200 overflow-x-auto">
                        <nav class="flex gap-1 px-4" aria-label="تبويبات لوحة التحكم">
                            @foreach ($role['tabs'] as $key => $label)
                                <button
                                    type="button"
                                    @click="tab = '{{ $key }}'"
                                    :class="tab === '{{ $key }}' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-700'"
                                    class="whitespace-nowrap py-4 px-3 border-b-2 text-sm font-medium transition"
                                >{{ $label }}</button>
                            @endforeach
                        </nav>
                    </div>

                    {{-- محتوى كل تبويب — نظرة عامة فقط في هذه المرحلة، بلا وحدات فعلية بعد --}}
                    <div class="p-6">
                        @foreach ($role['tabs'] as $key => $label)
                            <div x-show="tab === '{{ $key }}'" x-cloak>
                                <h3 class="text-lg font-semibold text-gray-800 mb-2">{{ $label }}</h3>
                                <p class="text-gray-500 text-sm">هذا التبويب قيد البناء — الوحدة الفعلية (البيانات والإجراءات) تُضاف في مرحلة لاحقة من خطة التنفيذ.</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endunless

        </div>
    </div>
</x-app-layout>
