<?php
/* ==========================================================================
 *  وصال — محتوى صفحة الهبوط: مسودة ثم نشر، بلغتين
 *
 *  الحقول والقوائم ونصوصها الافتراضية معرّفة في landing-schema.php.
 *
 *  التخزين: صفّان في landing_content — المنشور (live) والمسودة المشتركة
 *  (draft). كل صف يحفظ «الفروقات عن الافتراضي» فقط، فأي حقل لم يُعدَّل يتبع
 *  النص الافتراضي في السجل تلقائياً، و«الإرجاع للأصل» يعني حذف الفرق.
 *
 *  الواجهة تتعامل مع المستند «الفعّال» الكامل (الافتراضي + الفروقات)، وهذا
 *  الملف وحده يحوّل بين الشكلين — لا منطق دمج في المتصفح.
 *
 *  لا مسودة = المسودة تطابق المنشور. الحفظ الذي يعيد المسودة مطابقة للمنشور
 *  يحذف صفها، فعدّاد «تغييرات غير منشورة» صادق دائماً.
 * ========================================================================== */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/landing-schema.php';

/* ---------------------------------------------------------------- التنظيف */

/** نص نظيف: بلا وسوم ولا محارف تحكم، وسطر واحد إلا في النص الطويل */
function lpClean($v, bool $multiline): string {
    $v = strip_tags((string)$v);
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
    $v = $multiline ? preg_replace("/\r\n?/", "\n", $v) : preg_replace('/\s+/u', ' ', $v);
    return trim((string)$v);
}

/** قيمة حقل واحد منظّفة ومتحقَّق منها — {ar,en} أو {v}. غير الصارم (للترحيل) يقصّ بدل الرفض */
function lpCleanValue(array $fd, $raw, string $where, bool $strict): array {
    $raw = is_array($raw) ? $raw : [];
    $max = (int)$fd['max'];
    if (lpIsMono($fd['t'])) {
        $v = lpClean($raw['v'] ?? '', false);
        if (mb_strlen($v) > $max) {
            if ($strict) fail('القيمة أطول من الحد المسموح (' . $max . ' حرفاً) في: ' . $where);
            $v = '';
        }
        if ($v !== '' && ($e = lpMonoError($fd['t'], $v)) !== null) {
            if ($strict) fail($e . ' في: ' . $where);
            $v = '';
        }
        return ['v' => $v];
    }
    $out = [];
    foreach (['ar' => 'العربية', 'en' => 'English'] as $l => $ln) {
        $v = lpClean($raw[$l] ?? '', $fd['t'] === 'area');
        if (mb_strlen($v) > $max) {
            if ($strict) fail('النص أطول من الحد المسموح (' . $max . ' حرفاً) في: ' . $where . ' (' . $ln . ')');
            $v = mb_substr($v, 0, $max);
        }
        $out[$l] = $v;
    }
    return $out;
}

function lpMonoError(string $type, string $v): ?string {
    switch ($type) {
        case 'url':
            // https فقط — javascript: وأخواتها تصير ثغرة عند النقر
            if (!preg_match('#^https://#i', $v) || !filter_var($v, FILTER_VALIDATE_URL))
                return 'يجب أن يكون الرابط صحيحاً ويبدأ بـ https://';
            return null;
        case 'email':
            return filter_var($v, FILTER_VALIDATE_EMAIL) ? null : 'بريد إلكتروني غير صحيح';
        case 'tel':
            return preg_match('/^\+?[0-9][0-9\s\-()]{5,22}$/', $v) ? null : 'رقم غير صحيح، واستخدم الأرقام والمسافات وعلامة + فقط';
        case 'domain':
            return preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+(\/[^\s]*)?$/i', $v)
                ? null : 'النطاق غير صحيح، ومثاله: moh.gov.sa';
    }
    return null;
}

/** الفرق بين قيمة وافتراضيها — الفارغ يعني «الافتراضي» فلا يُخزَّن */
function lpOverride(array $fd, array $val, array $def): array {
    if (lpIsMono($fd['t'])) {
        return ($val['v'] !== '' && $val['v'] !== (string)($def['v'] ?? '')) ? ['v' => $val['v']] : [];
    }
    $o = [];
    foreach (['ar', 'en'] as $l) {
        if ($val[$l] !== '' && $val[$l] !== (string)($def[$l] ?? '')) $o[$l] = $val[$l];
    }
    return $o;
}

