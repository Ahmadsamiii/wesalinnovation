<?php
/* ==========================================================================
 *  وصال: دوال الصوت السعودي ومزوّداته، تستخدمها نقطة api/voice.php
 *
 *  بلا اعتماد على قاعدة البيانات أو الجلسة، فيضمّنها api/diag.php للفحص الحي
 *  وtools/check-voice.php للفحص دون شبكة. الثوابت (GROQ_KEY وغيرها) من
 *  config.php وقيمها الافتراضية في db.php، وشرحها في config.example.php.
 * ========================================================================== */

const VOICE_MAX_CHARS    = 200;          // حد Groq للطلب الواحد، والواجهة تقسّم تحته
const VOICE_STT_MAX_BYTES = 4 * 1048576;  // دقيقة تسجيل بأي صيغة يسجّلها متصفح أقل من هذا بكثير
/* عبارات يكتبها Whisper حين يسمع صمتاً أو ضجيجاً، تعلّمها من ترجمات المقاطع المرئية */
const VOICE_STT_NOISE = ['اشتركوا في القناة', 'اشترك في القناة', 'شكرا للمشاهدة', 'شكرا على المشاهدة',
                         'شكرا لكم على المشاهدة', 'ترجمة نانسي قنقر', 'نانسي قنقر', 'موسيقى'];
/* يرجّح مفردات المنصة ولهجتها عند التفريغ دون أن يفرض نصاً */
const VOICE_STT_PROMPT = 'سؤال باللهجة السعودية عن حقوق الأشخاص ذوي الإعاقة والخدمات المقدمة لهم، مثل بطاقة إثبات الإعاقة وهيئة رعاية الأشخاص ذوي الإعاقة.';

/* ---------- التخزين: الحالة والذاكرة المؤقتة ---------- */

