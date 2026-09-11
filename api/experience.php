<?php
/* ==========================================================================
 *  وصال — تجربة المستخدم وقياس الأداء
 *
 *  دعوات النسخة التجريبية بالحملات، إجابات الاستبانة، ومؤشرات الأداء.
 *  عمليتا الاستبانة (survey_state و submit_survey) عامتان لأن المدعو يجيب
 *  بلا حساب — والبقية لمدير النظام والمشرف.
 * ========================================================================== */
require_once __DIR__ . '/db.php';

$in  = body();
$act = $in['action'] ?? '';

/** دعوة الطالب: من رمز الرابط إن أُرسل، وإلا من جلسة المدعو */
function resolveInvitationId(array $in): ?int {
    $token = strtolower(clean($in['token'] ?? '', 40));
    if (preg_match('/^[a-f0-9]{40}$/', $token)) {
        $s = db()->prepare('SELECT id FROM invitations WHERE token=? LIMIT 1');
        $s->execute([$token]);
        if ($r = $s->fetch()) return (int)$r['id'];
    }
    return !empty($_SESSION['invitation_id']) ? (int)$_SESSION['invitation_id'] : null;
}

/** المتبقي من سقف دعوات آخر ٢٤ ساعة — null تعني بلا سقف */
function inviteQuotaLeft(): ?int {
    if (INVITE_DAILY_LIMIT <= 0) return null;
    $c = (int)db()->query('SELECT COUNT(*) c FROM invitations
                           WHERE sent_at >= (NOW() - INTERVAL 1 DAY)')->fetch()['c'];
    return max(0, INVITE_DAILY_LIMIT - $c);
}

/* --------------------------------------------------------------------------
 *  حالة الاستبانة — عامة
 *  تعيد حالة الدعوة والإجابات المحفوظة، حتى يكمل المدعو من حيث توقف
 *  لو رجع من نفس الرابط في أي وقت.
 * ------------------------------------------------------------------------ */
if ($act === 'survey_state') {
    rateLimit('survey', 30);
    ensureSchema();
    $invId = resolveInvitationId($in);
    if ($invId === null) out(['ok' => true, 'linked' => false, 'trial' => betaTrialActive()]);

    $s = db()->prepare('SELECT status FROM invitations WHERE id=? LIMIT 1');
    $s->execute([$invId]);
    $inv = $s->fetch();
    if (!$inv) out(['ok' => true, 'linked' => false, 'trial' => betaTrialActive()]);

    $s = db()->prepare('SELECT accessibility_need, ease_of_use, access_difficulty, access_details,
                               trust_in_sources, helped_access_service, pmf_reaction, nps_score,
                               return_intent, missing_service, other_feedback
                        FROM survey_responses WHERE invitation_id=? LIMIT 1');
    $s->execute([$invId]);
    $answers = $s->fetch() ?: null;
    out(['ok' => true, 'linked' => true, 'status' => $inv['status'],
         'answers' => $answers, 'trial' => betaTrialActive()]);
}

/* --------------------------------------------------------------------------
 *  تسليم الاستبانة — عامة
 *  كل الأسئلة اختيارية فردياً (بشرط إجابة واحدة على الأقل)، وإعادة الإرسال
 *  من نفس الدعوة تحدّث الإجابة القائمة بدل تكرار الصفوف.
 * ------------------------------------------------------------------------ */
if ($act === 'submit_survey') {
    rateLimit('survey', 6);
    ensureSchema();
    $invId = resolveInvitationId($in);

    $scale = function ($v, int $min, int $max): ?int {
        if ($v === null || $v === '') return null;
        $n = (int)$v;
        return ($n >= $min && $n <= $max) ? $n : null;
    };
    $oneOf = function ($v, array $allowed): ?string {
        return in_array((string)$v, $allowed, true) ? (string)$v : null;
    };

    $row = [
        'accessibility_need'    => clean($in['accessibility_need'] ?? '', 60) ?: null,
        'ease_of_use'           => $scale($in['ease_of_use'] ?? null, 1, 5),
        'access_difficulty'     => isset($in['access_difficulty']) && $in['access_difficulty'] !== ''
                                   ? (int)(bool)$in['access_difficulty'] : null,
        'access_details'        => clean($in['access_details'] ?? '', 500) ?: null,
        'trust_in_sources'      => $scale($in['trust_in_sources'] ?? null, 1, 5),
        'helped_access_service' => $scale($in['helped_access_service'] ?? null, 1, 5),
        'pmf_reaction'          => $oneOf($in['pmf_reaction'] ?? '',
                                   ['very_disappointed', 'somewhat_disappointed', 'not_disappointed']),
        'nps_score'             => $scale($in['nps_score'] ?? null, 0, 10),
        'return_intent'         => $oneOf($in['return_intent'] ?? '', ['yes', 'maybe', 'no']),
        'missing_service'       => clean($in['missing_service'] ?? '', 500) ?: null,
        'other_feedback'        => clean($in['other_feedback'] ?? '', 1000) ?: null,
    ];
    if (!array_filter($row, fn($v) => $v !== null))
        fail('جاوب على سؤال واحد على الأقل قبل الإرسال.');

    $existing = null;
    if ($invId !== null) {
        $s = db()->prepare('SELECT id FROM survey_responses WHERE invitation_id=? LIMIT 1');
        $s->execute([$invId]);
        $existing = $s->fetch();
    }

    $cols = array_keys($row);
    $vals = array_values($row);
    if ($existing) {
        db()->prepare('UPDATE survey_responses SET ' . implode('=?, ', $cols) . '=? WHERE id=?')
            ->execute([...$vals, (int)$existing['id']]);
    } else {
        db()->prepare('INSERT INTO survey_responses (invitation_id, ' . implode(', ', $cols) . ', created_at)
                       VALUES (?' . str_repeat(', ?', count($cols)) . ', NOW())')
            ->execute([$invId, ...$vals]);
    }
    if ($invId !== null)
        db()->prepare("UPDATE invitations SET status='completed_survey' WHERE id=?")->execute([$invId]);

    out(['ok' => true, 'updated' => (bool)$existing]);
}

/* ---------- بقية العمليات: مدير النظام والمشرف ---------- */
$STAFF = requireStaff();
if (!in_array($STAFF['role'], ['admin', 'mod'], true))
    fail('هذا القسم لمدير النظام والمشرف فقط.', 403);
ensureSchema();

switch ($act) {

/* إرسال دفعة دعوات باسم حملة — سطر لكل بريد أو فواصل بينها */
case 'send_invitations': {
    rateLimit('exp_send', 5);
    $campaign = clean($in['campaign'] ?? '', 80);
    if ($campaign === '') fail('اكتب اسم الحملة أولاً — هو اللي يجمع نتائجها لاحقاً.');

    $rawList = is_array($in['emails'] ?? null)
        ? $in['emails']
        : preg_split('/[\s,;،]+/u', (string)($in['emails'] ?? ''));
    $emails = [];
    foreach ($rawList as $e) {
        $e = strtolower(trim((string)$e));
        if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) $emails[$e] = true;
    }
    $emails = array_keys($emails);
    if (!$emails) fail('اكتب بريداً واحداً صحيحاً على الأقل.');
    if (count($emails) > 50) fail('خمسون بريداً كحد أقصى في الدفعة الواحدة.');

    /* سقف الإرسال اليومي — يحمي سمعة النطاق وحدود مزوّد البريد */
    $quota = inviteQuotaLeft();
    if ($quota !== null && $quota <= 0)
        fail('وصلت سقف دعوات اليوم (' . INVITE_DAILY_LIMIT . '). أكمل بكرة.');
    if ($quota !== null && count($emails) > $quota)
        fail('باقي من سقف اليوم ' . $quota . ' دعوة فقط — قلّل القائمة أو أكمل بكرة.');

    /* البريد المدعو سابقاً في نفس الحملة يُتخطى بدل تكرار دعوته */
    $sent = 0; $failedMail = 0; $skipped = 0;
    $dupe = db()->prepare('SELECT id FROM invitations WHERE email=? AND campaign_name=? LIMIT 1');
    $ins  = db()->prepare("INSERT INTO invitations (email, token, campaign_name, status, sent_at, created_by)
                           VALUES (?,?,?,'sent',NOW(),?)");
    foreach ($emails as $email) {
        $dupe->execute([$email, $campaign]);
        if ($dupe->fetch()) { $skipped++; continue; }
        $token = bin2hex(random_bytes(20));
        $ins->execute([$email, $token, $campaign, $STAFF['id']]);
        $mailed = sendMail($email, 'دعوتك لتجربة وصال', betaInviteEmailHtml(
            SITE_URL . '/invite/' . $token,
            SITE_URL . '/survey.html?t=' . $token
        ));
        $mailed ? $sent++ : $failedMail++;
    }
    audit($STAFF, 'beta_invite', $campaign, "أُرسلت $sent، تعذّر بريد $failedMail، مكررة $skipped");
    out(['ok' => true, 'sent' => $sent, 'failed' => $failedMail, 'skipped' => $skipped,
         'quota_left' => inviteQuotaLeft()]);
}

/* قائمة الدعوات — الأحدث أولاً، مع رابط كل دعوة لنسخه يدوياً عند الحاجة */
case 'invitations': {
    $rows = db()->query('SELECT id, email, token, campaign_name, status, sent_at, clicked_at, tried_at
                         FROM invitations ORDER BY id DESC LIMIT 300')->fetchAll();
    $list = array_map(fn($r) => [
        'id'       => (int)$r['id'],
        'email'    => $r['email'],
        'campaign' => $r['campaign_name'],
        'status'   => $r['status'],
        'link'     => SITE_URL . '/invite/' . $r['token'],
        't'        => strtotime($r['sent_at']) * 1000,
        'clicked'  => $r['clicked_at'] ? strtotime($r['clicked_at']) * 1000 : null,
        'tried'    => $r['tried_at']   ? strtotime($r['tried_at'])   * 1000 : null,
    ], $rows);
    out(['ok' => true, 'invitations' => $list, 'quota_left' => inviteQuotaLeft()]);
}

/* إجابات الاستبانة — الأحدث أولاً، مع بريد وحملة الدعوة إن كانت مربوطة */
case 'responses': {
    $rows = db()->query('SELECT r.id, r.accessibility_need, r.ease_of_use, r.access_difficulty,
                                r.access_details, r.trust_in_sources, r.helped_access_service,
                                r.pmf_reaction, r.nps_score, r.return_intent, r.missing_service,
                                r.other_feedback, r.created_at, i.email, i.campaign_name
                         FROM survey_responses r
                         LEFT JOIN invitations i ON i.id = r.invitation_id
                         ORDER BY r.id DESC LIMIT 300')->fetchAll();
    $int = fn($v) => $v !== null ? (int)$v : null;
    $list = array_map(fn($r) => [
        'id'       => (int)$r['id'],
        'email'    => $r['email'] ?: null,
        'campaign' => $r['campaign_name'] ?: null,
        'need'     => $r['accessibility_need'],
        'ease'     => $int($r['ease_of_use']),
        'difficulty'         => $r['access_difficulty'] !== null ? (bool)$r['access_difficulty'] : null,
        'difficulty_details' => $r['access_details'],
        'trust'    => $int($r['trust_in_sources']),
        'helped'   => $int($r['helped_access_service']),
        'pmf'      => $r['pmf_reaction'],
        'nps'      => $int($r['nps_score']),
        'return'   => $r['return_intent'],
        'missing'  => $r['missing_service'],
        'feedback' => $r['other_feedback'],
        't'        => strtotime($r['created_at']) * 1000,
    ], $rows);
    out(['ok' => true, 'responses' => $list]);
}

/* مؤشرات الأداء — القمع التحويلي ومقاييس الرضا من الإجابات */
case 'metrics': {
    /* الحالة تقدّمية (sent ← clicked ← tried ← completed_survey)،
       فكل مرحلة من القمع تشمل من وصل لما بعدها أيضاً */
    $f = db()->query("SELECT COUNT(*) total,
            COALESCE(SUM(status IN ('clicked','tried','completed_survey')),0) clicked,
            COALESCE(SUM(status IN ('tried','completed_survey')),0) tried,
            COALESCE(SUM(status='completed_survey'),0) surveyed
        FROM invitations")->fetch();

    $m = db()->query("SELECT COUNT(*) n,
            AVG(ease_of_use) ease, AVG(trust_in_sources) trust, AVG(helped_access_service) helped,
            COALESCE(SUM(pmf_reaction='very_disappointed'),0) pmf_very,
            COALESCE(SUM(pmf_reaction IS NOT NULL),0) pmf_n,
            COALESCE(SUM(nps_score>=9),0) promoters,
            COALESCE(SUM(nps_score<=6),0) detractors,
            COALESCE(SUM(nps_score IS NOT NULL),0) nps_n,
            COALESCE(SUM(return_intent='yes'),0) ret_yes,
            COALESCE(SUM(return_intent='maybe'),0) ret_maybe,
            COALESCE(SUM(return_intent='no'),0) ret_no,
            COALESCE(SUM(access_difficulty=1),0) diff_yes,
            COALESCE(SUM(access_difficulty IS NOT NULL),0) diff_n
        FROM survey_responses")->fetch();

    $pct = fn($a, $b) => (int)$b > 0 ? (int)round((int)$a / (int)$b * 100) : null;
    out(['ok' => true, 'metrics' => [
        'sent'        => (int)$f['total'],
        'clicked'     => (int)$f['clicked'],
        'tried'       => (int)$f['tried'],
        'surveyed'    => (int)$f['surveyed'],
        'click_rate'  => $pct($f['clicked'],  $f['total']),
        'try_rate'    => $pct($f['tried'],    $f['total']),
        'survey_rate' => $pct($f['surveyed'], $f['total']),
        'responses'   => (int)$m['n'],
        'ease'        => $m['ease']   !== null ? round((float)$m['ease'],   1) : null,
        'trust'       => $m['trust']  !== null ? round((float)$m['trust'],  1) : null,
        'helped'      => $m['helped'] !== null ? round((float)$m['helped'], 1) : null,
        /* Sean Ellis PMF: نسبة «سأنزعج جداً لو اختفى وصال» — فوق ٤٠٪ ممتاز */
        'pmf'         => $pct($m['pmf_very'], $m['pmf_n']),
        /* NPS: المروجون (٩-١٠) ناقص المنتقدون (٠-٦) */
        'nps'         => (int)$m['nps_n'] > 0
                         ? (int)round(((int)$m['promoters'] - (int)$m['detractors']) / (int)$m['nps_n'] * 100)
                         : null,
        'return_yes'   => (int)$m['ret_yes'],
        'return_maybe' => (int)$m['ret_maybe'],
        'return_no'    => (int)$m['ret_no'],
        'difficulty'   => $pct($m['diff_yes'], $m['diff_n']),
        'quota_left'   => inviteQuotaLeft(),
    ]]);
}

default: fail('طلب غير معروف.');
}
