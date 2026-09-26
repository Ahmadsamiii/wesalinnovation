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
 * tools/rag/ingest-kb.php في مستودع المنصة ({url, ref, title, text, ok}): ملف لكل
 * محتوى. url صفحته العامة المعتمدة وهي هويته في قاعدة المعرفة، وref مرجعه الرسمي
 * (source_url) إن وُجد، فتُنسب الإجابة المبنية عليه إلى جهة المرجع تحت الإجابة
 * (وزارة الصحة مثلاً) لا إلى مساحة العمل.
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

            $ref = (string) $content->source_url;

            File::put("{$directory}/wesal-content-{$content->id}.json", json_encode(array_filter([
                'url' => route('kb.show', $content),
                'ref' => preg_match('#^https?://#i', $ref) ? $ref : null,
                'title' => $content->published_title,
                'text' => $text,
                'ok' => true,
            ], fn ($value) => $value !== null), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
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
