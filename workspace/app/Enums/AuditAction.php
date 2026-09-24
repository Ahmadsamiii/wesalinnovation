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

    case ProjectCreated = 'project.created';
    case ProjectUpdated = 'project.updated';
    case ProjectDeleted = 'project.deleted';
    case ProjectSubmitted = 'project.submitted';
    case ProjectDecided = 'project.decided';
    case ProjectStarted = 'project.started';
    case ProjectCompleted = 'project.completed';
    case ProjectMemberAdded = 'project.member_added';
    case ProjectMemberRemoved = 'project.member_removed';
    case AttachmentUploaded = 'project.attachment_uploaded';
    case AttachmentDeleted = 'project.attachment_deleted';

    case TaskDeleted = 'task.deleted';

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
            self::ProjectCreated => 'إنشاء مشروع',
            self::ProjectUpdated => 'تعديل مشروع',
            self::ProjectDeleted => 'حذف مسودة مشروع',
            self::ProjectSubmitted => 'تقديم مشروع للاعتماد',
            self::ProjectDecided => 'قرار تنفيذي على مشروع',
            self::ProjectStarted => 'بدء تنفيذ مشروع',
            self::ProjectCompleted => 'إنجاز مشروع',
            self::ProjectMemberAdded => 'إضافة عضو لمشروع',
            self::ProjectMemberRemoved => 'إزالة عضو من مشروع',
            self::AttachmentUploaded => 'رفع مرفق',
            self::AttachmentDeleted => 'حذف مرفق',
            self::TaskDeleted => 'حذف مهمة',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::AuthFailed, self::UserDeactivated, self::ProjectDeleted, self::TaskDeleted, self::AttachmentDeleted => 'red',
            self::UserRoleChanged, self::PasswordReset, self::ProjectDecided => 'orange',
            self::UserCreated, self::InvitationAccepted, self::UserReactivated, self::ProjectCreated, self::ProjectCompleted => 'green',
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
