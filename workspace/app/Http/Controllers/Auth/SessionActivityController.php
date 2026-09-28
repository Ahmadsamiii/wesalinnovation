<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnforceSessionTimeouts;
use App\Support\PlatformSession;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * نقطتا عدّاد الخمول في الواجهة (idleTimeout في app.js). المهلة نفسها يفرضها
 * EnforceSessionTimeouts قبل أن يصل الطلب إلى هنا.
 */
class SessionActivityController extends Controller
{
    /**
     * نبضة نشاط: المستخدم يتحرك في الصفحة دون أن يرسل طلباً (يكتب عقداً طويلاً
     * مثلاً)، فتبقى جلسته حيّة. الوسيط جدّد آخر نشاط قبل الوصول إلى هنا.
     */
    public function heartbeat(): Response
    {
        return response()->noContent();
    }

    /**
     * انتهى العدّ التنازلي في الواجهة: خروج يُسجَّل تلقائياً لا يدوياً.
     */
    public function expire(Request $request): Response
    {
        EnforceSessionTimeouts::endSession($request, $request->user(), 'idle');

        if (PlatformSession::enabled()) {
            PlatformSession::endFromRequest($request, 'idle');
        }

        return response()->noContent();
    }
}
