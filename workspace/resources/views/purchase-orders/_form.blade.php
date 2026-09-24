@props(['order' => null, 'selectedProject' => null])

<div class="grid gap-4 sm:grid-cols-2">
    <x-form.select name="project_id" label="المشروع" :options="$projects" :value="$order?->project_id ?? $selectedProject" placeholder="اختر" required
                   hint="المشاريع المعتمدة أو الجارية فقط." />
    <x-form.input name="needed_by" label="مطلوب قبل" type="date" :value="$order?->needed_by?->format('Y-m-d')" />
    <x-form.input name="vendor_name" label="المورّد" :value="$order?->vendor_name" required />
    <x-form.input name="vendor_contact" label="بيانات التواصل مع المورّد" :value="$order?->vendor_contact" />
    <x-form.textarea name="description" label="الغرض من الشراء" :value="$order?->description" rows="3" class="sm:col-span-2" />
</div>

<x-line-items-editor :items="$order?->items ?? []" :vat-rate="$order?->vat_rate ?? config('workspace.vat_rate')" />

<p class="text-xs text-gray-500">
    ما يتجاوز إجماليه <x-money :amount="config('workspace.executive_approval_threshold')" /> يحتاج اعتماد المدير التنفيذي بعد مراجعة المالية.
</p>
