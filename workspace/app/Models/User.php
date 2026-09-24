<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Enums\AuditAction;
use App\Notifications\AccountInvitation;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;
use Throwable;

#[Fillable(['name', 'email', 'password', 'department', 'job_title', 'phone', 'joined_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * صلاحية رابط الدعوة محسوبة من لحظة إرسالها، لا من لحظة توليد الرابط:
     * عرض الرابط مجدداً لا يمدّ عمره.
     */
    public const INVITATION_VALID_DAYS = 7;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'joined_at' => 'date',
            'invited_at' => 'datetime',
            'invitation_accepted_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * مفتاح دور المستخدم. كل حساب بدور واحد يسنده مدير النظام.
     */
    public function roleName(): ?string
    {
        return $this->roles->first()?->name;
    }

    /**
     * يسند دوراً واحداً بدل أي دور سابق. الدور يُنشأ عند الحاجة من مفتاحه في
     * config/roles.php (المصدر الوحيد للأدوار)، فلا يتعطّل إنشاء الحسابات إن
     * لم تُشغَّل بذرة الأدوار على قاعدة جديدة.
     */
    public function assignSingleRole(string $role): void
    {
        abort_unless(array_key_exists($role, config('roles')), 422);

        $this->syncRoles([Role::findOrCreate($role, 'web')]);
    }

    public function roleLabel(): ?string
    {
        $role = $this->roleName();

        return $role ? config("roles.{$role}.label") : null;
    }

    /**
     * تبويبات دور المستخدم من config/roles.php مع أنماط المسارات التي تُبرزها.
     *
     * @return array<string, array{label: string, route: string, active: list<string>}>
     */
    public function roleTabs(): array
    {
        $role = $this->roleName();

        if ($role === null) {
            return [];
        }

        return collect(config("roles.{$role}.tabs", []))
            ->map(fn (array $tab): array => [
                'label' => $tab['label'],
                'route' => $tab['route'],
                'active' => $tab['active'] ?? [
                    Str::endsWith($tab['route'], '.index')
                        ? Str::beforeLast($tab['route'], '.index').'.*'
                        : $tab['route'],
                ],
            ])
            ->all();
    }

    /**
     * رابط موقّع لتعيين كلمة المرور. يحمل وقت الدعوة، فإعادة الإرسال (التي
     * تغيّره) تُبطل كل رابط سابق.
     */
    public function invitationUrl(): string
    {
        // توقيع نسبي: لا يتأثر بالبروتوكول أو النطاق الذي يراه الخادم خلف
        // وسيط (Cloudflare مثلاً)، فلا يُرفض رابط سليم لأن الخادم رآه http.
        return url(URL::temporarySignedRoute(
            'invitation.show',
            $this->invited_at->copy()->addDays(self::INVITATION_VALID_DAYS),
            ['user' => $this->getKey(), 'invited' => $this->invited_at->getTimestamp()],
            absolute: false,
        ));
    }

    /**
     * يجدّد وقت الدعوة (فيُبطل روابطها السابقة) ويرسلها. فشل البريد لا يُسقط
     * العملية: الحساب يبقى، ويظهر للمدير رابط الدعوة ليرسله بنفسه.
     */
    public function sendInvitation(): bool
    {
        $this->forceFill(['invited_at' => now()])->save();

        try {
            $this->notify(new AccountInvitation);
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }

        AuditLog::record(AuditAction::InvitationSent, $this, ['email' => $this->email]);

        return true;
    }

    public function isDeactivated(): bool
    {
        return $this->deactivated_at !== null;
    }

    public function status(): AccountStatus
    {
        return match (true) {
            $this->isDeactivated() => AccountStatus::Deactivated,
            $this->invitation_accepted_at === null => AccountStatus::Pending,
            default => AccountStatus::Active,
        };
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('deactivated_at');
    }

    /**
     * من يستطيع الدخول فعلاً: غير موقوف وقبِل دعوته.
     *
     * @param  Builder<User>  $query
     */
    public function scopeCanSignIn(Builder $query): void
    {
        $query->whereNull('deactivated_at')->whereNotNull('invitation_accepted_at');
    }

    /**
     * إيقاف آخر مدير نظام قادر على الدخول، أو تغيير دوره، يقفل إدارة الحسابات
     * على نفسها بلا أي طريق للعودة من داخل النظام.
     */
    public function isLastActiveSysadmin(): bool
    {
        return $this->hasRole('sysadmin')
            && ! static::role('sysadmin')->canSignIn()->whereKeyNot($this->getKey())->exists();
    }

    public function auditLabel(): string
    {
        return $this->name;
    }
}
