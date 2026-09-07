<?php
/* أداة جلب مكتبة أبحاث مركز الملك سلمان لأبحاث الإعاقة إلى قاعدة معرفة وصال.
   تعمل من سطر الأوامر على الخادم فقط (بيئة التطوير لا تصل إلى موقع المركز).

   الاستخدام:
     php tools/kscdr-scan.php scan  [رابط البداية]   مسح فقط: يطبع بنية الصفحة وقائمة الروابط دون تنزيل
     php tools/kscdr-scan.php fetch [رابط البداية]   جلب: يتتبع الصفحات وينزّل ملفات PDF ويكتب فهرس JSON
   الملفات تُحفظ في STORAGE_DIR/kscdr (خارج المجلد العام إن ضُبط في config.php)، ويُنشأ فيه
   ملف .htaccess يمنع الوصول المباشر احتياطاً. */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('cli only'); }
@include_once __DIR__ . '/../api/config.php';

const START_DEFAULT = 'https://kscdr.org.sa/ar/node/3393';
const MAX_PAGES     = 300;
const UA            = 'Mozilla/5.0 (compatible; WesalKB/1.0; +https://wesalinnovation.sa)';

$cmd   = $argv[1] ?? 'scan';
$start = $argv[2] ?? START_DEFAULT;
$root  = rtrim(defined('STORAGE_DIR') ? STORAGE_DIR : __DIR__ . '/../storage', '/') . '/kscdr';
if (!is_dir($root)) { mkdir($root, 0750, true); file_put_contents($root . '/.htaccess', "Require all denied\nDeny from all\n"); }

