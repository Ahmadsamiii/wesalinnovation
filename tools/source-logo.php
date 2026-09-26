<?php
/* ==========================================================================
 *  وصال: تجهيز شعار جهة لقسم «مصادر البيانات» في الصفحة الرئيسية
 *
 *  الاستخدام:
 *      php tools/source-logo.php <ملف الشعار> <النطاق> [--tint=#RRGGBB] [--scale=1]
 *  مثال:
 *      php tools/source-logo.php ~/Downloads/apd.png apd.gov.sa
 *
 *  يكتب assets/sources/<النطاق>.webp بلوحة موحّدة 800×320 (أربعة أضعاف مساحة
 *  العرض 200×80، فيبقى حاداً على الشاشات الكثيفة ومع التكبير)، حتى تظهر
 *  الشعارات متوازنة مهما اختلفت أبعاد ملفاتها الأصلية:
 *    - الخلفية البيضاء في ملف بلا شفافية تصير شفافة، والألوان تبقى كما هي
 *    - تُقصّ الحواف الفارغة حول الشعار
 *    - يُحجَّم الشعار بمساحته لا بعرضه: العريض لا يطغى والمربع لا يصغر
 *  بعدها أضف النطاق إلى SRC_LOGOS في index.html وحدّث بطاقته الثابتة إن كانت
 *  من البطاقات الافتراضية، ثم شغّل php tools/check-landing.php.
 *
 *  --tint   يلوّن شعاراً أحادي اللون بلون واحد، مثل شعار لا تتوفر منه إلا نسخة
 *           بيضاء (للخلفيات الداكنة)
 *  --scale  يكبّر الشعار أو يصغّره بعد الموازنة إن بدا أثقل أو أخف من جيرانه
 *  يقبل PNG وJPEG وWebP وGIF. ملف SVG: صدّره PNG بعرض 1600 بكسل على الأقل.
 * ========================================================================== */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("هذا السكربت يُشغَّل من الطرفية فقط.\n");
}

const LOGO_W = 800, LOGO_H = 320;
const LOGO_AREA = 0.36;    // مساحة الشعار المربع من اللوحة
const LOGO_BIAS = 0.4;     // 0.5 مساحة متساوية تماماً، والأقل يكبّر العريض قليلاً لأن حروفه أدق
const LOGO_FIT = 0.96;     // أقصى ما يشغله الشعار من عرض اللوحة وارتفاعها
const INK_ALPHA = 112;     // ألفا GD (0 معتم، 127 شفاف): ما فوقها فراغ لا يدخل في القص
const SRC_MAX = 1600;      // الملف الأكبر يُصغَّر أولاً، فاللوحة 800 لا تحتاج أكثر

ini_set('memory_limit', '512M');   // مصفوفات البكسل لملف بلا شفافية

function stop(string $msg): void { fwrite(STDERR, "✗ $msg\n"); exit(1); }

$args = [];
$opt = ['tint' => null, 'scale' => 1.0];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--tint=#?([0-9a-f]{6})$/i', $a, $m)) $opt['tint'] = array_map('hexdec', str_split($m[1], 2));
    elseif (preg_match('/^--scale=([0-9]*\.?[0-9]+)$/', $a, $m)) $opt['scale'] = (float)$m[1];
    elseif (str_starts_with($a, '--')) stop("خيار غير معروف: $a");
    else $args[] = $a;
}
[$src, $domain] = $args + [null, null];
if (!$src || !$domain) {
    exit("الاستخدام: php tools/source-logo.php <ملف الشعار> <النطاق> [--tint=#RRGGBB] [--scale=1]\n");
}
$domain = strtolower($domain);
if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $domain)) {
    stop("النطاق غير صحيح، ومثاله: moh.gov.sa");
}
if ($opt['scale'] <= 0 || $opt['scale'] > 3) stop('--scale بين 0 و3');
if (!is_file($src)) stop("لم أجد الملف: $src");

$im = @imagecreatefromstring((string)file_get_contents($src));
if (!$im) stop('الملف ليس صورة مدعومة (PNG أو JPEG أو WebP أو GIF).');
imagepalettetotruecolor($im);
imagealphablending($im, false);
imagesavealpha($im, true);
$w = imagesx($im);
$h = imagesy($im);
[$w0, $h0] = [$w, $h];
if (max($w, $h) > SRC_MAX) {
    $k = SRC_MAX / max($w, $h);
    $small = imagecreatetruecolor(max(1, (int)round($w * $k)), max(1, (int)round($h * $k)));
    imagealphablending($small, false);
    imagesavealpha($small, true);
    imagecopyresampled($small, $im, 0, 0, 0, 0, imagesx($small), imagesy($small), $w, $h);
    [$im, $w, $h] = [$small, imagesx($small), imagesy($small)];
}

/* ملف بلا شفافية: الأبيض يصير شفافاً. شفافية كل بكسل = بُعده عن الأبيض ÷ بُعد
   أقرب لون مصمت حوله، فالشكل الفاتح (ذهبي مثلاً) يبقى معتماً بالكامل، والحافة
   الناعمة حول الحروف تبقى ناعمة، واللون فوق الأبيض يطابق الأصل. */
