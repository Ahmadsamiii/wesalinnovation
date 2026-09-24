<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Component;

class RoleTabs extends Component
{
    /**
     * تبويبات دور المستخدم الحالي جاهزة للعرض. تبويب لم يُبنَ مساره بعد يشير
     * إلى صفحة «قيد البناء» بدل أن يختفي، فيعرف المستخدم أنه قادم.
     *
     * @var list<array{key: string, label: string, url: string, active: bool}>
     */
    public array $tabs;

    public function __construct(Request $request)
    {
        $this->tabs = collect($request->user()?->roleTabs() ?? [])
            ->map(function (array $tab, string $key) use ($request): array {
                $isBuilt = Route::has($tab['route']);

                return [
                    'key' => $key,
                    'label' => $tab['label'],
                    'url' => $isBuilt ? route($tab['route']) : route('sections.show', $key),
                    'active' => $isBuilt
                        ? $request->routeIs(...$tab['active'])
                        : $request->routeIs('sections.show') && $request->route('tab') === $key,
                ];
            })
            ->values()
            ->all();
    }

    public function render(): View|Closure|string
    {
        return view('components.role-tabs');
    }
}
