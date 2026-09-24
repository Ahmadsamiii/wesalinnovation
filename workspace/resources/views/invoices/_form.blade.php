@props(['invoice' => null, 'selectedProject' => null, 'selectedContract' => null])

<div class="grid gap-4 sm:grid-cols-2">
    <x-form.select name="project_id" label="المشروع" :options="$projects" :value="$invoice?->project_id ?? $selectedProject" placeholder="اختر" required
                   hint="الفاتورة تُصدر لعميل المشروع." />
    <x-form.select name="contract_id" label="العقد" :options="$contracts" :value="$invoice?->contract_id ?? $selectedContract" placeholder="بلا عقد"
                   hint="عقد ساري أو منتهٍ من نفس المشروع." />
    <x-form.input name="due_date" label="تاريخ الاستحقاق" type="date" :value="$invoice?->due_date?->format('Y-m-d')"
                  :hint="'إن تُرك: '.config('workspace.invoice_payment_terms_days').' يوماً من الإصدار.'" />
    <x-form.textarea name="notes" label="ملاحظات تظهر في الفاتورة" :value="$invoice?->notes" rows="2" />
</div>

<x-line-items-editor :items="$invoice?->items ?? []" :vat-rate="$invoice?->vat_rate ?? config('workspace.vat_rate')" />
