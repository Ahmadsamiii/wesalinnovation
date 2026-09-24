<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * تبويب «السجلات والتدقيق»: كل ما سُجّل عبر AuditLog::record، للقراءة فقط.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $groups = collect(AuditAction::cases())
            ->mapWithKeys(fn (AuditAction $action): array => [$action->group() => AuditAction::groupLabel($action->group())])
            ->all();

        $filters = $request->validate([
            'group' => ['nullable', Rule::in(array_keys($groups))],
            'action' => ['nullable', Rule::enum(AuditAction::class)],
            'user' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $logs = AuditLog::query()
            ->with(['user', 'subject'])
            ->when($filters['action'] ?? null, fn (Builder $query, string $action) => $query->where('action', $action))
            ->when($filters['group'] ?? null, fn (Builder $query, string $group) => $query->where('action', 'like', $group.'.%'))
            ->when($filters['user'] ?? null, fn (Builder $query, string $user) => $query->where('user_id', $user))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->where('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->where('created_at', '<', Carbon::parse($to)->addDay()))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.audit.index', [
            'logs' => $logs,
            'filters' => $filters,
            'groups' => $groups,
            'actionOptions' => collect(AuditAction::cases())->mapWithKeys(fn (AuditAction $action): array => [$action->value => $action->label()])->all(),
            'userOptions' => User::query()->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }
}
