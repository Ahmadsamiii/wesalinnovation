<?php
/* ==========================================================================
 *  وصال — محتوى الصفحات القابل للتحرير
 *
 *  كل مفتاح هنا يقابل عنصراً في index.html يحمل السمة data-cms.
 *  غياب المفتاح من قاعدة البيانات يعني أن النص الأصلي المكتوب في الصفحة
 *  هو المعروض — أي أن المنصة تعمل كاملة حتى لو كان الجدول فارغاً.
 *
 *  السجل أدناه هو المرجع الوحيد للحقول: منه تُبنى شاشة التحرير في لوحة
 *  مدير النظام، وبه يُتحقَّق من المدخلات عند الحفظ. إضافة حقل = سطر واحد هنا
 *  + سمة data-cms في الصفحة.
 * ========================================================================== */
require_once __DIR__ . '/db.php';

function contentFields(): array {
    return [
        ['g' => 'الهوية والتواصل', 'items' => [
            ['k' => 'social.x',         'l' => 'حساب إكس (X)',            't' => 'url',   'd' => 'https://x.com/Wesalhub'],
            ['k' => 'social.instagram', 'l' => 'حساب إنستقرام',           't' => 'url',   'd' => 'https://www.instagram.com/wesalhub'],
            ['k' => 'social.linkedin',  'l' => 'حساب لينكد إن',           't' => 'url',   'd' => 'https://www.linkedin.com/company/wesalksa0'],
            ['k' => 'contact.email',    'l' => 'البريد الإلكتروني',        't' => 'email', 'd' => 'info@wesal-hub.com'],
            ['k' => 'contact.phone',    'l' => 'رقم الجوال',              't' => 'tel',   'd' => '+966 50 112 0161'],
            ['k' => 'contact.hours',    'l' => 'ساعات العمل',             't' => 'text',  'd' => 'الأحد - الخميس، 8 ص - 6 م'],
        ]],
        ['g' => 'الواجهة الرئيسية', 'items' => [
            ['k' => 'hero.chip',   'l' => 'الشارة العلوية',       't' => 'text', 'd' => 'منصة ذكاء اصطناعي مُيسّرة للجميع'],
            ['k' => 'hero.title',  'l' => 'العنوان الرئيسي',      't' => 'text', 'd' => 'وصال — نفهمك ونسهّل وصولك'],
            ['k' => 'hero.sub',    'l' => 'النص التعريفي',        't' => 'area', 'd' => 'اسأل بلغتك عن حقوقك أو خدماتك أو التقنيات المساعدة، ويجيبك وصال بوضوح من مصادر رسمية سعودية مع ذكر المصدر — قراءةً أو استماعاً. النسخة التجريبية تركّز حالياً على الإعاقة الحركية والبصرية.'],
            ['k' => 'hero.ph',     'l' => 'نص حقل السؤال',        't' => 'text', 'd' => 'اسأل وصال عن أي شيء...'],
            ['k' => 'hero.cta1',   'l' => 'زر الإجراء الأول',     't' => 'text', 'd' => 'جرّب الآن مجاناً'],
            ['k' => 'hero.cta2',   'l' => 'زر الإجراء الثاني',    't' => 'text', 'd' => 'كيف يشتغل وصال'],
            ['k' => 'hero.t1t',    'l' => 'مؤشر ثقة ١ — العنوان', 't' => 'text', 'd' => 'نسخة تجريبية'],
            ['k' => 'hero.t1s',    'l' => 'مؤشر ثقة ١ — الوصف',   't' => 'text', 'd' => 'مفتوحة للجميع'],
            ['k' => 'hero.t2t',    'l' => 'مؤشر ثقة ٢ — العنوان', 't' => 'text', 'd' => 'بمصادر رسمية'],
            ['k' => 'hero.t2s',    'l' => 'مؤشر ثقة ٢ — الوصف',   't' => 'text', 'd' => 'مع كل إجابة'],
            ['k' => 'hero.t3t',    'l' => 'مؤشر ثقة ٣ — العنوان', 't' => 'text', 'd' => '24/7'],
            ['k' => 'hero.t3s',    'l' => 'مؤشر ثقة ٣ — الوصف',   't' => 'text', 'd' => 'متاح'],
        ]],
        ['g' => 'قسم المزايا', 'items' => [
            ['k' => 'feat.label', 'l' => 'عنوان القسم الصغير', 't' => 'text', 'd' => 'المزايا'],
            ['k' => 'feat.title', 'l' => 'عنوان القسم',        't' => 'text', 'd' => 'تجربة ذكية مصممة لتكون في متناول الجميع'],
            ['k' => 'feat.sub',   'l' => 'وصف القسم',          't' => 'area', 'd' => 'نجمع بين قوة الذكاء الاصطناعي وأفضل معايير إمكانية الوصول لنقدم تجربة فريدة ومُيسّرة'],
            ['k' => 'feat.1t', 'l' => 'ميزة ١ — العنوان', 't' => 'text', 'd' => 'إجابات ذكية ومبسّطة'],
            ['k' => 'feat.1d', 'l' => 'ميزة ١ — الوصف',   't' => 'area', 'd' => 'يفهم وصال أسئلتك ويقدّم إجابات واضحة ومبسّطة تناسب احتياجاتك المعلوماتية'],
            ['k' => 'feat.2t', 'l' => 'ميزة ٢ — العنوان', 't' => 'text', 'd' => 'تفاعل صوتي طبيعي'],
            ['k' => 'feat.2d', 'l' => 'ميزة ٢ — الوصف',   't' => 'area', 'd' => 'تحدّث مع وصال بصوتك واستمع للإجابات — مصمم لسهولة الاستخدام لجميع القدرات'],
            ['k' => 'feat.3t', 'l' => 'ميزة ٣ — العنوان', 't' => 'text', 'd' => 'واجهة مُيسّرة بالكامل'],
            ['k' => 'feat.3d', 'l' => 'ميزة ٣ — الوصف',   't' => 'area', 'd' => 'تحكم بحجم الخط والتباين والألوان والحركة لتجربة تناسبك تماماً'],
            ['k' => 'feat.4t', 'l' => 'ميزة ٤ — العنوان', 't' => 'text', 'd' => 'معلومات موثوقة وآمنة'],
            ['k' => 'feat.4d', 'l' => 'ميزة ٤ — الوصف',   't' => 'area', 'd' => 'إجاباتنا من الجهات الرسمية السعودية، ومع كل إجابة اسم مصدرها — وزر إبلاغ فوري عن أي معلومة غير دقيقة'],
            ['k' => 'feat.5t', 'l' => 'ميزة ٥ — العنوان', 't' => 'text', 'd' => 'مكتبة معرفية شاملة'],
            ['k' => 'feat.5d', 'l' => 'ميزة ٥ — الوصف',   't' => 'area', 'd' => 'آلاف الموارد والأدلة الإرشادية المصنّفة والمُيسّرة لسهولة الوصول'],
            ['k' => 'feat.6t', 'l' => 'ميزة ٦ — العنوان', 't' => 'text', 'd' => 'خصوصية في صميم التصميم'],
            ['k' => 'feat.6d', 'l' => 'ميزة ٦ — الوصف',   't' => 'area', 'd' => 'بياناتك لك وحدك: بلا إعلانات، بلا مشاركة مع أي طرف، وتقدر تصدّرها أو تحذفها في أي وقت من حسابك'],
        ]],
        ['g' => 'التذييل', 'items' => [
            ['k' => 'footer.about', 'l' => 'نبذة التذييل', 't' => 'area', 'd' => 'منصة ذكاء اصطناعي سعودية مُيسّرة تجيب الأشخاص ذوي الإعاقة من مصادر رسمية موثوقة، مع ذكر المصدر في كل إجابة'],
        ]],
    ];
}

