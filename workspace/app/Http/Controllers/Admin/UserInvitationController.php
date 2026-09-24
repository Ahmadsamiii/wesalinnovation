<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class UserInvitationController extends Controller
{
    /**
     * إعادة الإرسال تُبطل كل رابط سابق لنفس الحساب.
     */
    public function store(User $user): RedirectResponse
    {
        if ($user->status() !== AccountStatus::Pending) {
            return back()->with('error', 'لا دعوة معلّقة لهذا الحساب.');
        }

        return $user->sendInvitation()
            ? back()->with('status', "أُعيد إرسال الدعوة إلى {$user->email}.")
            : back()->with('error', 'تعذّر إرسال الدعوة بالبريد. انسخ الرابط أدناه وأرسله بنفسك.');
    }
}
