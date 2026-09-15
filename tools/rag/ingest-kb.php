<?php
/* ==========================================================================
 *  وصال — تلقيم قاعدة معرفة RAG من مخرجات دفتر جمع المصادر
 *
 *  الاستخدام:
 *      php tools/rag/ingest-kb.php /path/to/disability_sources
 *
 *  يتوقّع مجلداً فيه ملفات JSON بصيغة {url, title, text} (نفسها التي ينتجها
 *  tools/rag/collect-disability-sources.ipynb). لكل ملف: يقطّع النص، يحسب
 *  متجه تضمين لكل مقطع عبر Gemini، ويخزّنها في kb_chunks. إعادة تشغيله على
 *  نفس الرابط يستبدل مقاطعه القديمة بدل تكرارها.
 * ========================================================================== */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("هذا السكربت يُشغَّل من الطرفية فقط.\n");
}

$cfg = __DIR__ . '/../../api/config.php';
if (!file_exists($cfg)) {
    exit("لم أجد api/config.php — انسخ api/config.example.php إليه واملأ بياناته أولاً.\n");
}
require_once __DIR__ . '/../../api/db.php';
require_once __DIR__ . '/../../api/rag.php';
ensureSchema();

$dir = $argv[1] ?? null;
if (!$dir || !is_dir($dir)) {
    exit("مرّر مسار المجلد الذي يحوي ملفات JSON:\n  php tools/rag/ingest-kb.php /path/to/disability_sources\n");
}
if (!defined('GEMINI_KEY') || !GEMINI_KEY) {
    exit("GEMINI_KEY غير مضبوط في config.php — التضمين يحتاجه (اقرأ ملاحظة المزوّد في config.example.php).\n");
}

/** يقطّع نصاً طويلاً إلى مقاطع بحجم معقول لسياق نموذج اللغة، بتراكب بسيط. */
function chunkText(string $text, int $maxChars = 900, int $overlap = 120): array
{
    $paras = preg_split('/\n{2,}/u', trim($text));
    $chunks = [];
    $current = '';
    foreach ($paras as $p) {
        $p = trim($p);
        if ($p === '') continue;
        if (mb_strlen($current) + mb_strlen($p) + 1 > $maxChars && $current !== '') {
            $chunks[] = $current;
            $current = mb_substr($current, max(0, mb_strlen($current) - $overlap));
        }
        $current .= ($current === '' ? '' : "\n") . $p;
    }
    if (trim($current) !== '') $chunks[] = $current;
    return $chunks;
}

$files = glob(rtrim($dir, '/') . '/*.json');
if (!$files) {
    exit("لا ملفات JSON في: $dir\n");
}

$totalChunks = 0;
$totalDocs = 0;
$failed = 0;

foreach ($files as $file) {
    $data = json_decode((string) file_get_contents($file), true);
    if (!is_array($data) || empty($data['ok']) || empty($data['text'])) {
        echo "  تخطّي (بلا نص صالح): " . basename($file) . "\n";
        continue;
    }
    $url = (string) $data['url'];
    $title = $data['title'] ?? null;
    $chunks = chunkText((string) $data['text']);
    if (!$chunks) continue;

    db()->prepare('DELETE FROM kb_chunks WHERE source_url = ?')->execute([$url]);

    $ins = db()->prepare('INSERT INTO kb_chunks (source_url, source_title, chunk_index, chunk_text, embedding, created_at)
                           VALUES (?,?,?,?,?,NOW())');
    $ok = 0;
    foreach ($chunks as $i => $chunk) {
        $vec = ragEmbed($chunk, 'RETRIEVAL_DOCUMENT');
        if ($vec === null) {
            echo "    ✗ فشل تضمين مقطع " . ($i + 1) . " من $url\n";
            continue;
        }
        $ins->execute([$url, $title, $i, $chunk, json_encode($vec)]);
        $ok++;
    }
    echo "✓ $url — $ok/" . count($chunks) . " مقطعاً\n";
    $totalChunks += $ok;
    $totalDocs++;
    if ($ok < count($chunks)) $failed += (count($chunks) - $ok);
}

echo "\nتم: $totalDocs وثيقة، $totalChunks مقطعاً مُضمَّناً";
if ($failed) echo "، وفشل تضمين $failed مقطعاً (راجع الرسائل أعلاه)";
echo ".\n";
