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
        Schema::create('reference_letters', function (Blueprint $table) {
            $table->id();
            // يُسند عند الاعتماد: الطلب المرفوض لا يأخذ رقماً.
            $table->string('number', 32)->nullable()->unique();
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->string('addressee')->nullable();
            $table->string('purpose');
            $table->text('notes')->nullable();
            $table->string('status', 16)->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();

            // البيانات الوظيفية كما كانت لحظة الاعتماد: ترقية أو نقل لاحق لا
            // يغيّر نص إفادة صدرت.
            $table->string('holder_name')->nullable();
            $table->string('job_title')->nullable();
            $table->string('department')->nullable();
            $table->date('joined_at')->nullable();
            $table->string('verification_code', 16)->nullable()->unique();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reference_letters');
    }
};
