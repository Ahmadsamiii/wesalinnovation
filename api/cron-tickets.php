<?php
/**
 * صيانة دورية لتذاكر الدعم — سكربت Cron، لا نقطة API عادية.
 *
 * لا يُستدعى من واجهة المستخدم ولا يخدم أي زر في الموقع؛ الغرض الوحيد منه
 * أن يُشغَّله مجدوِل الاستضافة كل ساعة (راجع توصية الجدولة على Hostinger في
 * تقرير التسليم). سُمح أيضاً بتشغيله عبر رابط HTTP محروس بمفتاح سرّي، لأن
 * بعض لوحات الاستضافة (ومنها خطط Hostinger الأبسط أحياناً) لا تعرض خيار
 * "شغّل ملف PHP من الطرفية" وتكتفي بـCron من نوع "زر رابط" (wget/curl على
 * عنوان)، فيبقى هذا الملف صالحاً للحالتين معاً بلا نسخة مكررة.
 *
 * الاستخدام:
 *   من الطرفية (الطريقة الحقيقية على الاستضافة، عبر Cron أو للاختبار محلياً):
 *       php api/cron-tickets.php
 *   عبر رابط (فقط إن لم تدعم لوحة الاستضافة تشغيل php مباشرة، أو لتجربة يدوية):
 *       https://DOMAIN/api/cron-tickets.php?key=CRON_SECRET
 *
 * أربع مهام مستقلة تماماً بترتيب ثابت — كل واحدة بـtry/catch خاص بها حتى
 * لا يوقف عطل في واحدة تنفيذ البقية (مثلاً فشل SMTP في المهمة الرابعة يجب
 * ألا يمنع إغلاق التذاكر المنتهية مهلتها في المهمتين الثانية والثالثة):
 *   ١) تنبيه قرب مخالفة اتفاقية مستوى الخدمة (SLA) — مرة واحدة لكل تذكرة.
 *   ٢) تذكيران تصعديان ثم إغلاق تذاكر «بانتظار صاحب التذكرة» بلا رد ١٠ أيام.
 *   ٣) إغلاق تلقائي لتذاكر «مَحلولة» بلا اعتراض خلال ٧ أيام.
 *   ٤) تقرير أسبوعي مختصر لقادة/تنفيذيي فريق الدعم (خميس واحد في الأسبوع).
 *
 * لا يستورد api/tickets.php ولا يستدعي أياً من دوالّه (bumpPriority،
 * ticketEntry، notifyAgents...): ذلك الملف عقد مختوم ومختبَر لا يُلمَس، وحتى
 * لو أردنا لاستحال تضمينه هنا فعلياً — نهايته switch($action) تُنهي التنفيذ
 * بـexit() عبر fail() لأي $action فارغ، فتُسقط هذا السكربت بالكامل. لذلك
 * أدناه نسخ صغيرة مستقلة مكافئة لما نحتاجه فقط.
 */
require_once __DIR__ . '/db.php';

/* ---------------- الحارس ----------------
 * من الطرفية (PHP_SAPI==='cli'): لا حاجة لأي مفتاح — من يملك أصلاً وصولاً
 * لتشغيل php على الخادم (Cron أو SSH) موثوق ضمنياً، بنفس منطق أدوات
 * tools/*.php. من متصفح/أي طلب HTTP: يُشترط ?key= يطابق CRON_SECRET حرفياً
 * عبر مقارنة ثابتة الزمن (hash_equals) لمنع تسريب المفتاح بفروقات التوقيت،
 * وإلا 403 فوراً بلا أي رسالة تكشف سبب الرفض. CRON_SECRET فارغ (لم يُضبط
 * بعد في config.php) يعني رفض كل وصول عبر الرابط احتياطاً — لا يُسمح بمفتاح
 * فارغ يطابق قيمة فارغة. */
if (!defined('CRON_SECRET')) define('CRON_SECRET', '');
if (PHP_SAPI !== 'cli') {
    $key = (string)($_GET['key'] ?? '');
    if (CRON_SECRET === '' || !hash_equals(CRON_SECRET, $key)) {
        http_response_code(403);
        exit;
    }
}

ensureSchema();

/* ---------------- نسخ محلية مكافئة (بلا استيراد لـtickets.php) ---------------- */

