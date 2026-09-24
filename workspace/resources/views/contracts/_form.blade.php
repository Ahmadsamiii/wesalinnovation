@props(['contract' => null, 'selectedProject' => null])

<div class="grid gap-4 sm:grid-cols-2">
    <x-form.select name="project_id" label="المشروع" :options="$projects" :value="$contract?->project_id ?? $selectedProject" placeholder="اختر" required
                   hint="طرف العقد هو عميل المشروع." />
    <x-form.input name="title" label="عنوان العقد" :value="$contract?->title" required />
    <x-form.input name="value" label="القيمة قبل الضريبة (ريال)" type="number" step="0.01" min="0" :value="$contract?->value" required dir="ltr" />
    <div class="hidden sm:block"></div>
    <x-form.input name="start_date" label="تاريخ السريان" type="date" :value="$contract?->start_date?->format('Y-m-d')" />
    <x-form.input name="end_date" label="تاريخ الانتهاء" type="date" :value="$contract?->end_date?->format('Y-m-d')" />
    <x-form.textarea name="notes" label="ملاحظات وبنود خاصة" :value="$contract?->notes" rows="4" class="sm:col-span-2" />
</div>
