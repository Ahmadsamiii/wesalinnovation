<?php

namespace App\Http\Controllers\Reports;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * «التقارير التقنية» لمدير النظام: الدخول والمحاولات الفاشلة، ومن يستخدم
 * النظام فعلاً، والحسابات الخاملة التي تبقى باباً مفتوحاً بلا حاجة.
 */
class TechnicalReportController extends Controller
{
    use BuildsMonthlySeries;

    /**
     * حساب لم يدخل منذ هذه المدة يُعرض للمراجعة (إيقافه أو التأكد من حاجته).
     */
    private const DORMANT_DAYS = 60;

    public function __invoke(Request $request): View
    {
        $period = $this->period($request);

        $authEvents = AuditLog::query()
            ->whereIn('action', [AuditAction::AuthLogin, AuditAction::AuthFailed])
            ->where('created_at', '>=', $period['from'])
            ->get(['action', 'user_id', 'created_at']);
        $logins = $authEvents->where('action', AuditAction::AuthLogin);

        $lastLogins = AuditLog::query()->toBase()
            ->where('action', AuditAction::AuthLogin->value)
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->selectRaw('user_id, max(created_at) as last_at')
            ->pluck('last_at', 'user_id');

        $accounts = User::query()->canSignIn()->with('roles')->orderBy('name')->get();

        return view('reports.technical', [
            'period' => $period,
            'kpis' => [
                'accounts' => $accounts->count(),
                'activeLast30' => $lastLogins->filter(fn (string $at): bool => Carbon::parse($at)->gte(now()->subDays(30)))->count(),
                'failedLogins' => $authEvents->where('action', AuditAction::AuthFailed)->count(),
                'pendingInvitations' => User::query()->active()->whereNull('invitation_accepted_at')->count(),
                'deactivated' => User::query()->whereNotNull('deactivated_at')->count(),
            ],
            'loginSeries' => $this->monthly($logins, $period['keys'], fn (AuditLog $log) => $log->created_at),
            'failedSeries' => $this->monthly($authEvents->where('action', AuditAction::AuthFailed), $period['keys'], fn (AuditLog $log) => $log->created_at),
            'byRole' => $accounts
                ->groupBy(fn (User $user): string => $user->roleName() ?? '')
                ->map(fn ($users, string $role): array => [
                    'label' => config("roles.{$role}.label", 'بلا دور'),
                    'value' => $users->count(),
                    'url' => $role === '' ? null : route('users.index', ['role' => $role]),
                ])
                ->sortByDesc('value')
                ->values()
                ->all(),
            'activity' => AuditLog::query()->toBase()
                ->where('created_at', '>=', $period['from'])
                ->pluck('action')
                ->countBy(fn (string $action): string => AuditAction::from($action)->group())
                ->map(fn (int $count, string $group): array => [
                    'label' => AuditAction::groupLabel($group),
                    'value' => $count,
                    'url' => route('audit.index', ['group' => $group]),
                ])
                ->sortByDesc('value')
                ->values()
                ->all(),
            'dormant' => $accounts
                ->map(fn (User $user): array => ['user' => $user, 'lastLogin' => isset($lastLogins[$user->id]) ? Carbon::parse($lastLogins[$user->id]) : null])
                ->filter(fn (array $row): bool => $row['lastLogin'] === null || $row['lastLogin']->lt(now()->subDays(self::DORMANT_DAYS)))
                ->sortBy(fn (array $row): int => $row['lastLogin']?->getTimestamp() ?? 0)
                ->values(),
            'dormantDays' => self::DORMANT_DAYS,
        ]);
    }
}
