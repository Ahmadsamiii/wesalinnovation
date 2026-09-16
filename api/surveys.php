<?php
/* ==========================================================================
 *  وصال — محرّك الاستبيانات
 *
 *  استبيانات غير محدودة، أسئلتها بالكامل تحت تحكم مدير النظام والمشرف: رابط
 *  عام لأي زائر مجهول، ورابط دعوة شخصي يُرسل بالبريد، ويتعايشان على نفس
 *  الاستبيان. يحل محل api/experience.php (أُرشِف بعد ترحيل بياناته في
 *  migrateSurveys() — راجع api/db.php).
 *
 *  survey_public_state وsurvey_submit عامتان بلا حساب (المجيب زائر دائماً)،
 *  والبقية لمدير النظام والمشرف فقط.
 * ========================================================================== */
require_once __DIR__ . '/db.php';

$in  = body();
$act = $in['action'] ?? '';

/* -------------------------------------------------------------------------
 *  أدوات مشتركة
 * ---------------------------------------------------------------------- */

function surveyById(int $id): ?array {
    $s = db()->prepare('SELECT * FROM surveys WHERE id=? LIMIT 1');
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

/** يحل الاستبيان والدعوة (إن وُجدت) من رمز دعوة شخصي، أو جلسة سابقة تركها
 *  ذلك الرمز، أو رمز الرابط العام — بهذا الترتيب. */
function resolveSurveyContext(array $in): array {
    $token = strtolower(clean($in['token'] ?? '', 40));
    if (preg_match('/^[a-f0-9]{40}$/', $token)) {
        $s = db()->prepare('SELECT * FROM survey_invitations WHERE token=? LIMIT 1');
        $s->execute([$token]);
        if ($inv = $s->fetch()) {
            $survey = surveyById((int)$inv['survey_id']);
            if ($survey) return [$survey, $inv];
        }
        return [null, null];
    }
    if (!empty($_SESSION['invitation_id'])) {
        $s = db()->prepare('SELECT * FROM survey_invitations WHERE id=? LIMIT 1');
        $s->execute([(int)$_SESSION['invitation_id']]);
        if ($inv = $s->fetch()) {
            $survey = surveyById((int)$inv['survey_id']);
            if ($survey) return [$survey, $inv];
        }
    }
    $public = strtolower(clean($in['public'] ?? '', 40));
    if (preg_match('/^[a-f0-9]{40}$/', $public)) {
        $s = db()->prepare('SELECT * FROM surveys WHERE public_token=? AND public_link_enabled=1 LIMIT 1');
        $s->execute([$public]);
        if ($survey = $s->fetch()) return [$survey, null];
    }
    return [null, null];
}

/** أسئلة استبيان بترتيبها، كل سؤال ومعه خياراته */
function loadSurveyQuestions(int $surveyId): array {
    $qs = db()->prepare('SELECT * FROM survey_questions WHERE survey_id=? ORDER BY position, id');
    $qs->execute([$surveyId]);
    $questions = $qs->fetchAll();
    if (!$questions) return [];
    $ids = array_map(fn($q) => (int)$q['id'], $questions);
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $os = db()->prepare("SELECT * FROM survey_question_options WHERE question_id IN ($ph) ORDER BY position, id");
    $os->execute($ids);
    $byQ = [];
    foreach ($os->fetchAll() as $o) $byQ[(int)$o['question_id']][] = $o;
    foreach ($questions as &$q) $q['options'] = $byQ[(int)$q['id']] ?? [];
    unset($q);
    return $questions;
}

/** شكل السؤال كما يخرج للواجهة (عامة كانت أو إدارية) */
function questionOut(array $q): array {
    return [
        'id' => (int)$q['id'], 'type' => $q['type'],
        'question_text' => $q['question_text'], 'help_text' => $q['help_text'],
        'placeholder' => $q['placeholder'], 'is_required' => (bool)$q['is_required'],
        'scale_min' => $q['scale_min'] !== null ? (int)$q['scale_min'] : null,
        'scale_max' => $q['scale_max'] !== null ? (int)$q['scale_max'] : null,
        'scale_min_label' => $q['scale_min_label'], 'scale_max_label' => $q['scale_max_label'],
        'max_length' => $q['max_length'] !== null ? (int)$q['max_length'] : null,
        'options' => array_map(fn($o) => [
            'id' => (int)$o['id'], 'text' => $o['option_text'], 'value' => $o['option_value'],
            'has_followup' => (bool)$o['has_followup'], 'followup_label' => $o['followup_label'],
            'followup_max' => $o['followup_max'] !== null ? (int)$o['followup_max'] : null,
        ], $q['options']),
    ];
}

/* -------------------------------------------------------------------------
 *  حالة الاستبيان — عامة
 *  تعيد تعريف الأسئلة، وحالة الدعوة وأي إجابات سابقة لو الوصول برمز دعوة
 *  شخصي (الرابط العام لا يعيد إجابات سابقة أبداً — لا هوية تربطه بها).
 * ---------------------------------------------------------------------- */
if ($act === 'survey_public_state') {
    rateLimit('survey_state', 30);
    ensureSchema();
    [$survey, $inv] = resolveSurveyContext($in);
    if (!$survey || $survey['status'] !== 'published') out(['ok' => true, 'available' => false]);

    $answers = null;
    if ($inv) {
        $answers = [];
        $s = db()->prepare('SELECT sa.question_id, sa.answer_text, sa.option_id, sa.number_value
                            FROM survey_answers sa JOIN survey_submissions sub ON sub.id = sa.submission_id
                            WHERE sub.invitation_id=?');
        $s->execute([(int)$inv['id']]);
        foreach ($s->fetchAll() as $a) {
            $answers[(int)$a['question_id']] = [
                'option_id' => $a['option_id'] !== null ? (int)$a['option_id'] : null,
                'option_ids' => [],
                'text' => $a['answer_text'],
                'number' => $a['number_value'] !== null ? (int)$a['number_value'] : null,
            ];
        }
        $mo = db()->prepare('SELECT sa.question_id, sao.option_id FROM survey_answer_options sao
                             JOIN survey_answers sa ON sa.id = sao.answer_id
                             JOIN survey_submissions sub ON sub.id = sa.submission_id
                             WHERE sub.invitation_id=?');
        $mo->execute([(int)$inv['id']]);
        foreach ($mo->fetchAll() as $r) {
            $qid = (int)$r['question_id'];
            if (!isset($answers[$qid])) $answers[$qid] = ['option_id' => null, 'option_ids' => [], 'text' => null, 'number' => null];
            $answers[$qid]['option_ids'][] = (int)$r['option_id'];
        }
    }

    out(['ok' => true, 'available' => true,
        'survey' => [
            'id' => (int)$survey['id'], 'title' => $survey['title'], 'description' => $survey['description'],
            'thank_you_message' => $survey['thank_you_message'], 'grants_trial' => (bool)$survey['grants_trial'],
        ],
        'questions' => array_map('questionOut', loadSurveyQuestions((int)$survey['id'])),
        'linked' => (bool)$inv, 'status' => $inv['status'] ?? null,
        'trial_started' => $inv ? ($inv['trial_started_at'] !== null) : false,
        'answers' => $answers, 'trial' => betaTrialActive()]);
}

/* -------------------------------------------------------------------------
 *  تسليم الاستبيان — عامة
 *  الرابط العام: تسليم جديد دائماً (بلا هوية يُبنى عليها تحديث لاحق).
 *  رابط الدعوة الشخصي: تحديث نفس الصف بدل تكرار الرد، كما كان في النظام
 *  القديم — والحالة تتقدم فقط (sent/opened → completed) ولا تتراجع.
 * ---------------------------------------------------------------------- */
if ($act === 'survey_submit') {
    rateLimit('survey_submit', 6);
    ensureSchema();
    [$survey, $inv] = resolveSurveyContext($in);
    if (!$survey || $survey['status'] !== 'published') fail('هذا الاستبيان غير متاح حالياً.');
    $surveyId = (int)$survey['id'];
    $invId    = $inv ? (int)$inv['id'] : null;
    $openCampaign = $invId === null ? (clean($in['campaign'] ?? '', 80) ?: null) : null;

    $byId = []; $optsById = [];
    foreach (loadSurveyQuestions($surveyId) as $q) {
        $byId[(int)$q['id']] = $q;
        foreach ($q['options'] as $o) $optsById[(int)$q['id']][(int)$o['id']] = true;
    }

    $subId = null;
    if ($invId !== null) {
        $s = db()->prepare('SELECT id FROM survey_submissions WHERE invitation_id=? LIMIT 1');
        $s->execute([$invId]);
        if ($row = $s->fetch()) $subId = (int)$row['id'];
    }
    $isNew = $subId === null;
    if ($isNew) {
        db()->prepare('INSERT INTO survey_submissions (survey_id, invitation_id, campaign_name, submitted_at, updated_at)
                       VALUES (?,?,?,NOW(),NOW())')->execute([$surveyId, $invId, $openCampaign]);
        $subId = (int)db()->lastInsertId();
    } else {
        db()->prepare('UPDATE survey_submissions SET updated_at=NOW() WHERE id=?')->execute([$subId]);
    }

    $saveAnswer = function (int $qid, ?string $text, ?int $optId, ?int $num) use ($subId): int {
        db()->prepare('INSERT INTO survey_answers (submission_id, question_id, answer_text, option_id, number_value)
                       VALUES (?,?,?,?,?)
                       ON DUPLICATE KEY UPDATE answer_text=VALUES(answer_text), option_id=VALUES(option_id),
                       number_value=VALUES(number_value), id=LAST_INSERT_ID(id)')
            ->execute([$subId, $qid, $text, $optId, $num]);
        return (int)db()->lastInsertId();
    };

    $answeredAny = false;
    foreach ((array)($in['answers'] ?? []) as $a) {
        if (!is_array($a) || !isset($a['question_id'])) continue;
        $qid = (int)$a['question_id'];
        if (!isset($byId[$qid])) continue;   // سؤال لا يتبع هذا الاستبيان — يُتجاهل
        $q = $byId[$qid];

        if ($q['type'] === 'single_choice') {
            $optId = (isset($a['option_id']) && $a['option_id'] !== '') ? (int)$a['option_id'] : null;
            if ($optId !== null && !isset($optsById[$qid][$optId])) $optId = null;
            $follow = isset($a['followup_text']) ? clean($a['followup_text'], 500) : '';
            if ($optId === null && $follow === '') continue;
            $saveAnswer($qid, $follow ?: null, $optId, null);
            $answeredAny = true;
        } elseif ($q['type'] === 'multi_choice') {
            $ids = array_map('intval', is_array($a['option_ids'] ?? null) ? $a['option_ids'] : []);
            $ids = array_values(array_unique(array_filter($ids, fn($id) => isset($optsById[$qid][$id]))));
            if (!$ids) continue;
            $ansId = $saveAnswer($qid, null, null, null);
            db()->prepare('DELETE FROM survey_answer_options WHERE answer_id=?')->execute([$ansId]);
            $aoIns = db()->prepare('INSERT INTO survey_answer_options (answer_id, option_id) VALUES (?,?)');
            foreach ($ids as $oid) $aoIns->execute([$ansId, $oid]);
            $answeredAny = true;
        } elseif ($q['type'] === 'scale') {
            if (!isset($a['number']) || $a['number'] === '' || $a['number'] === null) continue;
            $n = (int)$a['number'];
            if ($n < (int)$q['scale_min'] || $n > (int)$q['scale_max']) continue;
            $saveAnswer($qid, null, null, $n);
            $answeredAny = true;
        } else {
            $t = clean($a['text'] ?? '', (int)($q['max_length'] ?: 1000));
            if ($t === '') continue;
            $saveAnswer($qid, $t, null, null);
            $answeredAny = true;
        }
    }

    if (!$answeredAny) {
        if ($isNew) db()->prepare('DELETE FROM survey_submissions WHERE id=?')->execute([$subId]);
        fail('جاوب على سؤال واحد على الأقل قبل الإرسال.');
    }

    if ($invId !== null) {
        db()->prepare("UPDATE survey_invitations SET status='completed', completed_at=NOW()
                       WHERE id=? AND status<>'completed'")->execute([$invId]);
    }

    out(['ok' => true, 'updated' => !$isNew]);
}

/* ---------- بقية العمليات: مدير النظام والمشرف ---------- */
$STAFF = requireStaff();
if (!in_array($STAFF['role'], ['admin', 'mod'], true))
    fail('هذا القسم لمدير النظام والمشرف فقط.', 403);
ensureSchema();

/** المتبقي من سقف دعوات آخر ٢٤ ساعة عبر كل الاستبيانات — نفس سقف النظام القديم */
function inviteQuotaLeft(): ?int {
    if (INVITE_DAILY_LIMIT <= 0) return null;
    $c = (int)db()->query('SELECT COUNT(*) c FROM survey_invitations
                           WHERE sent_at >= (NOW() - INTERVAL 1 DAY)')->fetch()['c'];
    return max(0, INVITE_DAILY_LIMIT - $c);
}

/** استبيان بمعرّفه أو فشل 404 — تستخدمها كل عمليات الإدارة أدناه */
function fetchSurveyOr404(int $id): array {
    $s = surveyById($id);
    if (!$s) fail('الاستبيان غير موجود.', 404);
    return $s;
}

/** حذف سؤال وكل ما يتبعه — بلا مفاتيح أجنبية في هذا المسار (كبقية db.php)
 *  فالتبعية تُزال يدوياً: خيارات الاختيار المتعدد المُسجَّلة، ثم الإجابات،
 *  ثم الخيارات، ثم السؤال نفسه. */
function deleteSurveyQuestionCascade(int $qid): void {
    db()->prepare('DELETE sao FROM survey_answer_options sao
                   JOIN survey_answers sa ON sa.id = sao.answer_id
                   WHERE sa.question_id = ?')->execute([$qid]);
    db()->prepare('DELETE FROM survey_answers WHERE question_id=?')->execute([$qid]);
    db()->prepare('DELETE FROM survey_question_options WHERE question_id=?')->execute([$qid]);
    db()->prepare('DELETE FROM survey_questions WHERE id=?')->execute([$qid]);
}

/** حذف خيار مُزال من محرّر الأسئلة — الإجابات القائمة لا تُحذف (فيها نص
 *  متابعة قد يكون مهماً)، فقط يُفصل ربطها بالخيار المحذوف. */
function deleteSurveyOptionCascade(int $oid): void {
    db()->prepare('DELETE FROM survey_answer_options WHERE option_id=?')->execute([$oid]);
    db()->prepare('UPDATE survey_answers SET option_id=NULL WHERE option_id=?')->execute([$oid]);
    db()->prepare('DELETE FROM survey_question_options WHERE id=?')->execute([$oid]);
}

switch ($act) {

/* قائمة كل الاستبيانات — الأحدث أولاً */
case 'survey_list': {
    $rows = db()->query('SELECT s.*,
            (SELECT COUNT(*) FROM survey_submissions  x WHERE x.survey_id=s.id) response_count,
            (SELECT COUNT(*) FROM survey_invitations  x WHERE x.survey_id=s.id) invitation_count
        FROM surveys s ORDER BY s.id DESC')->fetchAll();
    $list = array_map(fn($r) => [
        'id' => (int)$r['id'], 'title' => $r['title'], 'status' => $r['status'],
        'public_link_enabled' => (bool)$r['public_link_enabled'],
        'public_link' => ($r['public_link_enabled'] && $r['public_token']) ? SITE_URL . '/survey.html?s=' . $r['public_token'] : null,
        'grants_trial' => (bool)$r['grants_trial'],
        'response_count' => (int)$r['response_count'], 'invitation_count' => (int)$r['invitation_count'],
        'created_at' => strtotime($r['created_at']) * 1000,
    ], $rows);
    out(['ok' => true, 'surveys' => $list]);
}

/* تفاصيل استبيان كاملة — لتحميل محرّر الأسئلة */
case 'survey_get': {
    $survey = fetchSurveyOr404((int)($in['survey_id'] ?? 0));
    out(['ok' => true, 'survey' => [
        'id' => (int)$survey['id'], 'title' => $survey['title'], 'description' => $survey['description'],
        'thank_you_message' => $survey['thank_you_message'], 'status' => $survey['status'],
        'public_link_enabled' => (bool)$survey['public_link_enabled'],
        'public_link' => ($survey['public_link_enabled'] && $survey['public_token']) ? SITE_URL . '/survey.html?s=' . $survey['public_token'] : null,
        'grants_trial' => (bool)$survey['grants_trial'],
    ], 'questions' => array_map('questionOut', loadSurveyQuestions((int)$survey['id']))]);
}

/* إنشاء أو تعديل استبيان وكل أسئلته دفعة واحدة — المحرّر يرسل التعريف كاملاً
   في كل حفظ، أبسط وأضمن من عمليات جزئية متفرقة لإضافة/حذف/إعادة ترتيب. */
case 'survey_save': {
    $title = clean($in['title'] ?? '', 160);
    if ($title === '') fail('اكتب عنوان الاستبيان أولاً.');
    $description = clean($in['description'] ?? '', 500) ?: null;
    $thankYou    = clean($in['thank_you_message'] ?? '', 500) ?: null;
    $questionsIn = is_array($in['questions'] ?? null) ? array_values($in['questions']) : [];
    if (!$questionsIn) fail('أضف سؤالاً واحداً على الأقل.');

    $surveyId = !empty($in['survey_id']) ? (int)$in['survey_id'] : null;
    if ($surveyId) fetchSurveyOr404($surveyId);
    $now = date('Y-m-d H:i:s');
    $validTypes = ['single_choice', 'multi_choice', 'scale', 'short_text', 'long_text'];

    /* تحقق كامل قبل أي كتابة — حتى لا يُفتح تحويل (transaction) ثم يُقطع
       بمنتصفه بفشل تحقق لاحق، فيتّكئ التراجع عن التغييرات على قطع اتصال
       الخادم بدل رجوع صريح. كل سؤال هنا يتحوّل إلى صورة نظيفة جاهزة للكتابة. */
    $validated = [];
    foreach ($questionsIn as $pos => $q) {
        $type = (string)($q['type'] ?? '');
        if (!in_array($type, $validTypes, true)) fail('نوع سؤال غير معروف.');
        $qtext = clean($q['question_text'] ?? '', 500);
        if ($qtext === '') fail('كل سؤال لازم له نص.');
        $help = clean($q['help_text'] ?? '', 300) ?: null;
        $placeholder = ($type === 'short_text' || $type === 'long_text') ? (clean($q['placeholder'] ?? '', 200) ?: null) : null;
        $required = !empty($q['is_required']) ? 1 : 0;
        $sMin = $sMax = $maxLen = null; $sMinL = $sMaxL = null;
        if ($type === 'scale') {
            $sMin = (int)($q['scale_min'] ?? 1); $sMax = (int)($q['scale_max'] ?? 5);
            if ($sMax <= $sMin || ($sMax - $sMin) > 10 || $sMin < 0 || $sMax > 10) fail('حدود المقياس غير صحيحة.');
            $sMinL = clean($q['scale_min_label'] ?? '', 40) ?: null;
            $sMaxL = clean($q['scale_max_label'] ?? '', 40) ?: null;
        } elseif ($type === 'short_text' || $type === 'long_text') {
            $maxLen = max(1, min(2000, (int)($q['max_length'] ?? ($type === 'short_text' ? 200 : 1000))));
        }

        $opts = null;
        if ($type === 'single_choice' || $type === 'multi_choice') {
            $rawOpts = is_array($q['options'] ?? null) ? array_values($q['options']) : [];
            if (count($rawOpts) < 2) fail('أضف خيارين على الأقل لكل سؤال اختيار.');
            $opts = [];
            foreach ($rawOpts as $oi => $o) {
                $otext = clean($o['text'] ?? '', 200);
                if ($otext === '') fail('كل خيار لازم له نص.');
                $hasFollow = ($type === 'single_choice' && !empty($o['has_followup'])) ? 1 : 0;
                $opts[] = [
                    'id' => isset($o['id']) ? (int)$o['id'] : 0,
                    'position' => $oi,
                    'text' => $otext,
                    'value' => clean($o['value'] ?? '', 60) ?: ('opt' . ($oi + 1)),
                    'has_followup' => $hasFollow,
                    'followup_label' => $hasFollow ? (clean($o['followup_label'] ?? '', 200) ?: 'تفاصيل إضافية') : null,
                    'followup_max' => $hasFollow ? max(1, min(1000, (int)($o['followup_max'] ?? 500))) : null,
                ];
            }
        }

        $validated[] = [
            'id' => isset($q['id']) ? (int)$q['id'] : 0, 'position' => $pos, 'type' => $type,
            'question_text' => $qtext, 'help_text' => $help, 'placeholder' => $placeholder, 'is_required' => $required,
            'scale_min' => $sMin, 'scale_max' => $sMax, 'scale_min_label' => $sMinL, 'scale_max_label' => $sMaxL,
            'max_length' => $maxLen, 'options' => $opts,
        ];
    }

    db()->beginTransaction();
    try {
        if ($surveyId) {
            db()->prepare('UPDATE surveys SET title=?, description=?, thank_you_message=?, updated_at=? WHERE id=?')
                ->execute([$title, $description, $thankYou, $now, $surveyId]);
        } else {
            db()->prepare("INSERT INTO surveys (title, description, thank_you_message, status, created_by, created_at, updated_at)
                           VALUES (?,?,?,'draft',?,?,?)")
                ->execute([$title, $description, $thankYou, $STAFF['id'], $now, $now]);
            $surveyId = (int)db()->lastInsertId();
        }

        $existQ = db()->prepare('SELECT id FROM survey_questions WHERE survey_id=?');
        $existQ->execute([$surveyId]);
        $existingQIds = array_map('intval', array_column($existQ->fetchAll(), 'id'));
        $keptQIds = [];

        $qIns = db()->prepare("INSERT INTO survey_questions
            (survey_id, position, type, question_text, help_text, placeholder, is_required,
             scale_min, scale_max, scale_min_label, scale_max_label, max_length, created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $qUpd = db()->prepare('UPDATE survey_questions SET position=?, type=?, question_text=?, help_text=?,
            placeholder=?, is_required=?, scale_min=?, scale_max=?, scale_min_label=?, scale_max_label=?, max_length=?
            WHERE id=? AND survey_id=?');
        $oIns = db()->prepare('INSERT INTO survey_question_options
            (question_id, position, option_text, option_value, has_followup, followup_label, followup_max)
            VALUES (?,?,?,?,?,?,?)');
        $oUpd = db()->prepare('UPDATE survey_question_options SET position=?, option_text=?, option_value=?,
            has_followup=?, followup_label=?, followup_max=? WHERE id=? AND question_id=?');

        foreach ($validated as $q) {
            if ($q['id'] && in_array($q['id'], $existingQIds, true)) {
                $qid = $q['id'];
                $qUpd->execute([$q['position'], $q['type'], $q['question_text'], $q['help_text'], $q['placeholder'], $q['is_required'],
                    $q['scale_min'], $q['scale_max'], $q['scale_min_label'], $q['scale_max_label'], $q['max_length'], $qid, $surveyId]);
            } else {
                $qIns->execute([$surveyId, $q['position'], $q['type'], $q['question_text'], $q['help_text'], $q['placeholder'], $q['is_required'],
                    $q['scale_min'], $q['scale_max'], $q['scale_min_label'], $q['scale_max_label'], $q['max_length'], $now]);
                $qid = (int)db()->lastInsertId();
            }
            $keptQIds[] = $qid;

            if ($q['options'] !== null) {
                $existO = db()->prepare('SELECT id FROM survey_question_options WHERE question_id=?');
                $existO->execute([$qid]);
                $existingOIds = array_map('intval', array_column($existO->fetchAll(), 'id'));
                $keptOIds = [];
                foreach ($q['options'] as $o) {
                    if ($o['id'] && in_array($o['id'], $existingOIds, true)) {
                        $oid = $o['id'];
                        $oUpd->execute([$o['position'], $o['text'], $o['value'], $o['has_followup'], $o['followup_label'], $o['followup_max'], $oid, $qid]);
                    } else {
                        $oIns->execute([$qid, $o['position'], $o['text'], $o['value'], $o['has_followup'], $o['followup_label'], $o['followup_max']]);
                        $oid = (int)db()->lastInsertId();
                    }
                    $keptOIds[] = $oid;
                }
                foreach (array_diff($existingOIds, $keptOIds) as $roid) deleteSurveyOptionCascade((int)$roid);
            }
        }

        foreach (array_diff($existingQIds, $keptQIds) as $rqid) deleteSurveyQuestionCascade((int)$rqid);

        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }

    audit($STAFF, 'survey_save', $title, $surveyId ? "تعديل استبيان #$surveyId" : 'إنشاء استبيان جديد');
    out(['ok' => true, 'survey_id' => $surveyId]);
}

/* نشر الاستبيان أو إرجاعه لمسودة */
case 'survey_publish': {
    $surveyId = (int)($in['survey_id'] ?? 0);
    $survey = fetchSurveyOr404($surveyId);
    $publish = !empty($in['publish']);
    if ($publish) {
        $qc = db()->prepare('SELECT COUNT(*) c FROM survey_questions WHERE survey_id=?');
        $qc->execute([$surveyId]);
        if ((int)$qc->fetch()['c'] === 0) fail('أضف سؤالاً واحداً على الأقل قبل النشر.');
    }
    $status = $publish ? 'published' : 'draft';
    db()->prepare('UPDATE surveys SET status=?, updated_at=NOW(),
                   published_at=IF(? AND published_at IS NULL, NOW(), published_at) WHERE id=?')
        ->execute([$status, $publish ? 1 : 0, $surveyId]);
    audit($STAFF, 'survey_publish', $survey['title'], $status);
    out(['ok' => true, 'status' => $status]);
}

/* أرشفة — لا حذف نهائي؛ الاستبيان وردوده يبقيان للاطلاع لاحقاً */
case 'survey_archive': {
    $survey = fetchSurveyOr404((int)($in['survey_id'] ?? 0));
    db()->prepare("UPDATE surveys SET status='archived', updated_at=NOW() WHERE id=?")->execute([(int)$survey['id']]);
    audit($STAFF, 'survey_archive', $survey['title'], '');
    out(['ok' => true]);
}

/* تفعيل/إيقاف الرابط العام، مع خيار تجديد الرمز (يُبطل أي رابط سابق) */
case 'survey_set_public_link': {
    $survey = fetchSurveyOr404((int)($in['survey_id'] ?? 0));
    $enabled = !empty($in['enabled']);
    $regenerate = !empty($in['regenerate']);
    $token = $survey['public_token'];
    if ($token === null || $regenerate) $token = bin2hex(random_bytes(20));
    db()->prepare('UPDATE surveys SET public_link_enabled=?, public_token=?, updated_at=NOW() WHERE id=?')
        ->execute([$enabled ? 1 : 0, $token, (int)$survey['id']]);
    audit($STAFF, 'survey_public_link', $survey['title'], $enabled ? 'فُعِّل' : 'أُوقف');
    out(['ok' => true, 'public_link_enabled' => $enabled,
         'public_link' => $enabled ? (SITE_URL . '/survey.html?s=' . $token) : null]);
}

/* إرسال دفعة دعوات لاستبيان محدد — سطر لكل بريد أو فواصل بينها */
case 'survey_send_invitations': {
    rateLimit('survey_send', 5);
    $survey = fetchSurveyOr404((int)($in['survey_id'] ?? 0));
    if ($survey['status'] !== 'published') fail('انشر الاستبيان أولاً قبل إرسال دعوات له.');
    $surveyId = (int)$survey['id'];

    $campaign = clean($in['campaign'] ?? '', 80);
    if ($campaign === '') fail('اكتب اسم الحملة أولاً — هو اللي يجمع نتائجها لاحقاً.');
    $rawList = is_array($in['emails'] ?? null) ? $in['emails'] : preg_split('/[\s,;،]+/u', (string)($in['emails'] ?? ''));
    $emails = [];
    foreach ($rawList as $e) {
        $e = strtolower(trim((string)$e));
        if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) $emails[$e] = true;
    }
    $emails = array_keys($emails);
    if (!$emails) fail('اكتب بريداً واحداً صحيحاً على الأقل.');
    if (count($emails) > 50) fail('خمسون بريداً كحد أقصى في الدفعة الواحدة.');

    $quota = inviteQuotaLeft();
    if ($quota !== null && $quota <= 0) fail('وصلت سقف دعوات اليوم (' . INVITE_DAILY_LIMIT . '). أكمل بكرة.');
    if ($quota !== null && count($emails) > $quota) fail('باقي من سقف اليوم ' . $quota . ' دعوة فقط — قلّل القائمة أو أكمل بكرة.');

    $sent = 0; $failedMail = 0; $skipped = 0;
    $dupe = db()->prepare('SELECT id FROM survey_invitations WHERE survey_id=? AND email=? AND campaign_name=? LIMIT 1');
    $ins  = db()->prepare("INSERT INTO survey_invitations (survey_id, email, token, campaign_name, status, sent_at, created_by)
                           VALUES (?,?,?,?,'sent',NOW(),?)");
    foreach ($emails as $email) {
        $dupe->execute([$surveyId, $email, $campaign]);
        if ($dupe->fetch()) { $skipped++; continue; }
        $token = bin2hex(random_bytes(20));
        $ins->execute([$surveyId, $email, $token, $campaign, $STAFF['id']]);
        $mailed = sendMail($email, 'دعوتك لاستبيان: ' . $survey['title'],
            surveyInviteEmailHtml($survey['title'], SITE_URL . '/invite/' . $token, (bool)$survey['grants_trial']));
        $mailed ? $sent++ : $failedMail++;
    }
    audit($STAFF, 'survey_invite', $survey['title'], "الحملة $campaign — أُرسلت $sent، تعذّر بريد $failedMail، مكررة $skipped");
    out(['ok' => true, 'sent' => $sent, 'failed' => $failedMail, 'skipped' => $skipped, 'quota_left' => inviteQuotaLeft()]);
}

/* قائمة دعوات استبيان محدد */
case 'survey_invitations_list': {
    $survey = fetchSurveyOr404((int)($in['survey_id'] ?? 0));
    $rows = db()->prepare('SELECT id, email, campaign_name, token, status, sent_at, opened_at, completed_at, trial_started_at
                           FROM survey_invitations WHERE survey_id=? ORDER BY id DESC LIMIT 300');
    $rows->execute([(int)$survey['id']]);
    $list = array_map(fn($r) => [
        'id' => (int)$r['id'], 'email' => $r['email'], 'campaign' => $r['campaign_name'], 'status' => $r['status'],
        'link' => SITE_URL . '/invite/' . $r['token'],
        'sent' => strtotime($r['sent_at']) * 1000,
        'opened' => $r['opened_at'] ? strtotime($r['opened_at']) * 1000 : null,
        'completed' => $r['completed_at'] ? strtotime($r['completed_at']) * 1000 : null,
        'tried' => $r['trial_started_at'] ? strtotime($r['trial_started_at']) * 1000 : null,
    ], $rows->fetchAll());
    out(['ok' => true, 'invitations' => $list, 'quota_left' => inviteQuotaLeft()]);
}

/* ردود استبيان محدد — الأسئلة تُعاد أيضاً حتى تُبنى أعمدة الجدول ديناميكياً */
case 'survey_responses_list': {
    $survey = fetchSurveyOr404((int)($in['survey_id'] ?? 0));
    $surveyId = (int)$survey['id'];
    $questions = loadSurveyQuestions($surveyId);

    $rows = db()->prepare('SELECT s.id, s.campaign_name, s.submitted_at, i.email
                           FROM survey_submissions s LEFT JOIN survey_invitations i ON i.id = s.invitation_id
                           WHERE s.survey_id=? ORDER BY s.id DESC LIMIT 300');
    $rows->execute([$surveyId]);
    $subs = $rows->fetchAll();

    $answersBySub = []; $multiText = [];
    if ($subs) {
        $ids = array_map(fn($s) => (int)$s['id'], $subs);
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $as = db()->prepare("SELECT sa.*, o.option_text FROM survey_answers sa
                             LEFT JOIN survey_question_options o ON o.id = sa.option_id
                             WHERE sa.submission_id IN ($ph)");
        $as->execute($ids);
        foreach ($as->fetchAll() as $a) $answersBySub[(int)$a['submission_id']][(int)$a['question_id']] = $a;

        $aos = db()->prepare("SELECT sa.submission_id, sa.question_id, o.option_text FROM survey_answer_options sao
                              JOIN survey_answers sa ON sa.id = sao.answer_id
                              JOIN survey_question_options o ON o.id = sao.option_id
                              WHERE sa.submission_id IN ($ph)");
        $aos->execute($ids);
        foreach ($aos->fetchAll() as $r) $multiText[(int)$r['submission_id']][(int)$r['question_id']][] = $r['option_text'];
    }

    $list = array_map(function ($s) use ($answersBySub, $multiText, $questions) {
        $sid = (int)$s['id'];
        $answers = [];
        foreach ($questions as $q) {
            $qid = (int)$q['id'];
            if ($q['type'] === 'multi_choice') {
                $answers[$qid] = isset($multiText[$sid][$qid]) ? implode('، ', $multiText[$sid][$qid]) : null;
                continue;
            }
            $a = $answersBySub[$sid][$qid] ?? null;
            if ($a === null) { $answers[$qid] = null; continue; }
            $answers[$qid] = match ($q['type']) {
                'single_choice' => trim(($a['option_text'] ?? '') . ($a['answer_text'] ? ' — ' . $a['answer_text'] : '')) ?: null,
                'scale' => $a['number_value'] !== null ? (int)$a['number_value'] : null,
                default => $a['answer_text'],
            };
        }
        return ['id' => $sid, 'respondent' => $s['email'] ?: null, 'campaign' => $s['campaign_name'],
            't' => strtotime($s['submitted_at']) * 1000, 'answers' => $answers];
    }, $subs);

    out(['ok' => true, 'questions' => array_map('questionOut', $questions), 'responses' => $list]);
}

/* مؤشرات استبيان محدد — قمع الدعوات، وتحليل لكل سؤال */
case 'survey_metrics': {
    $survey = fetchSurveyOr404((int)($in['survey_id'] ?? 0));
    $surveyId = (int)$survey['id'];

    $f = db()->prepare("SELECT COUNT(*) total,
            COALESCE(SUM(status IN ('opened','completed')),0) opened,
            COALESCE(SUM(status='completed'),0) completed,
            COALESCE(SUM(trial_started_at IS NOT NULL),0) tried
        FROM survey_invitations WHERE survey_id=?");
    $f->execute([$surveyId]);
    $funnel = $f->fetch();
    $pct = fn($a, $b) => (int)$b > 0 ? (int)round((int)$a / (int)$b * 100) : null;

    $qMetrics = [];
    foreach (loadSurveyQuestions($surveyId) as $q) {
        $qid = (int)$q['id'];
        if ($q['type'] === 'single_choice' || $q['type'] === 'multi_choice') {
            if ($q['type'] === 'single_choice') {
                $rs = db()->prepare('SELECT option_id id, COUNT(*) c FROM survey_answers
                                     WHERE question_id=? AND option_id IS NOT NULL GROUP BY option_id');
            } else {
                $rs = db()->prepare('SELECT sao.option_id id, COUNT(*) c FROM survey_answer_options sao
                                     JOIN survey_answers sa ON sa.id = sao.answer_id
                                     WHERE sa.question_id=? GROUP BY sao.option_id');
            }
            $rs->execute([$qid]);
            $counts = [];
            foreach ($rs->fetchAll() as $r) $counts[(int)$r['id']] = (int)$r['c'];
            $qMetrics[] = ['question_id' => $qid, 'type' => $q['type'], 'question_text' => $q['question_text'],
                'options' => array_map(fn($o) => ['id' => (int)$o['id'], 'text' => $o['option_text'],
                    'count' => $counts[(int)$o['id']] ?? 0], $q['options'])];
        } elseif ($q['type'] === 'scale') {
            $s = db()->prepare('SELECT COUNT(*) n, AVG(number_value) a FROM survey_answers
                                WHERE question_id=? AND number_value IS NOT NULL');
            $s->execute([$qid]);
            $r = $s->fetch();
            $qMetrics[] = ['question_id' => $qid, 'type' => 'scale', 'question_text' => $q['question_text'],
                'count' => (int)$r['n'], 'average' => $r['a'] !== null ? round((float)$r['a'], 1) : null,
                'scale_max' => (int)$q['scale_max']];
        } else {
            $s = db()->prepare("SELECT COUNT(*) n FROM survey_answers WHERE question_id=? AND answer_text IS NOT NULL AND answer_text<>''");
            $s->execute([$qid]);
            $qMetrics[] = ['question_id' => $qid, 'type' => $q['type'], 'question_text' => $q['question_text'],
                'count' => (int)$s->fetch()['n']];
        }
    }

    $subCount = db()->prepare('SELECT COUNT(*) c FROM survey_submissions WHERE survey_id=?');
    $subCount->execute([$surveyId]);

    out(['ok' => true, 'metrics' => [
        'sent' => (int)$funnel['total'], 'opened' => (int)$funnel['opened'], 'completed' => (int)$funnel['completed'],
        'open_rate' => $pct($funnel['opened'], $funnel['total']), 'completion_rate' => $pct($funnel['completed'], $funnel['total']),
        'tried' => $survey['grants_trial'] ? (int)$funnel['tried'] : null,
        'responses' => (int)$subCount->fetch()['c'],
        'quota_left' => inviteQuotaLeft(),
        'questions' => $qMetrics,
    ]]);
}

default: fail('طلب غير معروف.');
}