$opaque = true;
for ($y = 0; $y < $h && $opaque; $y += max(1, intdiv($h, 64))) {
    for ($x = 0; $x < $w; $x += max(1, intdiv($w, 64))) {
        if ((imagecolorat($im, $x, $y) >> 24) & 0x7F) { $opaque = false; break; }
    }
}
if ($opaque) {
    $dist = [];
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $c = imagecolorat($im, $x, $y);
            $dist[] = 255 - min(($c >> 16) & 0xFF, ($c >> 8) & 0xFF, $c & 0xFF);
        }
    }
    // أقصى بُعد في جوار 5×5، أفقياً ثم رأسياً
    $near = $dist;
    foreach ([[1, $w], [$w, $h]] as [$step, $len]) {
        $prev = $near;
        foreach ($prev as $i => $v) {
            $p = $step === 1 ? $i % $w : intdiv($i, $w);
            for ($o = -2; $o <= 2; $o++) {
                if ($o && $p + $o >= 0 && $p + $o < $len && $prev[$i + $o * $step] > $v) $v = $prev[$i + $o * $step];
            }
            $near[$i] = $v;
        }
    }
    $clear = imagecolorallocatealpha($im, 255, 255, 255, 127);
    foreach ($dist as $i => $dv) {
        $a = $dv < 10 ? 0 : min(1, $dv / max(64, $near[$i]));
        $x = $i % $w;
        $y = intdiv($i, $w);
        if ($a < 0.12) {   // ضجيج ضغط JPEG حول الشعار
            imagesetpixel($im, $x, $y, $clear);
            continue;
        }
        $c = imagecolorat($im, $x, $y);
        $px = array_map(fn($v) => max(0, (int)round(255 - (255 - $v) / $a)), [($c >> 16) & 0xFF, ($c >> 8) & 0xFF, $c & 0xFF]);
        imagesetpixel($im, $x, $y, imagecolorallocatealpha($im, $px[0], $px[1], $px[2], (int)round((1 - $a) * 127)));
    }
}

/* حدود الحبر: أول وآخر صف وعمود فيهما بكسل مرئي */
$x0 = $w; $y0 = $h; $x1 = -1; $y1 = -1;
for ($y = 0; $y < $h; $y++) {
    for ($x = 0; $x < $w; $x++) {
        if (((imagecolorat($im, $x, $y) >> 24) & 0x7F) < INK_ALPHA) {
            if ($x < $x0) $x0 = $x;
            if ($x > $x1) $x1 = $x;
            if ($y < $y0) $y0 = $y;
            if ($y > $y1) $y1 = $y;
        }
    }
}
if ($x1 < 0) stop('الصورة فارغة: لا يوجد فيها شعار مرئي.');
$bw = $x1 - $x0 + 1;
$bh = $y1 - $y0 + 1;

/* الحجم بالمساحة: الشعار المربع بمساحة LOGO_AREA، وكلما عرض الشعار قصر بنسبة
   (العرض ÷ الارتفاع) مرفوعة إلى LOGO_BIAS */
$ratio = $bw / $bh;
$th = sqrt(LOGO_AREA * LOGO_W * LOGO_H) * $ratio ** -LOGO_BIAS * $opt['scale'];
$tw = $th * $ratio;
$k = min(1, LOGO_FIT * LOGO_W / $tw, LOGO_FIT * LOGO_H / $th);
$tw = max(1, (int)round($tw * $k));
$th = max(1, (int)round($th * $k));

$out = imagecreatetruecolor(LOGO_W, LOGO_H);
imagealphablending($out, false);
imagesavealpha($out, true);
imagefill($out, 0, 0, imagecolorallocatealpha($out, 255, 255, 255, 127));
// GD يرجّح الألوان بألفا عند التحجيم، فلا هالة داكنة أو فاتحة حول الحواف
imagecopyresampled($out, $im, intdiv(LOGO_W - $tw, 2), intdiv(LOGO_H - $th, 2), $x0, $y0, $tw, $th, $bw, $bh);

if ($opt['tint']) {
    [$r, $g, $b] = $opt['tint'];
    for ($y = 0; $y < LOGO_H; $y++) {
        for ($x = 0; $x < LOGO_W; $x++) {
            $a = (imagecolorat($out, $x, $y) >> 24) & 0x7F;
            if ($a < 127) imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, $r, $g, $b, $a));
        }
    }
}

$dir = __DIR__ . '/../assets/sources';
if (!is_dir($dir) && !mkdir($dir, 0755, true)) stop("تعذّر إنشاء $dir");
$dst = "$dir/$domain.webp";
// بلا فقد: حواف الحروف تبقى حادة (الثابت من PHP 8.1، وقبلها أعلى جودة)
if (!imagewebp($out, $dst, defined('IMG_WEBP_LOSSLESS') ? IMG_WEBP_LOSSLESS : 100)) stop("تعذّر كتابة $dst");

printf("✓ assets/sources/%s.webp  (%d×%d من %d×%d، الشعار %d×%d، %.1f KB)\n",
    $domain, LOGO_W, LOGO_H, $w0, $h0, $tw, $th, filesize($dst) / 1024);
echo "  أضف '$domain' إلى SRC_LOGOS في index.html إن لم يكن فيها، ثم شغّل php tools/check-landing.php\n";
