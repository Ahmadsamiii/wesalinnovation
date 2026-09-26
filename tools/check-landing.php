<?php
/* ==========================================================================
 *  وصال — فحص تطابق سجل محتوى صفحة الهبوط مع index.html
 *
 *  الاستخدام:
 *      php tools/check-landing.php
 *
 *  السجل (api/landing-schema.php) والصفحة (index.html) يحملان النص العربي
 *  الافتراضي نفسه: الصفحة تعرضه قبل وصول رد الخادم، والسجل يرجع إليه عند
 *  «الإرجاع للأصل». هذا الفحص يمنع انحرافهما بصمت:
 *    - كل حقل في السجل له عنصر في الصفحة، وكل data-lp في الصفحة له حقل
 *    - النص المكتوب في الصفحة يطابق الافتراضي العربي حرفياً
 *    - كل قائمة لها حاوية بقالب، وبطاقاتها الثابتة بنفس المعرّفات والترتيب
 *      والنصوص والأيقونات
 *    - شعارات «مصادر البيانات»: لكل نطاق في SRC_LOGOS ملف بمقاس
 *      tools/source-logo.php ولا ملف بلا نطاق، وكل بطاقة ثابتة تعرض شعار نطاقها
 *      أو الرمز العام
 *  لا يحتاج قاعدة بيانات ولا إعدادات.
 * ========================================================================== */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("هذا السكربت يُشغَّل من الطرفية فقط.\n");
}

require_once __DIR__ . '/../api/landing-schema.php';

