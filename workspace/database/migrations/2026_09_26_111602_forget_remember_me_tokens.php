<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * أُزيل خيار «تذكرني» مع الخروج التلقائي: كوكيه (400 يوم) كان يعيد الدخول
     * بصمت بعد انتهاء الجلسة فيُبطل مهلة الخمول. إفراغ الرموز يُبطل كل كوكي
     * صدر قبل الإزالة، فلا يبقى أحد داخل النظام به.
     */
    public function up(): void
    {
        DB::table('users')->whereNotNull('remember_token')->update(['remember_token' => null]);
    }

    /**
     * لا رجعة: الرموز المُفرغة لا تُسترجع، ولا حاجة إليها بعد إزالة الخيار.
     */
    public function down(): void
    {
        //
    }
};
