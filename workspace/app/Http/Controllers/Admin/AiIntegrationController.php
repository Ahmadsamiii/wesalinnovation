<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * «تكامل الذكاء الاصطناعي»: كيف يعمل مساعد المنصة العامة فعلاً — أي مزوّد
 * يجيب، وبأي سرعة، وكم سؤالاً انقطع أو بقي بلا جواب — من سجل أسئلته.
 *
 * اختيار المزوّد ومفاتيحه في إعدادات خادم المنصة (api/config.php) عمداً:
 * الأسرار لا تنتقل إلى قاعدة مساحة العمل، والتبديل يبقى قراراً على الخادم.
 */
class AiIntegrationController extends Controller
{
    private const DAYS = 30;

    public function __invoke(): View
    {
        if (! ChatLog::isConfigured()) {
            return view('admin.system.ai', ['connected' => false]);
        }

        try {
            $since = now()->subDays(self::DAYS - 1)->startOfDay();
            $logs = ChatLog::query()->where('created_at', '>=', $since)->get(['provider', 'model', 'stream', 'ttfb_ms', 'total_ms', 'aborted', 'created_at']);
            $unanswered = ChatLog::query()->where('created_at', '>=', $since)->whereNull('answer')->count();
            $latest = ChatLog::query()->latest('created_at')->first(['provider', 'model', 'created_at']);
        } catch (Throwable $exception) {
            return view('admin.system.ai', ['connected' => true, 'error' => Str::limit($exception->getMessage(), 300)]);
        }

        $days = collect(range(self::DAYS - 1, 0))->map(fn (int $ago) => now()->subDays($ago));
        $perDay = $logs->countBy(fn (ChatLog $log): string => $log->created_at->toDateString());

        return view('admin.system.ai', [
            'connected' => true,
            'error' => null,
            'total' => $logs->count(),
            'unanswered' => $unanswered,
            'aborted' => $logs->where('aborted', true)->count(),
            'medianTtfb' => self::percentile($logs->where('stream', true)->pluck('ttfb_ms')->filter(), 0.5),
            'latest' => $latest,
            'providers' => $logs->groupBy(fn (ChatLog $log): string => $log->provider ?? '—')
                ->map(fn (Collection $group, string $provider): array => [
                    'provider' => $provider,
                    'models' => $group->pluck('model')->filter()->unique()->values()->all(),
                    'count' => $group->count(),
                    'share' => (int) round($group->count() * 100 / max(1, $logs->count())),
                    'median' => self::percentile($group->pluck('total_ms')->filter(), 0.5),
                    'p90' => self::percentile($group->pluck('total_ms')->filter(), 0.9),
                    'aborted' => $group->where('aborted', true)->count(),
                ])
                ->sortByDesc('count')
                ->values()
                ->all(),
            'dayLabels' => $days->map(fn ($day): string => $day->translatedFormat('j M'))->all(),
            'dayTitles' => $days->map(fn ($day): string => $day->translatedFormat('l j F'))->all(),
            'daySeries' => $days->map(fn ($day): int => $perDay[$day->toDateString()] ?? 0)->all(),
        ]);
    }

    /**
     * @param  Collection<int, int>  $values
     */
    private static function percentile(Collection $values, float $percentile): ?int
    {
        if ($values->isEmpty()) {
            return null;
        }

        $sorted = $values->sort()->values();

        return (int) $sorted[max(0, (int) ceil($percentile * $sorted->count()) - 1)];
    }
}
