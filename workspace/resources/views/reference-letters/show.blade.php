<x-print-layout :title="$letter->number" :back="route('reference-letters.index')" back-label="طلبات الإفادة">
    <main class="mx-auto my-6 max-w-3xl rounded-xl bg-white p-12 shadow-sm print:m-0 print:max-w-none print:rounded-none print:p-0 print:shadow-none">
        <header class="flex items-start justify-between gap-6 border-b border-gray-200 pb-6">
            <div class="text-sm">
                <p>الرقم: <span dir="ltr" class="font-semibold">{{ $letter->number }}</span></p>
                <p>التاريخ: <x-date :value="$letter->decided_at" /></p>
            </div>
            <div class="text-end">
                <img src="{{ asset('images/logo-color.png') }}" alt="" class="ms-auto h-14 w-auto">
                <p class="mt-2 text-sm font-semibold">{{ config('workspace.company.name') }}</p>
            </div>
        </header>

        <h1 class="mt-10 text-center text-2xl font-bold">إفادة</h1>

        <p class="mt-8 leading-9">إلى: {{ $letter->addressee ?? 'من يهمه الأمر' }}</p>
        <p class="mt-2 leading-9">السلام عليكم ورحمة الله وبركاته،</p>

        <p class="mt-4 leading-9">تشهد {{ config('workspace.company.name') }} بصحة البيانات الوظيفية الآتية:</p>

        <table class="mt-4 w-full border border-gray-200 text-sm">
            <tbody class="divide-y divide-gray-200">
                <tr><th scope="row" class="w-1/3 bg-gray-50 px-4 py-3 text-start font-medium">الاسم</th><td class="px-4 py-3">{{ $letter->holder_name }}</td></tr>
                <tr><th scope="row" class="bg-gray-50 px-4 py-3 text-start font-medium">المسمى الوظيفي</th><td class="px-4 py-3">{{ $letter->job_title ?? '-' }}</td></tr>
                <tr><th scope="row" class="bg-gray-50 px-4 py-3 text-start font-medium">القسم</th><td class="px-4 py-3">{{ $letter->department ?? '-' }}</td></tr>
                <tr><th scope="row" class="bg-gray-50 px-4 py-3 text-start font-medium">تاريخ الالتحاق</th><td class="px-4 py-3"><x-date :value="$letter->joined_at" /></td></tr>
                <tr><th scope="row" class="bg-gray-50 px-4 py-3 text-start font-medium">الحالة الوظيفية</th><td class="px-4 py-3">على رأس العمل حتى تاريخ هذه الإفادة</td></tr>
            </tbody>
        </table>

        <p class="mt-6 leading-9">
            وقد مُنحت هذه الإفادة بناءً على طلب صاحب الشأن لتقديمها إلى {{ $letter->purpose }}، دون أدنى مسؤولية على المنشأة تجاه الغير.
        </p>

        <div class="mt-16 w-64 text-center text-sm">
            <p class="border-t border-gray-300 pt-2 font-semibold">{{ $letter->decider->name }}</p>
            <p class="text-gray-500">المدير التنفيذي</p>
        </div>

        <footer class="mt-16 border-t border-gray-100 pt-4 text-xs text-gray-500">
            يمكن التحقق من صحة هذه الإفادة بإدخال الرمز
            <span dir="ltr" class="font-mono text-sm font-semibold text-gray-700">{{ \App\Models\ReferenceLetter::formatVerificationCode($letter->verification_code) }}</span>
            في <span dir="ltr">{{ route('verify.show') }}</span>
        </footer>
    </main>
</x-print-layout>
