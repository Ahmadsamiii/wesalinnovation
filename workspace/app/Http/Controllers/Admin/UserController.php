<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountStatus;
use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * إدارة الحسابات — تبويب «الأدوار والصلاحيات» لمدير النظام. لا تسجيل ذاتي في
 * النظام، فهذه الصفحة هي الطريق الوحيد لدخول أي شخص.
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in(array_keys(config('roles')))],
            'status' => ['nullable', Rule::enum(AccountStatus::class)],
        ]);

        $users = User::query()
            ->with('roles')
            ->when($filters['q'] ?? null, function (Builder $query, string $search): void {
                $pattern = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn (Builder $query) => $query->where('name', 'like', $pattern)->orWhere('email', 'like', $pattern));
            })
            ->when($filters['role'] ?? null, fn (Builder $query, string $role) => $query->withRole($role))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => match (AccountStatus::from($status)) {
                AccountStatus::Deactivated => $query->whereNotNull('deactivated_at'),
                AccountStatus::Pending => $query->whereNull('deactivated_at')->whereNull('invitation_accepted_at'),
                AccountStatus::Active => $query->canSignIn(),
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $roleCounts = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', (new User)->getMorphClass())
            ->groupBy('roles.name')
            ->selectRaw('roles.name as role, count(*) as total')
            ->pluck('total', 'role');

        return view('admin.users.index', [
            'users' => $users,
            'filters' => $filters,
            'roleOptions' => $this->roleOptions(),
            'roleCounts' => $roleCounts,
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', ['roleOptions' => $this->roleOptions()]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data): User {
            // كلمة مرور عشوائية لا يعرفها أحد: الحساب لا يُدخَل حتى يعيّن
            // صاحبه كلمة مروره من رابط الدعوة.
            $user = User::create([...Arr::except($data, 'role'), 'password' => Str::password(40)]);
            $user->assignSingleRole($data['role']);

            AuditLog::record(AuditAction::UserCreated, $user, ['role' => $data['role']]);

            return $user;
        });

        return $user->sendInvitation()
            ? redirect()->route('users.edit', $user)->with('status', "أُنشئ الحساب وأُرسلت الدعوة إلى {$user->email}.")
            : redirect()->route('users.edit', $user)->with('error', 'أُنشئ الحساب لكن تعذّر إرسال الدعوة بالبريد. انسخ رابط الدعوة أدناه وأرسله بنفسك، أو أعد المحاولة.');
    }

    public function edit(User $user): View
    {
        $user->load('roles');

        return view('admin.users.edit', [
            'user' => $user,
            'roleOptions' => $this->roleOptions(),
            'activity' => AuditLog::query()
                ->whereMorphedTo('subject', $user)
                ->with('user')
                ->latest('id')
                ->limit(15)
                ->get(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $previousRole = $user->roleName();

        $user->fill(Arr::except($data, 'role'));
        $changedFields = array_keys($user->getDirty());
        $emailChanged = $user->isDirty('email');

        DB::transaction(function () use ($user, $data, $previousRole, $changedFields): void {
            $user->save();

            if ($changedFields !== []) {
                AuditLog::record(AuditAction::UserUpdated, $user, ['fields' => $changedFields]);
            }

            if ($data['role'] !== $previousRole) {
                $user->assignSingleRole($data['role']);
                AuditLog::record(AuditAction::UserRoleChanged, $user, ['from' => $previousRole, 'to' => $data['role']]);
            }
        });

        // دعوة معلّقة أُرسلت لبريد خاطئ: تُرسل للبريد المصحَّح ويبطل الرابط القديم.
        if ($emailChanged && $user->status() === AccountStatus::Pending && ! $user->sendInvitation()) {
            return redirect()->route('users.edit', $user)->with('error', 'حُفظت التعديلات لكن تعذّر إرسال الدعوة للبريد الجديد.');
        }

        return redirect()->route('users.edit', $user)->with('status', 'حُفظت التعديلات.');
    }

    /**
     * @return array<string, string>
     */
    private function roleOptions(): array
    {
        return collect(config('roles'))->map(fn (array $role): string => $role['label'])->all();
    }
}
