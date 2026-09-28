<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * رقم الحساب في المنصة العامة (users.id هناك). يربط الحساب المحلي بهويته
     * الموحدة دون تغيير رقمه المحلي، الذي تشير إليه المهام والعقود والفواتير
     * والشهادات: لا جدول في مساحة العمل يُعاد كتابته. فارغ لحساب لم يُربط بعد.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('platform_user_id')->nullable()->unique()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['platform_user_id']);
            $table->dropColumn('platform_user_id');
        });
    }
};
