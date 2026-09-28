<?php

namespace App\Http\Controllers\Hr;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\UpdateEmployeeRequest;
use App\Models\AuditLog;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * «الموظفون» لمدير الموارد البشرية: كل الحسابات غير العملاء، وتعديل القسم
 * والمسمى الوظيفي وتاريخ الانضمام وحدها. مدير النظام يقرأ القائمة فقط.
 */
class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $roles = collect(config('roles'))->except('client')->map(fn (array $role): string => $role['label'])->all();

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in(array_keys($roles))],
        ]);

        $employees = User::query()
            ->with('roles')
            ->when(
                $filters['role'] ?? null,
                fn (Builder $query, string $role) => $query->withRole($role),
                fn (Builder $query) => $query->employees(),
            )
            ->when($filters['q'] ?? null, fn (Builder $query, string $search) => $query->where('name', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('hr.employees.index', [
            'employees' => $employees,
            'filters' => $filters,
            'roles' => $roles,
            'canEdit' => $request->user()->hasRole('hr'),
        ]);
    }

    public function edit(User $user): View
    {
        abort_unless($user->hasAnyRole(User::employeeRoles()), 404);

        return view('hr.employees.edit', ['employee' => $user->load('roles')]);
    }

    public function update(UpdateEmployeeRequest $request, User $user): RedirectResponse
    {
        $user->fill($request->validated());

        $changes = [];
        foreach ($user->getDirty() as $field => $value) {
            $changes[$field] = ['from' => $this->plain($user->getOriginal($field)), 'to' => $this->plain($user->{$field})];
        }

        $user->save();

        if ($changes !== []) {
            AuditLog::record(AuditAction::EmployeeUpdated, $user, ['fields' => array_keys($changes), 'changes' => $changes]);
        }

        return redirect()->route('hr.employees')->with('status', "حُفظت البيانات الوظيفية لـ {$user->name}.");
    }

    /**
     * قيمة تُحفظ في سجل التدقيق نصاً: التاريخ بصيغة Y-m-d.
     */
    private function plain(mixed $value): mixed
    {
        return $value instanceof CarbonInterface ? $value->toDateString() : $value;
    }
}
