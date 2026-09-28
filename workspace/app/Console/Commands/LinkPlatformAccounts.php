<?php

namespace App\Console\Commands;

use App\Support\AccountLinker;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;

/**
 * ربط حسابات مساحة العمل بحسابات المنصة العامة قبل تشغيل الدخول الموحد.
 * ثلاث خطوات لا يُتجاوز أي منها: تقرير جاف يراجعه صاحب القرار، ثم تنفيذ على
 * الخطة نفسها بعد نسخ احتياطي للقاعدتين، ثم تراجع عند الحاجة.
 */
#[Signature('platform:link-accounts
    {--apply : تنفيذ الخطة التي روجعت، وبدونه تقرير جاف لا يغيّر شيئاً}
    {--plan= : ملف الخطة التي روجعت (يلزم مع --apply)}
    {--undo= : معرّف دفعة يُتراجَع عنها}
    {--allow-admin-promotion : موافقة صريحة على منح مدير النظام دور مدير في المنصة}
    {--plan-dir= : مجلد حفظ الخطط}
    {--backup-dir= : مجلد النسخ الاحتياطية}
    {--skip-backup : بلا نسخ احتياطي (للتجارب المحلية فقط)}')]
#[Description('ربط حسابات مساحة العمل بحسابات المنصة: تقرير جاف، ثم تنفيذ بعد نسخ احتياطي، ثم تراجع')]
class LinkPlatformAccounts extends Command
{
    private const REASONS = [
        'matched_email' => 'يُربط بحساب المنصة ذي البريد نفسه',
        'matched_email_history_only' => 'موقوف هنا: يُربط للتاريخ فقط بلا دور',
        'no_platform_account' => 'يُنشأ له حساب في المنصة بكلمة مروره الحالية',
        'pending_invitation' => 'دعوته لم تُقبل: يُدعى من المنصة بعد الربط',
        'no_role' => 'بلا دور: لا يُربط',
        'already_linked' => 'مربوط سابقاً',
        'needs_admin_promotion' => 'يحتاج موافقتك على منحه مدير نظام في المنصة',
        'platform_admin_with_other_local_role' => 'مدير نظام في المنصة ودوره هنا مختلف: قرّر أي الدورين',
        'platform_account_suspended' => 'حسابه موقوف في المنصة',
        'platform_account_linked_to_another' => 'حساب المنصة هذا مربوط بحساب آخر',
        'platform_org_role_differs' => 'دوره في المنصة يخالف دوره هنا',
    ];

    private const ACTIONS = ['link' => 'ربط', 'create' => 'إنشاء', 'skip' => 'تجاوز', 'conflict' => 'تعارض', 'done' => 'تم'];

    public function handle(AccountLinker $linker): int
    {
        if (! $this->platformIsReady()) {
            return self::FAILURE;
        }

        if ($batch = $this->option('undo')) {
            return $this->undo($linker, (string) $batch);
        }

        $plan = $linker->plan((bool) $this->option('allow-admin-promotion'));

        return $this->option('apply') ? $this->apply($linker, $plan) : $this->report($plan);
    }

    private function platformIsReady(): bool
    {
        if (blank(config('database.connections.platform.database'))) {
            $this->error('قاعدة المنصة غير مضبوطة. اضبط PLATFORM_DB_* في .env أولاً (DEPLOY.md).');

            return false;
        }

        $schema = Schema::connection('platform');
        if (! $schema->hasColumn('users', 'org_role') || ! $schema->hasTable('account_links')) {
            $this->error('قاعدة المنصة لم تُرقَّ بعد. افتح أي صفحة من المنصة مرة واحدة بعد نشرها، ثم أعد الأمر.');

            return false;
        }

        return true;
    }

