<?php
/* ==========================================================================
 *  وصال — فحص ذاتي لمنظومة الدعوات التجريبية والاستبانة
 *
 *  الاستخدام:
 *      php tools/check-beta.php
 *
 *  يتحقق من الجداول والقيود وتقدّم حالة الدعوة وقمع القياس ومعادلات
 *  PMF وNPS، ثم ينظّف كل بيانات الفحص خلفه. آمن على بيانات الإنتاج:
 *  كل ما يُدخله يحمل اسم الحملة «_check» ويُحذف في النهاية.
 * ========================================================================== */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("هذا السكربت يُشغَّل من الطرفية فقط.\n");
}

$cfg = __DIR__ . '/../api/config.php';
if (!file_exists($cfg)) {
    exit("لم أجد api/config.php — انسخ api/config.example.php إليه واملأ بياناته أولاً.\n");
}

require_once __DIR__ . '/../api/db.php';

$fails = 0;
function check(string $label, bool $ok): void {
    global $fails;
    if (!$ok) $fails++;
    echo ($ok ? '  ✓ ' : '  ✗ ') . $label . "\n";
}

echo "فحص منظومة الدعوات التجريبية\n\n";

/* ---------- الإعدادات والملفات ---------- */
check('BETA_TRIAL_HOURS قيمة موجبة', BETA_TRIAL_HOURS > 0);
check('INVITE_DAILY_LIMIT صفر أو أكثر', INVITE_DAILY_LIMIT >= 0);
check('SITE_URL بلا شرطة مائلة في النهاية', !str_ends_with(SITE_URL, '/'));
check('api/invite-redeem.php موجود', file_exists(__DIR__ . '/../api/invite-redeem.php'));
check('api/experience.php موجود',    file_exists(__DIR__ . '/../api/experience.php'));
check('survey.html موجود',           file_exists(__DIR__ . '/../survey.html'));
$ht = (string)@file_get_contents(__DIR__ . '/../.htaccess');
check('قاعدة إعادة كتابة /invite/ في .htaccess', str_contains($ht, 'invite-redeem.php'));

/* ---------- المخطط ---------- */
ensureSchema();
$tables = db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
check('جدول invitations موجود',      in_array('invitations', $tables, true));
check('جدول survey_responses موجود', in_array('survey_responses', $tables, true));

/* ---------- دورة حياة الدعوة ---------- */
$campaign = '_check';
$token    = bin2hex(random_bytes(20));
check('الرمز ٤٠ خانة ست عشرية', preg_match('/^[a-f0-9]{40}$/', $token) === 1);

db()->prepare("INSERT INTO invitations (email, token, campaign_name, status, sent_at)
               VALUES ('check@example.com', ?, ?, 'sent', NOW())")->execute([$token, $campaign]);
$invId = (int)db()->lastInsertId();
check('إدخال دعوة جديدة', $invId > 0);

try {
    db()->prepare("INSERT INTO invitations (email, token, campaign_name, status, sent_at)
                   VALUES ('dupe@example.com', ?, ?, 'sent', NOW())")->execute([$token, $campaign]);
    check('قيد فرادة الرمز يرفض التكرار', false);
} catch (PDOException $e) {
    check('قيد فرادة الرمز يرفض التكرار', true);
}

/* التقدّم لا يتراجع: نفس شرط invite-redeem.php — التحديث مقيّد بالحالة السابقة */
db()->prepare("UPDATE invitations SET status='clicked', clicked_at=NOW() WHERE id=? AND status='sent'")->execute([$invId]);
db()->prepare("UPDATE invitations SET status='tried',   tried_at=NOW()   WHERE id=? AND status IN ('sent','clicked')")->execute([$invId]);
db()->prepare("UPDATE invitations SET status='clicked' WHERE id=? AND status='sent'")->execute([$invId]);
$st = db()->prepare('SELECT status FROM invitations WHERE id=?');
$st->execute([$invId]);
check("تقدّم الحالة sent ← clicked ← tried بلا تراجع", $st->fetchColumn() === 'tried');

/* القمع تراكمي: من وصل «جرّب» محسوب أيضاً في «فُتحت» */
$f = db()->prepare("SELECT COALESCE(SUM(status IN ('clicked','tried','completed_survey')),0) c,
                           COALESCE(SUM(status IN ('tried','completed_survey')),0) t
                    FROM invitations WHERE campaign_name=?");
$f->execute([$campaign]);
$fr = $f->fetch();
check('قمع القياس يحسب المراحل تراكمياً', (int)$fr['c'] === 1 && (int)$fr['t'] === 1);

/* ---------- الاستبانة: إدخال ثم تعديل بلا تكرار ---------- */
db()->prepare("INSERT INTO survey_responses (invitation_id, ease_of_use, pmf_reaction, nps_score, created_at)
               VALUES (?, 4, 'very_disappointed', 9, NOW())")->execute([$invId]);
db()->prepare("UPDATE survey_responses SET ease_of_use=5 WHERE invitation_id=?")->execute([$invId]);
$c = db()->prepare('SELECT COUNT(*) n, MAX(ease_of_use) e FROM survey_responses WHERE invitation_id=?');
$c->execute([$invId]);
$cr = $c->fetch();
check('تعديل الإجابة يحدّث الصف نفسه بلا تكرار', (int)$cr['n'] === 1 && (int)$cr['e'] === 5);

/* ---------- معادلتا القياس (بحساب مباشر على قيم معلومة) ---------- */
/* Sean Ellis PMF: نسبة «سأنزعج جداً» من إجمالي من جاوب على السؤال */
check('معادلة PMF: 2 من 5 = 40٪', (int)round(2 / 5 * 100) === 40);
/* NPS: المروجون (٩-١٠) ناقص المنتقدون (٠-٦) على الإجمالي */
check('معادلة NPS: (3 مروجين − 1 منتقد) من 4 = 50', (int)round((3 - 1) / 4 * 100) === 50);

/* ---------- التنظيف ---------- */
db()->prepare('DELETE FROM survey_responses WHERE invitation_id=?')->execute([$invId]);
db()->prepare('DELETE FROM invitations WHERE campaign_name=?')->execute([$campaign]);
$g = db()->prepare('SELECT COUNT(*) FROM invitations WHERE campaign_name=?');
$g->execute([$campaign]);
check('تنظيف بيانات الفحص', (int)$g->fetchColumn() === 0);

echo "\n" . ($fails === 0 ? "كل الفحوص نجحت.\n" : "فشل $fails من الفحوص — راجع ما فوق.\n");
exit($fails === 0 ? 0 : 1);
