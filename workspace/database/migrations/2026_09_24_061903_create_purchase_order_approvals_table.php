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
        // تاريخ كامل لقرارات أمر الشراء في مرحلتيه (المالية ثم التنفيذي)، كما
        // project_decisions للمشاريع: الرفض ثم الاعتماد يتركان أثرين.
        Schema::create('purchase_order_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 16);
            $table->string('decision', 16);
            $table->text('note')->nullable();
            $table->foreignId('decided_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at');
            $table->timestamps();

            $table->index(['purchase_order_id', 'decided_at']);
            $table->index('decided_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_approvals');
    }
};
