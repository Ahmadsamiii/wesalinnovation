<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * «العملاء» لمدير علاقات العملاء: حسابات العملاء ومشاريعهم، للقراءة فقط.
 * مدير النظام يقرأ الصفحة أيضاً.
 */
class ClientController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        $clients = User::query()
            ->withRole('client')
            ->with(['clientProjects' => fn (HasMany $query) => $query->orderBy('name')])
            ->when($filters['q'] ?? null, function (Builder $query, string $search): void {
                $pattern = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn (Builder $query) => $query->where('name', 'like', $pattern)->orWhere('email', 'like', $pattern));
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('crm.clients', ['clients' => $clients, 'filters' => $filters]);
    }
}
