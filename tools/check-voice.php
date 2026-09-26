<?php
/* ==========================================================================
 *  وصال: فحص ذاتي للصوت السعودي (api/voice-lib.php)
 *
 *  الاستخدام:
 *      php tools/check-voice.php
 *
 *  يفحص دون شبكة ولا قاعدة بيانات ولا config.php: تنظيف النص قبل قراءته،
 *  وتهريب SSML، وترتيب الطبقات وإيقاف ما نفدت حصته منها، ومدد الإيقاف بعد
 *  كل نوع فشل، والتعرّف على صيغة التسجيل، وعبارات Whisper على الصمت، والحفظ
 *  المؤقت وسقف حجمه. ثم يتحقق أن الواجهة والإعدادات وسياسة الأمان والخصوصية
 *  متسقة مع الخادم. يعمل في مجلد مؤقت ويحذفه، فهو آمن على الإنتاج.
 *  المسار كاملاً في المتصفح (القراءة والبديل والإملاء) يُفحص يدوياً، أو بمزوّد
 *  وهمي عبر GROQ_BASE_URL و AZURE_SPEECH_REGION على نسخة محلية.
 * ========================================================================== */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("هذا السكربت يُشغَّل من الطرفية فقط.\n");
}

$tmp = sys_get_temp_dir() . '/wesal-voice-check-' . bin2hex(random_bytes(4));
@mkdir($tmp, 0700);
ini_set('error_log', $tmp . '/php-errors.log');   // سطور «paused» المتوقعة لا تزاحم نتيجة الفحص
define('STORAGE_DIR', $tmp);
define('GROQ_KEY', 'test');
define('GROQ_BASE_URL', 'http://127.0.0.1:9');
define('GROQ_TTS_VOICE_MALE', 'abdullah');
define('GROQ_TTS_VOICE_FEMALE', 'noura');
define('AZURE_SPEECH_KEY', 'test');
define('AZURE_SPEECH_REGION', 'uaenorth');
define('AZURE_TTS_VOICE_MALE', 'ar-SA-HamedNeural');
define('AZURE_TTS_VOICE_FEMALE', 'ar-SA-ZariyahNeural');
define('VOICE_TTS_ORDER', ' Groq, azure ,groq,other');
define('VOICE_CACHE_MB', 1);
require_once __DIR__ . '/../api/voice-lib.php';

$fails = 0;
function check(string $label, bool $ok, string $extra = ''): void {
    global $fails;
    if (!$ok) $fails++;
    echo ($ok ? '  ✓ ' : '  ✗ ') . $label . (!$ok && $extra !== '' ? '  | ' . $extra : '') . "\n";
}
function rmTree(string $d): void {
    foreach (glob($d . '/{,.}[!.,!..]*', GLOB_BRACE) ?: [] as $f) is_dir($f) ? rmTree($f) : @unlink($f);
    @rmdir($d);
}

echo "النص:\n";
$c = voiceCleanText("<b>حقك</b> في **البطاقة** 🙂 [هيئة رعاية الأشخاص ذوي الإعاقة](https://apd.gov.sa/x) • التفاصيل: www.hrsd.gov.sa\n\nانتهى");
check('يحذف الوسوم والرموز والروابط ويُبقي الكلام واسم المصدر', $c === 'حقك في البطاقة هيئة رعاية الأشخاص ذوي الإعاقة التفاصيل: انتهى', $c);
check('نص من رموز فقط يصبح فارغاً فلا يُطلب له صوت', voiceCleanText(" ** 🙂 # ") === '');
check('يصلح UTF-8 التالف بدل أن يُسقط النص كله', voiceCleanText("مرحبا\xC3") !== '');
$ssml = voiceSsml("أ & ب < ج 'د'", "ar-SA-HamedNeural");
check('SSML يهرّب & و < وعلامات التنصيص', str_contains($ssml, 'أ &amp; ب &lt; ج &apos;د&apos;') && str_contains($ssml, "name='ar-SA-HamedNeural'"), $ssml);

