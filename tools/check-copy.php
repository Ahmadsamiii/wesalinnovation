<?php
/* ==========================================================================
 *  وصال: فحص النصوص الظاهرة للمستخدم
 *
 *  الاستخدام:
 *      php tools/check-copy.php
 *
 *  يمنع عودة الشرطة الطويلة (U+2014) والقصيرة (U+2013) إلى أي نص يراه الزائر
 *  أو المستخدم، لأنها من أوضح علامات النص المولَّد آلياً. يفحص:
 *    - صفحات HTML: النصوص والسمات، والسلاسل داخل <script>
 *    - assets/landing-editor.js: سلاسله النصية
 *    - api/*.php: كل سلسلة نصية (رسائل الأخطاء والبريد والنصوص الافتراضية)
 *  ولا يفحص التعليقات البرمجية ولا CSS. الشرطة المحصورة بين قوسين أو علامتي
 *  تنصيص مثل «(—)» ذِكرٌ للحرف نفسه لا استعمالٌ له، فتُتجاوز.
 *  ويطبع تنبيهاً غير مانع لعبارات جاهزة يُفضّل تجنّبها (انظر README.md).
 *  لا يحتاج قاعدة بيانات ولا إعدادات.
 * ========================================================================== */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("هذا السكربت يُشغَّل من الطرفية فقط.\n");
}

$ROOT = realpath(__DIR__ . '/..');
const HTML_FILES = ['index.html', 'corporate.html', 'survey.html', 'ticket.html'];
const JS_FILES   = ['assets/landing-editor.js'];
/* أدوات تشخيص للمطوّر، يوصي README بحذفها من الإنتاج */
const PHP_SKIP   = ['diag.php', 'stream-test.php'];
const CLICHES    = ['رحلتك المعرفية', 'في صميم', 'تجربة فريدة', 'قوة الذكاء الاصطناعي', 'أحدث تقنيات',
                    'ليس مجرد', 'ليست مجرد', 'لا شعارات', 'بكل سهولة ويسر', 'نؤمن بأن'];

$errors = 0;
$warnings = 0;

/** يزيل ذِكر الحرف نفسه مثل (—) أو «–» ثم يبحث عن الشرطات */
function hasDash(string $s): bool {
    $s = preg_replace('/[(«"\'`]\s?[\x{2013}\x{2014}]\s?[)»"\'`]/u', '', $s);
    return (bool) preg_match('/[\x{2013}\x{2014}]/u', $s);
}

function report(string $file, int $line, string $kind, string $text): void {
    global $errors;
    $errors++;
    $t = trim(preg_replace('/\s+/u', ' ', $text));
    echo "  ✗ $file:$line [$kind] " . mb_substr($t, 0, 140) . (mb_strlen($t) > 140 ? '…' : '') . "\n";
}

function warnCliches(string $file, int $line, string $text): void {
    global $warnings;
    foreach (CLICHES as $c) {
        if (mb_strpos($text, $c) !== false) {
            $warnings++;
            echo "  ! $file:$line عبارة جاهزة «{$c}»\n";
        }
    }
}

