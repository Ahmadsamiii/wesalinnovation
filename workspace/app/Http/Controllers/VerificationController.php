<?php

namespace App\Http\Controllers;

use App\Enums\ReferenceLetterStatus;
use App\Models\Certificate;
use App\Models\ReferenceLetter;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * صفحة عامة بلا دخول: يتحقق منها أي طرف ثالث من شهادة أو إفادة أو بطاقة
 * موظف برمزها المطبوع. تعرض الحد الأدنى (نوع المستند واسم صاحبه وحالته)،
 * بلا بريد ولا جوال ولا أي بيانات مالية.
 */
class VerificationController extends Controller
{
    public function __invoke(Request $request, ?string $code = null): View
    {
        $code = Certificate::normalizeVerificationCode((string) ($code ?? $request->query('code', '')));

        if ($code === '') {
            return view('verify.show', ['code' => null, 'result' => null]);
        }

        return view('verify.show', ['code' => $code, 'result' => $this->lookup($code)]);
    }

    /**
     * @return array{kind: string, valid: bool, holder: string, details: array<string, string|null>}|null
     */
    private function lookup(string $code): ?array
    {
        if ($certificate = Certificate::with(['recipient', 'project'])->where('verification_code', $code)->first()) {
            return [
                'kind' => $certificate->type->label(),
                'valid' => ! $certificate->isRevoked(),
                'holder' => $certificate->recipient->name,
                'details' => [
                    'رقم الشهادة' => $certificate->number,
                    'المشروع' => $certificate->project->name,
                    'تاريخ الإصدار' => $certificate->issued_at->translatedFormat('j F Y'),
                    'الإلغاء' => $certificate->revoked_at?->translatedFormat('j F Y'),
                ],
            ];
        }

        if ($letter = ReferenceLetter::where('verification_code', $code)->where('status', ReferenceLetterStatus::Approved)->first()) {
            return [
                'kind' => 'إفادة وظيفية',
                'valid' => true,
                'holder' => $letter->holder_name,
                'details' => [
                    'رقم الإفادة' => $letter->number,
                    'المسمى الوظيفي' => $letter->job_title,
                    'تاريخ الإصدار' => $letter->decided_at->translatedFormat('j F Y'),
                ],
            ];
        }

        if ($user = User::where('card_code', $code)->first()) {
            return [
                'kind' => 'بطاقة موظف',
                // البطاقة سارية ما دام صاحبها على رأس العمل؛ الإيقاف يبطلها فوراً.
                'valid' => ! $user->isDeactivated(),
                'holder' => $user->name,
                'details' => [
                    'الرقم الوظيفي' => $user->employeeNumber(),
                    'المسمى الوظيفي' => $user->job_title,
                ],
            ];
        }

        return null;
    }
}
