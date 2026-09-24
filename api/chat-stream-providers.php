<?php
/* نداءات المزوّدين المتدفقة — توأم api/chat-shared.php لكن بـ cURL
   WRITEFUNCTION بدل RETURNTRANSFER، فتُسلَّم دلتا النص لحظة وصولها بدل
   انتظار اكتمال الرد كاملاً. كل دالة ask*Stream() ترجّع
   ['committed'=>bool,'broken'=>bool,'aborted'=>bool]:
     committed  وصل جزء نص حقيقي واحد على الأقل من هذا المزوّد
     broken     التزم فعلاً لكن النقل انقطع من عند المزوّد قبل الاكتمال الطبيعي
     aborted    التزم فعلاً لكن المستخدم أوقف البث (اتصال العميل انقطع) */

/* ---------- المحرّك المشترك: SSE فوق cURL ---------- */
function chatStreamCurl(string $url, string $body, array $headers, callable $extractText, callable $onDelta): array {
    $committed = false;
    $broken = false;
    $userAborted = false;
    $deliberateAbort = false;
    $status = null;
    $buf = '';
    $errBuf = '';
    $t0 = microtime(true);

    $headerFn = function ($ch, string $line) use (&$status): int {
        if ($status === null && preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) $status = (int) $m[1];
        return strlen($line);
    };

    $writeFn = function ($ch, string $data) use (
        &$buf, &$errBuf, &$committed, &$broken, &$userAborted, &$deliberateAbort, &$status, $t0, $extractText, $onDelta
    ): int {
        if ($status !== null && $status >= 400) {
            $errBuf .= $data;   // خطأ من أول استدعاء: اجمع نص التشخيص فقط، لا محتوى للعرض
            return strlen($data);
        }
        $buf .= $data;
        while (($p = strpos($buf, "\n\n")) !== false) {
            $frame = substr($buf, 0, $p);
            $buf = substr($buf, $p + 2);
            foreach (explode("\n", $frame) as $line) {
                if (strpos($line, 'data:') !== 0) continue;
                $json = trim(substr($line, 5));
                if ($json === '' || $json === '[DONE]') continue;
                $j = json_decode($json, true);
                if (!is_array($j)) continue;
                $text = $extractText($j);
                if ($text !== null && $text !== '') {
                    $committed = true;
                    $onDelta($text);
                }
            }
        }
        if (!$committed && (microtime(true) - $t0) > AI_FIRST_CONTENT_BUDGET) {
            $deliberateAbort = true;
            return 0;   // مهلة أول محتوى: انتقل لمزوّد/نموذج آخر
        }
        if ($committed && connection_aborted()) {
            $deliberateAbort = true;
            $userAborted = true;
            return 0;   // المستخدم ضغط "إيقاف" أو أغلق الصفحة
        }
        return strlen($data);
    };

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $headers),
        CURLOPT_HEADERFUNCTION => $headerFn,
        CURLOPT_WRITEFUNCTION => $writeFn,
        CURLOPT_CONNECTTIMEOUT => AI_CONNECT_TIMEOUT,
        CURLOPT_TIMEOUT => AI_TOTAL_TIMEOUT,
        CURLOPT_RETURNTRANSFER => false,
    ]);
    curl_exec($ch);
    $cerr = curl_error($ch);
    $cerrno = curl_errno($ch);
    curl_close($ch);

    if (!$committed) {
        $GLOBALS['ai_last_error'] = 'HTTP ' . ($status ?? 0) . ($cerr ? ' | ' . $cerr : '') . ' | ' . mb_substr($errBuf, 0, 220);
        error_log('WESAL_AI_STREAM_FAIL: ' . $GLOBALS['ai_last_error']);
    } elseif ($cerrno !== 0 && !$deliberateAbort) {
        $broken = true;   // التزم، ثم انقطع النقل من عند المزوّد — ليس المستخدم من أوقفه
        error_log('WESAL_AI_STREAM_BREAK: ' . $cerr);
    }
    return ['committed' => $committed, 'broken' => $broken, 'aborted' => $userAborted];
}

