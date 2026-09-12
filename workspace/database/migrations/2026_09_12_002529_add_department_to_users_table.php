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
            // القسم الوظيفي (فريق الهندسة، فريق التصميم...) — وصفي فقط، لا يتحكم
            // بالصلاحيات؛ التحكم بالوصول عبر أدوار spatie/laravel-permission.
            $table->string('department')->nullable()->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('department');
        });
    }
};
