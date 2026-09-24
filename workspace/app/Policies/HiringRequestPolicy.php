<?php

namespace App\Policies;

use App\Enums\HiringRequestStatus;
use App\Models\HiringRequest;
use App\Models\User;

/**
 * يطلب التوظيف مسؤولو الفرق، ويقرّره المدير التنفيذي. لا يعتمد أحد طلبه.
 */
class HiringRequestPolicy
{
    /**
     * أدوار تدير فريقاً فتطلب له.
     *
     * @var list<string>
     */
    public const REQUESTER_ROLES = ['pm', 'finance', 'sysadmin', 'medical'];

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['executive', ...self::REQUESTER_ROLES]);
    }

    public function view(User $user, HiringRequest $request): bool
    {
        return $user->hasRole('executive') || $request->requested_by === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(self::REQUESTER_ROLES);
    }

    /**
     * التعديل قبل القرار فقط، ولصاحب الطلب.
     */
    public function update(User $user, HiringRequest $request): bool
    {
        return $request->status === HiringRequestStatus::Pending && $request->requested_by === $user->id;
    }

    public function decide(User $user, HiringRequest $request): bool
    {
        return $request->status === HiringRequestStatus::Pending
            && $user->hasRole('executive')
            && $request->requested_by !== $user->id;
    }

    public function fill(User $user, HiringRequest $request): bool
    {
        return $request->status === HiringRequestStatus::Approved && $this->view($user, $request);
    }

    /**
     * صاحب الطلب يسحبه قبل القرار؛ وبعد الاعتماد يلغيه هو أو المدير التنفيذي.
     */
    public function cancel(User $user, HiringRequest $request): bool
    {
        return match ($request->status) {
            HiringRequestStatus::Pending => $request->requested_by === $user->id,
            HiringRequestStatus::Approved => $this->view($user, $request),
            default => false,
        };
    }
}
