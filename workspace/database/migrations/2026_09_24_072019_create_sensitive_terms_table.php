<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * كلمات يُنبَّه المدير الطبي إلى كل سؤال يحويها في مساعد المنصة. قائمة
     * بداية يعدّلها المدير الطبي من صفحة التنبيهات.
     */
    public function up(): void
    {
        Schema::create('sensitive_terms', function (Blueprint $table) {
            $table->id();
            $table->string('term', 100)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $terms = [
            // إيذاء النفس
            'انتحار', 'انتحر', 'أنتحر', 'اقتل نفسي', 'أقتل نفسي', 'إيذاء النفس', 'ايذاء النفس', 'أؤذي نفسي', 'اؤذي نفسي', 'لا أريد العيش', 'ما ابي اعيش',
            // الأدوية والجرعات
            'جرعة', 'جرعه', 'جرعة زائدة', 'تسمم', 'تسمّم', 'أثناء الحمل', 'اثناء الحمل',
            // الطوارئ
            'نزيف', 'ألم في الصدر', 'الم في الصدر', 'ضيق تنفس', 'ضيق في التنفس', 'جلطة', 'سكتة', 'تشنج', 'نوبة صرع', 'فقدان الوعي', 'إغماء', 'اغماء',
            // العنف والإساءة
            'تحرش', 'اعتداء', 'يضربني', 'عنف أسري', 'عنف اسري',
        ];

        DB::table('sensitive_terms')->insert(array_map(fn (string $term): array => [
            'term' => $term,
            'created_at' => now(),
            'updated_at' => now(),
        ], $terms));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sensitive_terms');
    }
};
