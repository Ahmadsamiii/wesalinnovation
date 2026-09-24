<?php

namespace App\Models;

use App\Enums\ApprovalDecision;
use App\Enums\ApprovalStage;
use App\Enums\AuditAction;
use App\Enums\PurchaseOrderStatus;
use App\Models\Concerns\HasLineItems;
use Database\Factories\PurchaseOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use LogicException;

#[Fillable(['project_id', 'vendor_name', 'vendor_contact', 'description', 'needed_by'])]
class PurchaseOrder extends Model
{
    /** @use HasFactory<PurchaseOrderFactory> */
    use HasFactory, HasLineItems;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'needed_by' => 'date',
            'vat_rate' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'submitted_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PurchaseOrder $order): void {
            $order->number ??= Sequence::next('PO');
            $order->vat_rate ??= config('workspace.vat_rate');
        });
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return HasMany<PurchaseOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class)->orderBy('position');
    }

    /**
     * @return HasMany<PurchaseOrderApproval, $this>
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(PurchaseOrderApproval::class)->latest('decided_at')->latest('id');
    }

    /**
     * عروض الأسعار والفواتير الواردة من المورّد.
     *
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest('id');
    }

    /**
     * التنفيذي والمالي يريان الكل؛ مدير المشاريع أوامر مشاريعه وما طلبه بنفسه.
     *
     * @param  Builder<PurchaseOrder>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->hasAnyRole(['executive', 'finance'])) {
            return;
        }

        $query->where(fn (Builder $query) => $query
            ->where('requested_by', $user->id)
            ->orWhereHas('project', fn (Builder $query) => $query->where('pm_id', $user->id)));
    }

    public function requiresExecutiveApproval(): bool
    {
        return (float) $this->total > (float) config('workspace.executive_approval_threshold');
    }

    public function submit(User $by): void
    {
        throw_unless($this->status->isEditable(), LogicException::class, 'Only a draft or rejected order can be submitted.');
        throw_if($this->items()->doesntExist(), LogicException::class, 'An order needs at least one item.');

        $this->forceFill(['status' => PurchaseOrderStatus::PendingFinance, 'submitted_at' => now()])->save();

        AuditLog::record(AuditAction::PurchaseOrderSubmitted, $this, actor: $by);
    }

    /**
     * المرحلة الأولى: المالية. الاعتماد ينهي المسار ما لم يتجاوز الإجمالي حد
     * الاعتماد التنفيذي، فينتقل للمدير التنفيذي.
     */
    public function review(ApprovalDecision $decision, User $by, ?string $note = null): void
    {
        throw_unless($this->status === PurchaseOrderStatus::PendingFinance, LogicException::class, 'Order is not awaiting finance review.');

        $next = match ($decision) {
            ApprovalDecision::Rejected => PurchaseOrderStatus::Rejected,
            ApprovalDecision::Approved => $this->requiresExecutiveApproval() ? PurchaseOrderStatus::PendingExecutive : PurchaseOrderStatus::Approved,
        };

        $this->recordDecision(ApprovalStage::Finance, $decision, $next, $by, $note, AuditAction::PurchaseOrderReviewed);
    }

    public function decide(ApprovalDecision $decision, User $by, ?string $note = null): void
    {
        throw_unless($this->status === PurchaseOrderStatus::PendingExecutive, LogicException::class, 'Order is not awaiting executive approval.');

        $next = $decision === ApprovalDecision::Approved ? PurchaseOrderStatus::Approved : PurchaseOrderStatus::Rejected;

        $this->recordDecision(ApprovalStage::Executive, $decision, $next, $by, $note, AuditAction::PurchaseOrderDecided);
    }

    public function markReceived(User $by): void
    {
        throw_unless($this->status === PurchaseOrderStatus::Approved, LogicException::class, 'Only an approved order can be received.');

        $this->forceFill(['status' => PurchaseOrderStatus::Received, 'received_at' => now()])->save();

        AuditLog::record(AuditAction::PurchaseOrderReceived, $this, actor: $by);
    }

    public function cancel(User $by, string $reason): void
    {
        throw_if(in_array($this->status, [PurchaseOrderStatus::Received, PurchaseOrderStatus::Cancelled], true), LogicException::class, 'Order can no longer be cancelled.');

        $this->forceFill(['status' => PurchaseOrderStatus::Cancelled])->save();

        AuditLog::record(AuditAction::PurchaseOrderCancelled, $this, ['note' => $reason], $by);
    }

    public function auditLabel(): string
    {
        return $this->number.': '.$this->vendor_name;
    }

    private function recordDecision(ApprovalStage $stage, ApprovalDecision $decision, PurchaseOrderStatus $next, User $by, ?string $note, AuditAction $action): void
    {
        DB::transaction(function () use ($stage, $decision, $next, $by, $note, $action): void {
            $this->approvals()->create([
                'stage' => $stage,
                'decision' => $decision,
                'note' => $note,
                'decided_by' => $by->id,
                'decided_at' => now(),
            ]);

            $this->forceFill(['status' => $next])->save();

            AuditLog::record($action, $this, array_filter([
                'decision_label' => $decision->label(),
                'note' => $note,
            ]), $by);
        });
    }
}
