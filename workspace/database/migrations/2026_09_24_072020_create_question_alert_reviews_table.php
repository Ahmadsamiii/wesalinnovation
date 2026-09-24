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
        Schema::create('question_alert_reviews', function (Blueprint $table) {
            $table->id();
            // معرّف السؤال في chat_logs بقاعدة المنصة (قاعدة أخرى، فلا مفتاح أجنبي).
            $table->unsignedBigInteger('chat_log_id')->unique();
            $table->string('outcome', 24);
            $table->text('note')->nullable();
            $table->foreignId('reviewed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('question_alert_reviews');
    }
};
