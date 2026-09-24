<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * تُرسل فوراً لا عبر طابور: الاستضافة المشتركة بلا عامل طوابير دائم، ودعوة
 * عالقة في طابور لا يعالجه أحد أسوأ من فشل ظاهر يعيد المدير المحاولة بعده.
 */
class AccountInvitation extends Notification
{
    use Queueable;

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('دعوة للانضمام إلى مساحة عمل وصال')
            ->greeting('مرحباً '.$notifiable->name)
            ->line('أنشأ لك مدير النظام حساباً في مساحة عمل وصال بدور «'.$notifiable->roleLabel().'».')
            ->line('عيّن كلمة المرور من الزر أدناه لتفعيل حسابك.')
            ->action('تفعيل الحساب', $notifiable->invitationUrl())
            ->line('الرابط صالح '.User::INVITATION_VALID_DAYS.' أيام ولاستخدام واحد. إن انتهى فاطلب من مدير النظام إعادة إرسال الدعوة.')
            ->salutation('وصال الابتكار');
    }
}
