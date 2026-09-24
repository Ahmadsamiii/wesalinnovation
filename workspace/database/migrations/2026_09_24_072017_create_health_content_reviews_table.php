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
        Schema::create('health_content_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('health_content_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->string('decision', 16);
            $table->text('note')->nullable();
            // ما عُرض على المراجِع بالضبط: القرار يبقى مفهوماً بعد أي تعديل لاحق.
            $table->unsignedInteger('version');
            $table->string('reviewed_title');
            $table->longText('reviewed_body');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['decision', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('health_content_reviews');
    }
};
