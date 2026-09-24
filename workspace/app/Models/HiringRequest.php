<?php

namespace App\Models;

use App\Enums\AuditAction;
use App\Enums\EmploymentType;
use App\Enums\HiringRequestStatus;
use Database\Factories\HiringRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * طلب توظيف: يرفعه مسؤول فريق بمبرراته، ويعتمده المدير التنفيذي أو يرفضه
 * بتعليل، ثم يُغلق بشغل الوظيفة أو بإلغائه.
 */
#[Fillable(['title', 'department', 'headcount', 'employment_type', 'justification', 'requirements', 'monthly_budget', 'target_start_date', 'project_id'])]
class HiringRequest extends Model
{
    /** @use HasFactory<HiringRequestFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'employment_type' => 'full_time',
        'headcount' => 1,
    ];

    protected static function booted(): void
    {
        static::creating(function (HiringRequest $request): void {
            $request->number ??= Sequence::next('HIR');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => HiringRequestStatus::class,
            'employment_type' => EmploymentType::class,
            'headcount' => 'integer',
            'monthly_budget' => 'decimal:2',
            'target_start_date' => 'date',
            'decided_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * المدير التنفيذي يرى كل الطلبات، وكل مسؤول طلباته.
     *
     * @param  Builder<HiringRequest>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->hasRole('executive')) {
            $query->where('requested_by', $user->id);
        }
    }

    public function approve(User $by, ?string $note = null): void
    {
        throw_unless($this->status === HiringRequestStatus::Pending, LogicException::class, 'Only a pending hiring request can be approved.');

        $this->decide(HiringRequestStatus::Approved, $by, $note);
        AuditLog::record(AuditAction::HiringApproved, $this, array_filter(['note' => $note]), $by);
    }

    public function reject(User $by, string $note): void
    {
        throw_unless($this->status === HiringRequestStatus::Pending, LogicException::class, 'Only a pending hiring request can be rejected.');

        $this->decide(HiringRequestStatus::Rejected, $by, $note);
        AuditLog::record(AuditAction::HiringRejected, $this, ['note' => $note], $by);
    }

    /**
     * @param  string  $note  من شُغلت به الوظيفة، أو ما يوثّق ذلك.
     */
    public function markFilled(User $by, string $note): void
    {
        throw_unless($this->status === HiringRequestStatus::Approved, LogicException::class, 'Only an approved hiring request can be filled.');

        $this->close(HiringRequestStatus::Filled, $by, $note);
        AuditLog::record(AuditAction::HiringFilled, $this, ['note' => $note], $by);
    }

    public function cancel(User $by, string $reason): void
    {
        throw_unless($this->status->isOpen(), LogicException::class, 'Only an open hiring request can be cancelled.');

        $this->close(HiringRequestStatus::Cancelled, $by, $reason);
        AuditLog::record(AuditAction::HiringCancelled, $this, ['reason' => $reason], $by);
    }

    public function auditLabel(): string
    {
        return $this->number.' — '.$this->title;
    }

    private function decide(HiringRequestStatus $status, User $by, ?string $note): void
    {
        $this->forceFill([
            'status' => $status,
            'decided_by' => $by->id,
            'decided_at' => now(),
            'decision_note' => $note,
        ])->save();
    }

    private function close(HiringRequestStatus $status, User $by, string $note): void
    {
        $this->forceFill([
            'status' => $status,
            'closed_by' => $by->id,
            'closed_at' => now(),
            'closing_note' => $note,
        ])->save();
    }
}
