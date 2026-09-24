<div class="space-y-4">
    <div class="grid gap-4 sm:grid-cols-3">
        <x-form.input name="title" label="العنوان" :value="$content?->title" required class="sm:col-span-2" />
        <x-form.select name="category" label="الفئة" :value="$content?->category" required placeholder="اختر"
                       :options="collect(\App\Enums\HealthContentCategory::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all()" />
    </div>
    <x-form.textarea name="summary" label="ملخص" :value="$content?->summary" rows="2" hint="سطر أو سطران يظهران أعلى الصفحة العامة." />
    <x-form.textarea name="body" label="النص" :value="$content?->body" rows="14" required hint="فقرات يفصل بينها سطر فارغ. اكتب بلغة يفهمها غير المختص." />
    <x-form.input name="source_url" label="المرجع الرسمي" type="url" :value="$content?->source_url" dir="ltr" hint="رابط الجهة الرسمية التي يستند إليها النص، إن وُجد." />
</div>
