<?php

namespace App\Console\Commands;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * أول حساب في الإنتاج: لا تسجيل ذاتي ولا بذور تجريبية هناك، فيُنشأ مدير النظام
 * الأول من الطرفية بدعوة كأي حساب، ثم ينشئ هو بقية الحسابات من الواجهة.
 */
#[Signature('workspace:create-sysadmin {email : بريد مدير النظام} {--name=مدير النظام : الاسم كما يظهر في النظام}')]
#[Description('إنشاء حساب مدير نظام وطباعة رابط دعوته')]
class CreateSysadmin extends Command
{
    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('البريد غير صالح.');

            return self::FAILURE;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->error('يوجد حساب بهذا البريد؛ أدِره من صفحة الأدوار والصلاحيات.');

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($email): User {
            // كلمة مرور عشوائية لا يعرفها أحد حتى تُقبل الدعوة، كما في الواجهة.
            $user = User::create(['name' => (string) $this->option('name'), 'email' => $email, 'password' => Str::password(40)]);
            $user->assignSingleRole('sysadmin');

            AuditLog::record(AuditAction::UserCreated, $user, ['role' => 'sysadmin', 'via' => 'console']);

            return $user;
        });

        $sent = $user->sendInvitation();

        $this->info("أُنشئ حساب مدير النظام {$email}.");
        $this->line($sent ? 'أُرسلت الدعوة بالبريد. الرابط نفسه (صالح '.User::INVITATION_VALID_DAYS.' أيام):' : 'تعذّر إرسال البريد؛ افتح هذا الرابط (صالح '.User::INVITATION_VALID_DAYS.' أيام):');
        $this->line($user->invitationUrl());

        return self::SUCCESS;
    }
}
