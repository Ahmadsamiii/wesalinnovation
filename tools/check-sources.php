<?php
/* ==========================================================================
 *  وصال: فحص «مصدر الإجابة» على الخادم (api/chat-shared.php وapi/rag.php)
 *
 *  الاستخدام:
 *      php tools/check-sources.php
 *
 *  يتحقق من فصل ذيل الرد (===SRC=== وأرقام المصادر، ثم ===ASK3=== والأسئلة)
 *  بصيغ يكتبها النموذج فعلاً، ومن أن الجهات المعروضة لا تكون إلا مما جلبه
 *  الاسترجاع: رقم خارج المجلوب يُهمل، وجهة واحدة لكل نطاق، وثلاث على الأكثر،
 *  والمرجع الرسمي (ref_url) قبل رابط الصفحة، والرابط غير https بلا رابط.
 *  ويتحقق أن علامة مقسومة بين دفعتين لا تُفلت أثناء البث.
 *  لا يحتاج قاعدة بيانات ولا إعدادات ولا مفتاح نموذج.
 * ========================================================================== */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("هذا السكربت يُشغَّل من الطرفية فقط.\n");
}

require_once __DIR__ . '/../api/chat-shared.php';

$fails = 0;
function check(string $name, bool $ok, $got = null): void {
    global $fails;
    if (!$ok) $fails++;
    echo '  ' . ($ok ? '✓' : '✗') . " $name"
        . (!$ok && $got !== null ? '  | ' . json_encode($got, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '') . "\n";
}

echo "فصل ذيل الرد:\n";
$r = chatSplitReply("الخلاصة.\n\n- نقطة\n\n===SRC=== 1,3\n\n===ASK3===\nسؤال أول؟\nسؤال ثانٍ؟\nسؤال ثالث؟");
check('الإجابة وحدها بلا ذيل', $r['reply'] === "الخلاصة.\n\n- نقطة", $r['reply']);
check('أرقام المصادر', $r['cited'] === [1, 3], $r['cited']);
check('الأسئلة الثلاثة', $r['suggestions'] === ['سؤال أول؟', 'سؤال ثانٍ؟', 'سؤال ثالث؟'], $r['suggestions']);

$r = chatSplitReply("نص\n===SRC=== ١، ٣ و ٢\n===ASK3===\nس؟");
check('أرقام عربية وفواصل عربية و«و»', $r['cited'] === [1, 3, 2], $r['cited']);

$r = chatSplitReply("نص\n===SRC===\n2\n===ASK3===\nس؟");
check('الأرقام في السطر التالي للعلامة', $r['cited'] === [2], $r['cited']);

$r = chatSplitReply("نص\n===ASK3===\nأ؟\nب؟\n===SRC=== 2");
check('المصادر بعد الأسئلة لا تصير سؤالاً', $r['cited'] === [2] && $r['suggestions'] === ['أ؟', 'ب؟'], $r);

$r = chatSplitReply("نص\n===SRC=== 0\n===ASK3===\nأ؟");
check('«0» لا مصدر', $r['cited'] === [0] && $r['reply'] === 'نص', $r);

$r = chatSplitReply("نص بلا ذيل");
check('بلا علامات: الإجابة كما هي', $r === ['reply' => 'نص بلا ذيل', 'suggestions' => [], 'cited' => []], $r);

$r = chatSplitReply("نص\n===ASK3===\nأ؟\nب؟\nج؟\nد؟");
check('بلا استرجاع: الأسئلة فقط وثلاث منها', $r['cited'] === [] && count($r['suggestions']) === 3, $r);

$r = chatSplitReply("نص\n===SRC=== لا يوجد\n===ASK3===\nأ؟");
check('كلام بعد العلامة بلا أرقام يُهمل', $r['cited'] === [] && $r['suggestions'] === ['أ؟'] && $r['reply'] === 'نص', $r);

echo "\nالجهات المعروضة:\n";
$chunks = [
    ['url' => 'https://www.hrsd.gov.sa/ar/services/disability', 'ref' => null, 'title' => 'الإعانة المالية | وزارة الموارد البشرية', 'text' => 'أ'],
    ['url' => 'https://apd.gov.sa/services', 'ref' => null, 'title' => 'خدمات الهيئة', 'text' => 'ب'],
    ['url' => 'https://hrsd.gov.sa/ar/other', 'ref' => null, 'title' => 'صفحة أخرى', 'text' => 'ج'],
    ['url' => 'https://workspace.wesalinnovation.sa/kb/7', 'ref' => 'https://www.moh.gov.sa/HealthAwareness/x', 'title' => 'التأهيل', 'text' => 'د'],
];
$s = chatSources($chunks, [2, 9, 0, -1, 1, 3]);
check('أرقام خارج المجلوب تُهمل وترتيب ذكرها يبقى', array_column($s, 'host') === ['apd.gov.sa', 'hrsd.gov.sa'], $s);
check('جهة واحدة لكل نطاق (أول صفحة ذُكرت منه)', $s[1]['url'] === 'https://www.hrsd.gov.sa/ar/services/disability', $s[1] ?? null);
check('www لا تدخل النطاق', $s[1]['host'] === 'hrsd.gov.sa', $s[1] ?? null);
$s = chatSources($chunks, [4]);
check('المرجع الرسمي قبل رابط الصفحة', $s[0]['host'] === 'moh.gov.sa' && $s[0]['url'] === 'https://www.moh.gov.sa/HealthAwareness/x', $s);
$many = [];
foreach (['a.gov.sa', 'b.gov.sa', 'c.gov.sa', 'd.gov.sa'] as $h) $many[] = ['url' => "https://$h/p", 'title' => $h, 'text' => ''];
check('ثلاث جهات على الأكثر', count(chatSources($many, [1, 2, 3, 4])) === 3);
$s = chatSources([['url' => 'http://old.gov.sa/p', 'title' => 'قديم', 'text' => '']], [1]);
check('رابط غير https يُعرض بلا رابط', $s[0]['url'] === null && $s[0]['host'] === 'old.gov.sa', $s);
$s = chatSources([['url' => 'javascript:alert(1)', 'title' => 'x', 'text' => '']], [1]);
check('رابط بلا نطاق لا يصير جهة', $s === [], $s);
check('بلا أرقام لا جهات', chatSources($chunks, []) === []);

echo "\nتوجيه النموذج:\n";
$block = ragContextBlock($chunks);
check('المصادر مرقّمة بترتيبها', str_contains($block, 'المصدر 1: الإعانة المالية') && str_contains($block, 'المصدر 4: التأهيل'), $block);
check('نطاق المرجع الرسمي ظاهر للنموذج', str_contains($block, '(moh.gov.sa)'), $block);
check('لا روابط بصيغة Markdown', !preg_match('/\]\(https?:/', $block), $block);

echo "\nعلامة مقسومة أثناء البث:\n";
check('«===SR» في آخر الدفعة محجوزة', chatTrailingPartialMarker('نص ===SR') === strlen('===SR'));
check('«===AS» في آخر الدفعة محجوزة', chatTrailingPartialMarker('نص ===AS') === strlen('===AS'));
check('«=» وحدها محجوزة', chatTrailingPartialMarker('نص =') === 1);
check('نص عادي لا يُحجز منه شيء', chatTrailingPartialMarker('نص عادي') === 0);
/* محاكاة: الرد يصل على دفعات بكل نقاط القطع الممكنة، وما قبل الحجز يُعرض فقط */
$full = "الخلاصة هنا.\n===SRC=== 1\n===ASK3===\nأ؟";
$leak = false;
for ($cut = 1; $cut < strlen($full); $cut++) {
    $shown = substr($full, 0, $cut);
    $pos = chatTailStart($shown);
    $visible = $pos === null ? substr($shown, 0, strlen($shown) - chatTrailingPartialMarker($shown)) : substr($shown, 0, $pos);
    if (strpos($visible, '===') !== false) { $leak = true; break; }
}
check('لا جزء من علامة يظهر للمستخدم في أي نقطة قطع', !$leak);

echo $fails ? "\n✗ فشل من الفحوص: $fails\n" : "\n✓ كل الفحوص ناجحة\n";
exit($fails ? 1 : 0);
