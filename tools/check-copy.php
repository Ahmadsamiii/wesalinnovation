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
 *      وما فيها من HTML مباشر
 *    - مساحة العمل: قوالب Blade، والسلاسل في app وconfig وlang وdatabase/seeders
 *      وroutes، وترجمات lang/*.json، وسلاسل resources/js
 *  ولا يفحص التعليقات البرمجية ولا CSS. الشرطة بين قوسين مثل «(—)» ذِكرٌ للحرف
 *  نفسه لا استعمالٌ له، فتُتجاوز.
 *  ويطبع تنبيهاً غير مانع لعبارات جاهزة يُفضّل تجنّبها (انظر README.md).
 *  لا يحتاج قاعدة بيانات ولا إعدادات ولا اعتماديات Composer.
 * ========================================================================== */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("هذا السكربت يُشغَّل من الطرفية فقط.\n");
}

$ROOT = realpath(__DIR__ . '/..');
const HTML_FILES = ['index.html', 'corporate.html', 'survey.html', 'ticket.html'];
const JS_FILES   = ['assets/landing-editor.js'];
/* مساحة العمل: مجلدات PHP التي تحمل نصوصاً ظاهرة (المصانع والاختبارات بيانات وهمية) */
const WS_PHP_DIRS = ['app', 'config', 'lang', 'database/seeders', 'routes'];
const CLICHES    = ['رحلتك المعرفية', 'في صميم', 'تجربة فريدة', 'قوة الذكاء الاصطناعي', 'أحدث تقنيات',
                    'ليس مجرد', 'ليست مجرد', 'لا شعارات', 'بكل سهولة ويسر', 'نؤمن بأن'];

$errors = 0;
$warnings = 0;

/** يزيل ذِكر الحرف نفسه مثل (—) أو «–» ثم يبحث عن الشرطات */
function hasDash(string $s): bool {
    $s = preg_replace('/[(«]\s?[\x{2013}\x{2014}]\s?[)»]/u', '', $s);
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

function check(string $file, int $line, string $kind, string $text): void {
    if (hasDash($text)) report($file, $line, $kind, $kind === 'HTML' || $kind === 'Blade' ? (trim(strip_tags($text)) ?: $text) : $text);
    warnCliches($file, $line, $text);
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

/** سلاسل PHP النصية بلا علامتي التنصيص، وما بين وسوم PHP من HTML مباشر */
function scanPhp(string $file, string $code, int $base = 1): void {
    foreach (token_get_all($code) as $tk) {
        if (!is_array($tk)) continue;
        $line = $base + $tk[2] - 1;
        if ($tk[0] === T_INLINE_HTML) {
            scanMarkup($file, $tk[1], $line);
        } elseif ($tk[0] === T_CONSTANT_ENCAPSED_STRING) {
            check($file, $line, 'PHP', substr($tk[1], 1, -1));
        } elseif ($tk[0] === T_ENCAPSED_AND_WHITESPACE) {
            check($file, $line, 'PHP', $tk[1]);
        }
    }
}

/** HTML أو قالب Blade: النص والسمات سطراً سطراً، وسلاسل <script> وكتل @php وحدها */
function scanMarkup(string $file, string $src, int $base = 1, bool $blade = false): void {
    /* كل مرحلة تستبدل ما تفحصه بأسطر فارغة، فيبقى رقم السطر من أي مرحلة صحيحاً */
    if ($blade) {
        $src = preg_replace_callback('/\{\{--.*?--\}\}/s', fn ($m) => blankOut($m[0]), $src);
        $at = $src;
        $src = preg_replace_callback('/(?<!@)@php(?!\s*\()(.*?)@endphp/s', function ($m) use ($file, $at, $base) {
            scanPhp($file, '<?php ' . $m[1][0], $base + substr_count($at, "\n", 0, $m[1][1]));
            return blankOut($m[0][0]);
        }, $src, -1, $count, PREG_OFFSET_CAPTURE);
    }
    $at = $src;
    $markup = preg_replace_callback('/<script\b([^>]*)>(.*?)<\/script>/is', function ($m) use ($file, $at, $base) {
        if (stripos($m[1][0], 'application/ld+json') === false) {
            foreach (jsStrings($m[2][0], $base + substr_count($at, "\n", 0, $m[2][1])) as [$ln, $s]) check($file, $ln, 'JS', $s);
        }
        return blankOut($m[0][0]);
    }, $src, -1, $count, PREG_OFFSET_CAPTURE);
    /* ما يبقى بعد حذف السكربت والأنماط والتعليقات نصٌّ ظاهر أو سمة ظاهرة */
    $markup = preg_replace_callback('/<!--.*?-->|<style\b.*?<\/style>/is', fn ($m) => blankOut($m[0]), $markup);
    foreach (explode("\n", $markup) as $k => $row) {
        check($file, $base + $k, $blade ? 'Blade' : 'HTML', $row);
    }
}

/** ملفات مجلد بامتداد معيّن، بترتيب ثابت */
function filesIn(string $dir, string $suffix): array {
    if (!is_dir($dir)) return [];
    $out = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $f) {
        if (str_ends_with($f->getFilename(), $suffix)) $out[] = $f->getPathname();
    }
    sort($out);
    return $out;
}

$rel = fn (string $path): string => ltrim(substr($path, strlen($ROOT)), '/');

echo "صفحات HTML:\n";
foreach (HTML_FILES as $f) {
    scanMarkup($f, file_get_contents("$ROOT/$f"));
}

echo "ملفات JS:\n";
foreach (JS_FILES as $f) {
    foreach (jsStrings(file_get_contents("$ROOT/$f")) as [$ln, $s]) check($f, $ln, 'JS', $s);
}

echo "رسائل الخادم (api/*.php):\n";
foreach (glob("$ROOT/api/*.php") as $path) {
    scanPhp($rel($path), file_get_contents($path));
}

echo "مساحة العمل (workspace/):\n";
$ws = "$ROOT/workspace";
foreach (filesIn("$ws/resources/views", '.blade.php') as $path) {
    scanMarkup($rel($path), file_get_contents($path), 1, true);
}
foreach (WS_PHP_DIRS as $dir) {
    foreach (filesIn("$ws/$dir", '.php') as $path) scanPhp($rel($path), file_get_contents($path));
}
foreach (glob("$ws/lang/*.json") ?: [] as $path) {
    /* المفاتيح نصوص Laravel الإنجليزية الأصلية، والقيم ترجمتها الظاهرة */
    foreach (explode("\n", file_get_contents($path)) as $k => $row) {
        if (preg_match('/^\s*"(?:[^"\\\\]|\\\\.)*"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/u', $row, $m)) check($rel($path), $k + 1, 'JSON', $m[1]);
    }
}
foreach (filesIn("$ws/resources/js", '.js') as $path) {
    foreach (jsStrings(file_get_contents($path)) as [$ln, $s]) check($rel($path), $ln, 'JS', $s);
}

if ($warnings) echo "\n! $warnings تنبيه لعبارات جاهزة (لا يمنع النجاح)\n";
echo $errors ? "\n✗ $errors نص ظاهر فيه شرطة طويلة أو قصيرة\n" : "\n✓ لا شرطات في النصوص الظاهرة\n";
exit($errors ? 1 : 0);
