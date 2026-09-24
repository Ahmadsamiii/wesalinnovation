<?php

namespace App\Models;

use App\Enums\AuditAction;
use App\Enums\ReferenceLetterStatus;
use App\Models\Concerns\HasVerificationCode;
use Database\Factories\ReferenceLetterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use LogicException;

#[Fillable(['addressee', 'purpose', 'notes'])]
class ReferenceLetter extends Model
{
    /** @use HasFactory<ReferenceLetterFactory> */
    use HasFactory, HasVerificationCode;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReferenceLetterStatus::class,
            'decided_at' => 'datetime',
            'joined_at' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * @param  Builder<ReferenceLetter>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->hasRole('executive')) {
            $query->where('requester_id', $user->id);
        }
    }

    /**
     * الاعتماد يثبّت البيانات الوظيفية كما هي الآن ويسند الرقم ورمز التحقق.
     */
    public function approve(User $by, ?string $note = null): void
    {
        throw_unless($this->status === ReferenceLetterStatus::Pending, LogicException::class, 'Only a pending request can be approved.');

        $requester = $this->requester;

        DB::transaction(function () use ($by, $note, $requester): void {
            $this->forceFill([
                'number' => Sequence::next('REF'),
                'status' => ReferenceLetterStatus::Approved,
                'decided_by' => $by->id,
                'decided_at' => now(),
                'decision_note' => $note,
                'holder_name' => $requester->name,
                'job_title' => $requester->job_title,
                'department' => $requester->department,
                'joined_at' => $requester->joined_at,
                'verification_code' => self::newVerificationCode(),
            ])->save();

            AuditLog::record(AuditAction::ReferenceLetterApproved, $this, array_filter(['note' => $note]), $by);
        });
    }

    public function reject(User $by, string $note): void
    {
        throw_unless($this->status === ReferenceLetterStatus::Pending, LogicException::class, 'Only a pending request can be rejected.');

        $this->forceFill([
            'status' => ReferenceLetterStatus::Rejected,
            'decided_by' => $by->id,
            'decided_at' => now(),
            'decision_note' => $note,
        ])->save();

        AuditLog::record(AuditAction::ReferenceLetterRejected, $this, ['note' => $note], $by);
    }

    public function auditLabel(): string
    {
        return ($this->number ?? 'طلب إفادة #'.$this->id).($this->requester ? ': '.$this->requester->name : '');
    }
}
