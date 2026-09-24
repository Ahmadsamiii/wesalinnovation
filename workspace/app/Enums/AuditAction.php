<?php

namespace App\Enums;

/**
 * كل ما يُسجَّل في سجل التدقيق. الاسم المخزَّن بصيغة «الكيان.الفعل» ثابت لا
 * يُعاد تسميته بعد أول استخدام: السطور القديمة تُقرأ به.
 */
enum AuditAction: string
{
    case AuthLogin = 'auth.login';
    case AuthFailed = 'auth.failed';
    case PasswordReset = 'auth.password_reset';

    case UserCreated = 'user.created';
    case UserUpdated = 'user.updated';
    case UserRoleChanged = 'user.role_changed';
    case UserDeactivated = 'user.deactivated';
    case UserReactivated = 'user.reactivated';
    case InvitationSent = 'user.invitation_sent';
    case InvitationAccepted = 'user.invitation_accepted';

    public function label(): string
    {
        return match ($this) {
            self::AuthLogin => 'تسجيل دخول',
            self::AuthFailed => 'محاولة دخول فاشلة',
            self::PasswordReset => 'تعيين كلمة المرور من رابط الاستعادة',
            self::UserCreated => 'إنشاء حساب',
            self::UserUpdated => 'تعديل بيانات حساب',
            self::UserRoleChanged => 'تغيير دور',
            self::UserDeactivated => 'إيقاف حساب',
            self::UserReactivated => 'إعادة تفعيل حساب',
            self::InvitationSent => 'إرسال دعوة',
            self::InvitationAccepted => 'قبول دعوة',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::AuthFailed, self::UserDeactivated => 'red',
            self::UserRoleChanged, self::PasswordReset => 'orange',
            self::UserCreated, self::InvitationAccepted, self::UserReactivated => 'green',
            self::AuthLogin => 'gray',
            default => 'blue',
        };
    }

    /**
     * الفئة التي يُصفّى بها السجل: الجزء قبل النقطة.
     */
    public function group(): string
    {
        return strstr($this->value, '.', true);
    }
}
