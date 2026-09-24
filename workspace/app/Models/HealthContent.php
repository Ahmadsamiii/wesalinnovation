<?php

namespace App\Models;

use App\Enums\ApprovalDecision;
use App\Enums\AuditAction;
use App\Enums\HealthContentCategory;
use App\Enums\HealthContentStatus;
use Database\Factories\HealthContentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * محتوى صحي يحرّره مدير النظام ولا يُنشر إلا باعتماد المدير الطبي. المنشور
 * نسخة ثابتة (published_*) منفصلة عن النسخة قيد التحرير.
 */
#[Fillable(['category', 'title', 'summary', 'body', 'source_url'])]
class HealthContent extends Model
{
    /** @use HasFactory<HealthContentFactory> */
    use HasFactory;

    /**
     * مدة صلاحية الاعتماد الطبي قبل وجوب إعادة المراجعة.
     */
    public const REVIEW_VALID_MONTHS = 12;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'version' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => HealthContentStatus::class,
            'category' => HealthContentCategory::class,
            'version' => 'integer',
            'published_version' => 'integer',
            'submitted_at' => 'datetime',
            'published_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'review_due_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return HasMany<HealthContentReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(HealthContentReview::class)->latest('id');
    }

    /**
     * @param  Builder<HealthContent>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    /**
     * نسخة التحرير تختلف عن المنشورة (تعديل لم يُعتمد بعد).
     */
    public function hasUnpublishedChanges(): bool
    {
        return $this->isPublished() && (
            $this->title !== $this->published_title
            || $this->summary !== $this->published_summary
            || $this->body !== $this->published_body
        );
    }

    public function isReviewOverdue(): bool
    {
        return $this->isPublished() && $this->review_due_on !== null && $this->review_due_on->isPast();
    }

    public function submit(User $by): void
    {
        throw_unless($this->status->canBeSubmitted(), LogicException::class, 'Content cannot be submitted from its current status.');

        $this->forceFill([
            'status' => HealthContentStatus::InReview,
            'version' => $this->version + 1,
            'submitted_at' => now(),
        ])->save();

        AuditLog::record(AuditAction::ContentSubmitted, $this, ['version' => $this->version], $by);
    }

    /**
     * الاعتماد ينشر النسخة المراجَعة كما هي ويبدأ مدة صلاحيتها.
     */
    public function approve(User $by, ?string $note = null): void
    {
        throw_unless($this->status === HealthContentStatus::InReview, LogicException::class, 'Only content in review can be approved.');

        DB::transaction(function () use ($by, $note): void {
            $this->recordReview(ApprovalDecision::Approved, $by, $note);

            $this->forceFill([
                'status' => HealthContentStatus::Approved,
                'published_title' => $this->title,
                'published_summary' => $this->summary,
                'published_body' => $this->body,
                'published_version' => $this->version,
                'published_at' => now(),
                'review_due_on' => today()->addMonths(self::REVIEW_VALID_MONTHS),
                'withdrawn_at' => null,
            ])->save();

            AuditLog::record(AuditAction::ContentApproved, $this, array_filter(['version' => $this->version, 'note' => $note]), $by);
        });
    }

    /**
     * الرفض لا يمسّ نسخة منشورة سابقة إن وُجدت.
     */
    public function reject(User $by, string $note): void
    {
        throw_unless($this->status === HealthContentStatus::InReview, LogicException::class, 'Only content in review can be rejected.');

        DB::transaction(function () use ($by, $note): void {
            $this->recordReview(ApprovalDecision::Rejected, $by, $note);
            $this->forceFill(['status' => HealthContentStatus::Rejected])->save();

            AuditLog::record(AuditAction::ContentRejected, $this, ['version' => $this->version, 'note' => $note], $by);
        });
    }

    /**
     * مراجعة دورية لمنشور لم يتغيّر: قرار جديد على النسخة المنشورة نفسها
     * ومدة صلاحية جديدة.
     */
    public function renewApproval(User $by, ?string $note = null): void
    {
        throw_unless($this->status === HealthContentStatus::Approved && $this->isPublished(), LogicException::class, 'Only approved, published content can be renewed.');

        DB::transaction(function () use ($by, $note): void {
            $this->reviews()->create([
                'reviewer_id' => $by->id,
                'decision' => ApprovalDecision::Approved,
                'note' => $note ?? 'تجديد الاعتماد بعد المراجعة الدورية.',
                'version' => $this->published_version,
                'reviewed_title' => $this->published_title,
                'reviewed_body' => $this->published_body,
                'submitted_at' => null,
            ]);
            $this->forceFill(['review_due_on' => today()->addMonths(self::REVIEW_VALID_MONTHS)])->save();

            AuditLog::record(AuditAction::ContentApproved, $this, array_filter(['version' => $this->published_version, 'renewal' => true, 'note' => $note]), $by);
        });
    }

    public function withdraw(User $by, string $reason): void
    {
        throw_unless($this->isPublished(), LogicException::class, 'Only published content can be withdrawn.');

        $this->forceFill([
            'status' => HealthContentStatus::Withdrawn,
            'published_title' => null,
            'published_summary' => null,
            'published_body' => null,
            'published_version' => null,
            'published_at' => null,
            'review_due_on' => null,
            'withdrawn_at' => now(),
        ])->save();

        AuditLog::record(AuditAction::ContentWithdrawn, $this, ['reason' => $reason], $by);
    }

    public function auditLabel(): string
    {
        return $this->title;
    }

    private function recordReview(ApprovalDecision $decision, User $by, ?string $note): void
    {
        $this->reviews()->create([
            'reviewer_id' => $by->id,
            'decision' => $decision,
            'note' => $note,
            'version' => $this->version,
            'reviewed_title' => $this->title,
            'reviewed_body' => $this->body,
            'submitted_at' => $this->submitted_at,
        ]);
    }
}
