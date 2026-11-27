<?php
/* ==========================================================================
 *  وصال: دوال بوابة قاعدة البيانات (api/db-gateway.php)
 *
 *  فحص جملة SQL قبل تنفيذها: تصنيفها قراءةً أو كتابة، ورفض ما لا تقبله
 *  البوابة أصلاً. بلا قاعدة بيانات ولا جلسة، فيشغّلها tools/check-db-gateway.php
 *  محلياً على أي جملة.
 *
 *  هذا الفحص حاجز أول لا يغني عن الحاجز الثاني: القراءة تُنفَّذ داخل معاملة
 *  READ ONLY يرفضها MySQL نفسه لو تسللت كتابة.
 * ========================================================================== */

/**
 * يعيد الجملة بعد إزالة التعليقات واستبدال النصوص والمعرّفات المقتبسة بعلامة
 * مكانها، فلا تُخدع الفحوص اللاحقة بكلمة موجودة داخل نص (مثل إدخال عبارة
 * «drop database» في عمود). يعيد null إن كانت الجملة غير سليمة التركيب أو فيها
 * تعليق تنفيذي (/*! أو /*+) لأن MySQL ينفّذ ما بداخله.
 */
function gwSkeleton(string $sql): ?string
{
    $out = '';
    $n = strlen($sql);
    $i = 0;
    while ($i < $n) {
        $c = $sql[$i];
        $d = $sql[$i + 1] ?? '';

        if ($c === "'" || $c === '"' || $c === '`') {
            $q = $c;
            $i++;
            while ($i < $n) {
                if ($q !== '`' && $sql[$i] === '\\') { $i += 2; continue; }
                if ($sql[$i] === $q) {
                    if (($sql[$i + 1] ?? '') === $q) { $i += 2; continue; }
                    break;
                }
                $i++;
            }
            if ($i >= $n) { return null; }
            $i++;
            $out .= $q === '`' ? ' ident ' : " '' ";
            continue;
        }

        /* تعليق السطر: «--» لا يبدأ تعليقاً في MySQL إلا إذا تبعته مسافة أو حرف تحكم */
        $after = $sql[$i + 2] ?? ' ';
        if (($c === '-' && $d === '-' && ($after === ' ' || ctype_cntrl($after))) || $c === '#') {
            while ($i < $n && $sql[$i] !== "\n") { $i++; }
            $out .= ' ';
            continue;
        }

        if ($c === '/' && $d === '*') {
            $flag = $sql[$i + 2] ?? '';
            if ($flag === '!' || $flag === '+') { return null; }
            $end = strpos($sql, '*/', $i + 2);
            if ($end === false) { return null; }
            $i = $end + 2;
            $out .= ' ';
            continue;
        }

        $out .= $c;
        $i++;
    }
    return $out;
}

/** هل في الجملة WHERE خارج أي قوسين؟ الاستعلام الفرعي لا يُحتسب. */
function gwHasTopLevelWhere(string $skeleton): bool
{
    $depth = 0;
    $n = strlen($skeleton);
    for ($i = 0; $i < $n; $i++) {
        $c = $skeleton[$i];
        if ($c === '(') { $depth++; continue; }
        if ($c === ')') { $depth = max(0, $depth - 1); continue; }
        if ($depth === 0 && ($c === 'w' || $c === 'W')
            && preg_match('/\GWHERE\b/i', $skeleton, $m, 0, $i)
            && ($i === 0 || !preg_match('/[A-Za-z0-9_]/', $skeleton[$i - 1]))) {
            return true;
        }
    }
    return false;
}

/**
 * يصنّف الجملة. يعيد:
 *   ['ok' => true,  'kind' => 'read'|'write', 'verb' => 'SELECT', 'ddl' => bool,
 *    'destructive' => bool, 'reason' => 'سبب الحذر أو ""']
 *   ['ok' => false, 'error' => 'سبب الرفض']
 */
