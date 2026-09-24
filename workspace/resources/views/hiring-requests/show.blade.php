<x-app-layout>
    <x-slot:title>{{ $hiringRequest->number }}: {{ $hiringRequest->title }}</x-slot:title>

    <x-page-header :title="$hiringRequest->title">
        <x-slot:breadcrumb><a href="{{ route('hiring-requests.index') }}" class="hover:underline">طلبات التوظيف</a> / <span dir="ltr">{{ $hiringRequest->number }}</span></x-slot:breadcrumb>
        <x-slot:actions>
            <x-badge :color="$hiringRequest->status->color()">{{ $hiringRequest->status->label() }}</x-badge>
            @can('update', $hiringRequest)
                <x-button variant="secondary" :href="route('hiring-requests.edit', $hiringRequest)">تعديل</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid [&>*]:min-w-0 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="الوظيفة">
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-gray-500">العدد ونوع التوظيف</dt><dd class="mt-0.5 font-medium">{{ $hiringRequest->headcount }} × {{ $hiringRequest->employment_type->label() }}</dd></div>
                    <div><dt class="text-gray-500">القسم</dt><dd class="mt-0.5 font-medium">{{ $hiringRequest->department ?? '-' }}</dd></div>
                    <div><dt class="text-gray-500">المباشرة المطلوبة</dt><dd class="mt-0.5 font-medium"><x-date :value="$hiringRequest->target_start_date" empty="حين تتوفر" /></dd></div>
                    <div><dt class="text-gray-500">التكلفة الشهرية التقديرية للوظيفة</dt><dd class="mt-0.5 font-medium">@if ($hiringRequest->monthly_budget !== null)<x-money :amount="$hiringRequest->monthly_budget" />@else - @endif</dd></div>
                    @if ($hiringRequest->project)
                        <div class="sm:col-span-2"><dt class="text-gray-500">لمشروع</dt><dd class="mt-0.5 font-medium"><a href="{{ route('projects.show', $hiringRequest->project) }}" class="text-brand-800 hover:underline">{{ $hiringRequest->project->name }}</a></dd></div>
                    @endif
                </dl>

                <h3 class="mt-6 text-sm font-semibold text-gray-700">المبررات</h3>
                <p class="mt-1 whitespace-pre-line text-sm leading-7 text-gray-800">{{ $hiringRequest->justification }}</p>

                @if ($hiringRequest->requirements)
                    <h3 class="mt-6 text-sm font-semibold text-gray-700">المهام والمتطلبات</h3>
                    <p class="mt-1 whitespace-pre-line text-sm leading-7 text-gray-800">{{ $hiringRequest->requirements }}</p>
                @endif
            </x-card>

            <x-card title="المسار">
                <ol class="space-y-4 text-sm" role="list">
                    <li>
                        <p class="font-medium">طلبه {{ $hiringRequest->requester->name }}</p>
                        <p class="text-xs text-gray-500"><x-date :value="$hiringRequest->created_at" time /></p>
                    </li>
                    @if ($hiringRequest->decider)
                        <li>
                            <p class="font-medium">{{ $hiringRequest->status === \App\Enums\HiringRequestStatus::Rejected ? 'رفضه' : 'اعتمده' }} {{ $hiringRequest->decider->name }}</p>
                            <p class="text-xs text-gray-500"><x-date :value="$hiringRequest->decided_at" time /></p>
                            @if ($hiringRequest->decision_note)
                                <p @class(['mt-1 rounded-lg p-3 text-xs', 'bg-red-50 text-red-800' => $hiringRequest->status === \App\Enums\HiringRequestStatus::Rejected, 'bg-surface text-gray-700' => $hiringRequest->status !== \App\Enums\HiringRequestStatus::Rejected])>{{ $hiringRequest->decision_note }}</p>
                            @endif
                        </li>
                    @endif
                    @if ($hiringRequest->closer)
                        <li>
                            <p class="font-medium">{{ $hiringRequest->status === \App\Enums\HiringRequestStatus::Filled ? 'أغلقه بشغل الوظيفة' : 'ألغاه' }} {{ $hiringRequest->closer->name }}</p>
                            <p class="text-xs text-gray-500"><x-date :value="$hiringRequest->closed_at" time /></p>
                            <p class="mt-1 whitespace-pre-line rounded-lg bg-surface p-3 text-xs text-gray-700">{{ $hiringRequest->closing_note }}</p>
                        </li>
                    @endif
                </ol>
            </x-card>
        </div>

        <div class="space-y-6">
            @can('decide', $hiringRequest)
                <x-card title="قرارك">
                    <form method="POST" action="{{ route('hiring-requests.approve', $hiringRequest) }}" class="space-y-3">
                        @csrf
                        <x-form.textarea name="note" label="ملاحظة مع الاعتماد" rows="2" id="approve-note" />
                        <x-button type="submit" variant="success" class="w-full">اعتماد الطلب</x-button>
                    </form>
                    <details class="mt-4" @if ($errors->has('reason')) open @endif>
                        <summary class="cursor-pointer text-sm text-red-700">رفض بتعليل</summary>
                        <form method="POST" action="{{ route('hiring-requests.reject', $hiringRequest) }}" class="mt-2 space-y-2">
                            @csrf
                            <x-form.textarea name="reason" label="التعليل" rows="2" id="reject-reason" required />
                            <x-button type="submit" variant="danger" size="sm">رفض</x-button>
                        </form>
                    </details>
                </x-card>
            @endcan

            @can('fill', $hiringRequest)
                <x-card title="شُغلت الوظيفة؟">
                    <form method="POST" action="{{ route('hiring-requests.fill', $hiringRequest) }}" class="space-y-3">
                        @csrf
                        <x-form.textarea name="note" label="من شُغلت به ومتى يباشر" rows="2" id="fill-note" required
                                         hint="حساب الموظف الجديد ينشئه مدير النظام بدعوة." />
                        <x-button type="submit" variant="success" class="w-full">إغلاق الطلب بشغل الوظيفة</x-button>
                    </form>
                </x-card>
            @endcan

            @can('cancel', $hiringRequest)
                <x-card>
                    <details @if ($errors->has('reason')) open @endif>
                        <summary class="cursor-pointer text-sm text-red-700">{{ $hiringRequest->status === \App\Enums\HiringRequestStatus::Pending ? 'سحب الطلب' : 'إلغاء الطلب' }}</summary>
                        <form method="POST" action="{{ route('hiring-requests.cancel', $hiringRequest) }}" class="mt-2 space-y-2">
                            @csrf
                            <x-form.textarea name="reason" label="السبب" rows="2" required />
                            <x-button type="submit" variant="danger" size="sm">تأكيد</x-button>
                        </form>
                    </details>
                </x-card>
            @endcan
        </div>
    </div>
</x-app-layout>