function get(string $url, ?string $saveTo = null): array {
    $ch = curl_init($url);
    $fp = $saveTo ? fopen($saveTo, 'wb') : null;
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => !$saveTo, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_TIMEOUT => 120, CURLOPT_USERAGENT => UA,
        CURLOPT_HTTPHEADER => ['Accept-Language: ar,en;q=0.8'],
    ]);
    if ($fp) curl_setopt($ch, CURLOPT_FILE, $fp);
    $body = curl_exec($ch);
    $info = curl_getinfo($ch); $err = curl_error($ch); curl_close($ch);
    if ($fp) fclose($fp);
    return ['ok' => !$err && $info['http_code'] < 400, 'code' => $info['http_code'], 'type' => $info['content_type'] ?? '', 'body' => is_string($body) ? $body : '', 'url' => $info['url'] ?? $url, 'err' => $err];
}
function absUrl(string $href, string $base): ?string {
    $href = trim(html_entity_decode($href));
    if ($href === '' || $href[0] === '#' || stripos($href, 'javascript:') === 0 || stripos($href, 'mailto:') === 0) return null;
    if (preg_match('~^https?://~i', $href)) return $href;
    $b = parse_url($base);
    $origin = $b['scheme'] . '://' . $b['host'] . (isset($b['port']) ? ':' . $b['port'] : '');
    if ($href[0] === '?') return $origin . ($b['path'] ?? '/') . $href;          // ترقيم صفحات Drupal: ?page=N على المسار نفسه
    if ($href[0] === '/') return $origin . $href;
    $dir = preg_replace('~/[^/]*$~', '/', $b['path'] ?? '/');
    return $origin . $dir . $href;
}
function parsePage(string $html, string $base): array {
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    libxml_clear_errors();
    $x = new DOMXPath($doc);
    $title = trim($x->evaluate('string(//title)'));
    $h1    = trim($x->evaluate('string(//h1)'));
    $links = [];
    foreach ($x->query('//a[@href]') as $a) {
        $u = absUrl($a->getAttribute('href'), $base); if (!$u) continue;
        $text = trim(preg_replace('/\s+/u', ' ', $a->textContent));
        $links[] = ['url' => $u, 'text' => $text];
    }
    $rows = $x->query('//*[contains(@class,"views-row") or contains(@class,"node--type") or contains(@class,"card") or self::article]')->length;
    /* تشريح الصفحة: العناوين، روابط المحتوى مقابل روابط القوائم، النماذج، الإطارات، ونصّ المحتوى */
    $heads = [];
    foreach ($x->query('//h1|//h2|//h3') as $h) { $t = trim(preg_replace('/\s+/u', ' ', $h->textContent)); if ($t !== '') $heads[] = $h->nodeName . ': ' . mb_substr($t, 0, 90); }
    $contentLinks = []; $navLinks = [];
    foreach ($x->query('//a[@href]') as $a) {
        $u = absUrl($a->getAttribute('href'), $base); if (!$u) continue;
        $inNav = false; $n = $a;
        while ($n = $n->parentNode) { if (!($n instanceof DOMElement)) break; $cls = ' ' . $n->getAttribute('class') . ' '; $tag = $n->nodeName;
            if (in_array($tag, ['nav', 'header', 'footer']) || preg_match('/ (menu|navbar|nav|breadcrumb|footer|header|toolbar)[ -]/', $cls) || $n->getAttribute('role') === 'navigation') { $inNav = true; break; } }
        $text = trim(preg_replace('/\s+/u', ' ', $a->textContent));
        if ($inNav) $navLinks[] = ['url' => $u, 'text' => $text]; else $contentLinks[] = ['url' => $u, 'text' => $text];
    }
    $forms = [];
    foreach ($x->query('//form') as $f) { $names = []; foreach ($x->query('.//input|.//select', $f) as $i) { $nm = $i->getAttribute('name'); if ($nm) $names[] = $nm; } $forms[] = ($f->getAttribute('action') ?: '(نفس الصفحة)') . ' [' . implode(', ', array_slice(array_unique($names), 0, 8)) . ']'; }
    $iframes = []; foreach ($x->query('//iframe[@src]') as $i) $iframes[] = $i->getAttribute('src');
    $mainText = '';
    foreach ($x->query('//main|//*[@id="content"]|//*[contains(@class,"region-content")]|//*[contains(@class,"main-content")]|//article') as $m) { $mainText = trim(preg_replace('/\s+/u', ' ', $m->textContent)); if (mb_strlen($mainText) > 80) break; }
    $ajaxViews = $x->query('//*[contains(@class,"view-") and (contains(@class,"js-view-dom-id") or contains(@class,"view-id"))]')->length;
    $dataSettings = $x->query('//script[@data-drupal-selector="drupal-settings-json"]')->length;
    $paragraphs = 0; $words = 0;
    foreach ($x->query('//main//p | //article//p | //*[contains(@class,"field--name-body")]//p') as $p) { $paragraphs++; $words += str_word_count(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $p->textContent)) ?: count(preg_split('/\s+/u', trim($p->textContent))); }
    $files = array_values(array_filter($links, fn($l) => preg_match('~\.(pdf|docx?|pptx?|xlsx?)(\?|$)~i', $l['url'])));
    $host  = parse_url($base, PHP_URL_HOST);
    $nodes = array_values(array_filter($links, fn($l) => parse_url($l['url'], PHP_URL_HOST) === $host && preg_match('~/(ar|en)/(node/\d+|[^?#]+)$~', $l['url']) && !preg_match('~\.(pdf|docx?|pptx?|xlsx?|jpe?g|png|css|js)(\?|$)~i', $l['url'])));
    $pages = array_values(array_filter($links, fn($l) => preg_match('~[?&]page=\d+~', $l['url'])));
    return compact('title', 'h1', 'links', 'rows', 'paragraphs', 'words', 'files', 'nodes', 'pages', 'heads', 'contentLinks', 'navLinks', 'forms', 'iframes', 'mainText', 'ajaxViews', 'dataSettings');
}
function uniqBy(array $list, string $k): array { $seen = []; $out = []; foreach ($list as $it) { if (isset($seen[$it[$k]])) continue; $seen[$it[$k]] = 1; $out[] = $it; } return $out; }

$r = get($start);
if (!$r['ok']) { fwrite(STDERR, "تعذّر فتح $start — HTTP {$r['code']} {$r['err']}\n"); exit(1); }
file_put_contents($root . '/last-scan.html', $r['body']);
$p = parsePage($r['body'], $r['url']);

