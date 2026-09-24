<?php

namespace App\Models;

use App\Enums\AuditAction;
use App\Enums\Priority;
use App\Enums\ProjectDecisionType;
use App\Enums\ProjectMemberRole;
use App\Enums\ProjectStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\TaskStatus;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use LogicException;

#[Fillable(['name', 'description', 'client_id', 'pm_id', 'priority', 'budget', 'start_date', 'end_date'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /**
     * نفس افتراضات المخطّط، ليعرفها النموذج قبل أول قراءة من القاعدة.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'priority' => 'normal',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'priority' => Priority::class,
            'budget' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'actual_start_date' => 'date',
            'actual_end_date' => 'date',
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function pm(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pm_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<ProjectMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function team(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')->withPivot(['role', 'joined_at'])->withTimestamps();
    }

    /**
     * @return HasMany<ProjectMilestone, $this>
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * @return HasMany<ProjectDecision, $this>
     */
    public function decisions(): HasMany
    {
        return $this->hasMany(ProjectDecision::class)->latest('decided_at')->latest('id');
    }

    /**
     * @return HasMany<Contract, $this>
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    /**
     * @return HasMany<PurchaseOrder, $this>
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * ما التزم به المشروع من ميزانيته: أوامر الشراء المقدّمة أو المعتمدة أو
     * المستلمة (المسودات والمرفوضة والملغاة لا تُحسب).
     */
    public function committedSpend(): string
    {
        return (string) $this->purchaseOrders()->whereIn('status', PurchaseOrderStatus::committed())->sum('total');
    }

    /**
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest('id');
    }

    /**
     * المشاريع التي يراها المستخدم: التنفيذي والمالي يريان الكل (الاعتماد
     * والعقود والفواتير تمر عليها كلها)، والعميل مشاريعه، وغيرهم ما يديره أو
     * هو عضو فيه.
     *
     * @param  Builder<Project>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->hasAnyRole(['executive', 'finance'])) {
            return;
        }

        if ($user->hasRole('client')) {
            $query->where('client_id', $user->id);

            return;
        }

        $query->where(fn (Builder $query) => $query
            ->where('pm_id', $user->id)
            ->orWhereHas('members', fn (Builder $query) => $query->where('user_id', $user->id)));
    }

    public function isManagedBy(User $user): bool
    {
        return $this->pm_id === $user->id || $user->hasRole('executive');
    }

    public function hasMember(User $user): bool
    {
        return $this->relationLoaded('members')
            ? $this->members->contains('user_id', $user->id)
            : $this->members()->where('user_id', $user->id)->exists();
    }

    public function isLedBy(User $user): bool
    {
        if ($this->relationLoaded('members')) {
            return $this->members->contains(fn (ProjectMember $member): bool => $member->user_id === $user->id && $member->role === ProjectMemberRole::Lead);
        }

        return $this->members()
            ->where('user_id', $user->id)
            ->where('role', ProjectMemberRole::Lead)
            ->exists();
    }

    /**
     * عدّادات الإنجاز في نفس استعلام القائمة، فلا يُستعلم عن كل مشروع وحده.
     *
     * @param  Builder<Project>  $query
     */
    public function scopeWithProgressCounts(Builder $query): void
    {
        $query->withCount([
            'tasks',
            'tasks as done_tasks_count' => fn (Builder $query) => $query->where('status', TaskStatus::Done),
            'milestones',
            'milestones as reached_milestones_count' => fn (Builder $query) => $query->whereNotNull('completed_at'),
        ]);
    }

    /**
     * نسبة الإنجاز: المهام المنجزة من كل المهام، وإن لم تكن مهام بعد فالمعالم
     * المبلوغة من كل المعالم.
     */
    public function progress(): int
    {
        if (! isset($this->tasks_count)) {
            $this->loadCount([
                'tasks',
                'tasks as done_tasks_count' => fn (Builder $query) => $query->where('status', TaskStatus::Done),
                'milestones',
                'milestones as reached_milestones_count' => fn (Builder $query) => $query->whereNotNull('completed_at'),
            ]);
        }

        if ($this->tasks_count > 0) {
            return (int) round($this->done_tasks_count * 100 / $this->tasks_count);
        }

        return $this->milestones_count > 0
            ? (int) round($this->reached_milestones_count * 100 / $this->milestones_count)
            : 0;
    }

    public function isOverdue(): bool
    {
        return $this->end_date !== null
            && $this->end_date->isPast()
            && ! $this->end_date->isToday()
            && ! $this->status->isTerminal();
    }

    public function submitForApproval(User $by): void
    {
        throw_unless($this->status->canBeSubmitted(), LogicException::class, 'Project cannot be submitted from its current status.');

        $this->forceFill(['status' => ProjectStatus::PendingApproval, 'submitted_at' => now()])->save();

        AuditLog::record(AuditAction::ProjectSubmitted, $this, actor: $by);
    }

    /**
     * يسجّل القرار في تاريخ القرارات ويغيّر الحالة معاً، أو لا شيء منهما.
     */
    public function decide(ProjectDecisionType $type, User $by, ?string $note = null): ProjectDecision
    {
        throw_unless($type->isAllowedFrom($this->status), LogicException::class, 'Decision not allowed from the current status.');

        return DB::transaction(function () use ($type, $by, $note): ProjectDecision {
            $decision = $this->decisions()->create([
                'decided_by' => $by->id,
                'type' => $type,
                'note' => $note,
                'decided_at' => now(),
            ]);

            $this->status = match ($type) {
                ProjectDecisionType::Approved => ProjectStatus::Approved,
                ProjectDecisionType::Rejected => ProjectStatus::Rejected,
                ProjectDecisionType::OnHold => ProjectStatus::OnHold,
                // يعود لما كان عليه قبل الإيقاف: قيد التنفيذ إن كان قد بدأ.
                ProjectDecisionType::Resumed => $this->actual_start_date ? ProjectStatus::InProgress : ProjectStatus::Approved,
                ProjectDecisionType::Cancelled => ProjectStatus::Cancelled,
            };
            $this->save();

            AuditLog::record(AuditAction::ProjectDecided, $this, array_filter([
                'decision' => $type->value,
                'decision_label' => $type->label(),
                'note' => $note,
            ]), $by);

            return $decision;
        });
    }

    public function start(User $by): void
    {
        throw_unless($this->status === ProjectStatus::Approved, LogicException::class, 'Only an approved project can start.');

        $this->forceFill(['status' => ProjectStatus::InProgress, 'actual_start_date' => today()])->save();

        AuditLog::record(AuditAction::ProjectStarted, $this, actor: $by);
    }

    public function complete(User $by): void
    {
        throw_unless($this->status === ProjectStatus::InProgress, LogicException::class, 'Only a project in progress can be completed.');

        $this->forceFill(['status' => ProjectStatus::Completed, 'actual_end_date' => today()])->save();

        AuditLog::record(AuditAction::ProjectCompleted, $this, actor: $by);
    }

    public function auditLabel(): string
    {
        return $this->name;
    }
}
