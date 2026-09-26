<?php
/* ==========================================================================
 *  وصال: فحص محرّك البث من المزوّد (chatStreamCurl في api/chat-stream-providers.php)
 *
 *  الاستخدام:
 *      php tools/check-stream.php
 *
 *  يشغّل مزوّداً وهمياً على منفذ محلي عابر ويتحقق من ثلاثة أشياء كسرت البث
 *  من قبل دون أن يظهر لها أثر:
 *    - نص المزوّد يصل دالة الكتابة (writeFn) فيلتزم البث. ضبط
 *      CURLOPT_RETURNTRANSFER بعد CURLOPT_WRITEFUNCTION كان يلغيها، فيُطبع رد
 *      المزوّد خاماً في رد الخادم ويسقط كل سؤال إلى chat.php.
 *    - المزوّد الصامت يُترك عند مهلة أول محتوى، لا بعد المهلة الكاملة.
 *    - خطأ المزوّد (429) لا يُحسب التزاماً ويُسجَّل رمزه لإعادة المحاولة.
 *  لا يحتاج قاعدة بيانات ولا إعدادات ولا مفتاح نموذج.
 * ========================================================================== */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("هذا السكربت يُشغَّل من الطرفية فقط.\n");
}

ini_set('error_log', '/dev/null');   // الإخفاقات المتعمَّدة أدناه تكتب سطر WESAL_AI_STREAM_FAIL

// الثوابت من chat-shared.php، بمهلة أول محتوى قصيرة ليسرع الفحص
const AI_CONNECT_TIMEOUT = 5;
const AI_TOTAL_TIMEOUT = 20;
const AI_FIRST_CONTENT_BUDGET = 2;
require_once __DIR__ . '/../api/chat-stream-providers.php';

$dir = sys_get_temp_dir() . '/wesal-check-stream-' . getmypid();
@mkdir($dir);
file_put_contents($dir . '/router.php', <<<'PHP'
<?php
$case = $_GET['case'] ?? '';
if ($case === 'error') { http_response_code(429); echo '{"error":"quota"}'; exit; }
header('Content-Type: text/event-stream');
if ($case === 'silent') { sleep(15); exit; }
foreach (['أهلاً ', 'بك'] as $t) {
    echo 'data: ' . json_encode(['t' => $t], JSON_UNESCAPED_UNICODE) . "\n\n";
    flush();
    usleep(100000);
}
PHP);

$port = 0;
for ($i = 0; $i < 20 && !$port; $i++) {
    $try = random_int(20000, 60000);
    $s = @stream_socket_server("tcp://127.0.0.1:$try");
    if ($s) { fclose($s); $port = $try; }
}
$proc = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", $dir . '/router.php'],
    [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']], $pipes,
    null, ['PHP_CLI_SERVER_WORKERS' => '3'] + getenv());
for ($i = 0; $i < 50; $i++) {
    $c = @fsockopen('127.0.0.1', $port);
    if ($c) { fclose($c); break; }
    usleep(100000);
}

$fails = 0;
function check(string $name, bool $ok, string $detail = ''): void {
    global $fails;
    if (!$ok) $fails++;
    echo '  ' . ($ok ? '✓' : '✗') . " $name" . (!$ok && $detail !== '' ? "  | $detail" : '') . "\n";
}
function run(int $port, string $case): array {
    $GLOBALS['ai_last_error'] = null;
    $got = '';
    $t0 = microtime(true);
    ob_start();   // ما يُطبع هنا بدل أن يصل writeFn هو علامة العطل نفسه
    $r = chatStreamCurl("http://127.0.0.1:$port/?case=$case", '{}', [],
        fn(array $j): ?string => $j['t'] ?? null,
        function (string $t) use (&$got): void { $got .= $t; });
    $leaked = ob_get_clean();
    return $r + ['text' => $got, 'leaked' => $leaked, 'secs' => microtime(true) - $t0,
                 'err' => (string) ($GLOBALS['ai_last_error'] ?? '')];
}

echo "محرّك البث من المزوّد:\n";
$r = run($port, 'ok');
check('نص المزوّد يصل دالة الكتابة فيلتزم البث', $r['committed'] && $r['text'] === 'أهلاً بك',
      'committed=' . var_export($r['committed'], true) . ' text=' . $r['text']);
check('لا يُطبع رد المزوّد خاماً في رد الخادم', $r['leaked'] === '', mb_substr(preg_replace('/\s+/u', ' ', $r['leaked']), 0, 80));

$r = run($port, 'silent');
check('المزوّد الصامت يُترك عند مهلة أول محتوى', !$r['committed'] && $r['secs'] < AI_FIRST_CONTENT_BUDGET + 2,
      sprintf('%.1f ثانية', $r['secs']));

$r = run($port, 'error');
check('خطأ المزوّد لا يُحسب التزاماً ويُسجَّل رمزه', !$r['committed'] && str_contains($r['err'], 'HTTP 429'), $r['err']);

proc_terminate($proc);
proc_close($proc);
@unlink($dir . '/router.php');
@rmdir($dir);

echo $fails ? "\n✗ فشل من الفحوص: $fails\n" : "\n✓ كل الفحوص ناجحة\n";
exit($fails ? 1 : 0);