/** السلاسل النصية في شيفرة JS مع أرقام أسطرها، بتخطي التعليقات والتعابير النمطية */
function jsStrings(string $src, int $line = 1): array {
    $out = [];
    $n = strlen($src);
    $i = 0;
    $prev = '';   // '' أو op: قد يبدأ بعده تعبير نمطي؛ id/str: قسمة
    $kw = ['return', 'typeof', 'case', 'in', 'of', 'else', 'do', 'void', 'new', 'delete', 'throw'];
    while ($i < $n) {
        $c = $src[$i];
        if ($c === "\n") { $line++; $i++; continue; }
        if ($c === ' ' || $c === "\t" || $c === "\r") { $i++; continue; }
        if ($c === '/' && ($src[$i + 1] ?? '') === '/') {
            $j = strpos($src, "\n", $i);
            $i = $j === false ? $n : $j;
            continue;
        }
        if ($c === '/' && ($src[$i + 1] ?? '') === '*') {
            $j = strpos($src, '*/', $i + 2);
            $end = $j === false ? $n : $j + 2;
            $line += substr_count($src, "\n", $i, $end - $i);
            $i = $end;
            continue;
        }
        if ($c === "'" || $c === '"' || $c === '`') {
            $start = $line;
            $buf = '';
            $j = $i + 1;
            while ($j < $n) {
                $d = $src[$j];
                if ($d === '\\') { $buf .= substr($src, $j, 2); $j += 2; continue; }
                if ($d === "\n") { $line++; if ($c !== '`') break; }
                if ($d === $c) break;
                $buf .= $d;
                $j++;
            }
            $out[] = [$start, $buf];
            $i = $j + 1;
            $prev = 'str';
            continue;
        }
        if ($c === '/' && ($prev === '' || $prev === 'op')) {
            $j = $i + 1;
            $inClass = false;
            while ($j < $n) {
                $d = $src[$j];
                if ($d === '\\') { $j += 2; continue; }
                if ($d === '[') $inClass = true;
                elseif ($d === ']') $inClass = false;
                elseif ($d === '/' && !$inClass) break;
                elseif ($d === "\n") break;
                $j++;
            }
            $i = $j + 1;
            while ($i < $n && ctype_alpha($src[$i])) $i++;
            $prev = 'str';
            continue;
        }
        if (ctype_alnum($c) || $c === '_' || $c === '$') {
            $j = $i;
            while ($j < $n && (ctype_alnum($src[$j]) || $src[$j] === '_' || $src[$j] === '$')) $j++;
            $prev = in_array(substr($src, $i, $j - $i), $kw, true) ? 'op' : 'id';
            $i = $j;
            continue;
        }
        if (ord($c) >= 0x80) { $prev = 'id'; $i++; continue; }
        $prev = ($c === ')' || $c === ']' || $c === '}') ? 'id' : 'op';
        $i++;
    }
    return $out;
}

/** يستبدل مقطعاً بأسطر فارغة بعددها حتى تبقى أرقام الأسطر صحيحة */
function blankOut(string $s): string { return str_repeat("\n", substr_count($s, "\n")); }

echo "صفحات HTML:\n";
foreach (HTML_FILES as $f) {
    $src = file_get_contents("$ROOT/$f");
    $scripts = [];
    $markup = preg_replace_callback('/<script\b([^>]*)>(.*?)<\/script>/is', function ($m) use (&$scripts) {
        if (stripos($m[1], 'application/ld+json') === false) $scripts[] = $m[0];
        return blankOut($m[0]);
    }, $src);
    /* ما يبقى بعد حذف السكربت والأنماط والتعليقات نصٌّ ظاهر أو سمة ظاهرة */
    $markup = preg_replace_callback('/<!--.*?-->|<style\b.*?<\/style>/is', fn($m) => blankOut($m[0]), $markup);
    foreach (explode("\n", $markup) as $k => $row) {
        if (hasDash($row)) report($f, $k + 1, 'HTML', trim(strip_tags($row)) ?: $row);
        warnCliches($f, $k + 1, $row);
    }
    foreach ($scripts as $block) {
        $at = strpos($src, $block);
        $base = substr_count($src, "\n", 0, $at) + 1;
        foreach (jsStrings($block, $base) as [$ln, $s]) {
            if (hasDash($s)) report($f, $ln, 'JS', $s);
            warnCliches($f, $ln, $s);
        }
    }
}

echo "ملفات JS:\n";
foreach (JS_FILES as $f) {
    foreach (jsStrings(file_get_contents("$ROOT/$f")) as [$ln, $s]) {
        if (hasDash($s)) report($f, $ln, 'JS', $s);
        warnCliches($f, $ln, $s);
    }
}

echo "رسائل الخادم (api/*.php):\n";
foreach (glob("$ROOT/api/*.php") as $path) {
    $f = 'api/' . basename($path);
    if (in_array(basename($path), PHP_SKIP, true)) continue;
    foreach (token_get_all(file_get_contents($path)) as $tk) {
        if (!is_array($tk) || !in_array($tk[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)) continue;
        if (hasDash($tk[1])) report($f, $tk[2], 'PHP', $tk[1]);
        warnCliches($f, $tk[2], $tk[1]);
    }
}

if ($warnings) echo "\n! $warnings تنبيه لعبارات جاهزة (لا يمنع النجاح)\n";
echo $errors ? "\n✗ $errors نص ظاهر فيه شرطة طويلة أو قصيرة\n" : "\n✓ لا شرطات في النصوص الظاهرة\n";
exit($errors ? 1 : 0);