/** خريطة المفتاح ← تعريفه، لاستخدامها في التحقق */
function contentIndex(): array {
    static $ix = null;
    if ($ix !== null) return $ix;
    $ix = [];
    foreach (contentFields() as $g) foreach ($g['items'] as $it) $ix[$it['k']] = $it;
    return $ix;
}

/** تحقّق حسب نوع الحقل — يعيد رسالة الخطأ أو null إذا كانت القيمة سليمة */
function contentError(array $field, string $v): ?string {
    $max = $field['t'] === 'area' ? 900 : 200;
    if (mb_strlen($v) > $max) return 'النص أطول من الحد المسموح (' . $max . ' حرفاً) في: ' . $field['l'];
    if ($v === '') return null;   // الفراغ يعني الرجوع للنص الأصلي
    switch ($field['t']) {
        case 'url':
            // http/https فقط — لمنع روابط javascript: التي تتحول إلى ثغرة عند النقر
            if (!preg_match('#^https?://#i', $v) || !filter_var($v, FILTER_VALIDATE_URL))
                return 'الرابط لازم يبدأ بـ https:// ويكون صحيحاً في: ' . $field['l'];
            break;
        case 'email':
            if (!filter_var($v, FILTER_VALIDATE_EMAIL)) return 'بريد إلكتروني غير صحيح في: ' . $field['l'];
            break;
        case 'tel':
            if (!preg_match('/^[0-9+\-\s()]{6,24}$/u', $v)) return 'رقم جوال غير صحيح في: ' . $field['l'];
            break;
    }
    return null;
}

