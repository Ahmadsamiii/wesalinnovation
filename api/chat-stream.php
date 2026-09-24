<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/chat-shared.php';
require_once __DIR__ . '/chat-stream-providers.php';
rateLimit('chat', 30);   // نفس دلو chat.php بالضبط — لا يمنح مزيج المسارين حصة مضاعفة

/* حارس db.php يفتح ob_start() غير مشروط (السطر الموازي في db.php) — يُفرَّغ هنا
   كلياً لهذا الملف فقط، فتبقى out()/fail() المشتركتان صالحتين بلا تعديل لأي
   مسار "قبل الالتزام بالبث" (discardStrayOutput() تتوافق أصلاً مع صفر مستويات). */
while (ob_get_level() > 0) { ob_end_clean(); }

$reqT0 = microtime(true);

$in      = body();
$message = clean($in['message'] ?? '', 1500);
$mode    = ($in['mode'] ?? 'simple') === 'detailed' ? 'detailed' : 'simple';
$history = is_array($in['history'] ?? null) ? array_slice($in['history'], -6) : [];

if (mb_strlen($message) < 2) fail('اكتب سؤالك أولاً.');

$st = smallTalkReply($message);
if ($st !== null) {
    chatLogInteraction(null, $message, $st, 'small', 0);
    out(['ok' => true, 'reply' => $st, 'cost' => 0, 'small' => true]);
}

$ip   = clientIp();
$cost = chatCost($message);
chatCircuitBreaker($ip, $cost);

$u = currentUser();
$bal  = chatCheckBalance($u, $ip, $cost);
$u    = $bal['u'];
$left = $bal['left'];

$SYSTEM = chatSystemPrompt($mode);
[$SYSTEM, ] = chatAugmentWithRag($SYSTEM, $message);

/* ---------- البث ---------- */
function sseCommitHeaders(): void {
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache, no-transform');
    header('X-Accel-Buffering: no');
    header('Content-Encoding: none');
    @ini_set('zlib.output_compression', '0');
    @ini_set('output_buffering', '0');
    @ini_set('implicit_flush', '1');
    set_time_limit(60);
}
function sseEmit(string $event, array $data): void {
    echo 'event: ' . $event . "\ndata: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    flush();
}
/** طول أطول بادئة لاحقة من $s تطابق بداية $marker — يمنع إفلات جزء ناشئ من
 *  العلامة ===ASK3=== لو وصلت مقسومة بين دفعتين قرب حافة نافذة الأمان. */
function chatTrailingPartialMarker(string $s, string $marker): int {
    $max = min(strlen($marker) - 1, strlen($s));
    for ($k = $max; $k > 0; $k--) {
        if (substr($s, -$k) === substr($marker, 0, $k)) return $k;
    }
    return 0;
}

const ASK3_MARKER = '===ASK3===';

$committedHeaders = false;
$ttfbMs = null;
$pending = '';          // مُعلَّق ريثما يُتأكَّد أمانه (تعتيم اسم المزوّد + العلامة)
$inSuggestions = false; // بعد اكتشاف العلامة: توقف عن delta، اجمع للاقتراحات
$suggestTail = '';
$fullReply = '';        // النص الخام كاملاً (بلا فصل الذيل) — للتسجيل فقط

$onDelta = function (string $text) use (
    &$committedHeaders, &$ttfbMs, &$pending, &$inSuggestions, &$suggestTail, &$fullReply, $reqT0
): void {
    $fullReply .= $text;

    if (!$committedHeaders) {
        $committedHeaders = true;
        $ttfbMs = (int) round((microtime(true) - $reqT0) * 1000);
        sseCommitHeaders();
    }

    if ($inSuggestions) { $suggestTail .= $text; return; }

    $pending .= $text;

    $markerPos = strpos($pending, ASK3_MARKER);
    if ($markerPos !== false) {
        $before = substr($pending, 0, $markerPos);
        if ($before !== '') sseEmit('delta', ['text' => chatScrubReply($before)]);
        $inSuggestions = true;
        $suggestTail = substr($pending, $markerPos + strlen(ASK3_MARKER));
        $pending = '';
        return;
    }

    $safetyBytes = AI_SCRUB_SAFETY_CHARS * 4;   // هامش بالبايت يغطي حروفاً عربية متعددة البايتات
    $len = strlen($pending);
    if ($len <= $safetyBytes) return;

    $windowStart = $len - $safetyBytes;
    $window = substr($pending, $windowStart);
    $sp = strrpos($window, ' ');
    $nl = strrpos($window, "\n");
    $rel = ($sp === false) ? $nl : (($nl === false) ? $sp : max($sp, $nl));
    $cut = ($rel === false) ? $windowStart : ($windowStart + $rel + 1);

    $cut -= chatTrailingPartialMarker(substr($pending, 0, $cut), ASK3_MARKER);
    if ($cut <= 0) return;

    $safe = substr($pending, 0, $cut);
    $pending = substr($pending, $cut);
    if ($safe !== '') sseEmit('delta', ['text' => chatScrubReply($safe)]);
};

/* ---------- بديل تلقائي قبل الالتزام ---------- */
$providerUsed = null;
$r = ['committed' => false, 'broken' => false, 'aborted' => false];
foreach (chatProviderOrder() as $p) {
    $r = chatAskProviderStream($p, $SYSTEM, $message, $history, $onDelta);
    if ($committedHeaders) { $providerUsed = $p; break; }
}

if (!$committedHeaders) {
    // لم يلتزم أي مزوّد بالبث: لم يُرسَل أي بايت بعد، فرد JSON عادي تماماً
    // كما chat.php — الواجهة تسقط بنفس مسارها المعتاد (chat.php ثم المحلي).
    chatLogInteraction($u, $message, null, $mode, $cost, [
        'provider' => null,
        'stream'   => true,
        'total_ms' => (int) round((microtime(true) - $reqT0) * 1000),
    ]);
    out(['ok' => false, 'fallback' => true]);
}

/* التزمنا: أنهِ ما تبقى معلَّقاً وأرسل الاقتراحات إن وُجدت */
if ($pending !== '') { sseEmit('delta', ['text' => chatScrubReply($pending)]); $pending = ''; }

$suggestions = [];
if ($inSuggestions) {
    $ex = chatExtractSuggestions(ASK3_MARKER . $suggestTail);
    $suggestions = $ex['suggestions'];
    if ($suggestions) sseEmit('suggestions', ['items' => $suggestions]);
}

$cleanFull = chatExtractSuggestions($fullReply)['reply'] ?: $fullReply;
chatLogInteraction($u, $message, $cleanFull, $mode, $cost, [
    'model'    => $GLOBALS['ai_last_model'] ?? null,
    'provider' => $providerUsed,
    'stream'   => true,
    'ttfb_ms'  => $ttfbMs,
    'total_ms' => (int) round((microtime(true) - $reqT0) * 1000),
    'aborted'  => !empty($r['aborted']),
]);

if (!empty($r['broken'])) {
    sseEmit('error', ['message' => 'انقطع الاتصال بالمزوّد قبل اكتمال الرد.']);
} else {
    sseEmit('done', ['tokens' => $left, 'cost' => $cost]);
}