echo "الطبقات:\n";
check('الترتيب من VOICE_TTS_ORDER بلا تكرار ولا طبقة مجهولة', voiceTiers() === ['groq', 'azure'], json_encode(voiceTiers()));
check('طبقة الرد المطلوبة أولاً', voiceOrder('azure', ['groq', 'azure']) === ['azure', 'groq']);
check('طلب طبقة غير متاحة يعيد المتاح بترتيبه', voiceOrder('groq', ['azure']) === ['azure'] && voiceOrder('', ['groq', 'azure']) === ['groq', 'azure']);
check('الصوت حسب الجنس لكل طبقة', voiceName('groq', 'female') === 'noura' && voiceName('azure', 'male') === 'ar-SA-HamedNeural');
voicePause('groq', 3600, 'HTTP 429 test');
check('الطبقة الموقوفة تُستبعد حتى موعدها', voiceTiers(time()) === ['azure'] && voiceTiers(time() + 3601) === ['groq', 'azure']);
check('وحالة الإعداد (status) لا تتأثر بالإيقاف', voiceTiers() === ['groq', 'azure']);
voicePause('groq', 60, 'أقصر');
check('إيقاف أقصر لا يقصّر إيقافاً قائماً', (int)(voiceState()['groq']['until'] ?? 0) >= time() + 3500);

echo "مدد الإيقاف:\n";
$r = fn(int $code, int $retry = 0, string $body = '') => ['code' => $code, 'retry' => $retry, 'body' => $body];
check('429 بمهلة من المزوّد: المهلة نفسها', voicePauseFor($r(429, 7200)) === 7200);
check('429 بمهلة طويلة جداً: يوم وساعتان على الأكثر', voicePauseFor($r(429, 999999)) === 26 * 3600);
check('429 يذكر الحصة بلا مهلة: 6 ساعات', voicePauseFor($r(429, 0, '{"error":"Quota exceeded"}')) === 6 * 3600);
check('429 بلا مهلة: دقيقة', voicePauseFor($r(429)) === 60);
check('مفتاح مرفوض: 10 دقائق', voicePauseFor($r(401)) === 600 && voicePauseFor($r(403)) === 600);
check('انقطاع أو عطل عند المزوّد: دقيقة', voicePauseFor($r(0)) === 60 && voicePauseFor($r(503)) === 60);
check('رفض خاص بالنص (400): لا إيقاف', voicePauseFor($r(400)) === 0);

echo "الصوت والتسجيل:\n";
$wav = 'RIFF' . pack('V', 36) . 'WAVEfmt ' . str_repeat("\0", 300);
check('WAV سليم يُقبل', voiceLooksLikeAudio(['code' => 200, 'body' => $wav, 'type' => '']));
check('خطأ JSON بحالة 200 لا يُقبل صوتاً', !voiceLooksLikeAudio(['code' => 200, 'body' => str_repeat('{"error":"x"}', 30), 'type' => 'application/json']));
$f = $tmp . '/sniff';
$sniff = function (string $bytes) use ($f) { file_put_contents($f, $bytes); return voiceSniffAudio($f)[1] ?? null; };
check('يعرف webm وogg وm4a وwav وmp3 من بايتاتها',
      $sniff("\x1A\x45\xDF\xA3" . str_repeat("\0", 12)) === 'webm' && $sniff('OggS' . str_repeat("\0", 12)) === 'ogg'
      && $sniff("\0\0\0\x20ftypM4A " . str_repeat("\0", 4)) === 'm4a' && $sniff($wav) === 'wav' && $sniff('ID3' . str_repeat("\0", 13)) === 'mp3');
check('ويرفض ما ليس صوتاً', $sniff('<?php echo 1; ?>xxxx') === null);
check('عبارات Whisper على الصمت تُعدّ فراغاً', voiceIsNoise('اشتركوا في القناة.') && voiceIsNoise(' ترجمة نانسي قنقر ') && voiceIsNoise('...'));
check('وسؤال حقيقي يمر', !voiceIsNoise('وش الخدمات اللي تقدمها هيئة رعاية الأشخاص ذوي الإعاقة؟'));