/* ------------------------------------------------- فعّال ← فروقات (حفظ) */

/** قسم فعّال كامل من الواجهة ← فروقاته عن الافتراضي، مع التحقق من كل قيمة */
function lpSparseSection(array $sec, $eff, bool $strict): array {
    if (!is_array($eff)) {
        if ($strict) fail('بيانات غير صالحة في قسم: ' . $sec['label']);
        return [];
    }
    $o = [];
    if ($sec['hideable'] && !empty($eff['hidden'])) $o['h'] = 1;
    $fo = [];
    foreach ($sec['fields'] as $fd) {
        $val = lpCleanValue($fd, $eff['f'][$fd['k']] ?? null, $sec['label'] . ' ← ' . $fd['l'], $strict);
        if ($ov = lpOverride($fd, $val, $fd['d'])) $fo[$fd['k']] = $ov;
    }
    if ($fo) $o['f'] = $fo;
    foreach ($sec['lists'] as $ld) {
        $lo = lpSparseList($sec, $ld, $eff['l'][$ld['k']] ?? null, $strict);
        if ($lo !== null) $o['l'][$ld['k']] = $lo;
    }
    return $o;
}

/** قائمة بطاقات ← null إن طابقت الافتراضي تماماً، وإلا ترتيبها الكامل بفروق كل بطاقة */
function lpSparseList(array $sec, array $ld, $items, bool $strict): ?array {
    if (!is_array($items)) return null;
    $items = array_values($items);
    if (count($items) > $ld['max']) {
        if ($strict) fail('الحد الأقصى ' . $ld['max'] . ' في: ' . $sec['label'] . ' ← ' . $ld['l']);
        $items = array_slice($items, 0, $ld['max']);
    }
    $defs = [];
    foreach ($ld['items'] as $pos => $d) $defs[$d['id']] = [$pos, $d];
    $icons = landingIcons();
    $same = count($items) === count($ld['items']);
    $out = [];
    $seen = [];
    foreach ($items as $pos => $it) {
        if (!is_array($it)) {
            if ($strict) fail('بطاقة غير صالحة في: ' . $sec['label']);
            continue;
        }
        $id = (string)($it['id'] ?? '');
        $isDef = isset($defs[$id]) && empty($it['custom']);
        if ($isDef && isset($seen[$id])) continue;   // بطاقة افتراضية مكررة: الأولى تكفي
        if (!$isDef && (!preg_match('/^c[a-z0-9]{5,15}$/', $id) || isset($defs[$id]) || isset($seen[$id]))) {
            $id = 'c' . bin2hex(random_bytes(5));
        }
        $seen[$id] = 1;
        $where = $sec['label'] . ' ← ' . $ld['item'] . ' ' . ($pos + 1);
        $icon = null;
        if ($ld['icon']) {
            $icon = (string)($it['icon'] ?? '');
            if (!isset($icons[$icon])) $icon = $isDef ? (string)($defs[$id][1]['i'] ?? 'star') : 'star';
        }
        $e = ['id' => $id];
        if ($isDef) {
            [$dpos, $d] = $defs[$id];
            if ($dpos !== $pos) $same = false;
            if (!empty($it['hidden'])) { $e['h'] = 1; $same = false; }
            if ($ld['icon'] && $icon !== ($d['i'] ?? null)) { $e['i'] = $icon; $same = false; }
            $fo = [];
            foreach ($ld['fields'] as $fd) {
                $val = lpCleanValue($fd, $it['f'][$fd['k']] ?? null, $where . ' ← ' . $fd['l'], $strict);
                if ($ov = lpOverride($fd, $val, $d['f'][$fd['k']] ?? [])) $fo[$fd['k']] = $ov;
            }
            if ($fo) { $e['f'] = $fo; $same = false; }
        } else {
            $same = false;
            $e['c'] = 1;
            if (!empty($it['hidden'])) $e['h'] = 1;
            if ($ld['icon']) $e['i'] = $icon;
            $fo = [];
            foreach ($ld['fields'] as $fd) {
                $val = lpCleanValue($fd, $it['f'][$fd['k']] ?? null, $where . ' ← ' . $fd['l'], $strict);
                if ($ov = lpOverride($fd, $val, [])) $fo[$fd['k']] = $ov;
            }
            if ($fo) $e['f'] = $fo;
        }
        $out[] = $e;
    }
    return $same ? null : $out;
}

/* ----------------------------------------------- فروقات ← فعّال (عرض) */

