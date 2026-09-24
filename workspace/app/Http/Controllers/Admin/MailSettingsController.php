<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * «تكامل البريد الإلكتروني»: الإعداد الفعلي كما يراه التطبيق (بلا أسرار)،
 * ورسالة اختبار تثبت أن الدعوات واستعادة كلمة المرور تصل فعلاً.
 */
class MailSettingsController extends Controller
{
    public function show(Request $request): View
    {
        $mailer = (string) config('mail.default');
        $settings = config("mail.mailers.{$mailer}", []);

        return view('admin.system.mail', [
            'mailer' => $mailer,
            'delivers' => ! in_array($mailer, ['log', 'array'], true),
            'settings' => array_filter([
                'طريقة الإرسال' => $mailer,
                'الخادم' => $settings['host'] ?? null,
                'المنفذ' => $settings['port'] ?? null,
                'التشفير' => $settings['scheme'] ?? $settings['encryption'] ?? null,
                'اسم المستخدم' => isset($settings['username']) ? Str::mask((string) $settings['username'], '•', 3, -4) : null,
                'عنوان المرسل' => config('mail.from.address'),
                'اسم المرسل' => config('mail.from.name'),
            ], fn ($value): bool => filled($value)),
            'recentTests' => AuditLog::query()
                ->where('action', AuditAction::MailTestSent)
                ->with('user')
                ->latest('id')
                ->limit(5)
                ->get(),
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $validated = $request->validate(['to' => ['required', 'email:rfc', 'max:255']]);

        try {
            Mail::raw(
                "هذه رسالة اختبار من مساحة عمل وصال.\n\nوصولها يعني أن رسائل الدعوات واستعادة كلمة المرور تصل أيضاً.\n\nأرسلها: {$request->user()->name}",
                fn ($message) => $message->to($validated['to'])->subject('رسالة اختبار من '.config('app.name')),
            );
        } catch (Throwable $exception) {
            AuditLog::record(AuditAction::MailTestSent, properties: ['to' => $validated['to'], 'delivered' => false, 'error' => Str::limit($exception->getMessage(), 300)]);

            return back()->withInput()->with('error', 'تعذّر الإرسال: '.Str::limit($exception->getMessage(), 300));
        }

        AuditLog::record(AuditAction::MailTestSent, properties: ['to' => $validated['to'], 'delivered' => true]);

        return back()->with('status', in_array(config('mail.default'), ['log', 'array'], true)
            ? 'كُتبت الرسالة في سجل التطبيق ولم تُرسل: طريقة الإرسال الحالية لا توصل البريد.'
            : "سُلّمت الرسالة لخادم البريد إلى {$validated['to']}. تحقق من وصولها (ومن مجلد البريد غير المرغوب).");
    }
}
