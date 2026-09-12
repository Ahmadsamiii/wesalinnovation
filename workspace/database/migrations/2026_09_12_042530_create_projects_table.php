<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();

            // العميل اختياري: مشروع داخلي بحت لا يتبع عميلاً خارجياً.
            $table->foreignId('client_id')->nullable()->constrained('users')->nullOnDelete();
            // مدير المشروع إلزامي، ولا يُحذف حسابه وله مشاريع قائمة —
            // يجب نقل الإسناد أولاً (نفس مبدأ حماية آخر مدير نظام نشط).
            $table->foreignId('pm_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            $table->string('status')->default('draft');
            $table->string('priority')->default('normal');

            $table->decimal('budget', 12, 2)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('pm_id');
            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
