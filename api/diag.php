<?php
/* أداة تشخيص للمشرف فقط — افتح wesalinnovation.sa/api/diag.php وأنت مسجّل دخول بحساب المشرف */
require_once __DIR__ . '/db.php';

$u = null;
try { $u = currentUser(); } catch (Throwable $e) {}
if (!$u || $u['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'هذه الصفحة خاصة بمدير النظام. سجّل دخولك بحسابه في المتصفح نفسه ثم افتحها مرة أخرى.'], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------- فحص المزوّد ---------- */
function tryProvider(string $name, string $url, array $payload, array $headers): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $headers),
        CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 20,
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    $body = mb_substr((string)$res, 0, 400);
    if ($err)                    $status = 'فشل الاتصال بالمزوّد من الخادم: ' . $err;
    elseif ($code === 200)       $status = 'يعمل بنجاح ✅';
    elseif ($code === 400 && str_contains($body, 'API_KEY_INVALID')) $status = 'المفتاح غير صالح ❌. أنشئ مفتاحاً جديداً من aistudio.google.com/apikey (يبدأ بـ AIza) وضعه في config.php';
    elseif ($code === 400)       $status = 'طلب مرفوض (400). راجع التفاصيل';
    elseif ($code === 401 || $code === 403) $status = 'المفتاح مرفوض أو بلا صلاحية ❌. جدّد المفتاح';
    elseif ($code === 404)       $status = 'اسم النموذج غير موجود. راجع GEMINI_MODEL في config.php';
    elseif ($code === 429)       $status = 'تجاوزت حد الاستخدام مؤقتاً. انتظر دقائق ثم جرّب';
    else                         $status = 'رد غير متوقع (HTTP ' . $code . ')';
    return ['provider' => $name, 'http' => $code, 'status' => $status, 'sample' => $body];
}

/* ---------- فحص ملفات الصور: هل هي موجودة؟ وهل محتواها فعلاً صورة؟ ---------- */
function sniffImage(string $rel): array {
    $path = dirname(__DIR__) . '/' . $rel;
    if (!file_exists($path)) return ['الحالة' => '⚠️ غير موجود. ارفعه بهذا الاسم بالضبط في public_html'];
    $sz = filesize($path);
    $head = (string)file_get_contents($path, false, null, 0, 256);
    $sig = 'غير معروف';
    if (str_starts_with($head, "\x89PNG"))                    $sig = 'png';
    elseif (str_starts_with($head, "\xFF\xD8\xFF"))           $sig = 'jpg';
    elseif (str_starts_with($head, 'GIF8'))                   $sig = 'gif';
    elseif (str_starts_with($head, 'RIFF') && strpos($head, 'WEBP') !== false) $sig = 'webp';
    elseif (str_starts_with($head, "\x00\x00\x01\x00"))       $sig = 'ico';
    elseif (str_starts_with($head, '%PDF'))                   $sig = 'pdf';
    elseif (preg_match('/^\s*(<\?xml|<svg)/i', $head))        $sig = 'svg';
    elseif (preg_match('/<!DOCTYPE html|<html/i', $head))     $sig = 'html';
    $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
    $extNorm = $ext === 'jpeg' ? 'jpg' : $ext;
    $kb = round($sz / 1024) . 'KB';
    if ($sz < 200)        return ['الحالة' => '❌ الملف شبه فارغ (' . $sz . ' بايت). أعد رفعه', 'الحجم' => $kb];
    if ($sig === 'html')  return ['الحالة' => '❌ المحتوى صفحة HTML وليس صورة. غالباً حُفظت صفحة العرض في درايف بدل الملف؛ افتح الرابط واضغط زر التنزيل ثم ارفع الصورة نفسها', 'الحجم' => $kb];
    if ($sig === 'pdf')   return ['الحالة' => '❌ الملف PDF وليس صورة. صدّره بصيغة PNG', 'الحجم' => $kb];
    if ($sig === 'svg' && $extNorm !== 'svg')
        return ['الحالة' => '❌ الامتداد .' . $ext . ' لكن المحتوى SVG. صدّره بصيغة PNG أو غيّر امتداده إلى .svg', 'الحجم' => $kb];
    if ($sig !== 'غير معروف' && $extNorm !== $sig && !($sig === 'jpg' && $extNorm === 'jpg'))
        return ['الحالة' => '⚠️ الامتداد .' . $ext . ' لكن المحتوى ' . $sig . '. غيّر الامتداد إلى الصحيح', 'الحجم' => $kb, 'النوع_الفعلي' => $sig];
    if ($sig === 'غير معروف') return ['الحالة' => '❌ المحتوى غير مقروء كصورة. أعد التصدير والرفع', 'الحجم' => $kb];
    return ['الحالة' => '✅ سليم (' . $sig . '، ' . $kb . ')'];
}

