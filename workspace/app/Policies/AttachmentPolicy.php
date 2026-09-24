<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;

class AttachmentPolicy
{
    /**
     * التحميل لمن يرى تفاصيل المشروع الداخلية؛ المرفقات مستندات عمل لا تُعرض للعميل.
     */
    public function view(User $user, Attachment $attachment): bool
    {
        return $user->can('viewInternals', $attachment->project());
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        $project = $attachment->project();

        return ! $project->status->isTerminal()
            && ($attachment->uploaded_by === $user->id || $project->isManagedBy($user));
    }
}
