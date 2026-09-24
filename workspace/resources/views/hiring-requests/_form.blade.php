<div class="grid gap-4 sm:grid-cols-2">
    <x-form.input name="title" label="المسمى الوظيفي" :value="$hiringRequest?->title" required class="sm:col-span-2" />
    <x-form.input name="department" label="القسم" :value="$hiringRequest?->department ?? auth()->user()->department" />
    <x-form.select name="employment_type" label="نوع التوظيف" :value="$hiringRequest?->employment_type ?? \App\Enums\EmploymentType::FullTime" required
                   :options="collect(\App\Enums\EmploymentType::cases())->mapWithKeys(fn ($type) => [$type->value => $type->label()])->all()" />
    <x-form.input name="headcount" label="العدد" type="number" min="1" max="20" :value="$hiringRequest?->headcount ?? 1" required dir="ltr" />
    <x-form.input name="target_start_date" label="المباشرة المطلوبة" type="date" :value="$hiringRequest?->target_start_date?->format('Y-m-d')" />
    <x-form.input name="monthly_budget" label="التكلفة الشهرية التقديرية للوظيفة (ريال)" type="number" step="0.01" min="0" :value="$hiringRequest?->monthly_budget" dir="ltr"
                  hint="للمساعدة على القرار؛ لا يُعرض لغير المدير التنفيذي وصاحب الطلب." />
    @if ($projects !== [])
        <x-form.select name="project_id" label="لمشروع" :options="$projects" :value="$hiringRequest?->project_id" placeholder="فريق دائم (لا مشروع بعينه)" />
    @endif
    <x-form.textarea name="justification" label="المبررات" :value="$hiringRequest?->justification" rows="4" required class="sm:col-span-2"
                     hint="ما الحمل أو الفجوة التي تسدّها الوظيفة، وأثر عدم شغلها." />
    <x-form.textarea name="requirements" label="المهام والمتطلبات" :value="$hiringRequest?->requirements" rows="4" class="sm:col-span-2" />
</div>
