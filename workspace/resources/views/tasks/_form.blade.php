@props(['task' => null, 'defaultMilestone' => null])

<div class="grid gap-4 sm:grid-cols-2">
    <x-form.input name="title" label="عنوان المهمة" :value="$task?->title" required class="sm:col-span-2" />
    <x-form.textarea name="description" label="الوصف" :value="$task?->description" rows="5" class="sm:col-span-2" />
    <x-form.select name="assignee_id" label="المسند إليه" :options="$assignees" :value="$task?->assignee_id" placeholder="بلا إسناد"
                   hint="مدير المشروع أو أحد أعضاء فريقه." />
    <x-form.select name="milestone_id" label="المعلم" :options="$milestones" :value="$task?->milestone_id ?? $defaultMilestone" placeholder="بلا معلم" />
    <x-form.select name="priority" label="الأولوية" :options="$priorities" :value="$task?->priority ?? \App\Enums\Priority::Normal" required />
    <x-form.input name="due_date" label="الموعد النهائي" type="date" :value="$task?->due_date?->format('Y-m-d')" />
</div>
