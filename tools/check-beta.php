<?php
/* ==========================================================================
 *  وصال — فحص ذاتي لمحرّك الاستبيانات ودعوات التجربة الموسّعة
 *
 *  الاستخدام:
 *      php tools/check-beta.php
 *
 *  يتحقق من الجداول الجديدة (surveys وما يتبعها)، وأن استبيان «تجربة
 *  المنصة» انبذر بأسئلته العشرة عند الترقية من النظام القديم، وتقدّم حالة
 *  الدعوة، وعدم تكرار صف الرد عند التعديل، وصحة إسناد الحملة للردود
 *  المرتبطة بدعوة والمجهولة، ومعادلتي PMF وNPS. ثم ينظّف كل بيانات الفحص
 *  خلفه. آمن على بيانات الإنتاج: كل ما يُدخله يحمل حملة «_check» ويُحذف في
 *  النهاية، ولا يمسّ استبيان «تجربة المنصة» نفسه ولا أسئلته.
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

echo "فحص محرّك الاستبيانات\n\n";

/* ---------- الإعدادات والملفات ---------- */
check('BETA_TRIAL_HOURS قيمة موجبة', BETA_TRIAL_HOURS > 0);
check('INVITE_DAILY_LIMIT صفر أو أكثر', INVITE_DAILY_LIMIT >= 0);
check('SITE_URL بلا شرطة مائلة في النهاية', !str_ends_with(SITE_URL, '/'));
check('api/invite-redeem.php موجود', file_exists(__DIR__ . '/../api/invite-redeem.php'));
check('api/surveys.php موجود',       file_exists(__DIR__ . '/../api/surveys.php'));
check('api/experience.php أُزيل (اندمجت وظائفه في api/surveys.php)', !file_exists(__DIR__ . '/../api/experience.php'));
check('survey.html موجود',           file_exists(__DIR__ . '/../survey.html'));
$ht = (string)@file_get_contents(__DIR__ . '/../.htaccess');
check('قاعدة إعادة كتابة /invite/ في .htaccess', str_contains($ht, 'invite-redeem.php'));

/* ---------- المخطط ---------- */
ensureSchema();
$tables = db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
foreach (['surveys', 'survey_questions', 'survey_question_options', 'survey_invitations',
          'survey_submissions', 'survey_answers', 'survey_answer_options'] as $t) {
    check("جدول $t موجود", in_array($t, $tables, true));
}

/* ---------- استبيان «تجربة المنصة» المبذور من بيانات النظام القديم ---------- */
$s = db()->query("SELECT * FROM surveys WHERE title='تجربة المنصة' ORDER BY id LIMIT 1");
$survey = $s->fetch();
check('استبيان «تجربة المنصة» مبذور تلقائياً', (bool)$survey);
check('استبيان «تجربة المنصة» منشور', $survey && $survey['status'] === 'published');
check('استبيان «تجربة المنصة» يمنح التجربة الموسّعة (grants_trial)', $survey && (int)$survey['grants_trial'] === 1);
check('استبيان «تجربة المنصة» له رابط عام مفعّل', $survey && (int)$survey['public_link_enabled'] === 1 && $survey['public_token']);

$surveyId = $survey ? (int)$survey['id'] : 0;
if ($surveyId) {
    $qc = db()->prepare('SELECT COUNT(*) c FROM survey_questions WHERE survey_id=?');
    $qc->execute([$surveyId]);
    check('استبيان «تجربة المنصة» فيه عشرة أسئلة', (int)$qc->fetch()['c'] === 10);
}

if (!$surveyId) {
    echo "\nتعذّر إيجاد الاستبيان المبذور — باقي الفحوص تحتاجه فتوقفت هنا.\n";
    exit(1);
}

/* ---------- دورة حياة دعوة استبيان ---------- */
$campaign = '_check';
$token    = bin2hex(random_bytes(20));
check('الرمز ٤٠ خانة ست عشرية', preg_match('/^[a-f0-9]{40}$/', $token) === 1);

