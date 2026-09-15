<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            // متعدّد الأشكال لأن المرفق يلزم على المشروع وعلى المهمة معاً،
            // وجدولان متطابقان إلا في المفتاح يضاعفان كل منطق رفع وتحقّق.
            $table->morphs('attachable');

            // المسار على القرص من توليد الخادم وحده. original_name للعرض فقط
            // ولا يدخل في بناء أي مسار: اسم يرفعه المستخدم يصلح لاجتياز المسار.
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();

            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
