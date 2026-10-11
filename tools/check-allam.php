<?php
/* ==========================================================================
 *  وصال: فحص مزوّد علام (ALLaM) على خادم وهمي بصيغة OpenAI
 *
 *  الاستخدام:
 *      php tools/check-allam.php
 *
 *  يشغّل خادماً وهمياً على منفذ محلي عابر بنقطتين (a وb)، ويتحقق من:
 *    - الرد العادي والمتدفق من النقطة الأولى، ومفتاح الخادم يصل في الترويسة
 *    - الانتقال للنقطة الثانية لو فشلت الأولى قبل أي نص، في المسارين
 *    - عطل النقطتين معاً يرجع «لا رد» فتنتقل الصفحة لقاعدتها المحلية
 *    - قصّ التاريخ لنافذة 4096 توكن: أقدم دور يسقط (سؤال وجوابه)، والرد يُحدّ بما يبقى،
 *      والأدوار تتناوب بالترتيب الذي يشترطه قالب علام
 *    - ترتيب المزوّدين: علام وحده افتراضياً، واحتياطي خارجي فقط إن سُمّي صراحة
 *    - المزوّد مطفي بلا ALLAM_BASE_URL، والنقاط تُنظَّف (شرطة أخيرة، تكرار، بروتوكول غريب)
 *    - تعتيم اسم علام اللاتيني في ردود المساعد، دون المساس بكلمة «علام» العربية
 *  لا يحتاج قاعدة بيانات ولا نموذجاً حقيقياً ولا مفتاحاً.
 * ========================================================================== */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("هذا السكربت يُشغَّل من الطرفية فقط.\n");
}

ini_set('error_log', '/dev/null');   // الإخفاقات المتعمَّدة أدناه تكتب سطر WESAL_AI_FAIL

