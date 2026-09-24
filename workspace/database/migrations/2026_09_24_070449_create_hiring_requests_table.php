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
        Schema::create('hiring_requests', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->unique();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            // وظيفة لمشروع بعينه؛ فارغ للفرق الدائمة.
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title');
            $table->string('department')->nullable();
            $table->unsignedTinyInteger('headcount')->default(1);
            $table->string('employment_type', 16)->default('full_time');
            $table->text('justification');
            $table->text('requirements')->nullable();
            // تقدير شهري للوظيفة الواحدة بالريال للقرار، لا راتب فعلي.
            $table->decimal('monthly_budget', 10, 2)->nullable();
            $table->date('target_start_date')->nullable();

            $table->string('status', 16)->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();

            // الإغلاق: شُغلت الوظيفة أو أُلغي الطلب، ومعه من شُغلت به أو سبب الإلغاء.
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->text('closing_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('requested_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hiring_requests');
    }
};
