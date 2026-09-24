<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // شهادة إنجاز المشروع للعميل، وشهادة مشاركة لعضو الفريق. كلتاهما تصدر
        // عن مشروع منجز، وتُلغى بسبب مكتوب ولا تُحذف: رمز التحقق يبقى يجيب
        // «ملغاة» لمن يحمل نسخة قديمة منها.
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->unique();
            $table->string('type', 32);
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->text('body')->nullable();
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('issued_at');
            $table->string('verification_code', 16)->unique();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('revocation_reason')->nullable();
            $table->timestamps();

            $table->index(['type', 'project_id', 'recipient_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
