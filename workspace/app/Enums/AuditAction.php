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

    case ContractCreated = 'finance.contract_created';
    case ContractActivated = 'finance.contract_activated';
    case ContractClosed = 'finance.contract_closed';
    case PurchaseOrderCreated = 'finance.po_created';
    case PurchaseOrderSubmitted = 'finance.po_submitted';
    case PurchaseOrderReviewed = 'finance.po_reviewed';
    case PurchaseOrderDecided = 'finance.po_decided';
    case PurchaseOrderReceived = 'finance.po_received';
    case PurchaseOrderCancelled = 'finance.po_cancelled';
    case InvoiceCreated = 'finance.invoice_created';
    case InvoiceIssued = 'finance.invoice_issued';
    case InvoiceCancelled = 'finance.invoice_cancelled';
    case PaymentRecorded = 'finance.payment_recorded';

    case CertificateIssued = 'certificate.issued';
    case CertificateRevoked = 'certificate.revoked';
    case ReferenceLetterRequested = 'certificate.letter_requested';
    case ReferenceLetterApproved = 'certificate.letter_approved';
    case ReferenceLetterRejected = 'certificate.letter_rejected';

    case HiringRequested = 'hiring.requested';
    case HiringApproved = 'hiring.approved';
    case HiringRejected = 'hiring.rejected';
    case HiringFilled = 'hiring.filled';
    case HiringCancelled = 'hiring.cancelled';

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
            self::ContractCreated => 'إنشاء عقد',
            self::ContractActivated => 'توقيع عقد وتفعيله',
            self::ContractClosed => 'إغلاق عقد',
            self::PurchaseOrderCreated => 'إنشاء أمر شراء',
            self::PurchaseOrderSubmitted => 'تقديم أمر شراء',
            self::PurchaseOrderReviewed => 'مراجعة مالية لأمر شراء',
            self::PurchaseOrderDecided => 'قرار تنفيذي على أمر شراء',
            self::PurchaseOrderReceived => 'استلام أمر شراء',
            self::PurchaseOrderCancelled => 'إلغاء أمر شراء',
            self::InvoiceCreated => 'إنشاء فاتورة',
            self::InvoiceIssued => 'إصدار فاتورة',
            self::InvoiceCancelled => 'إلغاء فاتورة',
            self::PaymentRecorded => 'تسجيل دفعة',
            self::CertificateIssued => 'إصدار شهادة',
            self::CertificateRevoked => 'إلغاء شهادة',
            self::ReferenceLetterRequested => 'طلب إفادة',
            self::ReferenceLetterApproved => 'اعتماد إفادة',
            self::ReferenceLetterRejected => 'رفض طلب إفادة',
            self::HiringRequested => 'طلب توظيف',
            self::HiringApproved => 'اعتماد طلب توظيف',
            self::HiringRejected => 'رفض طلب توظيف',
            self::HiringFilled => 'شغل وظيفة',
            self::HiringCancelled => 'إلغاء طلب توظيف',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::AuthFailed, self::UserDeactivated, self::ProjectDeleted, self::TaskDeleted, self::AttachmentDeleted,
            self::PurchaseOrderCancelled, self::InvoiceCancelled, self::CertificateRevoked, self::ReferenceLetterRejected,
            self::HiringRejected, self::HiringCancelled => 'red',
            self::UserRoleChanged, self::PasswordReset, self::ProjectDecided, self::PurchaseOrderReviewed, self::PurchaseOrderDecided => 'orange',
            self::UserCreated, self::InvitationAccepted, self::UserReactivated, self::ProjectCreated, self::ProjectCompleted,
            self::ContractActivated, self::InvoiceIssued, self::PaymentRecorded, self::CertificateIssued, self::ReferenceLetterApproved,
            self::HiringApproved, self::HiringFilled => 'green',
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
