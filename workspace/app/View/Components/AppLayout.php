<?php

namespace App\View\Components;

use App\Models\HiringRequest;
use App\Models\ReferenceLetter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * هيكل الصفحات: القائمة الجانبية بتبويبات دور المستخدم (config/roles.php)،
 * والخدمات التي ليست تبويباً لدوره، وعنوان القسم الحالي في الشريط العلوي.
 */
class AppLayout extends Component
{
    /**
     * current: «page» على صفحة التبويب نفسها، و«true» داخل قسمه (صفحة مشروع
     * تحت «مشاريعي» مثلاً)، فلا تُعلن قارئات الشاشة صفحتين حاليتين.
     *
     * @var list<array{key: string, label: string, url: string, active: bool, current: ?string}>
     */
    public array $tabs;

    /**
     * خدمات يصل إليها الدور خارج تبويباته (طلب إفادة، طلبات التوظيف).
     *
     * @var list<array{key: string, label: string, url: string, active: bool, current: ?string}>
     */
    public array $services;

    public ?string $section;

    public function __construct(Request $request)
    {
        $user = $request->user();
        $roleTabs = $user?->roleTabs() ?? [];

        // تبويب لم يُبنَ مساره بعد يشير إلى صفحة «قيد البناء» بدل أن يختفي.
        $this->tabs = collect($roleTabs)
            ->map(function (array $tab, string $key) use ($request): array {
                $isBuilt = Route::has($tab['route']);

                $active = $isBuilt
                    ? $request->routeIs(...$tab['active'])
                    : $request->routeIs('sections.show') && $request->route('tab') === $key;
                $onTabPage = $isBuilt ? $request->routeIs($tab['route']) : $active;

                return [
                    'key' => $key,
                    'label' => $tab['label'],
                    'url' => $isBuilt ? route($tab['route']) : route('sections.show', $key),
                    'active' => $active,
                    'current' => $active ? ($onTabPage ? 'page' : 'true') : null,
                ];
            })
            ->values()
            ->all();

        $tabRoutes = array_column($roleTabs, 'route');
        $this->services = $user === null ? [] : collect([
            ['key' => 'reference_request', 'label' => $user->hasRole('executive') ? 'طلبات الإفادة' : 'طلب إفادة', 'route' => 'reference-letters.index', 'allowed' => $user->can('viewAny', ReferenceLetter::class)],
            ['key' => 'hiring_requests', 'label' => 'طلبات التوظيف', 'route' => 'hiring-requests.index', 'allowed' => $user->can('viewAny', HiringRequest::class)],
        ])
            ->filter(fn (array $service): bool => $service['allowed'] && ! in_array($service['route'], $tabRoutes, true))
            ->map(function (array $service) use ($request): array {
                $active = $request->routeIs(str_replace('.index', '.*', $service['route']));

                return [
                    'key' => $service['key'],
                    'label' => $service['label'],
                    'url' => route($service['route']),
                    'active' => $active,
                    'current' => $active ? ($request->routeIs($service['route']) ? 'page' : 'true') : null,
                ];
            })
            ->values()
            ->all();

        $this->section = collect([...$this->tabs, ...$this->services])->firstWhere('active', true)['label']
            ?? ($request->routeIs('profile.*') ? 'الملف الشخصي' : null);
    }

    public function render(): View
    {
        return view('layouts.app');
    }
}
