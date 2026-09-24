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
            // بيانات وظيفية تظهر في البطاقة الرقمية ونص الإفادة.
            $table->string('job_title')->nullable()->after('department');
            $table->string('phone', 30)->nullable()->after('job_title');
            $table->date('joined_at')->nullable()->after('phone');

            // الانضمام بدعوة: يُنشئ مدير النظام الحساب بكلمة مرور عشوائية لا
            // يعرفها أحد، ويعيّنها صاحبه من رابط الدعوة. إعادة الإرسال تغيّر
            // invited_at فتُبطل كل رابط سابق.
            $table->timestamp('invited_at')->nullable()->after('remember_token');
            $table->timestamp('invitation_accepted_at')->nullable()->after('invited_at');

            // الإيقاف بديل الحذف: الحسابات مرتبطة بقرارات ومهام وعقود لا يجوز
            // أن تفقد من نُسبت إليه.
            $table->timestamp('deactivated_at')->nullable()->after('invitation_accepted_at');
            $table->timestamp('last_login_at')->nullable()->after('deactivated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'job_title', 'phone', 'joined_at',
                'invited_at', 'invitation_accepted_at', 'deactivated_at', 'last_login_at',
            ]);
        });
    }
};