function gwClassify(string $sql): array
{
    $sql = trim($sql);
    if ($sql === '') { return ['ok' => false, 'error' => 'الجملة فارغة.']; }
    if (strlen($sql) > 60000) { return ['ok' => false, 'error' => 'الجملة أطول من المسموح.']; }

    $sk = gwSkeleton($sql);
    if ($sk === null) {
        return ['ok' => false, 'error' => 'تركيب الجملة غير سليم، أو فيها تعليق تنفيذي لا تقبله البوابة.'];
    }
    $sk = trim(rtrim(trim($sk), ';'));
    if ($sk === '') { return ['ok' => false, 'error' => 'الجملة فارغة.']; }
    if (strpos($sk, ';') !== false) {
        return ['ok' => false, 'error' => 'جملة واحدة في كل طلب.'];
    }

    if (!preg_match('/^[\s(]*([A-Za-z]+)/', $sk, $m)) {
        return ['ok' => false, 'error' => 'لم أتعرف على نوع الجملة.'];
    }
    $verb = strtoupper($m[1]);

    $reads  = ['SELECT', 'SHOW', 'DESCRIBE', 'DESC', 'EXPLAIN', 'WITH'];
    $writes = ['INSERT', 'UPDATE', 'DELETE', 'REPLACE', 'CREATE', 'ALTER', 'DROP', 'TRUNCATE', 'RENAME'];
    if (!in_array($verb, $reads, true) && !in_array($verb, $writes, true)) {
        return ['ok' => false, 'error' => 'نوع الجملة غير مدعوم في البوابة: ' . $verb . '.'];
    }

    /* ما لا يُقبل في أي وضع: كتابة ملفات الخادم، وقراءتها، وما يعلّق الاتصال */
    if (preg_match('/\bINTO\s+(OUTFILE|DUMPFILE)\b|\bLOAD_FILE\s*\(|\bLOAD\s+DATA\b|\bSLEEP\s*\(|\bBENCHMARK\s*\(|\bGET_LOCK\s*\(/i', $sk)) {
        return ['ok' => false, 'error' => 'الجملة فيها دالة أو عبارة ممنوعة في البوابة (ملفات الخادم أو التعليق المتعمد).'];
    }
    if (preg_match('/^[\s(]*(DROP|CREATE)\s+(DATABASE|SCHEMA|USER)\b/i', $sk)
        || preg_match('/^[\s(]*ALTER\s+(DATABASE|SCHEMA|USER)\b/i', $sk)) {
        return ['ok' => false, 'error' => 'إدارة قواعد البيانات والمستخدمين خارج نطاق البوابة.'];
    }

    if (in_array($verb, $reads, true)) {
        return ['ok' => true, 'kind' => 'read', 'verb' => $verb, 'ddl' => false, 'destructive' => false, 'reason' => ''];
    }

    $ddl = in_array($verb, ['CREATE', 'ALTER', 'DROP', 'TRUNCATE', 'RENAME'], true);
    $reason = '';
    if ($verb === 'DROP' || $verb === 'TRUNCATE') {
        $reason = 'حذف جدول أو إفراغه';
    } elseif ($verb === 'ALTER' && preg_match('/\bDROP\b/i', $sk)) {
        $reason = 'حذف عمود أو فهرس';
    } elseif (($verb === 'UPDATE' || $verb === 'DELETE') && !gwHasTopLevelWhere($sk)) {
        $reason = 'تعديل أو حذف بلا شرط WHERE، فيشمل كل الصفوف';
    }

    return ['ok' => true, 'kind' => 'write', 'verb' => $verb, 'ddl' => $ddl,
            'destructive' => $reason !== '', 'reason' => $reason];
}

/** هل اسم هذا العمود من الأعمدة التي لا تخرج قيمها في نتائج القراءة؟ */
function gwIsRedactedColumn(string $name, string $pattern): bool
{
    return $pattern !== '' && @preg_match($pattern, $name) === 1;
}
