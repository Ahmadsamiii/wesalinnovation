<?php

namespace App\Models;

use App\Enums\AuditAction;
use App\Enums\CertificateType;
use App\Models\Concerns\HasVerificationCode;
use Database\Factories\CertificateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['type', 'project_id', 'recipient_id', 'title', 'body'])]
class Certificate extends Model
{
    /** @use HasFactory<CertificateFactory> */
    use HasFactory, HasVerificationCode;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CertificateType::class,
            'issued_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Certificate $certificate): void {
            $certificate->number ??= Sequence::next('CERT');
            $certificate->verification_code ??= self::newVerificationCode();
            $certificate->issued_at ??= now();
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
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * التنفيذي يرى الكل، ومدير المشروع شهادات مشاريعه، وغيرهما ما صدر لهم.
     *
     * @param  Builder<Certificate>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->hasRole('executive')) {
            return;
        }

        $query->where(fn (Builder $query) => $query
            ->where('recipient_id', $user->id)
            ->orWhereHas('project', fn (Builder $query) => $query->where('pm_id', $user->id)));
    }

    /**
     * @param  Builder<Certificate>  $query
     */
    public function scopeValid(Builder $query): void
    {
        $query->whereNull('revoked_at');
    }

    public static function defaultTitle(CertificateType $type, Project $project): string
    {
        return $type->label().' «'.$project->name.'»';
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function revoke(User $by, string $reason): void
    {
        throw_if($this->isRevoked(), LogicException::class, 'Certificate already revoked.');

        $this->forceFill(['revoked_at' => now(), 'revoked_by' => $by->id, 'revocation_reason' => $reason])->save();

        AuditLog::record(AuditAction::CertificateRevoked, $this, ['note' => $reason], $by);
    }

    public function auditLabel(): string
    {
        return $this->number.($this->recipient ? ': '.$this->recipient->name : '');
    }
}
