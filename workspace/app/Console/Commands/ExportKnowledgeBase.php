<?php

namespace App\Console\Commands;

use App\Enums\HealthContentStatus;
use App\Models\HealthContent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * يصدّر المحتوى الصحي المعتمد ملفات JSON بالصيغة التي يقرؤها
 * tools/rag/ingest-kb.php في مستودع المنصة ({url, title, text, ok}): ملف لكل
 * محتوى، ورابطه صفحته العامة المعتمدة التي يستشهد بها المساعد.
 */
#[Signature('content:export-kb {directory : مجلد الإخراج (يُنشأ إن لم يوجد)}')]
#[Description('تصدير المحتوى الصحي المعتمد لقاعدة معرفة مساعد المنصة')]
class ExportKnowledgeBase extends Command
{
    public function handle(): int
    {
        $directory = rtrim((string) $this->argument('directory'), '/');
        File::ensureDirectoryExists($directory);

        $published = HealthContent::query()->published()->orderBy('id')->get();

        foreach ($published as $content) {
            $text = collect([$content->published_summary, $content->published_body])->filter()->implode("\n\n");

            File::put("{$directory}/wesal-content-{$content->id}.json", json_encode([
                'url' => route('kb.show', $content),
                'title' => $content->published_title,
                'text' => $text,
                'ok' => true,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        }

        $this->info("صُدّر {$published->count()} محتوى معتمداً إلى {$directory}");
        $this->line("التالي على خادم المنصة: php tools/rag/ingest-kb.php {$directory}");

        $withdrawn = HealthContent::query()->where('status', HealthContentStatus::Withdrawn)->get();

        if ($withdrawn->isNotEmpty()) {
            $this->newLine();
            $this->warn('محتوى مسحوب ما زال في قاعدة المعرفة إن أُدخل سابقاً؛ احذفه من قاعدة المنصة:');

            foreach ($withdrawn as $content) {
                $this->line("DELETE FROM kb_chunks WHERE source_url = '".route('kb.show', $content)."';");
            }
        }

        return self::SUCCESS;
    }
}
