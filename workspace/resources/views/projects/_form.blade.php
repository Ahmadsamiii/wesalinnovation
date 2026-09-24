@props(['project' => null])

<div class="grid gap-4 sm:grid-cols-2">
    <x-form.input name="name" label="اسم المشروع" :value="$project?->name" required class="sm:col-span-2" />
    <x-form.textarea name="description" label="الوصف والنطاق" :value="$project?->description" rows="5" class="sm:col-span-2"
                     hint="ما يراه المدير التنفيذي عند الاعتماد: الهدف والنطاق والمخرجات." />

    @if ($canChoosePm)
        <x-form.select name="pm_id" label="مدير المشروع" :options="$pms" :value="$project?->pm_id" placeholder="اختر" required />
    @endif
    <x-form.select name="client_id" label="العميل" :options="$clients" :value="$project?->client_id" placeholder="مشروع داخلي بلا عميل"
                   hint="حساب العميل يرى حالة المشروع وتقدّمه من بوابته." />
    <x-form.select name="priority" label="الأولوية" :options="$priorities" :value="$project?->priority ?? \App\Enums\Priority::Normal" required />
    <x-form.input name="budget" label="الميزانية (ريال)" type="number" step="0.01" min="0" :value="$project?->budget" dir="ltr" />
    <x-form.input name="start_date" label="تاريخ البداية المخطط" type="date" :value="$project?->start_date?->format('Y-m-d')" />
    <x-form.input name="end_date" label="تاريخ النهاية المخطط" type="date" :value="$project?->end_date?->format('Y-m-d')" />
</div>
