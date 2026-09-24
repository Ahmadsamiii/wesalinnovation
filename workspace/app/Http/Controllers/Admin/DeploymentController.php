<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * «النطاقات والنشر»: أين تعمل مساحة العمل، وأي إصدار منشور، وحالة ما يحتاجه
 * الإنتاج. الإصدار يكتبه سكربت النشر في storage/app/release.json.
 */
class DeploymentController extends Controller
{
    public function __invoke(Request $request): View
    {
        $release = null;
        $releaseFile = storage_path('app/release.json');

        if (is_file($releaseFile)) {
            $data = json_decode((string) file_get_contents($releaseFile), true);

            if (is_array($data)) {
                $release = [
                    'commit' => isset($data['commit']) ? substr((string) $data['commit'], 0, 12) : null,
                    'branch' => $data['branch'] ?? null,
                    'deployed_at' => isset($data['deployed_at']) ? Carbon::parse($data['deployed_at']) : null,
                ];
            }
        }

        return view('admin.system.deployment', [
            'appUrl' => config('app.url'),
            'host' => $request->getHost(),
            'secure' => $request->isSecure(),
            'release' => $release,
            'checklist' => [
                ['label' => 'البيئة production', 'done' => app()->isProduction()],
                ['label' => 'وضع التطوير مطفأ (APP_DEBUG=false)', 'done' => ! config('app.debug')],
                ['label' => 'الرابط الأساسي https', 'done' => str_starts_with((string) config('app.url'), 'https://')],
                ['label' => 'الطلب الحالي وصل عبر https', 'done' => $request->isSecure()],
                ['label' => 'البريد يُرسل فعلاً (لا log)', 'done' => ! in_array(config('mail.default'), ['log', 'array'], true)],
                ['label' => 'ملفات الواجهة مبنية', 'done' => file_exists(public_path('build/manifest.json'))],
                ['label' => 'الإعدادات مخزّنة (config:cache)', 'done' => app()->configurationIsCached()],
                ['label' => 'المسارات مخزّنة (route:cache)', 'done' => app()->routesAreCached()],
            ],
        ]);
    }
}