/**
 * سطر نظامي في سجل التذكرة — نسخة محلية صغيرة مكافئة لجزء من ticketEntry()
 * في tickets.php. author_id/author_name فارغان دائماً وkind='system' لأن كل
 * ما يكتبه هذا السكربت مصدره النظام لا شخص، وهو أيضاً ما يجعل publicEntries()
 * هناك تعرضه للمستفيد باسم "النظام" بدل اسم موظف أو "أنت".
 *
 * $touchUpdated=false حين لا يجوز مسّ updated_at — تحديداً تذكيرا الانتظار
 * الوسيطان (٥/٨ أيام): لو حدّثا updated_at لصفّرا العدّاد الذي تقيسه
 * taskWaitingTickets() نفسها بالاعتماد على نفس الحقل، فتتأجل مهلة الـ١٠ أيام
 * إلى ما لا نهاية بدل الإغلاق الفعلي — كل تذكير كان سيعيد ضبط ساعته.
 */
function cronSystemEntry(int $ticketId, string $body, array $meta = [], bool $touchUpdated = true, string $visibility = 'public'): void
{
    db()->prepare("INSERT INTO ticket_entries (ticket_id,author_id,author_name,kind,visibility,body,meta,created_at)
                   VALUES (?,NULL,NULL,'system',?,?,?,NOW())")
        ->execute([$ticketId, $visibility, mb_substr($body, 0, 4000), $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null]);
    if ($touchUpdated) {
        db()->prepare('UPDATE support_tickets SET updated_at=NOW() WHERE id=?')->execute([$ticketId]);
    }
}

/** نسخة محلية من فكرة notifyAgents() في tickets.php (بلا استدعاء لذلك الملف):
 *  تُخطر كل قادة/تنفيذيي الدعم حين لا يوجد معالج مُسنَد بعد لتذكرة قريبة من
 *  مخالفة الاتفاقية — نفس منطق "L1 يستقبل الكل بلا استثناء". */
function cronNotifyLeads(int $ticketId, string $title, string $body): void
{
    foreach (db()->query("SELECT id FROM users WHERE support_level IN ('lead','exec')")->fetchAll() as $row) {
        notify((int)$row['id'], 'ticket_sla_warning', $title, $body, '/tickets.html?id=' . $ticketId);
    }
}

/** جدول صغير مستقل خاص بهذا السكربت وحده — لا صلة له بترقيات db.php،
 *  فيبقى إنشاؤه هنا بدل إضافته إلى migrateTickets() (المسموح تعديلها فقط
 *  لعمود/عمودين على support_tickets، لا جدول جديد كامل). */
function ensureCronRunsTable(): void
{
    db()->exec("CREATE TABLE IF NOT EXISTS cron_runs (
        job VARCHAR(60) NOT NULL PRIMARY KEY,
        last_run_at DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/* ---------------- المهمة ١: تنبيه قرب مخالفة اتفاقية مستوى الخدمة ---------------- */

/**
 * تنبيه واحد فقط لكل تذكرة (sla_warned_at يمنع التكرار) عندما يبقى على
 * موعد حلّها ٢٥٪ أو أقل من مدتها الأصلية. المدة الأصلية تُقاس من created_at
 * لا updated_at: due_resolution يُحسب مرة واحدة فقط عند الإنشاء في
 * tickets.php (slaDue()) ولا يُعاد حسابه لاحقاً أبداً — حتى bumpPriority()
 * عند إعادة الفتح لا يمسّه — فـcreated_at هو بداية النافذة الحقيقية؛
 * updated_at يتغيّر مع كل رد وملاحظة فلا يصلح مرجعاً لحساب "المدة الأصلية".
 */
function taskSlaWarnings(): int
{
    $rows = db()->query("SELECT id, ref, subject, type, assignee_id, created_at, due_resolution
                         FROM support_tickets
                         WHERE status NOT IN ('resolved','closed')
                           AND due_resolution IS NOT NULL
                           AND sla_warned_at IS NULL")->fetchAll();
    $now   = time();
    $count = 0;
    foreach ($rows as $t) {
        $created = strtotime($t['created_at']);
        $due     = strtotime($t['due_resolution']);
        $total   = $due - $created;
        if ($total <= 0) continue; // بيانات غير منطقية (نادر) — تجاهلها بدل استنتاج خاطئ
        $remaining = $due - $now;
        if ($remaining > $total * 0.25) continue; // ما زال أمامها أكثر من ربع مدتها

        $id    = (int)$t['id'];
        $title = $t['ref'] . ' — قرُبت من مخالفة اتفاقية مستوى الخدمة';
        $body  = (string)($t['subject'] ?: $t['type']);
        if ($t['assignee_id']) {
            notify((int)$t['assignee_id'], 'ticket_sla_warning', $title, $body, '/tickets.html?id=' . $id);
        } else {
            cronNotifyLeads($id, $title, $body);
        }
        db()->prepare('UPDATE support_tickets SET sla_warned_at=NOW() WHERE id=?')->execute([$id]);
        $count++;
    }
    return $count;
}

/* ---------------- المهمة ٢: تذاكر «بانتظار صاحب التذكرة» ---------------- */

/**
 * تذكير بعد ٥ أيام، تذكير أخير بعد ٨، إغلاق بعد ١٠ إن لم يصل رد — بدل إغلاق
 * مفاجئ بلا إنذار. العدّاد يُقاس من updated_at كتقريب معقول لـ"آخر دخول
 * لحالة waiting": حالة 'wait' في tickets.php تستدعي ticketEntry() مباشرة بعد
 * تغيير الحالة، وticketEntry() تحدّث updated_at في نفس اللحظة — فيبقى قريباً
 * جداً من لحظة الدخول الفعلية في كل الحالات العملية. رد صاحب التذكرة ينقل
 * الحالة فوراً إلى in_progress (case 'reply' في tickets.php) فتخرج التذكرة
 * من نطاق هذا الاستعلام تلقائياً — لا حاجة لفحص "هل رُدّ عليها" بشكل منفصل.
 *
 * كل تحديث محروس بشرط WHERE إضافي (status='waiting' و/أو reminder_count
 * المتوقَّع) والتحقق من rowCount() قبل إرسال أي تنبيه أو كتابة سجل، حتى لا
 * يُنتج تشغيلان متزامنان (رابط + Cron مثلاً) تنبيهاً مكرَّراً لنفس التذكرة.
 */
function taskWaitingTickets(): array
{
    $reminded = 0;
    $closed   = 0;
    $rows = db()->query("SELECT id, ref, user_id, updated_at, waiting_reminder_count
                         FROM support_tickets WHERE status='waiting'")->fetchAll();

    foreach ($rows as $t) {
        $id   = (int)$t['id'];
        $days = (time() - strtotime($t['updated_at'])) / 86400;
        $rc   = (int)$t['waiting_reminder_count'];

        if ($days >= 10) {
            $upd = db()->prepare("UPDATE support_tickets SET status='closed', closed_at=NOW() WHERE id=? AND status='waiting'");
            $upd->execute([$id]);
            if ($upd->rowCount() === 0) continue; // أُغلقت أو تغيّرت حالتها للتو من مكان آخر
            cronSystemEntry($id,
                'أُغلقت هذه التذكرة تلقائياً لعدم ورود ردّ من صاحبها خلال 10 أيام من طلب معلومات إضافية. افتح تذكرة جديدة إن كنت ما زلت بحاجة للمساعدة.',
                ['auto' => true, 'reason' => 'waiting_timeout'], true);
            if ($t['user_id']) {
                notify((int)$t['user_id'], 'ticket_closed', 'أُغلقت ' . $t['ref'],
                       'أُغلقت تلقائياً لعدم الرد خلال 10 أيام من طلب معلومات إضافية.', '/dashboard#tickets');
            }
            $closed++;
        } elseif ($days >= 8 && $rc < 2) {
            $upd = db()->prepare("UPDATE support_tickets SET waiting_reminder_count=2 WHERE id=? AND status='waiting' AND waiting_reminder_count<2");
            $upd->execute([$id]);
            if ($upd->rowCount() === 0) continue;
            cronSystemEntry($id,
                'تبقّى يومان فقط قبل إغلاق هذه التذكرة تلقائياً لعدم الرد — أرسل ردّك إن كنت ما زلت بحاجة للمساعدة.',
                ['auto' => true, 'reminder' => 2], false);
            if ($t['user_id']) {
                notify((int)$t['user_id'], 'ticket_waiting_reminder', 'تذكير أخير: ' . $t['ref'],
                       'ستُغلق تلقائياً خلال يومين إن لم يصل ردّك.', '/dashboard#tickets');
            }
            $reminded++;
        } elseif ($days >= 5 && $rc < 1) {
            $upd = db()->prepare("UPDATE support_tickets SET waiting_reminder_count=1 WHERE id=? AND status='waiting' AND waiting_reminder_count<1");
            $upd->execute([$id]);
            if ($upd->rowCount() === 0) continue;
            cronSystemEntry($id,
                'لم يصلنا ردّك بعد على هذا الطلب منذ 5 أيام. إن لم نستلم ردّاً خلال 5 أيام أخرى (10 أيام إجمالاً) ستُغلق التذكرة تلقائياً.',
                ['auto' => true, 'reminder' => 1], false);
            if ($t['user_id']) {
                notify((int)$t['user_id'], 'ticket_waiting_reminder', 'تذكير: ' . $t['ref'],
                       'رجاءً أرسل ردّك حتى نُكمل معالجة طلبك.', '/dashboard#tickets');
            }
            $reminded++;
        }
    }
    return ['reminded' => $reminded, 'closed' => $closed];
}

/* ---------------- المهمة ٣: إغلاق تلقائي بعد الحل ---------------- */

/**
 * تذكرة 'resolved' مضى عليها من resolved_at أكثر من 7 أيام بلا اعتراض تُغلق
 * تلقائياً. لا حاجة لفحص سجل الأحداث بحثاً عن reopen: حالة 'reopened' كانت
 * ستغيّر status فوراً (case 'reopen' في tickets.php)، فبقاؤها 'resolved' بعد
 * انقضاء المهلة يعني وحده عدم وجود اعتراض.
 */
function taskAutoCloseResolved(): int
{
    $count = 0;
    $rows = db()->query("SELECT id, ref, user_id FROM support_tickets
                         WHERE status='resolved' AND resolved_at IS NOT NULL
                           AND resolved_at <= NOW() - INTERVAL 7 DAY")->fetchAll();
    foreach ($rows as $t) {
        $id  = (int)$t['id'];
        $upd = db()->prepare("UPDATE support_tickets SET status='closed', closed_at=NOW() WHERE id=? AND status='resolved'");
        $upd->execute([$id]);
        if ($upd->rowCount() === 0) continue; // اعتُرض عليها أو أُغلقت للتو من مكان آخر
        cronSystemEntry($id,
            'أُغلقت هذه التذكرة تلقائياً لعدم ورود اعتراض على الحل خلال 7 أيام. إن احتجت إعادة فتحها لاحقاً فذلك متاح خلال 14 يوماً من الإغلاق.',
            ['auto' => true, 'reason' => 'resolved_timeout'], true);
        if ($t['user_id']) {
            notify((int)$t['user_id'], 'ticket_closed', 'أُغلقت ' . $t['ref'],
                   'أُغلقت تلقائياً لعدم وجود اعتراض على الحل.', '/dashboard#tickets');
        }
        $count++;
    }
    return $count;
}

/* ---------------- المهمة ٤: تقرير أسبوعي ---------------- */

/** الخميس: آخر يوم عمل قبل عطلة نهاية الأسبوع (الجمعة/السبت)، بنفس تعريف
 *  عطلة نهاية الأسبوع في isBusinessHour() بـtickets.php — وقت طبيعي لخلاصة
 *  الأسبوع قبل انطلاقها. date('N') بصيغة ISO-8601: 1=الاثنين .. 7=الأحد. */
const CRON_WEEKLY_REPORT_DOW = 4;

/**
 * تقرير أسبوعي مختصر لقادة/تنفيذيي فريق الدعم، مرة واحدة فقط في أول تشغيل
 * يوم الخميس. علامة cron_runs تمنع تكراره لو شُغِّل هذا السكربت عدة مرات في
 * نفس اليوم — وهو المتوقَّع تماماً مع جدولة كل ساعة. يعيد عدد الرسائل
 * المُرسلة فعلاً (صفر في كل حالات التخطي: ليس خميساً، أو أُرسل اليوم مسبقاً،
 * أو لا يوجد قادة/تنفيذيون) حتى يبقى ملخّص التنفيذ رقمياً بحتاً.
 */
function taskWeeklyReport(): int
{
    ensureCronRunsTable();
    if ((int)date('N') !== CRON_WEEKLY_REPORT_DOW) return 0;

    $s = db()->prepare('SELECT last_run_at FROM cron_runs WHERE job=?');
    $s->execute(['ticket_weekly_report']);
    $last = $s->fetch();
    if ($last && $last['last_run_at'] && date('Y-m-d', strtotime($last['last_run_at'])) === date('Y-m-d')) {
        return 0; // أُرسل التقرير اليوم مسبقاً
    }

    $open    = (int)db()->query("SELECT COUNT(*) c FROM support_tickets WHERE status NOT IN ('resolved','closed')")->fetch()['c'];
    $overdue = (int)db()->query("SELECT COUNT(*) c FROM support_tickets
                                 WHERE status NOT IN ('resolved','closed')
                                   AND due_resolution IS NOT NULL AND due_resolution < NOW()")->fetch()['c'];
    $avgRow = db()->query("SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) a, COUNT(*) c
                           FROM support_tickets
                           WHERE resolved_at IS NOT NULL AND resolved_at >= NOW() - INTERVAL 7 DAY")->fetch();
    $resolvedN = (int)$avgRow['c'];
    $avgTxt    = $avgRow['a'] !== null ? round((float)$avgRow['a'], 1) . ' ساعة' : 'لا توجد تذاكر حُلّت هذا الأسبوع';

    $html = '<div dir="rtl" style="font-family:Tahoma,Arial,sans-serif;line-height:1.9">'
        . '<p>تقرير أسبوعي — تذاكر الدعم في وصال</p>'
        . '<ul>'
        . '<li>التذاكر المفتوحة حالياً: <b>' . $open . '</b></li>'
        . '<li>المتجاوزة لموعد حلّها: <b>' . $overdue . '</b></li>'
        . '<li>متوسط زمن الحل هذا الأسبوع (' . $resolvedN . ' تذكرة): <b>' . $avgTxt . '</b></li>'
        . '</ul></div>';

    $sent = 0;
    foreach (db()->query("SELECT email FROM users WHERE support_level IN ('lead','exec')")->fetchAll() as $u) {
        try {
            if (sendMail($u['email'], 'تقرير أسبوعي — تذاكر الدعم', $html)) $sent++;
        } catch (Throwable $e) { error_log('WESAL_CRON_WEEKLY_MAIL_FAIL: ' . $e->getMessage()); }
    }

    db()->prepare('INSERT INTO cron_runs (job, last_run_at) VALUES (?, NOW())
                   ON DUPLICATE KEY UPDATE last_run_at=NOW()')->execute(['ticket_weekly_report']);
    return $sent;
}

/* ---------------- التنفيذ ---------------- */

$summary = [
    'ok'                 => true,
    'sla_warned'         => 0,
    'waiting_reminded'   => 0,
    'waiting_closed'     => 0,
    'resolved_closed'    => 0,
    'weekly_report_sent' => 0,
];

try { $summary['sla_warned'] = taskSlaWarnings(); }
catch (Throwable $e) { error_log('WESAL_CRON_SLA_FAIL: ' . $e->getMessage()); }

try {
    $w = taskWaitingTickets();
    $summary['waiting_reminded'] = $w['reminded'];
    $summary['waiting_closed']   = $w['closed'];
} catch (Throwable $e) { error_log('WESAL_CRON_WAITING_FAIL: ' . $e->getMessage()); }

try { $summary['resolved_closed'] = taskAutoCloseResolved(); }
catch (Throwable $e) { error_log('WESAL_CRON_RESOLVED_FAIL: ' . $e->getMessage()); }

try { $summary['weekly_report_sent'] = taskWeeklyReport(); }
catch (Throwable $e) { error_log('WESAL_CRON_WEEKLY_FAIL: ' . $e->getMessage()); }

/* ملخّص عددي بحت لأغراض تتبّع التنفيذ فقط — بلا أي تفصيل حسّاس (لا مراجع
   تذاكر، لا أسماء، لا بريد) يظهر في استجابة HTTP. */
out($summary);
