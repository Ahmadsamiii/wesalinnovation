<?php
/* ==========================================================================
 *  وصال — استلام رابط دعوة استبيان
 *
 *  GET /invite/{token} — قاعدة إعادة الكتابة في .htaccess تسلّم الرمز هنا.
 *  الاستبيان الذي يمنح تجربة موسّعة (grants_trial=1، كاستبيان «تجربة المنصة»
 *  الافتراضي) يفعّلها في الجلسة ويحوّل للصفحة الرئيسية كما كان دائماً؛ أي
 *  استبيان آخر يحوّل مباشرة لصفحته. لا JSON هنا لأن الطالب متصفح قادم من
 *  رسالة بريد، لا الواجهة.
 * ========================================================================== */
require_once __DIR__ . '/db.php';

header('Content-Type: text/html; charset=utf-8');   // تلغي ترويسة JSON من db.php

/** تحويل للواجهة — الصفحة الرئيسية تقرأ المعاملات وتعرض الرسالة المناسبة */
function redirectHome(string $qs): void {
    header('Location: /?' . $qs, true, 302);
    exit;
}
function redirectSurvey(string $token): void {
    header('Location: /survey.html?t=' . $token, true, 302);
    exit;
}

rateLimit('invite_redeem', 20);

$token = strtolower((string)($_GET['token'] ?? ''));
if (!preg_match('/^[a-f0-9]{40}$/', $token)) redirectHome('invited=0');

ensureSchema();
$s = db()->prepare('SELECT id, survey_id, status FROM survey_invitations WHERE token=? LIMIT 1');
$s->execute([$token]);
$inv = $s->fetch();
if (!$inv) redirectHome('invited=0');

$sv = db()->prepare('SELECT grants_trial FROM surveys WHERE id=? LIMIT 1');
$sv->execute([(int)$inv['survey_id']]);
$grantsTrial = (bool)($sv->fetch()['grants_trial'] ?? false);

$_SESSION['invitation_id'] = (int)$inv['id'];
unset($_SESSION['invitation_tried']);

/* أول فتح للرابط يقدّم الحالة إلى «فُتح» — والحالات الأبعد لا تتراجع */
if ($inv['status'] === 'sent') {
    db()->prepare("UPDATE survey_invitations SET status='opened', opened_at=NOW()
                   WHERE id=? AND status='sent'")
        ->execute([(int)$inv['id']]);
}

if (!$grantsTrial) redirectSurvey($token);

/* تفعيل التجربة الموسّعة في الجلسة — chat.php يفحصها عبر betaTrialActive().
   إعادة فتح الرابط تجدّد المدة، فالمدعو لا يُحرم لو تأخر عن أول نقرة. */
$until = time() + BETA_TRIAL_HOURS * 3600;
$_SESSION['beta_trial_until'] = $until;

/* الرمز يرجع للواجهة حتى تعرض للمدعو زر «كمّل الاستبانة» بعد ما يجرّب */
redirectHome('invited=1&trial_until=' . ($until * 1000) . '&t=' . $token);
