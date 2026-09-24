<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * «صحة النظام»: فحوص حيّة لما يكسر مساحة العمل بصمت إن اختلّ — كل فحص يقول
 * ما وجده وما يُفعل إن لم يكن سليماً.
 */
class SystemHealthController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.system.health', [
            'checks' => array_values(array_filter([
                $this->database(),
                $this->migrations(),
                $this->storage(),
                $this->diskSpace(),
                $this->cache(),
                $this->mail(),
                $this->frontend(),
                $this->security(),
                $this->failedJobs(),
                $this->platform(),
            ])),
            'environment' => [
                'البيئة' => app()->environment(),
                'PHP' => PHP_VERSION,
                'Laravel' => app()->version(),
                'المنطقة الزمنية' => config('app.timezone'),
                'قاعدة البيانات' => config('database.default'),
                'وقت الخادم' => now()->translatedFormat('j F Y، H:i'),
            ],
            'recentErrors' => $this->recentErrors(),
        ]);
    }

    /**
     * @return array{label: string, status: string, detail: string}
     */
    private function check(string $label, string $status, string $detail): array
    {
        return ['label' => $label, 'status' => $status, 'detail' => $detail];
    }

    private function database(): array
    {
        try {
            DB::select('select 1');

            return $this->check('قاعدة البيانات', 'ok', 'متصلة ('.DB::connection()->getDriverName().').');
        } catch (Throwable $exception) {
            return $this->check('قاعدة البيانات', 'fail', 'تعذّر الاتصال: '.Str::limit($exception->getMessage(), 160));
        }
    }

    private function migrations(): array
    {
        $migrator = app('migrator');

        if (! $migrator->repositoryExists()) {
            return $this->check('ترحيلات قاعدة البيانات', 'fail', 'جدول الترحيلات غير موجود. شغّل: php artisan migrate --force');
        }

        $files = $migrator->getMigrationFiles([database_path('migrations'), ...$migrator->paths()]);
        $pending = array_diff(array_keys($files), $migrator->getRepository()->getRan());

        return $pending === []
            ? $this->check('ترحيلات قاعدة البيانات', 'ok', 'كلها منفّذة ('.count($files).').')
            : $this->check('ترحيلات قاعدة البيانات', 'fail', count($pending).' ترحيلاً لم يُنفَّذ. شغّل: php artisan migrate --force');
    }

    private function storage(): array
    {
        try {
            $probe = '.health-'.Str::random(8);
            Storage::disk('local')->put($probe, 'ok');
            Storage::disk('local')->delete($probe);
            $logsWritable = is_writable(storage_path('logs'));
        } catch (Throwable $exception) {
            return $this->check('مجلد التخزين', 'fail', 'المرفقات لا تُحفظ: '.Str::limit($exception->getMessage(), 160));
        }

        return $logsWritable
            ? $this->check('مجلد التخزين', 'ok', 'المرفقات والسجلات قابلة للكتابة.')
            : $this->check('مجلد التخزين', 'fail', 'مجلد السجلات storage/logs غير قابل للكتابة؛ الأخطاء لن تُسجَّل.');
    }

    private function diskSpace(): array
    {
        $free = @disk_free_space(storage_path());

        if ($free === false) {
            return $this->check('المساحة الحرة', 'warn', 'تعذّر قياسها على هذا الخادم.');
        }

        $gigabytes = number_format($free / 1024 ** 3, 1).' غيغابايت';

        return match (true) {
            $free < 500 * 1024 ** 2 => $this->check('المساحة الحرة', 'fail', "{$gigabytes} فقط؛ رفع المرفقات سيفشل قريباً."),
            $free < 2 * 1024 ** 3 => $this->check('المساحة الحرة', 'warn', "{$gigabytes}؛ راقبها أو وسّع المساحة."),
            default => $this->check('المساحة الحرة', 'ok', $gigabytes.'.'),
        };
    }

    private function cache(): array
    {
        try {
            $key = 'health:'.Str::random(8);
            Cache::put($key, 'ok', 10);
            $works = Cache::get($key) === 'ok';
            Cache::forget($key);
        } catch (Throwable $exception) {
            return $this->check('الذاكرة المؤقتة', 'fail', Str::limit($exception->getMessage(), 160));
        }

        return $works
            ? $this->check('الذاكرة المؤقتة', 'ok', 'تعمل ('.config('cache.default').').')
            : $this->check('الذاكرة المؤقتة', 'fail', 'ما يُكتب فيها لا يُقرأ؛ حدود الطلبات ورموز الاستعادة تتأثر.');
    }

    private function mail(): array
    {
        $mailer = (string) config('mail.default');

        if (in_array($mailer, ['log', 'array'], true)) {
            return $this->check('البريد', app()->isProduction() ? 'fail' : 'warn', "الرسائل تُكتب في السجل ({$mailer}) ولا تُرسل: الدعوات واستعادة كلمة المرور لن تصل. اضبط MAIL_MAILER=smtp.");
        }

        if (str_ends_with((string) config('mail.from.address'), '@example.com')) {
            return $this->check('البريد', 'warn', 'عنوان المرسل ما زال الافتراضي (example.com)؛ اضبط MAIL_FROM_ADDRESS.');
        }

        return $this->check('البريد', 'ok', "يُرسل عبر {$mailer}".(config("mail.mailers.{$mailer}.host") ? ' ('.config("mail.mailers.{$mailer}.host").')' : '').'. جرّبه من «تكامل البريد الإلكتروني».');
    }

    private function frontend(): array
    {
        return file_exists(public_path('build/manifest.json'))
            ? $this->check('ملفات الواجهة', 'ok', 'مبنية (public/build).')
            : $this->check('ملفات الواجهة', 'fail', 'غير مبنية؛ الصفحات بلا تنسيق. شغّل: npm ci && npm run build');
    }

    private function security(): array
    {
        $issues = [];

        if (app()->isProduction() && config('app.debug')) {
            $issues[] = 'APP_DEBUG مفعّل في الإنتاج ويكشف تفاصيل الأخطاء لأي زائر';
        }

        if (app()->isProduction() && ! str_starts_with((string) config('app.url'), 'https://')) {
            $issues[] = 'APP_URL لا يبدأ بـ https://، فروابط الدعوات والتحقق تُرسل بلا تشفير';
        }

        if (! app()->isProduction()) {
            return $this->check('إعدادات الأمان', 'warn', 'البيئة '.app()->environment().' لا production؛ طبيعي محلياً فقط.');
        }

        return $issues === []
            ? $this->check('إعدادات الأمان', 'ok', 'وضع الإنتاج، بلا وضع تطوير، وبرابط https.')
            : $this->check('إعدادات الأمان', 'fail', implode('؛ ', $issues).'.');
    }

    private function failedJobs(): ?array
    {
        if (! Schema::hasTable('failed_jobs')) {
            return null;
        }

        $failed = DB::table('failed_jobs')->count();

        return $failed === 0
            ? $this->check('المهام الخلفية', 'ok', 'لا مهام فاشلة.')
            : $this->check('المهام الخلفية', 'warn', "{$failed} مهمة فاشلة. راجعها: php artisan queue:failed");
    }

    private function platform(): array
    {
        if (! ChatLog::isConfigured()) {
            return $this->check('قاعدة المنصة العامة', 'off', 'غير مربوطة (اختيارية): تُفعّل إحصاءات الذكاء الاصطناعي وتنبيهات الأسئلة الحساسة.');
        }

        try {
            $today = ChatLog::query()->where('created_at', '>=', now()->subDay())->count();

            return $this->check('قاعدة المنصة العامة', 'ok', "متصلة للقراءة؛ {$today} سؤالاً خلال ٢٤ ساعة.");
        } catch (Throwable $exception) {
            return $this->check('قاعدة المنصة العامة', 'fail', 'مضبوطة لكن تعذّرت القراءة: '.Str::limit($exception->getMessage(), 160));
        }
    }

    /**
     * آخر أخطاء السجل (السطر الأول من كل خطأ فقط).
     *
     * @return list<array{time: string, message: string}>
     */
    private function recentErrors(): array
    {
        $logs = glob(storage_path('logs/*.log')) ?: [];

        if ($logs === []) {
            return [];
        }

        usort($logs, fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));
        $file = $logs[0];
        $size = filesize($file);
        $handle = fopen($file, 'r');
        fseek($handle, max(0, $size - 256 * 1024));
        $tail = stream_get_contents($handle);
        fclose($handle);

        preg_match_all('/^\[(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2})[^\]]*\] \w+\.(?:ERROR|CRITICAL|ALERT|EMERGENCY): (.+)$/m', (string) $tail, $matches, PREG_SET_ORDER);

        return collect($matches)
            ->take(-10)
            ->reverse()
            ->map(fn (array $match): array => ['time' => $match[1], 'message' => Str::limit($match[2], 220)])
            ->values()
            ->all();
    }
}