$in = body();
switch ($in['action'] ?? 'get') {

    /* عام — تناديه الصفحة عند كل تحميل */
    case 'get': {
        out(['ok' => true, 'content' => contentMap()]);
    }

    /* سجل الحقول لبناء شاشة التحرير — لمدير النظام فقط */
    case 'schema': {
        requireAdmin();
        out(['ok' => true, 'groups' => contentFields(), 'content' => contentMap()]);
    }

    case 'save': {
        $admin = requireAdmin();
        rateLimit('content', 30);
        $items = is_array($in['items'] ?? null) ? $in['items'] : [];
        if (!$items) fail('ما فيه تغييرات للحفظ.');

        $ix = contentIndex();
        $clean = [];
        foreach ($items as $k => $v) {
            $k = (string)$k;
            if (!isset($ix[$k])) fail('حقل غير معروف: ' . clean($k, 80));
            $v = trim(strip_tags((string)$v));
            if ($e = contentError($ix[$k], $v)) fail($e);
            $clean[$k] = $v;
        }

        $set = db()->prepare('INSERT INTO site_content (ckey,cval,updated_by,updated_at) VALUES (?,?,?,NOW())
                              ON DUPLICATE KEY UPDATE cval=VALUES(cval), updated_by=VALUES(updated_by), updated_at=NOW()');
        $del = db()->prepare('DELETE FROM site_content WHERE ckey=?');
        db()->beginTransaction();
        try {
            foreach ($clean as $k => $v) {
                // القيمة الفارغة تحذف الصف فيعود النص الأصلي المكتوب في الصفحة
                if ($v === '') $del->execute([$k]);
                else           $set->execute([$k, $v, $admin['id']]);
            }
            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            fail(APP_DEBUG ? $e->getMessage() : 'تعذّر حفظ المحتوى. حاول مرة أخرى.', 500);
        }

        audit($admin, 'content', implode('، ', array_keys($clean)), count($clean) . ' حقلاً');
        out(['ok' => true, 'content' => contentMap(), 'saved' => count($clean)]);
    }

    /* إرجاع مجموعة كاملة لنصوصها الأصلية */
    case 'reset_group': {
        $admin = requireAdmin();
        $g = clean($in['group'] ?? '', 60);
        $keys = [];
        foreach (contentFields() as $grp) if ($grp['g'] === $g) foreach ($grp['items'] as $it) $keys[] = $it['k'];
        if (!$keys) fail('مجموعة غير معروفة.');
        $ph = implode(',', array_fill(0, count($keys), '?'));
        db()->prepare("DELETE FROM site_content WHERE ckey IN ($ph)")->execute($keys);
        audit($admin, 'content_reset', $g, count($keys) . ' حقلاً');
        out(['ok' => true, 'content' => contentMap()]);
    }

    default: fail('طلب غير معروف.');
}