$fails = 0;
function bad(string $msg): void { global $fails; $fails++; echo "  ✗ $msg\n"; }
function norm(string $s): string { return trim(preg_replace('/\s+/u', ' ', html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8'))); }

$html = file_get_contents(__DIR__ . '/../index.html');
libxml_use_internal_errors(true);
$dom = new DOMDocument();
$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
$xp = new DOMXPath($dom);

/** العناصر خارج <template> فقط — محلل HTML4 لا يعرف القوالب فيعرض محتواها كعناصر عادية */
function live(DOMXPath $xp, string $q): array {
    $out = [];
    foreach ($xp->query($q) as $n) {
        $inTpl = false;
        for ($p = $n->parentNode; $p; $p = $p->parentNode) if ($p->nodeName === 'template') { $inTpl = true; break; }
        if (!$inTpl) $out[] = $n;
    }
    return $out;
}

$known = [];
echo "الحقول:\n";
foreach (landingSchema() as $sec) {
    foreach ($sec['fields'] as $fd) {
        $key = $sec['id'] . '.' . $fd['k'];
        $known[$key] = true;
        $mono = lpIsMono($fd['t']);
        $def = $mono ? $fd['d']['v'] : $fd['d']['ar'];
        $txt = live($xp, "//*[@data-lp='$key']");
        $ph = live($xp, "//*[@data-lp-ph='$key']");
        $href = live($xp, "//*[@data-lp-href='$key']");
        if (!$txt && !$ph && !$href) { bad("$key: لا يوجد عنصر في الصفحة"); continue; }
        foreach ($txt as $n) if (norm($n->textContent) !== norm($def)) bad("$key: نص الصفحة «" . norm($n->textContent) . "» ≠ الافتراضي «" . $def . "»");
        foreach ($ph as $n) if (norm($n->getAttribute('placeholder')) !== norm($def)) bad("$key: placeholder مختلف عن الافتراضي");
        foreach ($href as $n) {
            $want = $n->getAttribute('data-lp-prefix') . $def;
            if ($n->getAttribute('href') !== $want) bad("$key: href «" . $n->getAttribute('href') . "» ≠ «{$want}»");
        }
    }
}
foreach (['data-lp', 'data-lp-ph', 'data-lp-href'] as $attr) {
    foreach (live($xp, "//*[@$attr]") as $n) {
        $k = $n->getAttribute($attr);
        if (!isset($known[$k])) bad("$attr=\"$k\" في الصفحة بلا حقل في السجل");
    }
}

echo "القوائم:\n";
$lists = [];
foreach (landingSchema() as $sec) {
    foreach ($sec['lists'] as $ld) {
        $key = $sec['id'] . '.' . $ld['k'];
        $lists[$key] = true;
        $box = live($xp, "//*[@data-lp-list='$key']");
        if (count($box) !== 1) { bad("$key: متوقع حاوية واحدة، وُجد " . count($box)); continue; }
        $box = $box[0];
        $tpl = $xp->query('./template', $box);
        if ($tpl->length !== 1) bad("$key: لا يوجد <template> داخل الحاوية");
        else foreach ($ld['fields'] as $fd) {
            if ($xp->query(".//*[@data-lpf='{$fd['k']}']", $tpl->item(0))->length === 0) bad("$key: القالب بلا data-lpf=\"{$fd['k']}\"");
        }
        if ($ld['icon'] && $tpl->length && $xp->query('.//*[@data-lpi]', $tpl->item(0))->length === 0) bad("$key: القالب بلا data-lpi للأيقونة");
        $items = $xp->query('./*[@data-lp-id]', $box);
        $ids = [];
        foreach ($items as $n) $ids[] = $n->getAttribute('data-lp-id');
        $want = array_map(fn($d) => $d['id'], $ld['items']);
        if ($ids !== $want) { bad("$key: البطاقات الثابتة [" . implode(',', $ids) . "] ≠ السجل [" . implode(',', $want) . "]"); continue; }
        foreach ($ld['items'] as $i => $d) {
            $n = $items->item($i);
            foreach ($ld['fields'] as $fd) {
                $f = $xp->query(".//*[@data-lpf='{$fd['k']}']", $n);
                $def = lpIsMono($fd['t']) ? $d['f'][$fd['k']]['v'] : $d['f'][$fd['k']]['ar'];
                if ($f->length !== 1) bad("$key.{$d['id']}: لا يوجد data-lpf=\"{$fd['k']}\"");
                elseif (norm($f->item(0)->textContent) !== norm($def)) bad("$key.{$d['id']}.{$fd['k']}: «" . norm($f->item(0)->textContent) . "» ≠ «{$def}»");
            }
            if ($ld['icon']) {
                $u = $xp->query('.//*[local-name()="use"]', $n);
                $h = $u->length ? $u->item(0)->getAttribute('href') : '';
                if ($h !== '#ic-' . ($d['i'] ?? '')) bad("$key.{$d['id']}: الأيقونة «{$h}» ≠ «#ic-{$d['i']}»");
            }
        }
    }
}
foreach (live($xp, '//*[@data-lp-list]') as $n) {
    if (!isset($lists[$n->getAttribute('data-lp-list')])) bad('data-lp-list="' . $n->getAttribute('data-lp-list') . '" بلا قائمة في السجل');
}

echo "الشعارات:\n";
$logos = [];
if (!preg_match('/const SRC_LOGOS=\{([^}]*)\};/', $html, $m)) bad('SRC_LOGOS غير موجودة في index.html');
else {
    preg_match_all("/'([a-z0-9.-]+)':([1-9][0-9]*)/", $m[1], $mm, PREG_SET_ORDER);
    foreach ($mm as [, $d, $v]) $logos[$d] = (int)$v;
}
$logoDir = __DIR__ . '/../assets/sources';
foreach ($logos as $d => $v) {
    $f = "$logoDir/$d.webp";
    $info = is_file($f) ? @getimagesize($f) : false;
    if (!$info) bad("SRC_LOGOS: الملف assets/sources/$d.webp غير موجود");
    elseif ($info[2] !== IMAGETYPE_WEBP || $info[0] !== 800 || $info[1] !== 320) {
        bad("assets/sources/$d.webp ليس WebP بمقاس 800×320، جهّزه بـ php tools/source-logo.php");
    }
}
foreach (glob("$logoDir/*") ?: [] as $f) {
    if (!isset($logos[preg_replace('/\.webp$/', '', basename($f))])) bad('assets/sources/' . basename($f) . ' بلا نطاق في SRC_LOGOS');
}
if (strpos($html, '<symbol id="ic-landmark"') === false) bad('رمز الجهة بلا شعار ic-landmark غير معرّف في الصفحة');
/** نفس تطبيع srcLogo() في الصفحة: بلا www. ولا مسار */
function logoDomain(string $v): string { return explode('/', preg_replace('/^www\./', '', strtolower(trim($v))))[0]; }
foreach (landingSchema() as $sec) {
    foreach ($sec['lists'] as $ld) {
        $key = $sec['id'] . '.' . $ld['k'];
        $box = live($xp, "//*[@data-lp-list='$key']");
        $tpl = count($box) === 1 ? $xp->query('./template', $box[0]) : null;
        $slot = $tpl && $tpl->length ? $xp->query('.//*[@data-lpl]', $tpl->item(0)) : null;
        if (!$slot || !$slot->length) continue;
        $fk = $slot->item(0)->getAttribute('data-lpl');
        if (!array_filter($ld['fields'], fn($fd) => $fd['k'] === $fk && $fd['t'] === 'domain')) {
            bad("$key: data-lpl=\"$fk\" ليس حقل نطاق في السجل");
            continue;
        }
        $items = $xp->query('./*[@data-lp-id]', $box[0]);
        if ($items->length !== count($ld['items'])) continue;   // الترتيب أُبلغ عنه أعلاه
        foreach ($ld['items'] as $i => $d) {
            $s = $xp->query('.//*[@data-lpl]', $items->item($i));
            if ($s->length !== 1) { bad("$key.{$d['id']}: لا يوجد data-lpl للشعار"); continue; }
            $dom = logoDomain($d['f'][$fk]['v'] ?? '');
            $img = $xp->query('.//img', $s->item(0));
            if (isset($logos[$dom])) {
                $want = "assets/sources/$dom.webp?v={$logos[$dom]}";
                $el = $img->length === 1 ? $img->item(0) : null;
                if (!$el || $el->getAttribute('src') !== $want) bad("$key.{$d['id']}: الشعار ليس «{$want}»");
                elseif (!$el->hasAttribute('alt') || $el->getAttribute('alt') !== '' || $el->getAttribute('width') !== '200' || $el->getAttribute('height') !== '80') {
                    bad("$key.{$d['id']}: الشعار يحتاج alt=\"\" width=\"200\" height=\"80\" كما في srcLogo()");
                }
            } elseif ($img->length || !preg_match('/\bis-empty\b/', $s->item(0)->getAttribute('class'))
                      || !$xp->query('.//*[local-name()="use"][@href="#ic-landmark"]', $s->item(0))->length) {
                bad("$key.{$d['id']}: «{$dom}» بلا ملف شعار، فيجب أن تعرض البطاقة الرمز العام");
            }
        }
    }
}

echo "الأقسام:\n";
foreach (landingSchema() as $sec) {
    if (!live($xp, "//*[@data-lp-sec='{$sec['id']}']")) bad("{$sec['id']}: لا يوجد data-lp-sec في الصفحة");
}
$icons = landingIcons();
foreach ($icons as $k => $l) {
    if (strpos($html, '<symbol id="ic-' . $k . '"') === false) bad("الأيقونة ic-$k غير معرّفة في الصفحة");
}

echo $fails ? "\n✗ $fails مشكلة\n" : "\n✓ السجل والصفحة متطابقان\n";
exit($fails ? 1 : 0);
