<?php

namespace App\Models;

use App\Enums\AuditAction;
use App\Enums\ContractStatus;
use App\Enums\InvoiceStatus;
use Database\Factories\ContractFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use LogicException;

#[Fillable(['project_id', 'title', 'value', 'start_date', 'end_date', 'notes'])]
class Contract extends Model
{
    /** @use HasFactory<ContractFactory> */
    use HasFactory;

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
            'status' => ContractStatus::class,
            'value' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'signed_on' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Contract $contract): void {
            $contract->number ??= Sequence::next('CT');
            // العميل لحظة التعاقد؛ تغيير عميل المشروع لاحقاً لا يغيّر طرف العقد.
            $contract->client_id ??= $contract->project?->client_id;
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
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest('id');
    }

    /**
     * التنفيذي والمالي يريان الكل، ومدير المشروع عقود مشاريعه، والعميل عقوده
     * بعد توقيعها (المسودة شأن داخلي).
     *
     * @param  Builder<Contract>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->hasAnyRole(['executive', 'finance'])) {
            return;
        }

        if ($user->hasRole('client')) {
            $query->where('client_id', $user->id)->where('status', '!=', ContractStatus::Draft);

            return;
        }

        $query->whereHas('project', fn (Builder $query) => $query->where('pm_id', $user->id));
    }

    /**
     * مجموع الفواتير الصادرة على العقد (غير الملغاة ولا المسودات).
     */
    public function invoicedTotal(): string
    {
        return (string) $this->invoices()->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::Paid])->sum('total');
    }

    public function activate(User $by, Carbon $signedOn): void
    {
        throw_unless($this->status === ContractStatus::Draft, LogicException::class, 'Only a draft contract can be activated.');

        $this->forceFill(['status' => ContractStatus::Active, 'signed_on' => $signedOn])->save();

        AuditLog::record(AuditAction::ContractActivated, $this, actor: $by);
    }

    public function close(ContractStatus $status, User $by, ?string $reason = null): void
    {
        throw_unless($this->status === ContractStatus::Active, LogicException::class, 'Only an active contract can be closed.');
        throw_unless(in_array($status, [ContractStatus::Completed, ContractStatus::Terminated], true), LogicException::class, 'Invalid closing status.');

        $this->forceFill(['status' => $status])->save();

        AuditLog::record(AuditAction::ContractClosed, $this, array_filter(['note' => trim($status->label().($reason ? ': '.$reason : ''))]), $by);
    }

    public function auditLabel(): string
    {
        return $this->number.' — '.$this->title;
    }
}