echo "الحفظ المؤقت:\n";
$file = voiceCacheFile('groq', 'abdullah', 'مرحبا');
check('ملف الحفظ باسم بصمة وامتداد الطبقة داخل مجلد الصوت', (bool)preg_match('~/voice/cache/[0-9a-f]{64}\.wav$~', $file), $file);
check('بصمة مختلفة لصوت آخر أو طبقة أخرى', $file !== voiceCacheFile('groq', 'noura', 'مرحبا') && voiceCacheFile('azure', 'x', 'مرحبا') !== $file);
voiceCachePut($file, $wav);
check('يُحفظ ويُقرأ كما هو', @file_get_contents($file) === $wav);
check('مجلد الصوت محجوب عن المتصفح', str_contains((string)@file_get_contents($tmp . '/voice/.htaccess'), 'Require all denied'));
$big = str_repeat("\0", 400 * 1024);
foreach (range(1, 4) as $i) { $p = voiceCacheFile('azure', 'v', "نص $i"); file_put_contents($p, $big); touch($p, time() - 100 + $i); }
voiceCachePrune(dirname($file), true);
$kept = array_map(fn($i) => is_file(voiceCacheFile('azure', 'v', "نص $i")), range(1, 4));
check('فوق السقف يُحذف الأقدم أولاً حتى 80% منه', $kept === [false, false, true, true] && is_file($file), json_encode($kept));

echo "الاتساق مع الواجهة والإعدادات:\n";
$root = __DIR__ . '/..';
$html = (string)@file_get_contents($root . '/index.html');
preg_match('/const VOICE=\{[^}]*chunk:(\d+)/', $html, $m);
check('جمل الواجهة (VOICE.chunk) تحت حد الخادم', isset($m[1]) && (int)$m[1] > 0 && (int)$m[1] <= VOICE_MAX_CHARS, $m[1] ?? 'غير موجود');
check('الواجهة تطلب api/voice.php وتقرأ الطبقة من X-Voice-Tier', str_contains($html, "API+'voice.php'") && str_contains($html, "'X-Voice-Tier'"));
check('سياسة الخصوصية تشرح الصوت السعودي والإملاء', str_contains($html, '<h2>القراءة الصوتية والأسئلة بالصوت</h2>') && str_contains($html, '«صوت سعودي»') && str_contains($html, '«صوت الجهاز»'));
$ht = (string)@file_get_contents($root . '/.htaccess');
check('CSP يسمح بتشغيل الصوت من blob:', (bool)preg_match("/media-src 'self' blob:/", $ht));
check('Permissions-Policy يسمح بالميكروفون للموقع نفسه', str_contains($ht, 'microphone=(self)'));
$ex = (string)@file_get_contents($root . '/api/config.example.php');
$db = (string)@file_get_contents($root . '/api/db.php');
$missing = [];
foreach (['GROQ_KEY', 'GROQ_TTS_MODEL', 'GROQ_TTS_VOICE_MALE', 'GROQ_TTS_VOICE_FEMALE', 'GROQ_STT_MODEL', 'AZURE_SPEECH_KEY',
          'AZURE_SPEECH_REGION', 'AZURE_TTS_VOICE_MALE', 'AZURE_TTS_VOICE_FEMALE', 'VOICE_TTS_ORDER', 'VOICE_DAILY_IP_CHARS',
          'VOICE_DAILY_IP_STT', 'VOICE_CACHE_MB'] as $k) {
    if (!str_contains($ex, "define('$k'") || !str_contains($db, "'$k'")) $missing[] = $k;
}
check('كل ثابت موثّق في config.example.php وله قيمة افتراضية في db.php', !$missing, implode('، ', $missing));
check('chat-stream.php يحرر قفل الجلسة قبل البث', str_contains((string)@file_get_contents($root . '/api/chat-stream.php'), 'session_write_close();'));

rmTree($tmp);
echo "\n" . ($fails ? "✗ فشل $fails فحصاً\n" : "✓ كل الفحوص ناجحة\n");
exit($fails ? 1 : 0);
