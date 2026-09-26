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

/* آخر ما يُكتب في الجلسة قبل هذا السطر. PHP يقفل ملف الجلسة طوال الطلب، والبث
   قد ينتظر المزوّد عشرات الثواني، فكان كل طلب آخر من المتصفح نفسه ينتظره: استطلاع
   الإشعارات، وطلب chat.php البديل إن انقطع الاتصال، وقراءة الجمل الأولى بالصوت
   السعودي أثناء الكتابة (api/voice.php)، فيطول الانتظار أضعافاً. */
session_write_close();

$SYSTEM = chatSystemPrompt($mode);
[$SYSTEM, $ragChunks] = chatAugmentWithRag($SYSTEM, $message);

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
$committedHeaders = false;
$ttfbMs = null;
$pending = '';          // مُعلَّق ريثما يُتأكَّد أمانه (تعتيم اسم المزوّد + علامات الذيل)
$inSuggestions = false; // بعد أول علامة ذيل (chatTailStart): توقف عن delta، واجمع الذيل
$suggestTail = '';      // الذيل من علامته: أرقام المصادر والأسئلة المقترحة
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

    $markerPos = chatTailStart($pending);
    if ($markerPos !== null) {
        $before = substr($pending, 0, $markerPos);
        if ($before !== '') sseEmit('delta', ['text' => chatScrubReply($before)]);
        $inSuggestions = true;
        $suggestTail = substr($pending, $markerPos);
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

    $cut -= chatTrailingPartialMarker(substr($pending, 0, $cut));
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

if ($inSuggestions) {
    $ex = chatSplitReply($suggestTail);
    if ($ex['suggestions']) sseEmit('suggestions', ['items' => $ex['suggestions']]);
    $sources = chatSources($ragChunks, $ex['cited']);
    if ($sources) sseEmit('sources', ['items' => $sources]);
}

$cleanFull = chatSplitReply($fullReply)['reply'] ?: $fullReply;
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