/* ---------- Gemini ---------- */
function askGeminiStream(string $sys, string $msg, array $hist, callable $onDelta, bool $noThinking = true): array {
    $models = [GEMINI_MODEL];
    if (defined('GEMINI_FALLBACKS'))
        foreach (explode(',', GEMINI_FALLBACKS) as $m) { $m = trim($m); if ($m !== '') $models[] = $m; }
    foreach ($models as $model) {
        $nt = $noThinking;
        $r = askGeminiOnceStream($model, $sys, $msg, $hist, $onDelta, $nt);
        if ($r['committed']) return $r;
        $err = $GLOBALS['ai_last_error'] ?? '';
        if (strpos($err, 'HTTP 429') !== false) {                 // حصة: انتظر ثم أعد نفس المحاولة مرة واحدة
            usleep(1300000);
            $r = askGeminiOnceStream($model, $sys, $msg, $hist, $onDelta, $nt);
            if ($r['committed']) return $r;
            $err = $GLOBALS['ai_last_error'] ?? '';
        }
        if ($nt && strpos($err, 'HTTP 400') !== false) { // تراجع آمن: قد يكون حقل thinkingConfig غير مدعوم لهذا النموذج
            $r = askGeminiOnceStream($model, $sys, $msg, $hist, $onDelta, false);   // بلا thinkingConfig إطلاقاً
            if ($r['committed']) return $r;
        }
        // أي خطأ آخر → جرّب النموذج التالي فوراً
    }
    return ['committed' => false, 'broken' => false, 'aborted' => false];
}
function askGeminiOnceStream(string $model, string $sys, string $msg, array $hist, callable $onDelta, bool $noThinking = true): array {
    if (!GEMINI_KEY) return ['committed' => false, 'broken' => false, 'aborted' => false];
    $contents = [];
    foreach ($hist as $h) {
        $contents[] = ['role' => (($h['role'] ?? '') === 'user' ? 'user' : 'model'),
                       'parts' => [['text' => mb_substr((string)($h['text'] ?? ''), 0, AI_HIST_CHAR_LIMIT)]]];
    }
    $contents[] = ['role' => 'user', 'parts' => [['text' => $msg]]];
    $generationConfig = ['temperature' => AI_TEMPERATURE, 'maxOutputTokens' => AI_MAX_OUTPUT_TOKENS, 'topP' => AI_TOP_P];
    if ($noThinking) $generationConfig['thinkingConfig'] = ['thinkingBudget' => 0];
    $body = json_encode([
        'system_instruction' => ['parts' => [['text' => $sys]]],
        'contents' => $contents,
        'generationConfig' => $generationConfig,
    ], JSON_UNESCAPED_UNICODE);
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model
         . ':streamGenerateContent?alt=sse&key=' . GEMINI_KEY;
    $r = chatStreamCurl($url, $body, [], 'chatExtractGeminiText', $onDelta);
    if ($r['committed']) $GLOBALS['ai_last_model'] = $model;
    return $r;
}

/* ---------- OpenAI ---------- */
function askOpenAIStream(string $sys, string $msg, array $hist, callable $onDelta): array {
    if (!OPENAI_KEY) return ['committed' => false, 'broken' => false, 'aborted' => false];
    $msgs = [['role' => 'system', 'content' => $sys]];
    foreach ($hist as $h) {
        $msgs[] = ['role' => (($h['role'] ?? '') === 'user' ? 'user' : 'assistant'),
                   'content' => mb_substr((string)($h['text'] ?? ''), 0, AI_HIST_CHAR_LIMIT)];
    }
    $msgs[] = ['role' => 'user', 'content' => $msg];
    $body = json_encode(['model' => OPENAI_MODEL, 'messages' => $msgs, 'temperature' => 0.5,
                          'max_tokens' => AI_MAX_OUTPUT_TOKENS, 'stream' => true], JSON_UNESCAPED_UNICODE);
    $extract = fn(array $j): ?string => $j['choices'][0]['delta']['content'] ?? null;
    $r = chatStreamCurl('https://api.openai.com/v1/chat/completions', $body,
        ['Authorization: Bearer ' . OPENAI_KEY], $extract, $onDelta);
    if ($r['committed']) $GLOBALS['ai_last_model'] = OPENAI_MODEL;
    return $r;
}

