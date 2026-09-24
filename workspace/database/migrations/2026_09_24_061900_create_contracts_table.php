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
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->unique();
            // عقد قائم يمنع حذف مشروعه: المستند القانوني لا يسقط بحذف ما يتبعه.
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            // العميل لحظة إنشاء العقد، لا عميل المشروع الحالي إن تغيّر لاحقاً.
            $table->foreignId('client_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('title');
            // القيمة قبل ضريبة القيمة المضافة.
            $table->decimal('value', 14, 2);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status')->default('draft');
            $table->date('signed_on')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('client_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