    /**
     * @param  array{id: string, items: list<array<string, mixed>>, summary: array<string, int>, blockers: list<string>}  $plan
     */
    private function report(array $plan): int
    {
        $s = $plan['summary'];
        $this->newLine();
        $this->line('<options=bold>تقرير الربط (لم يتغير شيء)</options>');
        $this->table(['البند', 'العدد'], [
            ['كل حسابات مساحة العمل', $s['total']],
            ['تُربط بحساب موجود في المنصة', $s['link']],
            ['يُنشأ لها حساب في المنصة', $s['create']],
            ['تُتجاوز (دعوة لم تُقبل أو بلا دور)', $s['skip']],
            ['تعارضات تنتظر قرارك', $s['conflict']],
            ['مربوطة سابقاً', $s['done']],
            ['تُمنح مدير نظام في المنصة', $s['promotes_admin']],
            ['جوال لم ينتقل (غير صالح أو مكرر)', $s['phone_dropped']],
        ]);

        $rows = array_map(fn (array $i): array => [
            $i['ws_id'], $i['email'], $i['role'] ?? '-', self::ACTIONS[$i['action']] ?? $i['action'],
            self::REASONS[$i['reason']] ?? $i['reason'],
        ], $plan['items']);
        $this->table(['رقم', 'البريد', 'الدور', 'الإجراء', 'السبب'], $rows);

        foreach ($plan['blockers'] as $blocker) {
            $this->warn($blocker === 'no_linked_sysadmin'
                ? 'لن يبقى مدير نظام مربوط بحساب مدير في المنصة، فلن يُنفَّذ الربط. راجع التعارضات وموافقة الترقية.'
                : $blocker);
        }

        $dir = $this->option('plan-dir') ?: storage_path('app/private/link-plans');
        File::ensureDirectoryExists($dir);
        $file = $dir.'/plan-'.now()->format('Ymd-His').'-'.substr($plan['id'], 0, 8).'.json';
        File::put($file, json_encode($plan, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $this->newLine();
        $this->info('حُفظت الخطة: '.$file);
        $this->line('بعد المراجعة وأخذ الموافقة، لتنفيذ هذه الخطة نفسها:');
        $this->line('  php artisan platform:link-accounts --apply --plan='.$file.($s['promotes_admin'] > 0 ? ' --allow-admin-promotion' : ''));

        return self::SUCCESS;
    }

    /**
     * @param  array{id: string, items: list<array<string, mixed>>, summary: array<string, int>, blockers: list<string>}  $plan
     */
    private function apply(AccountLinker $linker, array $plan): int
    {
        $file = (string) $this->option('plan');
        if ($file === '' || ! is_file($file)) {
            $this->error('التنفيذ يلزمه ملف الخطة التي روجعت: --plan=<الملف>. شغّل التقرير أولاً.');

            return self::FAILURE;
        }

        $reviewed = json_decode((string) file_get_contents($file), true);
        if (! is_array($reviewed) || ($reviewed['id'] ?? null) !== $plan['id']) {
            $this->error('البيانات تغيّرت منذ التقرير الذي روجع (أو الخطة بموافقة مختلفة). لا تنفيذ. أعد التقرير وراجعه.');

            return self::FAILURE;
        }

        if ($plan['blockers'] !== []) {
            $this->error('لن يبقى مدير نظام مربوط بحساب مدير في المنصة. لا تنفيذ.');

            return self::FAILURE;
        }

        if (! $this->option('skip-backup') && ! $this->backup()) {
            return self::FAILURE;
        }

        $batch = $linker->apply($plan);
        $s = $plan['summary'];

        $this->info("تم الربط. الدفعة: {$batch}");
        $this->line("  رُبط {$s['link']} وأُنشئ {$s['create']} وتُجوّز {$s['skip']} وتعارض {$s['conflict']}.");
        $this->line('للتراجع عن هذه الدفعة: php artisan platform:link-accounts --undo='.$batch);
        $this->line('الخطوة التالية: UNIFIED_AUTH=true في .env هنا، وUNIFIED_SESSION=true في api/config.php في المنصة.');

        return self::SUCCESS;
    }

    private function undo(AccountLinker $linker, string $batch): int
    {
        $result = $linker->undo($batch);
        $this->info("تُراجع عن الدفعة {$batch}: أُعيد {$result['restored']}، وحُذف {$result['deleted']}، وأُوقف {$result['suspended']}.");

        return self::SUCCESS;
    }

    /**
     * نسخة كاملة من القاعدتين قبل أي تعديل. لا ربط بلا نسخة تُقرأ. مع غير MySQL
     * (الاختبارات) لا نسخ، وبلا mysqldump في الخادم يُرفض التنفيذ.
     */
    private function backup(): bool
    {
        $connections = ['workspace' => config('database.default'), 'platform' => 'platform'];
        if (collect($connections)->contains(fn (string $name): bool => config("database.connections.{$name}.driver") !== 'mysql')) {
            $this->warn('القاعدة ليست MySQL: لا نسخ احتياطي تلقائي.');

            return true;
        }

        $dir = $this->option('backup-dir') ?: storage_path('app/private/link-backups');
        File::ensureDirectoryExists($dir);
        $stamp = now()->format('Ymd-His');

        foreach ($connections as $label => $name) {
            $c = config("database.connections.{$name}");
            $file = "{$dir}/link-{$stamp}-{$label}.sql.gz";
            $process = Process::fromShellCommandline(
                'mysqldump --single-transaction --skip-lock-tables -h "$B_HOST" -P "$B_PORT" -u "$B_USER" "$B_DB" | gzip > "$B_FILE"',
                null,
                ['B_HOST' => $c['host'], 'B_PORT' => $c['port'], 'B_USER' => $c['username'], 'B_DB' => $c['database'], 'B_FILE' => $file, 'MYSQL_PWD' => (string) $c['password']],
                timeout: 600,
            );
            $process->run();

            if (! $process->isSuccessful() || ! is_file($file) || filesize($file) < 200) {
                $this->error("تعذّر النسخ الاحتياطي لقاعدة {$label}: ".trim($process->getErrorOutput() ?: 'ملف فارغ').'. لا تنفيذ.');

                return false;
            }

            $this->line('  نسخة '.$label.': '.$file.' ('.round(filesize($file) / 1024).' ك.ب)');
        }

        return true;
    }
}
