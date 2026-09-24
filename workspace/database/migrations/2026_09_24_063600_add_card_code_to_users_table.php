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
        Schema::table('users', function (Blueprint $table) {
            // رمز التحقق من البطاقة الرقمية، يُولَّد عند أول عرض لها.
            $table->string('card_code', 16)->nullable()->unique()->after('last_login_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['card_code']);
            $table->dropColumn('card_code');
        });
    }
};
