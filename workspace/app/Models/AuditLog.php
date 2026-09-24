<?php

namespace App\Models;

use App\Enums\AuditAction;
use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['user_id', 'action', 'subject_type', 'subject_id', 'properties', 'ip_address'])]
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    /**
     * سجل إلحاقي: السطر لا يُعدَّل بعد كتابته.
     */
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * يسجّل إجراءً باسم المستخدم الحالي ما لم يُمرَّر منفّذ آخر.
     *
     * @param  array<string, mixed>  $properties
     */
    public static function record(AuditAction $action, ?Model $subject = null, array $properties = [], ?User $actor = null): self
    {
        // اسم الكيان وقت الإجراء يُحفظ مع السطر، فيبقى السجل مقروءاً حتى لو
        // حُذف الكيان أو تغيّر اسمه لاحقاً.
        if ($subject && method_exists($subject, 'auditLabel')) {
            $properties['subject_label'] ??= $subject->auditLabel();
        }

        return static::create([
            'user_id' => ($actor ?? auth()->user())?->getKey(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'properties' => $properties === [] ? null : $properties,
            'ip_address' => request()->ip(),
        ]);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * اسم الكيان المتأثر كما يُعرض في السجل، حتى لو حُذف الكيان لاحقاً.
     */
    public function subjectLabel(): ?string
    {
        if ($this->subject && method_exists($this->subject, 'auditLabel')) {
            return $this->subject->auditLabel();
        }

        return $this->properties['subject_label'] ?? null;
    }

    /**
     * تفاصيل مقروءة لما تغيّر، مشتقة من الخصائص المخزَّنة مع السطر.
     */
    public function details(): ?string
    {
        $properties = $this->properties ?? [];

        return match ($this->action) {
            AuditAction::UserRoleChanged => sprintf(
                'من «%s» إلى «%s»',
                self::roleLabel($properties['from'] ?? null),
                self::roleLabel($properties['to'] ?? null),
            ),
            AuditAction::UserUpdated => isset($properties['fields'])
                ? 'الحقول: '.implode('، ', array_map(
                    fn (string $field): string => __("validation.attributes.{$field}"),
                    $properties['fields'],
                ))
                : null,
            AuditAction::AuthFailed, AuditAction::InvitationSent => $properties['email'] ?? null,
            AuditAction::ProjectDecided, AuditAction::PurchaseOrderReviewed, AuditAction::PurchaseOrderDecided => trim(($properties['decision_label'] ?? '').(isset($properties['note']) ? ': '.$properties['note'] : '')),
            AuditAction::ProjectMemberAdded, AuditAction::ProjectMemberRemoved => $properties['member'] ?? null,
            AuditAction::AttachmentUploaded, AuditAction::AttachmentDeleted => $properties['file'] ?? null,
            default => $properties['note'] ?? null,
        };
    }

    private static function roleLabel(?string $role): string
    {
        return $role ? config("roles.{$role}.label", $role) : 'بلا دور';
    }
}