if ($cmd === 'scan') {
    echo "== مسح: {$r['url']} (HTTP {$r['code']}, {$r['type']}, " . strlen($r['body']) . " بايت)\n";
    echo "العنوان: {$p['title']}\nH1: {$p['h1']}\n";
    echo "عناصر قائمة محتملة: {$p['rows']} | فقرات: {$p['paragraphs']} | كلمات تقريباً: {$p['words']}\n";
    echo "روابط: " . count($p['links']) . " | ملفات: " . count($p['files']) . " | صفحات داخلية: " . count(uniqBy($p['nodes'], 'url')) . " | ترقيم صفحات: " . count(uniqBy($p['pages'], 'url')) . "\n\n";
    echo "-- العناوين:\n"; foreach (array_slice($p['heads'], 0, 25) as $h) echo "  $h\n";
    echo "-- نص المحتوى (أول 500 حرف):\n  " . mb_substr($p['mainText'], 0, 500) . "\n";
    echo "-- النماذج: " . (count($p['forms']) ? '' : 'لا يوجد') . "\n"; foreach (array_slice($p['forms'], 0, 5) as $f) echo "  $f\n";
    echo "-- إطارات: " . (count($p['iframes']) ? implode(' | ', $p['iframes']) : 'لا يوجد') . " | عروض Drupal ديناميكية: {$p['ajaxViews']} | drupal-settings: {$p['dataSettings']}\n";
    echo "-- الملفات (حتى 40):\n";   foreach (array_slice(uniqBy($p['files'], 'url'), 0, 40) as $l) echo "  {$l['url']}  ← {$l['text']}\n";
    echo "-- روابط المحتوى (خارج القوائم، حتى 80):\n"; foreach (array_slice(uniqBy($p['contentLinks'], 'url'), 0, 80) as $l) echo "  {$l['url']}  ← {$l['text']}\n";
    echo "-- روابط القوائم: " . count(uniqBy($p['navLinks'], 'url')) . " (محذوفة من العرض)\n";
    echo "-- ترقيم الصفحات:\n"; foreach (array_slice(uniqBy($p['pages'], 'url'), 0, 10) as $l) echo "  {$l['url']}\n";
    echo "\nالنسخة الخام محفوظة في $root/last-scan.html — أرسل لي هذا الناتج كاملاً.\n";
    exit(0);
}

/* fetch: تتبّع ترقيم الصفحات وجمع الصفحات الداخلية وملفاتها */
$index = []; $queue = [$r['url']]; $seenPages = []; $items = [];
while ($queue && count($seenPages) < MAX_PAGES) {
    $url = array_shift($queue); if (isset($seenPages[$url])) continue; $seenPages[$url] = 1;
    $rr = $url === $r['url'] ? $r : get($url); if (!$rr['ok']) { echo "تخطّي $url (HTTP {$rr['code']})\n"; continue; }
    $pp = parsePage($rr['body'], $rr['url']);
    foreach (uniqBy($pp['pages'], 'url') as $l) if (!isset($seenPages[$l['url']])) $queue[] = $l['url'];
    foreach (uniqBy($pp['files'], 'url') as $l) $items[$l['url']] = ['title' => $l['text'], 'file' => $l['url'], 'from' => $rr['url']];
    foreach (uniqBy($pp['nodes'], 'url') as $l) if (!isset($items[$l['url']]) && $l['url'] !== $rr['url']) $items[$l['url']] = ['title' => $l['text'], 'page' => $l['url'], 'from' => $rr['url']];
    echo "صفحة: {$rr['url']} → ملفات " . count($pp['files']) . "، عناصر " . count($pp['nodes']) . "\n";
}
/* زيارة صفحات العناصر لالتقاط ملفاتها ونصّها */
$n = 0;
foreach ($items as $key => &$it) {
    if (empty($it['page']) || $n >= MAX_PAGES) continue;
    $n++;
    $rr = get($it['page']); if (!$rr['ok']) continue;
    $pp = parsePage($rr['body'], $rr['url']);
    $it['h1'] = $pp['h1'] ?: $pp['title'];
    $it['files'] = array_column(uniqBy($pp['files'], 'url'), 'url');
    $it['words'] = $pp['words'];
    $it['html'] = 'pages/' . md5($it['page']) . '.html';
    @mkdir($root . '/pages', 0750, true);
    file_put_contents($root . '/' . $it['html'], $rr['body']);
}
unset($it);
/* تنزيل الملفات */
@mkdir($root . '/files', 0750, true);
$dl = 0;
foreach ($items as &$it) {
    $files = $it['files'] ?? (isset($it['file']) ? [$it['file']] : []);
    foreach ($files as $f) {
        $name = md5($f) . '.' . strtolower(pathinfo(parse_url($f, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'bin');
        $path = $root . '/files/' . $name;
        if (!file_exists($path)) { $rr = get($f, $path); if (!$rr['ok'] || filesize($path) < 1000) { @unlink($path); continue; } $dl++; }
        $it['local'][] = ['url' => $f, 'path' => 'files/' . $name, 'bytes' => filesize($path), 'sha1' => sha1_file($path)];
    }
}
unset($it);
file_put_contents($root . '/index.json', json_encode(['start' => $start, 'fetched_at' => gmdate('c'), 'items' => array_values($items)], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "\nتم: " . count($items) . " عنصراً، $dl ملفاً منزّلاً. الفهرس: $root/index.json\n";