/** مجلد الصوت في التخزين الخاص، أو '' إن تعذّر إنشاؤه (يعمل كل شيء بلا تخزين مؤقت) */
function voiceDir(): string {
    static $dir = null;
    if ($dir !== null) return $dir;
    $dir = rtrim(defined('STORAGE_DIR') ? STORAGE_DIR : dirname(__DIR__) . '/storage', '/') . '/voice';
    if (!is_dir($dir . '/cache') && !@mkdir($dir . '/cache', 0750, true)) return $dir = '';
    if (!is_file($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
    return $dir;
}

function voiceState(): array {
    $dir = voiceDir();
    if ($dir === '' || !is_file($dir . '/state.json')) return [];
    $j = json_decode((string)@file_get_contents($dir . '/state.json'), true);
    return is_array($j) ? $j : [];
}

/** يوقف طبقة مدة محددة (نفاد حصة، أو مفتاح مرفوض، أو عطل عند المزوّد) */
function voicePause(string $tier, int $seconds, string $why): void {
    @error_log('[wesal-voice] ' . $tier . ' paused ' . $seconds . 's: ' . $why);
    $dir = voiceDir();
    if ($dir === '' || !($fp = @fopen($dir . '/state.json', 'c+'))) return;
    if (flock($fp, LOCK_EX)) {
        $j = json_decode((string)stream_get_contents($fp), true);
        if (!is_array($j)) $j = [];
        $until = time() + $seconds;
        if ((int)($j[$tier]['until'] ?? 0) < $until) $j[$tier] = ['until' => $until, 'why' => mb_substr($why, 0, 200)];
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($j, JSON_UNESCAPED_UNICODE));
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
}

/** الطبقات المضبوطة مفاتيحها بالترتيب، ومع $now تُستبعد الموقوفة حالياً */
function voiceTiers(?int $now = null): array {
    $state = $now === null ? [] : voiceState();
    $out = [];
    foreach (explode(',', VOICE_TTS_ORDER) as $t) {
        $t = strtolower(trim($t));
        if (in_array($t, $out, true)) continue;
        if (!($t === 'groq' && GROQ_KEY !== '') && !($t === 'azure' && AZURE_SPEECH_KEY !== '')) continue;
        if ($now !== null && (int)($state[$t]['until'] ?? 0) > $now) continue;
        $out[] = $t;
    }
    return $out;
}

/** ترتيب المحاولة لطلب: طبقة الرد المطلوبة أولاً إن كانت متاحة، ثم البقية */
function voiceOrder(string $want, array $available): array {
    if (!in_array($want, $available, true)) return $available;
    return array_values(array_unique(array_merge([$want], $available)));
}

function voiceName(string $tier, string $gender): string {
    if ($tier === 'groq') return $gender === 'female' ? GROQ_TTS_VOICE_FEMALE : GROQ_TTS_VOICE_MALE;
    return $gender === 'female' ? AZURE_TTS_VOICE_FEMALE : AZURE_TTS_VOICE_MALE;
}

/** نوع الملف الصوتي لكل طبقة: Groq يولّد WAV فقط لهذا النموذج، وAzure نطلب منه MP3 أصغر حجماً */
function voiceMime(string $tier): array {
    return $tier === 'groq' ? ['audio/wav', 'wav'] : ['audio/mpeg', 'mp3'];
}

function voiceCacheFile(string $tier, string $voice, string $text): string {
    $dir = voiceDir();
    if ($dir === '' || VOICE_CACHE_MB <= 0) return '';
    return $dir . '/cache/' . hash('sha256', $tier . '|' . $voice . '|' . $text) . '.' . voiceMime($tier)[1];
}

function voiceCachePut(string $file, string $audio): void {
    if ($file === '') return;
    $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($tmp, $audio) !== strlen($audio) || !@rename($tmp, $file)) @unlink($tmp);
    voiceCachePrune(dirname($file));
}

/** حذف الأقدم استخداماً حين يتجاوز الحجم السقف، احتمالياً حتى لا يُثقل كل طلب */
function voiceCachePrune(string $dir, bool $force = false): void {
    if (!$force && random_int(1, 40) !== 1) return;
    $list = [];
    $total = 0;
    foreach (array_merge(glob($dir . '/*.wav') ?: [], glob($dir . '/*.mp3') ?: []) as $f) {
        $s = @filesize($f);
        if ($s === false) continue;
        $total += $s;
        $list[] = [(int)@filemtime($f), $s, $f];
    }
    $cap = VOICE_CACHE_MB * 1048576;
    if ($total <= $cap) return;
    sort($list);
    foreach ($list as [, $s, $f]) {
        if ($total <= $cap * 0.8) break;
        if (@unlink($f)) $total -= $s;
    }
}

/* ---------- النص ---------- */

/** ما يُقرأ فقط: بلا وسوم ولا روابط ولا رموز تنسيق أو رموز تعبيرية */
function voiceCleanText(string $t): string {
    $t = strip_tags(mb_scrub($t, 'UTF-8'));
    $t = (string)preg_replace('/\[([^\]]*)\]\([^)]*\)/u', '$1', $t);   // [الاسم](الرابط): يُقرأ الاسم وحده
    $t = (string)preg_replace('/https?:\/\/\S+|www\.\S+/u', ' ', $t);
    $t = (string)preg_replace('/[*_#`>|~\[\]{}\x{2022}\x{25AA}\x{25CF}\x{25E6}]+/u', ' ', $t);
    $t = (string)preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}\x{200D}]/u', '', $t);
    $t = (string)preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $t);
    return trim((string)preg_replace('/\s+/u', ' ', $t));
}

/** SSML لطلب Azure، والنص مهرَّب لأنه قد يحوي < أو & */
function voiceSsml(string $text, string $voice): string {
    return "<speak version='1.0' xml:lang='ar-SA'><voice name='" . htmlspecialchars($voice, ENT_XML1 | ENT_QUOTES, 'UTF-8')
         . "'>" . htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</voice></speak>';
}

function voiceIsNoise(string $t): bool {
    $n = trim((string)preg_replace('/[\p{P}\s]+/u', ' ', $t));
    if ($n === '') return true;
    foreach (VOICE_STT_NOISE as $p) {
        if (mb_strpos($n, $p) !== false && mb_strlen($n) <= mb_strlen($p) + 6) return true;
    }
    return false;
}

/** صيغة التسجيل من أول بايتاته، لا من اسم الملف أو نوعه المعلَن */
function voiceSniffAudio(string $path): ?array {
    $h = (string)@file_get_contents($path, false, null, 0, 16);
    if (strlen($h) < 12) return null;
    if (strncmp($h, "\x1A\x45\xDF\xA3", 4) === 0) return ['audio/webm', 'webm'];
    if (strncmp($h, 'OggS', 4) === 0) return ['audio/ogg', 'ogg'];
    if (substr($h, 4, 4) === 'ftyp') return ['audio/mp4', 'm4a'];
    if (strncmp($h, 'RIFF', 4) === 0 && substr($h, 8, 4) === 'WAVE') return ['audio/wav', 'wav'];
    if (strncmp($h, 'fLaC', 4) === 0) return ['audio/flac', 'flac'];
    if (strncmp($h, 'ID3', 3) === 0 || (ord($h[0]) === 0xFF && (ord($h[1]) & 0xE0) === 0xE0)) return ['audio/mpeg', 'mp3'];
    return null;
}