/* ---------- Claude (Anthropic Messages API، stream:true) ---------- */
function askClaudeStream(string $sys, string $msg, array $hist, callable $onDelta): array {
    if (!CLAUDE_KEY) return ['committed' => false, 'broken' => false, 'aborted' => false];
    $msgs = [];
    foreach ($hist as $h) {
        $msgs[] = ['role' => (($h['role'] ?? '') === 'user' ? 'user' : 'assistant'),
                   'content' => mb_substr((string)($h['text'] ?? ''), 0, AI_HIST_CHAR_LIMIT)];
    }
    $msgs[] = ['role' => 'user', 'content' => $msg];
    $body = json_encode(['model' => CLAUDE_MODEL, 'max_tokens' => AI_MAX_OUTPUT_TOKENS, 'system' => $sys,
                          'messages' => $msgs, 'thinking' => ['type' => 'disabled'], 'stream' => true], JSON_UNESCAPED_UNICODE);
    // فقط أحداث content_block_delta تحمل delta.text؛ الأنواع الأخرى (message_start،
    // content_block_start، message_stop...) ترجّع null هنا بصمت فلا تُنتج دلتا.
    $extract = fn(array $j): ?string => $j['delta']['text'] ?? null;
    $r = chatStreamCurl('https://api.anthropic.com/v1/messages', $body,
        ['x-api-key: ' . CLAUDE_KEY, 'anthropic-version: 2023-06-01'], $extract, $onDelta);
    if ($r['committed']) $GLOBALS['ai_last_model'] = CLAUDE_MODEL;
    return $r;
}

/* ---------- Kimi (واجهة متوافقة مع OpenAI) ---------- */
function askKimiStream(string $sys, string $msg, array $hist, callable $onDelta): array {
    if (!KIMI_KEY) return ['committed' => false, 'broken' => false, 'aborted' => false];
    $msgs = [['role' => 'system', 'content' => $sys]];
    foreach ($hist as $h) {
        $msgs[] = ['role' => (($h['role'] ?? '') === 'user' ? 'user' : 'assistant'),
                   'content' => mb_substr((string)($h['text'] ?? ''), 0, AI_HIST_CHAR_LIMIT)];
    }
    $msgs[] = ['role' => 'user', 'content' => $msg];
    $body = json_encode(['model' => KIMI_MODEL, 'messages' => $msgs, 'temperature' => 0.5,
                          'max_tokens' => AI_MAX_OUTPUT_TOKENS, 'stream' => true], JSON_UNESCAPED_UNICODE);
    $extract = fn(array $j): ?string => $j['choices'][0]['delta']['content'] ?? null;
    $r = chatStreamCurl(rtrim(KIMI_BASE_URL, '/') . '/chat/completions', $body,
        ['Authorization: Bearer ' . KIMI_KEY], $extract, $onDelta);
    if ($r['committed']) $GLOBALS['ai_last_model'] = KIMI_MODEL;
    return $r;
}

function chatAskProviderStream(string $provider, string $sys, string $msg, array $hist, callable $onDelta): array {
    switch ($provider) {
        case 'gemini': return askGeminiStream($sys, $msg, $hist, $onDelta);
        case 'openai': return askOpenAIStream($sys, $msg, $hist, $onDelta);
        case 'claude': return askClaudeStream($sys, $msg, $hist, $onDelta);
        case 'kimi':   return askKimiStream($sys, $msg, $hist, $onDelta);
        default:       return ['committed' => false, 'broken' => false, 'aborted' => false];
    }
}
