<?php
/* ==========================================================================
 *  وصال — استرجاع من قاعدة المعرفة (RAG)
 *
 *  يحوّل سؤال المستخدم إلى متجه، يقارنه بمتجهات مقاطع kb_chunks المخزَّنة،
 *  ويرجّع أقرب مقاطع لتُحقن في توجيه النموذج مع مصدرها. هذا يعطي إجابات
 *  مستندة لنصوص رسمية فعلية بدل معرفة عامة قد تكون قديمة أو مختلَقة.
 *
 *  فشل أي خطوة هنا (لا مفتاح، انقطاع الشبكة، قاعدة معرفة فارغة) يجب أن يرجّع
 *  مصفوفة فارغة بصمت لا أن يكسر المحادثة — RAG تحسين، لا شرط لعمل الدردشة.
 * ========================================================================== */

const RAG_EMBED_MODEL = 'gemini-embedding-001';
const RAG_TOP_K = 4;
// أقل تشابه مقبول (جيب-كوساين، من ١- إلى ١). أقل من هذا يعني السؤال غالباً
// خارج قاعدة المعرفة أصلاً — عرض مقطع غير ذي صلة أسوأ من عدم عرض شيء.
const RAG_MIN_SCORE = 0.55;

/** استدعاء Gemini embedContent. يرجّع null بصمت عند أي فشل — لا يوقف الدردشة أبداً. */
function ragEmbed(string $text, string $taskType): ?array
{
    if (!defined('GEMINI_KEY') || !GEMINI_KEY) return null;
    $ch = curl_init(
        'https://generativelanguage.googleapis.com/v1beta/models/' . RAG_EMBED_MODEL
        . ':embedContent?key=' . GEMINI_KEY
    );
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'content'  => ['parts' => [['text' => mb_substr($text, 0, 8000)]]],
            'taskType' => $taskType,
        ], JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_TIMEOUT => 15,
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($res === false || $code >= 400) {
        error_log('WESAL_RAG_EMBED_FAIL: HTTP ' . $code . ' | ' . mb_substr((string) $res, 0, 200));
        return null;
    }
    $j = json_decode($res, true);
    $vals = $j['embedding']['values'] ?? null;
    return is_array($vals) ? $vals : null;
}

function ragCosine(array $a, array $b): float
{
    $dot = 0.0;
    $na = 0.0;
    $nb = 0.0;
    $n = min(count($a), count($b));
    for ($i = 0; $i < $n; $i++) {
        $dot += $a[$i] * $b[$i];
        $na += $a[$i] * $a[$i];
        $nb += $b[$i] * $b[$i];
    }
    if ($na <= 0 || $nb <= 0) return 0.0;
    return $dot / (sqrt($na) * sqrt($nb));
}

/**
 * أقرب مقاطع قاعدة المعرفة لسؤال المستخدم. ref رابط المرجع الرسمي الذي يُنسب
 * إليه المقطع إن اختلف عن صفحته (محتوى مساحة العمل الصحي)، وإلا null.
 * @return array<int, array{text:string, url:string, ref:?string, title:?string, score:float}>
 */
function ragRetrieve(string $query): array
{
    try {
        $qVec = ragEmbed($query, 'RETRIEVAL_QUERY');
        if ($qVec === null) return [];

        ensureSchema();   // عمود ref_url على قاعدة قديمة، قبل أن يسقط الاستعلام بلا أثر
        $rows = db()->query('SELECT chunk_text, source_url, ref_url, source_title, embedding FROM kb_chunks')->fetchAll();
        if (!$rows) return [];

        $scored = [];
        foreach ($rows as $r) {
            $vec = json_decode($r['embedding'], true);
            if (!is_array($vec)) continue;
            $score = ragCosine($qVec, $vec);
            if ($score >= RAG_MIN_SCORE) {
                $scored[] = ['text' => $r['chunk_text'], 'url' => $r['source_url'], 'ref' => $r['ref_url'],
                             'title' => $r['source_title'], 'score' => $score];
            }
        }
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($scored, 0, RAG_TOP_K);
    } catch (Throwable $e) {
        error_log('WESAL_RAG_RETRIEVE_FAIL: ' . $e->getMessage());
        return [];
    }
}

/** نطاق الرابط بلا www. — به تُعرَّف الجهة (اسمها وشعارها) في الواجهة. */
function ragHost(string $url): string
{
    $h = strtolower((string) parse_url($url, PHP_URL_HOST));
    return preg_replace('/^www\./', '', rtrim($h, '.'));
}

/** يبني كتلة نصية تُلحق بتوجيه النموذج، أو '' إن لم يوجد شيء ذو صلة. المصادر
 *  مرقّمة بترتيبها في $chunks، ورقمها هو ما يذكره النموذج بعد ===SRC=== (chatSources). */
function ragContextBlock(array $chunks): string
{
    if (!$chunks) return '';
    $out = "\n\nمعلومات مسترجَعة من مصادر رسمية مرقّمة (استخدمها حصراً لأي حقيقة رسمية):\n";
    foreach ($chunks as $i => $c) {
        $host = ragHost((string) (($c['ref'] ?? '') ?: $c['url']));
        $out .= 'المصدر ' . ($i + 1) . ': ' . ($c['title'] ?: 'مصدر رسمي') . ($host !== '' ? " ($host)" : '') . "\n"
              . mb_substr($c['text'], 0, 1200) . "\n\n";
    }
    return $out;
}

/** حال قاعدة المعرفة للوحة الإدارة وصفحة التشخيص: عدد صفحاتها ومقاطعها وجهاتها.
 *  الجهة نطاق المرجع الرسمي إن وُجد وإلا نطاق الصفحة، كما تظهر تحت الإجابة.
 *  @return array{pages:int, chunks:int, hosts:array<string,int>} */
function ragKbStats(): array
{
    $rows = db()->query('SELECT source_url, MAX(ref_url) ref, COUNT(*) n FROM kb_chunks GROUP BY source_url')->fetchAll();
    $hosts = [];
    $chunks = 0;
    foreach ($rows as $r) {
        $h = ragHost((string) ($r['ref'] ?: $r['source_url']));
        if ($h !== '') $hosts[$h] = ($hosts[$h] ?? 0) + 1;
        $chunks += (int) $r['n'];
    }
    arsort($hosts);
    return ['pages' => count($rows), 'chunks' => $chunks, 'hosts' => $hosts];
}
