<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/chat-shared.php';
rateLimit('chat', 30);

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
$bal = chatCheckBalance($u, $ip, $cost);
$u   = $bal['u'];
$left = $bal['left'];

$SYSTEM = chatSystemPrompt($mode);
[$SYSTEM, ] = chatAugmentWithRag($SYSTEM, $message);
$thinkingLevel = chatThinkingLevel($mode);

$reply = null;
$providerUsed = null;
foreach (chatProviderOrder() as $p) {
    $reply = chatAskProvider($p, $SYSTEM, $message, $history, $thinkingLevel);
    if ($reply !== null) { $providerUsed = $p; break; }
}

$suggestions = [];
if ($reply) {
    $reply = chatScrubReply($reply);
    $ex = chatExtractSuggestions($reply);
    $reply = $ex['reply'];
    $suggestions = $ex['suggestions'];
}

chatLogInteraction($u, $message, $reply, $mode, $cost, [
    'model'    => $GLOBALS['ai_last_model'] ?? null,
    'provider' => $providerUsed,
    'stream'   => false,
    'total_ms' => (int) round((microtime(true) - $reqT0) * 1000),
]);

if (!$reply) out(['ok' => false, 'fallback' => true]);   // الواجهة ترد من قاعدة المعرفة المحلية

out(['ok' => true, 'reply' => trim($reply), 'tokens' => $left, 'cost' => $cost, 'suggestions' => $suggestions]);