$dir = sys_get_temp_dir() . '/wesal-check-allam-' . getmypid();
@mkdir($dir);
file_put_contents($dir . '/router.php', <<<'PHP'
<?php
// /a/v1/chat/completions أو /b/v1/chat/completions. حالة كل نقطة في ملف mode-a وmode-b: ok أو fail
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (!preg_match('#^/([ab])/v1/chat/completions$#', $uri, $m)) { http_response_code(404); echo '{}'; exit; }
$ep = $m[1];
$mode = trim((string) @file_get_contents(__DIR__ . "/mode-$ep"));
$in = json_decode(file_get_contents('php://input'), true) ?: [];
$h = function_exists('getallheaders') ? getallheaders() : [];
file_put_contents(__DIR__ . "/last-$ep.json", json_encode(['auth' => $h['Authorization'] ?? '', 'req' => $in], JSON_UNESCAPED_UNICODE));
file_put_contents(__DIR__ . '/hits', $ep, FILE_APPEND);
if ($mode === 'fail') { http_response_code(503); echo '{"error":"down"}'; exit; }
if (!empty($in['stream'])) {
    header('Content-Type: text/event-stream');
    foreach (['أهلاً ', 'بك ', 'من ' . $ep] as $t) {
        echo 'data: ' . json_encode(['choices' => [['delta' => ['content' => $t]]]], JSON_UNESCAPED_UNICODE) . "\n\n";
        flush();
        usleep(50000);
    }
    echo "data: [DONE]\n\n";
    exit;
}
header('Content-Type: application/json');
echo json_encode(['choices' => [['message' => ['content' => 'أهلاً بك من ' . $ep]]]], JSON_UNESCAPED_UNICODE);
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

// إعداد المنصة كما يكتبه config.php، ثم المنطق المشترك ومحرّك البث
define('AI_PROVIDER', 'allam');
define('ALLAM_BASE_URL', "http://127.0.0.1:$port/a/v1/");     // الشرطة الأخيرة تُزال
define('ALLAM_BASE_URL_2', "http://127.0.0.1:$port/b/v1");
define('ALLAM_KEY', 'test-key');
define('ALLAM_MODEL', 'allam-test-model');
define('ALLAM_CONTEXT', 4096);
require_once __DIR__ . '/../api/chat-shared.php';
require_once __DIR__ . '/../api/chat-stream-providers.php';

$fails = 0;
function check(string $name, bool $ok, string $detail = ''): void {
    global $fails;
    if (!$ok) $fails++;
    echo '  ' . ($ok ? '✓' : '✗') . " $name" . (!$ok && $detail !== '' ? "  | $detail" : '') . "\n";
}
function mode(string $a, string $b): void {
    global $dir;
    file_put_contents("$dir/mode-a", $a);
    file_put_contents("$dir/mode-b", $b);
    @unlink("$dir/hits");
}
function hits(): string { global $dir; return (string) @file_get_contents("$dir/hits"); }
function last(string $ep): array { global $dir; return json_decode((string) @file_get_contents("$dir/last-$ep.json"), true) ?: []; }
/** يشغّل شيفرة في عملية جديدة بثوابت مختلفة (الثابت لا يُعاد تعريفه في العملية نفسها) */
function child(array $defs, string $code): string {
    $boot = '';
    foreach ($defs as $k => $v) $boot .= 'define(' . var_export($k, true) . ',' . var_export($v, true) . ');';
    $prog = $boot . 'require ' . var_export(__DIR__ . '/../api/chat-shared.php', true) . ';' . $code;
    return trim((string) shell_exec(escapeshellarg(PHP_BINARY) . ' -d error_log=/dev/null -r ' . escapeshellarg($prog) . ' 2>&1'));
}
function stream(): array {
    $got = '';
    $r = askAllamStream('نظام', 'سؤال', [], function (string $t) use (&$got): void { $got .= $t; });
    return [$r, $got];
}
$sys = 'نظام'; $msg = 'سؤال';

echo "الرد العادي:\n";
mode('ok', 'ok');
$GLOBALS['ai_last_model'] = null;
$t = askAllam($sys, $msg, []);
check('النقطة الأولى تجيب وحدها', $t === 'أهلاً بك من a' && hits() === 'a', (string) $t . ' | ' . hits());
check('اسم النموذج يُسجَّل للتقارير', ($GLOBALS['ai_last_model'] ?? '') === 'allam-test-model');
check('مفتاح الخادم يصل في الترويسة', (last('a')['auth'] ?? '') === 'Bearer test-key', json_encode(last('a')));
check('الطلب باسم النموذج المضبوط وبحدّ للرد', (last('a')['req']['model'] ?? '') === 'allam-test-model' && (last('a')['req']['max_tokens'] ?? 0) > 0);
mode('fail', 'ok');
$t = askAllam($sys, $msg, []);
check('تفشل الأولى فتجيب الثانية', $t === 'أهلاً بك من b' && hits() === 'ab', (string) $t . ' | ' . hits());
mode('fail', 'fail');
$t = askAllam($sys, $msg, []);
check('النقطتان معاً لا تجيبان: لا رد', $t === null && hits() === 'ab', json_encode($t) . ' | ' . hits());

echo "الرد المتدفق:\n";
mode('ok', 'ok');
[$r, $got] = stream();
check('النقطة الأولى تبث وتلتزم', $r['committed'] === true && $got === 'أهلاً بك من a' && hits() === 'a', $got . ' | ' . hits());
mode('fail', 'ok');
[$r, $got] = stream();
check('تفشل الأولى قبل أي نص فتبث الثانية', $r['committed'] === true && $got === 'أهلاً بك من b' && hits() === 'ab', $got . ' | ' . hits());
mode('fail', 'fail');
[$r, $got] = stream();
check('النقطتان معاً لا تبثان: لا التزام', $r['committed'] === false && $got === '', $got);

echo "قصّ السياق لنافذة 4096:\n";
$ctx = 4096;
$ar = fn(string $seed, int $n): string => mb_substr(str_repeat($seed . ' ', (int) ceil($n / (mb_strlen($seed) + 1))), 0, $n);
$bigSys = $ar('نظام وصال مع المصادر المسترجعة', 7500);
$hist = [];
for ($i = 0; $i < 3; $i++) {
    $hist[] = ['role' => 'user', 'text' => $ar('سؤال ' . $i, 800)];
    $hist[] = ['role' => 'assistant', 'text' => $ar('جواب ' . $i, 800)];
}
$p = allamPayload($bigSys, $ar('سؤال اليوم', 100), $hist);
$m = $p['messages'];
$roles = array_map(fn($x) => $x['role'], $m);
$est = ALLAM_TEMPLATE_TOKENS + array_sum(array_map(fn($x) => allamTokens($x['content']) + 8, array_slice($m, 1, -1))) + allamTokens($bigSys) + allamTokens($m[count($m) - 1]['content']);
check('التاريخ الطويل يُقصّ من الأقدم', count($m) < 2 + count($hist) && count($m) >= 4, 'عدد الرسائل ' . count($m));
check('الأدوار تتناوب: نظام ثم مستخدم ومساعد... وتنتهي بالمستخدم',
    $roles[0] === 'system' && $roles[1] === 'user' && $roles[count($roles) - 1] === 'user' && (count($roles) - 2) % 2 === 0, implode(',', $roles));
check('الأحدث هو الباقي', $m[count($m) - 2]['content'] === $hist[5]['text']);
check('المجموع مع الرد داخل النافذة', $est + $p['max_tokens'] <= $ctx, "$est + {$p['max_tokens']}");
check('الرد محدود بما يبقى لا بالسقف الكامل', $p['max_tokens'] < AI_MAX_OUTPUT_TOKENS && $p['max_tokens'] >= 64, (string) $p['max_tokens']);
$small = allamPayload($ar('نظام', 2600), $ar('سؤال', 100), [['role' => 'user', 'text' => 'مرحبا'], ['role' => 'assistant', 'text' => 'أهلاً']]);
check('الطلب الصغير يمر كما هو بسقف الرد الكامل', count($small['messages']) === 4 && $small['max_tokens'] === AI_MAX_OUTPUT_TOKENS, count($small['messages']) . ' / ' . $small['max_tokens']);

echo "تناوب الأدوار:\n";
$q = allamPayload('نظام', 'سؤال اليوم', [
    ['role' => 'assistant', 'text' => 'رد يتيم في البداية'],
    ['role' => 'user', 'text' => 'أ'], ['role' => 'user', 'text' => 'ب'],
    ['role' => 'assistant', 'text' => 'ج'],
    ['role' => 'user', 'text' => 'سؤال بلا رد'],
])['messages'];
check('يُترك الرد اليتيم، وتُدمج الأدوار المتتالية، ويُدمج السؤال بلا رد في سؤال اليوم',
    count($q) === 4 && $q[1]['role'] === 'user' && $q[1]['content'] === "أ\nب" && $q[2]['role'] === 'assistant' && $q[2]['content'] === 'ج'
    && $q[3]['role'] === 'user' && $q[3]['content'] === "سؤال بلا رد\nسؤال اليوم", json_encode($q, JSON_UNESCAPED_UNICODE));

echo "الترتيب والإعداد:\n";
$o = fn(array $defs): string => child($defs, 'echo json_encode(chatProviderOrder());');
check('علام وحده افتراضياً: لا مزوّد خارجي', $o(['AI_PROVIDER' => 'allam']) === '["allam"]', $o(['AI_PROVIDER' => 'allam']));
check('احتياطي خارجي فقط إن سُمّي صراحة', $o(['AI_PROVIDER' => 'allam', 'ALLAM_FALLBACK' => 'openai']) === '["allam","openai"]');
check('اسم احتياطي غير معروف يُتجاهل', $o(['AI_PROVIDER' => 'allam', 'ALLAM_FALLBACK' => 'مجهول']) === '["allam"]');
check('ترتيب بقية المزوّدين لم يتغير', $o(['AI_PROVIDER' => 'kimi']) === '["kimi","gemini"]' && $o(['AI_PROVIDER' => 'openai']) === '["openai","gemini"]');
check('بلا ALLAM_BASE_URL المزوّد مطفي ولا يتصل بشيء',
    child(['AI_PROVIDER' => 'allam'], 'echo json_encode([allamEndpoints(), askAllam("s", "m", [])]);') === '[[],null]');
check('النقاط تُنظَّف: شرطة أخيرة وتكرار وبروتوكول غريب',
    child(['ALLAM_BASE_URL' => 'http://h:1/v1/', 'ALLAM_BASE_URL_2' => 'http://h:1/v1'], 'echo json_encode(allamEndpoints());') === '["http:\/\/h:1\/v1"]'
    && child(['ALLAM_BASE_URL' => 'ftp://h/v1', 'ALLAM_BASE_URL_2' => ''], 'echo json_encode(allamEndpoints());') === '[]');
check('بلا مفتاح لا ترويسة تفويض', child([], 'echo json_encode(allamHeaders());') === '[]' && child(['ALLAM_KEY' => 'k'], 'echo json_encode(allamHeaders());') === '["Authorization: Bearer k"]');

echo "الإعدادات:\n";
$cfgSrc = (string) file_get_contents(__DIR__ . '/../api/config.example.php');
$dbSrc = (string) file_get_contents(__DIR__ . '/../api/db.php');
preg_match_all("/define\('(ALLAM_[A-Z0-9_]+)'/", $cfgSrc, $mm);
$missing = array_filter($mm[1], fn(string $k): bool => strpos($dbSrc, "'$k'") === false);
check('كل ثوابت علام في config.example.php لها قيمة افتراضية في db.php (فلا يتعطل config.php القائم بعد الترقية)', count($mm[1]) === 6 && !$missing, implode(',', $missing));

echo "تعتيم الاسم في ردود المساعد:\n";
$old = 'Gem' . 'ini';   // اسم مزوّد سابق يُبنى هنا لئلا يُكتب حرفياً
$clean = chatScrubReply("أنا ALLaM ومعي $old وبعدها allam");
check('اسم علام اللاتيني وغيره من أسماء المزوّدين يُعتَّم', stripos($clean, 'allam') === false && stripos($clean, $old) === false, $clean);
check('كلمة «علام» العربية لا تُمسّ', chatScrubReply('علام تسأل؟') === 'علام تسأل؟');

if (is_resource($proc)) { proc_terminate($proc); proc_close($proc); }
array_map('unlink', glob("$dir/*") ?: []);
@rmdir($dir);
echo $fails ? "\n✗ فشل $fails\n" : "\n✓ مزوّد علام سليم\n";
exit($fails ? 1 : 0);
