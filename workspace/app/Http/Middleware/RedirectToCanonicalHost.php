<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * مساحة العمل تنتقل من نطاقها الفرعي إلى مسار داخل الموقع الرئيسي
 * (wesalinnovation.sa/workspace). مع ضبط WORKSPACE_CANONICAL_URL يُحوَّل كل طلب
 * قراءة يصل من مضيف آخر إلى العنوان المعتمد بمساره واستعلامه، فتبقى الروابط
 * القديمة تعمل: التحقق المطبوع على الشهادات والبطاقات (/verify)، وصفحات المحتوى
 * الصحي (/kb) التي يستشهد بها المساعد، ودعوات الحسابات، وأي رابط حُفظ.
 *
 * التحويل مؤقت (302) في أول أيام التبديل ليسهل الرجوع، ثم يُرفع إلى 301 بضبط
 * WORKSPACE_CANONICAL_STATUS. فحص الصحة /up لا يُحوَّل: سكربت النشر يسأله
 * على العنوان المضبوط في APP_URL. الطلبات غير القراءة (نموذج قديم) لا تُحوَّل
 * حتى لا يضيع جسمها. بلا القيمة لا يفعل شيئاً.
 */
class RedirectToCanonicalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $canonical = (string) config('workspace.canonical_url');

        if ($canonical === '' || ! $request->isMethodSafe() || $request->is('up')) {
            return $next($request);
        }

        $host = parse_url($canonical, PHP_URL_HOST);
        if (! is_string($host) || Str::lower($request->getHost()) === Str::lower($host)) {
            return $next($request);
        }

        return redirect()->away(rtrim($canonical, '/').$request->getRequestUri(), (int) config('workspace.canonical_status', 302));
    }
}
