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
        // ترقيم تسلسلي لكل نوع مستند في كل سنة (INV-2026-0001). صف واحد لكل
        // نوع وسنة يُقفل أثناء الزيادة، فلا يتكرر رقم ولا تُترك فجوة بسبب
        // مسودة محذوفة (الفاتورة تأخذ رقمها عند الإصدار لا عند الإنشاء).
        Schema::create('sequences', function (Blueprint $table) {
            $table->id();
            $table->string('name', 32);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['name', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sequences');
    }
};