/* ---------- المزوّدون ---------- */

/** POST عام: يعيد الحالة والجسم ونوعه ومهلة Retry-After بالثواني */
function voiceHttp(string $url, $body, array $headers, int $timeout): array {
    $hdr = [];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => $headers, CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$hdr): int {
            $p = strpos($line, ':');
            if ($p) $hdr[strtolower(trim(substr($line, 0, $p)))] = trim(substr($line, $p + 1));
            return strlen($line);
        },
    ]);
    $res  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    $ra = $hdr['retry-after'] ?? '';
    $retry = ctype_digit($ra) ? (int)$ra : ($ra !== '' && ($ts = strtotime($ra)) ? max(0, $ts - time()) : 0);
    return ['code' => $res === false ? 0 : $code, 'body' => (string)$res, 'type' => strtolower($hdr['content-type'] ?? ''),
            'retry' => $retry, 'err' => $err];
}

function voiceGroqTts(string $text, string $voice): array {
    return voiceHttp(rtrim(GROQ_BASE_URL, '/') . '/audio/speech',
        json_encode(['model' => GROQ_TTS_MODEL, 'input' => $text, 'voice' => $voice, 'response_format' => 'wav'], JSON_UNESCAPED_UNICODE),
        ['Content-Type: application/json', 'Authorization: Bearer ' . GROQ_KEY], 20);
}

/** AZURE_SPEECH_REGION اسم منطقة مثل uaenorth، أو رابط كامل لنقطة مخصصة */
function voiceAzureUrl(): string {
    $r = trim(AZURE_SPEECH_REGION);
    return preg_match('~^https?://~', $r) ? $r : 'https://' . $r . '.tts.speech.microsoft.com/cognitiveservices/v1';
}

function voiceAzureTts(string $text, string $voice): array {
    return voiceHttp(voiceAzureUrl(), voiceSsml($text, $voice), [
        'Ocp-Apim-Subscription-Key: ' . AZURE_SPEECH_KEY,
        'Content-Type: application/ssml+xml',
        'X-Microsoft-OutputFormat: audio-24khz-48kbitrate-mono-mp3',
        'User-Agent: Wesal',
    ], 20);
}

/** صوت صالح فعلاً: رد 200 بجسم صوتي لا رسالة خطأ بحالة ناجحة */
function voiceLooksLikeAudio(array $r): bool {
    if ($r['code'] !== 200 || strlen($r['body']) < 200) return false;
    $b = $r['body'];
    return strncmp($b, 'RIFF', 4) === 0 || strncmp($b, 'ID3', 3) === 0
        || (ord($b[0]) === 0xFF && (ord($b[1]) & 0xE0) === 0xE0) || strncmp($r['type'], 'audio/', 6) === 0;
}

/**
 * مدة إيقاف الطبقة بعد فشل، أو 0 إن كان الفشل خاصاً بهذا النص وحده.
 * 429 بلا Retry-After: عند Azure غالباً ضغط مؤقت على الصوت في المنطقة، فدقيقة تكفي،
 * إلا إن ذكر الرد الحصة صراحة. الحد الأعلى يوم وساعتان حتى لا تبقى طبقة موقوفة خطأً.
 */
function voicePauseFor(array $r): int {
    $c = $r['code'];
    if ($c === 429) {
        if ($r['retry'] > 0) return min($r['retry'], 26 * 3600);
        return stripos($r['body'], 'quota') !== false ? 6 * 3600 : 60;
    }
    if ($c === 401 || $c === 403) return 600;   // مفتاح مرفوض أو حصة شهرية منتهية
    if ($c === 0 || $c >= 500) return 60;       // انقطاع أو عطل عند المزوّد
    return 0;
}

function voiceSendAudio(string $audio, string $tier, string $cache): void {
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: ' . voiceMime($tier)[0]);
    header('Content-Length: ' . strlen($audio));
    header('Cache-Control: private, max-age=86400');
    header('X-Voice-Tier: ' . $tier);
    header('X-Voice-Cache: ' . $cache);
    echo $audio;
    exit;
}

/** طلبات المتصفح من صفحة أخرى تُرفض: لا يُستهلك رصيد الصوت من مواقع غيرنا */
function voiceSameOrigin(): bool {
    $site = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';
    if ($site !== '' && $site !== 'same-origin' && $site !== 'none') return false;
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === '' || $origin === 'null') return $origin === '';
    return strtolower((string)parse_url($origin, PHP_URL_HOST)) === strtolower((string)parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));
}
