<x-app-layout>
    <x-slot:title>إصدار شهادة</x-slot:title>

    <x-page-header title="إصدار شهادة" description="الشهادات تصدر للمشاريع المنجزة: شهادة إنجاز لعميل المشروع، وشهادات مشاركة لأعضاء فريقه.">
        <x-slot:breadcrumb><a href="{{ route('certificates.index') }}" class="hover:underline">الشهادات</a></x-slot:breadcrumb>
    </x-page-header>

    @if ($projects->isEmpty())
        <x-empty-state title="لا مشاريع منجزة تديرها بعد." />
    @else
        <x-card>
            {{-- اختيار المشروع يعيد تحميل الصفحة ليعرض عميله وفريقه. --}}
            <form method="GET" action="{{ route('certificates.create') }}" class="mb-6 flex flex-wrap items-end gap-3 border-b border-gray-100 pb-6">
                <x-form.select name="project" label="المشروع المنجز" :options="$projects->pluck('name', 'id')->all()" :value="$project->id" />
                <input type="hidden" name="type" value="{{ $type->value }}">
                <x-button type="submit" variant="secondary">عرض</x-button>
            </form>

            <form method="POST" action="{{ route('certificates.store') }}" class="space-y-6" x-data="{ type: @js($type->value) }">
                @csrf
                <input type="hidden" name="project_id" value="{{ $project->id }}">

                <fieldset>
                    <legend class="text-sm font-medium text-gray-700">نوع الشهادة</legend>
                    <div class="mt-2 flex flex-wrap gap-4 text-sm">
                        @foreach (\App\Enums\CertificateType::cases() as $option)
                            <label class="flex items-center gap-2">
                                <input type="radio" name="type" value="{{ $option->value }}" x-model="type" class="border-gray-300 text-brand-800 focus:ring-brand-600">
                                {{ $option->label() }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div x-show="type === 'completion'" class="rounded-lg bg-surface p-4 text-sm">
                    @if ($project->client)
                        تصدر لعميل المشروع: <strong>{{ $project->client->name }}</strong>
                    @else
                        <span class="text-red-700">المشروع بلا عميل؛ لا تصدر له شهادة إنجاز.</span>
                    @endif
                </div>

                <fieldset x-show="type === 'participation'" x-cloak>
                    <legend class="text-sm font-medium text-gray-700">أعضاء الفريق</legend>
                    @if ($project->members->isEmpty())
                        <p class="mt-2 text-sm text-gray-500">لا أعضاء في فريق هذا المشروع.</p>
                    @else
                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                            @foreach ($project->members as $member)
                                <label class="flex items-center gap-2 rounded-lg border border-gray-200 p-3 text-sm">
                                    <input type="checkbox" name="recipients[]" value="{{ $member->user_id }}" checked class="rounded border-gray-300 text-brand-800 focus:ring-brand-600">
                                    {{ $member->user->name }} <span class="text-xs text-gray-500">({{ $member->role->label() }})</span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                    <x-input-error :messages="$errors->get('recipients')" class="mt-1" />
                </fieldset>

                <x-form.input name="title" label="عنوان الشهادة" hint="إن تُرك: «{{ \App\Enums\CertificateType::Completion->label() }} «{{ $project->name }}»» أو ما يناسب النوع." />
                <x-form.textarea name="body" label="نص إضافي" rows="3" hint="مثل مخرجات المشروع، أو كلمة شكر لعضو الفريق." />

                <div class="flex gap-2">
                    <x-button type="submit">إصدار</x-button>
                    <x-button variant="secondary" :href="route('certificates.index')">إلغاء</x-button>
                </div>
            </form>
        </x-card>
    @endif
</x-app-layout>