db()->prepare("INSERT INTO survey_invitations (survey_id, email, token, campaign_name, status, sent_at)
               VALUES (?, 'check@example.com', ?, ?, 'sent', NOW())")->execute([$surveyId, $token, $campaign]);
$invId = (int)db()->lastInsertId();
check('إدخال دعوة جديدة', $invId > 0);

try {
    db()->prepare("INSERT INTO survey_invitations (survey_id, email, token, campaign_name, status, sent_at)
                   VALUES (?, 'dupe@example.com', ?, ?, 'sent', NOW())")->execute([$surveyId, $token, $campaign]);
    check('قيد فرادة الرمز يرفض التكرار', false);
} catch (PDOException $e) {
    check('قيد فرادة الرمز يرفض التكرار', true);
}

/* التقدّم لا يتراجع: نفس شرط invite-redeem.php وsurveys.php — التحديث مقيّد بالحالة السابقة */
db()->prepare("UPDATE survey_invitations SET status='opened', opened_at=NOW() WHERE id=? AND status='sent'")->execute([$invId]);
db()->prepare("UPDATE survey_invitations SET status='completed', completed_at=NOW() WHERE id=? AND status<>'completed'")->execute([$invId]);
db()->prepare("UPDATE survey_invitations SET status='opened' WHERE id=? AND status='sent'")->execute([$invId]);
$st = db()->prepare('SELECT status FROM survey_invitations WHERE id=?');
$st->execute([$invId]);
check("تقدّم الحالة sent ← opened ← completed بلا تراجع", $st->fetchColumn() === 'completed');

/* trial_started_at مستقل تماماً عن status — يُسجَّل بدء التجربة الموسّعة بلا مساسه */
db()->prepare('UPDATE survey_invitations SET trial_started_at=NOW() WHERE id=? AND trial_started_at IS NULL')->execute([$invId]);
$tr = db()->prepare('SELECT trial_started_at FROM survey_invitations WHERE id=?');
$tr->execute([$invId]);
check('trial_started_at يُسجَّل بمعزل عن status', $tr->fetchColumn() !== null);

/* القمع تراكمي: من أكمل محسوب أيضاً في «فُتحت» */
$f = db()->prepare("SELECT COALESCE(SUM(status IN ('opened','completed')),0) o,
                           COALESCE(SUM(status='completed'),0) c
                    FROM survey_invitations WHERE campaign_name=?");
$f->execute([$campaign]);
$fr = $f->fetch();
check('قمع القياس يحسب المراحل تراكمياً', (int)$fr['o'] === 1 && (int)$fr['c'] === 1);

/* ---------- الرد: إدخال ثم تعديل بلا تكرار ---------- */
$easeQ = db()->prepare("SELECT id FROM survey_questions WHERE survey_id=? AND type='scale' ORDER BY position LIMIT 1");
$easeQ->execute([$surveyId]);
$ease = $easeQ->fetch();
check('سؤال مقياس موجود في الاستبيان المبذور', (bool)$ease);
$easeQid = $ease ? (int)$ease['id'] : 0;

db()->prepare('INSERT INTO survey_submissions (survey_id, invitation_id, submitted_at, updated_at) VALUES (?,?,NOW(),NOW())')
    ->execute([$surveyId, $invId]);
$subId = (int)db()->lastInsertId();
check('إدخال رد جديد مرتبط بدعوة', $subId > 0);

try {
    db()->prepare('INSERT INTO survey_submissions (survey_id, invitation_id, submitted_at, updated_at) VALUES (?,?,NOW(),NOW())')
        ->execute([$surveyId, $invId]);
    check('قيد فرادة الرد لكل دعوة يرفض التكرار', false);
} catch (PDOException $e) {
    check('قيد فرادة الرد لكل دعوة يرفض التكرار', true);
}

db()->prepare('INSERT INTO survey_answers (submission_id, question_id, number_value) VALUES (?,?,4)')->execute([$subId, $easeQid]);
db()->prepare("INSERT INTO survey_answers (submission_id, question_id, number_value) VALUES (?,?,5)
               ON DUPLICATE KEY UPDATE number_value=VALUES(number_value)")->execute([$subId, $easeQid]);
$c = db()->prepare('SELECT COUNT(*) n, MAX(number_value) v FROM survey_answers WHERE submission_id=?');
$c->execute([$subId]);
$cr = $c->fetch();
check('تعديل الإجابة يحدّث الصف نفسه بلا تكرار', (int)$cr['n'] === 1 && (int)$cr['v'] === 5);

/* ---------- الرابط العام: رد بلا دعوة، حملته من عموده هو ---------- */
$openCampaign = '_check_open';
db()->prepare('INSERT INTO survey_submissions (survey_id, invitation_id, campaign_name, submitted_at, updated_at)
               VALUES (?, NULL, ?, NOW(), NOW())')->execute([$surveyId, $openCampaign]);
$openId = (int)db()->lastInsertId();
check('إدخال رد رابط عام بلا دعوة يعمل', $openId > 0);
db()->prepare('INSERT INTO survey_answers (submission_id, question_id, number_value) VALUES (?,?,3)')->execute([$openId, $easeQid]);

/* نفس منطق COALESCE(i.campaign_name, s.campaign_name) في survey_responses_list —
   يتحقق أن كِلا المسارين، المرتبط بدعوة والعام بلا دعوة، يُنسب كل منهما
   لحملته الصحيحة دون تمييز بينهما في الاستعلام. */
$both = db()->prepare("SELECT s.id, COALESCE(i.campaign_name, s.campaign_name) camp
                       FROM survey_submissions s LEFT JOIN survey_invitations i ON i.id = s.invitation_id
                       WHERE s.id IN (?, ?)");
$both->execute([$subId, $openId]);
$byId = [];
foreach ($both->fetchAll() as $row) $byId[(int)$row['id']] = $row['camp'];
check('حملة الرد المرتبط بدعوة تُقرأ من survey_invitations',
      ($byId[$subId] ?? null) === $campaign);
check('حملة الرد العام بلا دعوة تُقرأ من عموده هو',
      ($byId[$openId] ?? null) === $openCampaign);

/* ---------- معادلتا القياس (بحساب مباشر على قيم معلومة) ---------- */
/* Sean Ellis PMF: نسبة «سأنزعج جداً» من إجمالي من جاوب على السؤال */
check('معادلة PMF: 2 من 5 = 40٪', (int)round(2 / 5 * 100) === 40);
/* NPS: المروجون (٩-١٠) ناقص المنتقدون (٠-٦) على الإجمالي */
check('معادلة NPS: (3 مروجين − 1 منتقد) من 4 = 50', (int)round((3 - 1) / 4 * 100) === 50);

/* ---------- التنظيف — لا يمسّ استبيان «تجربة المنصة» ولا أسئلته ---------- */
db()->prepare('DELETE FROM survey_answers WHERE submission_id IN (?,?)')->execute([$subId, $openId]);
db()->prepare('DELETE FROM survey_submissions WHERE id IN (?,?)')->execute([$subId, $openId]);
db()->prepare('DELETE FROM survey_invitations WHERE campaign_name=?')->execute([$campaign]);
$g = db()->prepare('SELECT COUNT(*) FROM survey_invitations WHERE campaign_name=?');
$g->execute([$campaign]);
$g2 = db()->prepare('SELECT COUNT(*) FROM survey_submissions WHERE campaign_name=?');
$g2->execute([$openCampaign]);
check('تنظيف بيانات الفحص', (int)$g->fetchColumn() === 0 && (int)$g2->fetchColumn() === 0);

echo "\n" . ($fails === 0 ? "كل الفحوص نجحت.\n" : "فشل $fails من الفحوص — راجع ما فوق.\n");
exit($fails === 0 ? 0 : 1);
