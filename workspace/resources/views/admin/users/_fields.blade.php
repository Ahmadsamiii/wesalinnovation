@props(['user' => null, 'roleOptions'])

<div class="grid gap-4 sm:grid-cols-2">
    <x-form.input name="name" label="الاسم الكامل" :value="$user?->name" required autocomplete="off" />
    <x-form.input name="email" label="البريد الإلكتروني" type="email" :value="$user?->email" required dir="ltr" autocomplete="off"
                  hint="بريد الدخول، وإليه تُرسل الدعوة." />
    <x-form.select name="role" label="الدور" :options="$roleOptions" :value="$user?->roleName()" placeholder="اختر الدور" required
                   hint="يحدد التبويبات والصلاحيات. دور واحد لكل حساب." />
    <x-form.input name="department" label="القسم" :value="$user?->department" />
    <x-form.input name="job_title" label="المسمى الوظيفي" :value="$user?->job_title" hint="يظهر في البطاقة الرقمية والإفادات." />
    <x-form.input name="phone" label="رقم الجوال" type="tel" :value="$user?->phone" dir="ltr" />
    <x-form.input name="joined_at" label="تاريخ الانضمام" type="date" :value="$user?->joined_at?->format('Y-m-d')" />
</div>
