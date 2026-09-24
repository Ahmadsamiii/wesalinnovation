<x-app-layout>
    <x-slot:title>{{ $project->name }}</x-slot:title>

    <x-project-header :project="$project" active="overview">
        @can('update', $project)
            <x-button variant="secondary" size="sm" :href="route('projects.edit', $project)">تعديل</x-button>
        @endcan
        @can('delete', $project)
            <form method="POST" action="{{ route('projects.destroy', $project) }}" onsubmit="return confirm(@js('حذف مسودة «'.$project->name.'» بكل مهامها ومعالمها؟'))">
                @csrf
                @method('DELETE')
                <x-button type="submit" variant="danger" size="sm">حذف المسودة</x-button>
            </form>
        @endcan
    </x-project-header>

    <div class="grid [&>*]:min-w-0 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- ما ينتظر هذا المستخدم الآن في دورة حياة المشروع --}}
            @php($lastDecision = $project->decisions->first())
            @if (auth()->user()->can('submit', $project) || auth()->user()->can('start', $project) || auth()->user()->can('complete', $project) || auth()->user()->can('decide', $project) || $project->status === \App\Enums\ProjectStatus::PendingApproval)
                <x-card title="الخطوة التالية">
                    @if ($project->status === \App\Enums\ProjectStatus::Rejected && $lastDecision)
                        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                            <p class="font-semibold">رُفض بتاريخ <x-date :value="$lastDecision->decided_at" /> — {{ $lastDecision->decider->name }}</p>
                            <p class="mt-1">{{ $lastDecision->note }}</p>
                        </div>
                    @endif

                    @can('submit', $project)
                        <p class="mb-3 text-sm text-gray-600">
                            @if ($project->milestones->isEmpty())
                                لم تُضف معالم بعد؛ المدير التنفيذي يعتمد على المراحل والمواعيد في قراره.
                            @else
                                المشروع جاهز للتقديم بـ{{ $project->milestones->count() }} معالم.
                            @endif
                        </p>
                        <form method="POST" action="{{ route('projects.submit', $project) }}">
                            @csrf
                            <x-button type="submit">{{ $project->status === \App\Enums\ProjectStatus::Rejected ? 'إعادة التقديم للاعتماد' : 'تقديم للاعتماد' }}</x-button>
                        </form>
                    @endcan

                    @if ($project->status === \App\Enums\ProjectStatus::PendingApproval && auth()->user()->cannot('decide', $project))
                        <p class="text-sm text-gray-600">بانتظار قرار المدير التنفيذي منذ <x-date :value="$project->submitted_at" relative />.</p>
                    @endif

                    @can('start', $project)
                        <p class="mb-3 text-sm text-gray-600">اعتُمد المشروع. ابدأ التنفيذ حين يجهز الفريق؛ تُسجَّل البداية الفعلية اليوم.</p>
                        <form method="POST" action="{{ route('projects.start', $project) }}">
                            @csrf
                            <x-button type="submit">بدء التنفيذ</x-button>
                        </form>
                    @endcan

                    @can('complete', $project)
                        <form method="POST" action="{{ route('projects.complete', $project) }}" class="space-y-3">
                            @csrf
                            @if (session('confirm_complete'))
                                <label class="flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" name="confirm_open_tasks" value="1" class="rounded border-gray-300 text-brand-800 focus:ring-brand-600" required>
                                    أؤكد إنجاز المشروع رغم وجود مهام مفتوحة
                                </label>
                            @endif
                            <x-button type="submit" variant="success">تسجيل إنجاز المشروع</x-button>
                        </form>
                    @endcan

                    @can('decide', $project)
                        <form method="POST" action="{{ route('projects.decide', $project) }}" class="space-y-4 @if (auth()->user()->can('submit', $project) || auth()->user()->can('start', $project) || auth()->user()->can('complete', $project)) mt-6 border-t border-gray-100 pt-6 @endif">
                            @csrf
                            <fieldset>
                                <legend class="text-sm font-medium text-gray-700">القرار التنفيذي</legend>
                                <div class="mt-2 flex flex-wrap gap-4">
                                    @foreach ($decisionTypes as $type)
                                        <label class="flex items-center gap-2 text-sm">
                                            <input type="radio" name="type" value="{{ $type->value }}" required @checked(old('type') === $type->value)
                                                   class="border-gray-300 text-brand-800 focus:ring-brand-600">
                                            {{ $type->label() }}
                                        </label>
                                    @endforeach
                                </div>
                                <x-input-error :messages="$errors->get('type')" class="mt-1" />
                            </fieldset>
                            <x-form.textarea name="note" label="التعليل" rows="3" hint="مطلوب للرفض والإيقاف والإلغاء، ويراه مدير المشروع." />
                            <x-button type="submit">تسجيل القرار</x-button>
                        </form>
                    @endcan
                </x-card>
            @endif

            <x-card title="التقدّم">
                <x-progress :value="$project->progress()" />
                <dl class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-5">
                    @foreach (\App\Enums\TaskStatus::cases() as $status)
                        <div class="rounded-lg bg-surface p-3 text-center">
                            <dt class="text-xs text-gray-500">{{ $status->label() }}</dt>
                            <dd class="mt-1 text-xl font-bold text-ink">{{ $taskCounts[$status->value] ?? 0 }}</dd>
                        </div>
                    @endforeach
                </dl>

                @if ($overdueTasks->isNotEmpty())
                    <h3 class="mt-6 text-sm font-semibold text-red-700">مهام متأخرة</h3>
                    <ul class="mt-2 divide-y divide-gray-100 text-sm" role="list">
                        @foreach ($overdueTasks as $task)
                            <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                                <a href="{{ route('tasks.show', $task) }}" class="font-medium text-brand-800 hover:underline">{{ $task->title }}</a>
                                <span class="text-gray-500">{{ $task->assignee?->name ?? 'بلا إسناد' }} — كان مستحقاً <x-date :value="$task->due_date" /></span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            <x-card title="المراحل والمعالم" :padding="false">
                <x-slot:actions>
                    <x-button variant="ghost" size="sm" :href="route('projects.milestones.index', $project)">إدارة المعالم</x-button>
                </x-slot:actions>
                @if ($project->milestones->isEmpty())
                    <p class="p-5 text-sm text-gray-500">لا معالم بعد.</p>
                @else
                    <ol class="divide-y divide-gray-100" role="list">
                        @foreach ($project->milestones as $milestone)
                            <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3 text-sm">
                                <div class="flex items-center gap-2">
                                    @if ($milestone->isReached())
                                        <x-badge color="green">بُلغ</x-badge>
                                    @elseif ($milestone->isOverdue())
                                        <x-badge color="red">متأخر</x-badge>
                                    @endif
                                    <span class="font-medium">{{ $milestone->title }}</span>
                                </div>
                                <span class="text-gray-500">
                                    {{ $milestone->done_tasks_count }}/{{ $milestone->tasks_count }} مهام —
                                    <x-date :value="$milestone->due_date" empty="بلا موعد" />
                                </span>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-card>

            <x-card title="سجل القرارات" :padding="false">
                @if ($project->decisions->isEmpty())
                    <p class="p-5 text-sm text-gray-500">لا قرارات بعد.</p>
                @else
                    <ul class="divide-y divide-gray-100" role="list">
                        @foreach ($project->decisions as $decision)
                            <li class="px-5 py-3 text-sm">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <span class="flex items-center gap-2">
                                        <x-badge :color="$decision->type->color()">{{ $decision->type->label() }}</x-badge>
                                        {{ $decision->decider->name }}
                                    </span>
                                    <x-date :value="$decision->decided_at" time class="text-xs text-gray-500" />
                                </div>
                                @if ($decision->note)
                                    <p class="mt-2 text-gray-600">{{ $decision->note }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="التفاصيل">
                @if ($project->description)
                    <p class="mb-4 whitespace-pre-line text-sm leading-7 text-gray-700">{{ $project->description }}</p>
                @endif
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">مدير المشروع</dt><dd>{{ $project->pm->name }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">العميل</dt><dd>{{ $project->client?->name ?? 'داخلي' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">الأولوية</dt><dd><x-badge :color="$project->priority->color()">{{ $project->priority->label() }}</x-badge></dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">الميزانية</dt><dd>{{ $project->budget !== null ? number_format((float) $project->budget, 2).' ر.س' : '—' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">المخطط</dt><dd><x-date :value="$project->start_date" /> ← <x-date :value="$project->end_date" /></dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">الفعلي</dt><dd><x-date :value="$project->actual_start_date" /> ← <x-date :value="$project->actual_end_date" /></dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">أنشأه</dt><dd>{{ $project->creator->name }}</dd></div>
                </dl>
            </x-card>

            @if ($finance)
                <x-card title="المالية">
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-2"><dt class="text-gray-500">الميزانية</dt><dd>@if ($project->budget !== null)<x-money :amount="$project->budget" />@else — @endif</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-gray-500">أوامر شراء ملتزَم بها</dt><dd><x-money :amount="$finance['committed']" /></dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-gray-500">قيمة العقود</dt><dd><x-money :amount="$finance['contracted']" /></dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-gray-500">المفوتر</dt><dd><x-money :amount="$finance['invoiced']" /></dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-gray-500">المحصّل</dt><dd><x-money :amount="$finance['collected']" /></dd></div>
                    </dl>
                    @if ($project->budget !== null && (float) $finance['committed'] > (float) $project->budget)
                        <p class="mt-3 rounded-lg bg-red-50 p-3 text-xs text-red-800" role="alert">الالتزامات تتجاوز الميزانية.</p>
                    @endif
                    <div class="mt-4 flex flex-wrap gap-2">
                        <x-button variant="ghost" size="sm" :href="route('contracts.index', ['project' => $project->id])">العقود</x-button>
                        <x-button variant="ghost" size="sm" :href="route('purchase-orders.index', ['project' => $project->id])">أوامر الشراء</x-button>
                        <x-button variant="ghost" size="sm" :href="route('invoices.index', ['project' => $project->id])">الفواتير</x-button>
                    </div>
                </x-card>
            @endif

            <x-card title="الفريق" :padding="false">
                <x-slot:actions>
                    <x-button variant="ghost" size="sm" :href="route('projects.members.index', $project)">إدارة الفريق</x-button>
                </x-slot:actions>
                <ul class="divide-y divide-gray-100 text-sm" role="list">
                    <li class="flex items-center justify-between px-5 py-3">
                        <span class="font-medium">{{ $project->pm->name }}</span>
                        <x-badge color="brand">مدير المشروع</x-badge>
                    </li>
                    @foreach ($project->members as $member)
                        <li class="flex items-center justify-between px-5 py-3">
                            <span>{{ $member->user->name }}</span>
                            <span class="text-xs text-gray-500">{{ $member->role->label() }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        </div>
    </div>
</x-app-layout>
