<?php

namespace App\Http\Controllers;

use App\Enums\CertificateType;
use App\Models\Certificate;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * «بطاقتي الرقمية وشهاداتي»: بطاقة الموظف برمز تحقق عام، وشهادات المشاركة
 * التي صدرت له.
 */
class CardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        abort_if($user->hasRole('client') || $user->roleName() === null, 403);

        return view('card.show', [
            'user' => $user,
            'code' => $user->cardCode(),
            'certificates' => Certificate::query()
                ->where('recipient_id', $user->id)
                ->where('type', CertificateType::Participation)
                ->with('project')
                ->latest('issued_at')
                ->get(),
        ]);
    }
}