function lpMerge(array $fd, array $def, $ov): array {
    $ov = is_array($ov) ? $ov : [];
    if (lpIsMono($fd['t'])) {
        $v = (string)($ov['v'] ?? '');
        return ['v' => $v !== '' ? $v : (string)($def['v'] ?? '')];
    }
    $r = [];
    foreach (['ar', 'en'] as $l) {
        $v = (string)($ov[$l] ?? '');
        $r[$l] = $v !== '' ? $v : (string)($def[$l] ?? '');
    }
    return $r;
}

function lpEffList(array $ld, $sparse): array {
    $defs = [];
    foreach ($ld['items'] as $d) $defs[$d['id']] = $d;
    $icons = landingIcons();
    $src = is_array($sparse) ? $sparse : array_map(fn($d) => ['id' => $d['id']], $ld['items']);
    $out = [];
    foreach ($src as $it) {
        $id = (string)($it['id'] ?? '');
        $custom = !empty($it['c']);
        if (!$custom && !isset($defs[$id])) continue;   // بطاقة افتراضية أُزيلت من السجل لاحقاً
        $d = $custom ? ['f' => []] : $defs[$id];
        $f = [];
        foreach ($ld['fields'] as $fd) $f[$fd['k']] = lpMerge($fd, $d['f'][$fd['k']] ?? [], $it['f'][$fd['k']] ?? []);
        $icon = null;
        if ($ld['icon']) {
            $icon = (string)($it['i'] ?? ($d['i'] ?? 'star'));
            if (!isset($icons[$icon])) $icon = (string)($d['i'] ?? 'star');
        }
        $out[] = ['id' => $id, 'custom' => $custom, 'hidden' => !empty($it['h']), 'icon' => $icon, 'f' => $f];
    }
    return $out;
}

/** المستند الفعّال الكامل: كل قسم بكل حقوله وقوائمه */
function lpEffective(array $doc): array {
    $out = [];
    foreach (landingSchema() as $sec) {
        $so = $doc['s'][$sec['id']] ?? [];
        $e = ['hidden' => $sec['hideable'] && !empty($so['h']), 'f' => [], 'l' => []];
        foreach ($sec['fields'] as $fd) $e['f'][$fd['k']] = lpMerge($fd, $fd['d'], $so['f'][$fd['k']] ?? []);
        foreach ($sec['lists'] as $ld) $e['l'][$ld['k']] = lpEffList($ld, $so['l'][$ld['k']] ?? null);
        if (!$e['l']) $e['l'] = new stdClass();   // كائن JSON لا مصفوفة فارغة
        $out[$sec['id']] = $e;
    }
    return $out;
}

/** صيغة موحّدة للمقارنة: الأقسام بترتيب السجل، بلا الفارغ منها */
function lpCanon(array $doc): array {
    $c = [];
    foreach (landingSchema() as $sec) {
        if (!empty($doc['s'][$sec['id']])) $c[$sec['id']] = $doc['s'][$sec['id']];
    }
    return $c;
}

/* ---------------------------------------------------------------- التخزين */

function lpRow(string $state, bool $lock = false): ?array {
    $s = db()->prepare('SELECT doc, etag, updated_by, updated_name, updated_at FROM landing_content WHERE state=?'
                       . ($lock ? ' FOR UPDATE' : ''));
    $s->execute([$state]);
    $r = $s->fetch();
    if (!$r) return null;
    $doc = json_decode((string)$r['doc'], true);
    return [
        'doc'  => is_array($doc) ? $doc : ['s' => []],
        'etag' => (string)$r['etag'],
        'uid'  => $r['updated_by'] !== null ? (int)$r['updated_by'] : null,
        'by'   => $r['updated_name'],
        'at'   => strtotime((string)$r['updated_at']) * 1000,
    ];
}

