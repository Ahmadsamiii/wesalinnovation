<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalDecision;
use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * قرارا أمر الشراء: مراجعة المالية ثم اعتماد التنفيذي فيما يتجاوز الحد. الرفض
 * في المرحلتين يحتاج تعليلاً يراه مقدّم الطلب.
 */
class PurchaseOrderApprovalController extends Controller
{
    public function review(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        Gate::authorize('review', $purchaseOrder);

        [$decision, $note] = $this->validated($request);
        $purchaseOrder->review($decision, $request->user(), $note);

        return back()->with('status', match ($purchaseOrder->status) {
            PurchaseOrderStatus::PendingExecutive => 'اعتمدت المالية الأمر، وانتقل للاعتماد التنفيذي لتجاوزه الحد.',
            PurchaseOrderStatus::Approved => 'اعتُمد أمر الشراء.',
            default => 'رُفض أمر الشراء وعاد لمقدّمه.',
        });
    }

    public function decide(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        Gate::authorize('decide', $purchaseOrder);

        [$decision, $note] = $this->validated($request);
        $purchaseOrder->decide($decision, $request->user(), $note);

        return back()->with('status', $decision === ApprovalDecision::Approved ? 'اعتُمد أمر الشراء.' : 'رُفض أمر الشراء وعاد لمقدّمه.');
    }

    /**
     * @return array{0: ApprovalDecision, 1: ?string}
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'decision' => ['required', Rule::enum(ApprovalDecision::class)],
            'note' => ['nullable', 'required_if:decision,rejected', 'string', 'max:2000'],
        ], ['note.required_if' => 'الرفض يحتاج تعليلاً يراه مقدّم الطلب.']);

        return [ApprovalDecision::from($validated['decision']), $validated['note'] ?? null];
    }
}
