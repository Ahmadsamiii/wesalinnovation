<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            // حذف المعلم لا يُسقط مهامه: تبقى في المشروع بلا معلم بدل أن
            // تختفي مع إعادة تنظيم الخطة.
            $table->foreignId('milestone_id')->nullable()
                ->constrained('project_milestones')->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('todo');
            $table->string('priority')->default('normal');

            // مهمة غير مسندة حالة مشروعة (في قائمة الانتظار بانتظار موارد).
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            $table->date('due_date')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            // ترتيب البطاقة داخل عمودها في عرض الكانبان.
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['project_id', 'status', 'position']);
            $table->index('assignee_id');
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