function lpWrite(string $state, array $doc, ?array $user): string {
    $etag = bin2hex(random_bytes(8));
    db()->prepare('INSERT INTO landing_content (state, doc, etag, updated_by, updated_name, updated_at)
                   VALUES (?,?,?,?,?,NOW())
                   ON DUPLICATE KEY UPDATE doc=VALUES(doc), etag=VALUES(etag), updated_by=VALUES(updated_by),
                                           updated_name=VALUES(updated_name), updated_at=NOW()')
        ->execute([$state, json_encode(['v' => 1, 's' => lpCanon($doc)], JSON_UNESCAPED_UNICODE),
                   $etag, $user['id'] ?? null, $user['name'] ?? null]);
    return $etag;
}

/** المنشور — وفي أول تشغيل يُبنى من محتوى النظام القديم (site_content) إن وُجد */
function lpLive(): array {
    $r = lpRow('live');
    if ($r) return $r;
    $doc = lpMigrateLegacy();
    db()->prepare('INSERT IGNORE INTO landing_content (state, doc, etag, updated_by, updated_name, updated_at)
                   VALUES (\'live\',?,?,NULL,NULL,NOW())')
        ->execute([json_encode(['v' => 1, 's' => lpCanon($doc)], JSON_UNESCAPED_UNICODE), bin2hex(random_bytes(8))]);
    return lpRow('live') ?? ['doc' => $doc, 'etag' => '', 'uid' => null, 'by' => null, 'at' => 0];
}

/** مفاتيح النظام القديم (نص عربي فقط) ← مكانها في المستند الجديد */
function lpMigrateLegacy(): array {
    $old = contentMap();
    $eff = lpEffective(['s' => []]);
    if (!$old) return ['s' => []];
    $fields = [
        'social.x' => ['footer', 'x', 'v'], 'social.instagram' => ['footer', 'instagram', 'v'],
        'social.linkedin' => ['footer', 'linkedin', 'v'], 'contact.email' => ['footer', 'email', 'v'],
        'contact.phone' => ['footer', 'phone', 'v'], 'contact.hours' => ['footer', 'hours', 'ar'],
        'footer.about' => ['footer', 'about', 'ar'],
        'hero.chip' => ['hero', 'chip', 'ar'], 'hero.title' => ['hero', 'title', 'ar'], 'hero.sub' => ['hero', 'sub', 'ar'],
        'hero.ph' => ['hero', 'ph', 'ar'], 'hero.cta1' => ['hero', 'cta1', 'ar'], 'hero.cta2' => ['hero', 'cta2', 'ar'],
        'feat.label' => ['features', 'label', 'ar'], 'feat.title' => ['features', 'title', 'ar'], 'feat.sub' => ['features', 'sub', 'ar'],
    ];
    $items = [
        'hero.t1t' => ['hero', 'trust', 'beta', 't'], 'hero.t1s' => ['hero', 'trust', 'beta', 's'],
        'hero.t2t' => ['hero', 'trust', 'sources', 't'], 'hero.t2s' => ['hero', 'trust', 'sources', 's'],
        'hero.t3t' => ['hero', 'trust', 'always', 't'], 'hero.t3s' => ['hero', 'trust', 'always', 's'],
    ];
    foreach (['smart', 'voice', 'accessible', 'trusted', 'library', 'privacy'] as $i => $id) {
        $items['feat.' . ($i + 1) . 't'] = ['features', 'items', $id, 'title'];
        $items['feat.' . ($i + 1) . 'd'] = ['features', 'items', $id, 'desc'];
    }
    foreach ($old as $k => $v) {
        if (isset($fields[$k])) {
            [$s, $f, $l] = $fields[$k];
            $eff[$s]['f'][$f][$l] = $v;
        } elseif (isset($items[$k])) {
            [$s, $list, $id, $f] = $items[$k];
            foreach ($eff[$s]['l'][$list] as &$it) if ($it['id'] === $id) $it['f'][$f]['ar'] = $v;
            unset($it);
        }
    }
    $doc = ['s' => []];
    foreach (landingSchema() as $sec) {
        $sec_eff = json_decode(json_encode($eff[$sec['id']]), true);   // stdClass ← مصفوفة
        if ($sp = lpSparseSection($sec, $sec_eff, false)) $doc['s'][$sec['id']] = $sp;
    }
    return $doc;
}

/** بيانات شاشة التحرير كاملة */
function lpEditorPayload(bool $withSchema): array {
    $live = lpLive();
    $draft = lpRow('draft');
    $p = [
        'ok'        => true,
        'live'      => lpEffective($live['doc']),
        'liveEtag'  => $live['etag'],
        'liveBy'    => $live['by'],
        'liveAt'    => $live['at'],
        'draft'     => lpEffective(($draft ?? $live)['doc']),
        'hasDraft'  => $draft !== null,
        'draftEtag' => $draft['etag'] ?? '',
        'draftBy'   => $draft['by'] ?? null,
        'draftAt'   => $draft['at'] ?? 0,
    ];
    if ($withSchema) {
        $p['schema'] = landingSchema();
        $p['icons'] = landingIcons();
    }
    return $p;
}

function lpLabels(array $ids): string {
    $n = [];
    foreach ($ids as $id) if ($s = lpSection($id)) $n[] = $s['label'];
    return implode('، ', $n);
}

/* ---------------------------------------------------------------- العمليات */

ensureSchema();
$in = body();
try {
    switch ($in['action'] ?? 'get') {

        /* عام — تناديه الصفحة عند كل تحميل */
        case 'get': {
            $live = lpLive();
            out(['ok' => true, 'doc' => lpEffective($live['doc']), 'etag' => $live['etag']]);
        }

        /* المسودة كما ستظهر — للمعاينة في اللوحة أو في تبويب مستقل */
        case 'preview': {
            requireAdmin();
            $d = lpRow('draft') ?? lpLive();
            out(['ok' => true, 'doc' => lpEffective($d['doc']), 'draft' => true]);
        }

        case 'editor': {
            requireAdmin();
            out(lpEditorPayload(true));
        }

        /* حفظ تلقائي: الأقسام المعدّلة فقط، فمديران يحرّران قسمين مختلفين لا
           يمسح أحدهما عمل الآخر. etag الأساس يكشف أن غيرك حفظ بعد آخر مزامنة. */
        case 'save_draft': {
            $admin = requireAdmin();
            rateLimit('landing', 240);
            $secs = $in['sections'] ?? null;
            if (!is_array($secs) || !$secs) fail('لا توجد تغييرات للحفظ.');
            $base = (string)($in['etag'] ?? '');

            db()->beginTransaction();
            $row = lpRow('draft', true);
            $live = lpLive();
            $doc = $row ? $row['doc'] : $live['doc'];
            $conflict = ($row['etag'] ?? '') !== $base;
            foreach ($secs as $id => $eff) {
                $sec = lpSection((string)$id);
                if (!$sec) fail('قسم غير معروف: ' . clean((string)$id, 40));
                $sp = lpSparseSection($sec, $eff, true);
                if ($sp) $doc['s'][$sec['id']] = $sp;
                else unset($doc['s'][$sec['id']]);
            }
            if (lpCanon($doc) === lpCanon($live['doc'])) {
                db()->exec("DELETE FROM landing_content WHERE state='draft'");
                $etag = '';
            } else {
                $etag = lpWrite('draft', $doc, $admin);
            }
            db()->commit();
            out([
                'ok' => true, 'etag' => $etag, 'hasDraft' => $etag !== '',
                'conflict' => $conflict && $row !== null, 'by' => $row['by'] ?? null,
                'draft' => lpEffective($doc), 'draftBy' => $etag !== '' ? $admin['name'] : null,
                'liveEtag' => $live['etag'],
            ]);
        }

        case 'discard': {
            $admin = requireAdmin();
            $had = lpRow('draft') !== null;
            db()->exec("DELETE FROM landing_content WHERE state='draft'");
            if ($had) audit($admin, 'landing_discard', 'صفحة الهبوط');
            out(lpEditorPayload(false));
        }

        case 'publish': {
            $admin = requireAdmin();
            rateLimit('landing_pub', 20);
            $base = (string)($in['etag'] ?? '');

            db()->beginTransaction();
            $row = lpRow('draft', true);
            if (!$row) fail('لا توجد تغييرات غير منشورة.', 409);
            if ($row['etag'] !== $base) {
                fail('تغيّرت المسودة للتو' . ($row['by'] ? ' (آخر تعديل: ' . $row['by'] . ')' : '')
                     . '. راجع آخر نسخة ثم انشر.', 409);
            }
            $live = lpLive();
            $a = lpCanon($live['doc']);
            $b = lpCanon($row['doc']);
            $changed = [];
            foreach (landingSchema() as $sec) {
                if (($a[$sec['id']] ?? null) !== ($b[$sec['id']] ?? null)) $changed[] = $sec['id'];
            }
            lpWrite('live', $row['doc'], $admin);
            db()->exec("DELETE FROM landing_content WHERE state='draft'");
            db()->commit();

            audit($admin, 'landing_publish', lpLabels($changed), count($changed) . ' قسم');
            out(lpEditorPayload(false) + ['published' => count($changed)]);
        }

        default: fail('طلب غير معروف.');
    }
} catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    fail(APP_DEBUG ? $e->getMessage() : 'حدث خطأ غير متوقع. حاول مرة أخرى.', 500);
}