$out = [
    'php'  => PHP_VERSION,
    'curl' => function_exists('curl_init') ? 'متوفر' : 'غير متوفر ❌',
    'mail' => function_exists('mail') ? 'متوفر' : 'غير متوفر',
];

try { db()->query('SELECT 1'); $out['database'] = 'متصلة ✅'; }
catch (Throwable $e) { $out['database'] = 'فشل الاتصال ❌. راجع DB_PASS في config.php'; }

if (!defined('GEMINI_KEY') || GEMINI_KEY === '') {
    $out['gemini'] = ['status' => 'المفتاح فارغ في config.php ❌'];
} else {
    $out['gemini'] = tryProvider('gemini',
        'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent?key=' . GEMINI_KEY,
        ['contents' => [['role' => 'user', 'parts' => [['text' => 'قل كلمة: تمام']]]],
         'generationConfig' => ['maxOutputTokens' => 10]], []);
    $out['gemini']['key_hint'] = substr(GEMINI_KEY, 0, 6) . '…' . substr(GEMINI_KEY, -4) . ' (المفاتيح الصحيحة تبدأ عادة بـ AIza)';
}

if (defined('OPENAI_KEY') && OPENAI_KEY !== '') {
    $out['openai'] = tryProvider('openai', 'https://api.openai.com/v1/chat/completions',
        ['model' => OPENAI_MODEL, 'messages' => [['role' => 'user', 'content' => 'قل: تمام']], 'max_tokens' => 5],
        ['Authorization: Bearer ' . OPENAI_KEY]);
} else { $out['openai'] = ['status' => 'غير مفعّل (اختياري)']; }

/* فحص ملفات الشعار والأيقونات */
$out['ملفات_الشعار'] = [];
foreach (['logo-color.png','logo-white.png','logo.png','favicon-32.png','favicon-192.png','favicon.ico','og-cover.png',
          'partners/kscdr.png','partners/hackathon.png','partners/ai-year.png'] as $f) {
    $out['ملفات_الشعار'][$f] = sniffImage($f);
}

/* كل ملفات الصور الموجودة فعلاً في جذر الموقع — يكشف أخطاء التسمية والحروف الكبيرة */
$root = dirname(__DIR__);
$found = [];
foreach ((array)glob($root . '/*.{png,PNG,jpg,JPG,jpeg,svg,SVG,webp,ico}', GLOB_BRACE) as $p) {
    $found[] = basename($p) . ' (' . round(filesize($p) / 1024) . 'KB)';
}
$out['الصور_الموجودة_في_الجذر'] = $found ?: ['لا توجد أي صور في جذر الموقع!'];

$g = $out['gemini']['status'] ?? '';
$logoBad = [];
foreach (['logo-color.png','logo-white.png'] as $f)
    if (!str_contains($out['ملفات_الشعار'][$f]['الحالة'], '✅')) $logoBad[] = $f;
$out['الخلاصة'] = (str_contains($g, '✅') ? 'النموذج يعمل ✅. ' : 'النموذج لا يستجيب. عالج حالة Gemini أعلاه. ')
    . ($logoBad ? 'ملفات الشعار فيها مشكلة (' . implode('، ', $logoBad) . '). اقرأ حالتها أعلاه.' : 'ملفات الشعار سليمة ✅. إن لم يظهر الشعار فالسبب نسخة محفوظة في المتصفح أو في Cloudflare: اضغط Ctrl+Shift+R ثم نفّذ Purge Cache.');

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
