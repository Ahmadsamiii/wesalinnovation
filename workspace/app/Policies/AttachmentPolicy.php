<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\Contract;
use App\Models\PurchaseOrder;
use App\Models\User;

class AttachmentPolicy
{
    /**
     * مرفقات العقد وأمر الشراء تتبع صلاحية مستندها (العميل يحمّل نسخة عقده
     * الموقّع)؛ مرفقات المشروع ومهامه مستندات عمل داخلية لا يراها العميل.
     */
    public function view(User $user, Attachment $attachment): bool
    {
        $attachable = $attachment->attachable;

        return match (true) {
            $attachable instanceof Contract, $attachable instanceof PurchaseOrder => $user->can('view', $attachable),
            default => $user->can('viewInternals', $attachment->project()),
        };
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        $attachable = $attachment->attachable;

        if ($attachable instanceof Contract || $attachable instanceof PurchaseOrder) {
            return $user->can('upload', $attachable)
                && ($attachment->uploaded_by === $user->id || $user->hasRole('finance'));
        }

        $project = $attachment->project();

        return ! $project->status->isTerminal()
            && ($attachment->uploaded_by === $user->id || $project->isManagedBy($user));
    }
}
