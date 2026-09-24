<?php
/**
 * تذاكر الدعم الفني — نقطة النهاية الموحّدة.
 *
 * ثلاثة أنواع من المتعاملين تصل هذه الملف:
 *   الضيف        — يرفع تذكرة ويتابعها برمز التتبّع السرّي وحده.
 *   المستفيد      — مسجّل دخول، يرى تذاكره هو فقط.
 *   المعالج       — support_level غير 'none'، يرى الطابور ويعالج ويحيل.
 *
 * قاعدة حاكمة: ما يراه صاحب التذكرة يُحدَّد في الاستعلام لا في الواجهة
 * (WHERE visibility='public')، لأن الإخفاء في المتصفح يُتجاوَز بأدوات المطوّر.
 */
require_once __DIR__ . '/db.php';
ensureSchema();

$in     = body();
$action = $in['action'] ?? '';

/* ---------------- مساعدات ---------------- */

/** مستوى الدعم للمستخدم الحالي، أو 'none' لغير المعالجين */
function supportLevel(?array $u): string
{
    return $u['support_level'] ?? 'none';
}

/** المعالج هو من له مستوى دعم — مستقل عن دور المنصة (admin/mod/...) */
function requireAgent(): array
{
    $u = currentUser();
    if (!$u || supportLevel($u) === 'none') fail('ليست لديك صلاحية على تذاكر الدعم.', 403);
    return $u;
}

/** ترتيب مستويات الإحالة: الأعلى رقماً يستقبل من الأدنى */
function levelRank(string $lvl): int
{
    return ['none' => 0, 'agent' => 1, 'lead' => 2, 'exec' => 3][$lvl] ?? 0;
}

/**
 * الأولوية تُحسب ولا تُختار، حتى لا يصير كل شيء عاجلاً.
 * التأثير من نوع الطلب، والإلحاح يصفه صاحب التذكرة، والناتج مصفوفة ITIL.
 */
function computePriority(string $type, string $urgency, bool $widespread = false): string
{
    $impact = $widespread ? 'wide' : 'single';
    $matrix = [
        'wide'    => ['high' => 'critical', 'normal' => 'critical', 'low' => 'high'],
        'partial' => ['high' => 'critical', 'normal' => 'high',     'low' => 'normal'],
        'single'  => ['high' => 'high',     'normal' => 'normal',   'low' => 'low'],
    ];
    $p = $matrix[$impact][$urgency] ?? 'normal';

    /* قاعدة وصال: عائق وصول أو معلومة خاطئة عن حق قانوني يرتفع درجة —
       منصة لذوي الإعاقة تعامل عائق الوصول كعُطل لا كاقتراح تحسين. */
    if (in_array($type, [TICKET_TYPE_ACCURACY, TICKET_TYPE_ACCESS], true)) {
        $p = bumpPriority($p);
    }
    return $p;
}

function bumpPriority(string $p): string
{
    return ['low' => 'normal', 'normal' => 'high', 'high' => 'critical', 'critical' => 'critical'][$p] ?? $p;
}

/** يضيف سطراً في سجل التذكرة ويحدّث وقت آخر تعديل */
function ticketEntry(int $ticketId, ?array $author, string $kind, string $visibility, string $body, array $meta = []): void
{
    db()->prepare('INSERT INTO ticket_entries (ticket_id,author_id,author_name,kind,visibility,body,meta,created_at)
                   VALUES (?,?,?,?,?,?,?,NOW())')
        ->execute([
            $ticketId, $author['id'] ?? null, $author['name'] ?? null, $kind, $visibility,
            mb_substr($body, 0, 4000), $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
        ]);
    db()->prepare('UPDATE support_tickets SET updated_at=NOW() WHERE id=?')->execute([$ticketId]);
}

/** يجعل المستخدم متابعاً للتذكرة — المُحيل يبقى مشتركاً بعد خروجها من يده */
function watchTicket(int $ticketId, ?int $userId): void
{
    if (!$userId) return;
    db()->prepare('INSERT IGNORE INTO ticket_watchers (ticket_id,user_id,created_at) VALUES (?,?,NOW())')
        ->execute([$ticketId, $userId]);
}

/** الاسم الأول فقط: يبقى التواصل إنسانياً دون كشف هوية الموظف كاملة */
function agentDisplayName(?string $full): string
{
    $first = trim(explode(' ', trim((string)$full))[0] ?? '');
    return $first !== '' ? $first . ' من فريق وصال' : 'فريق وصال';
}

/** شكل التذكرة كما يراه صاحبها — بلا أي حقل داخلي */
function publicTicket(array $t): array
{
    return [
        'ref'      => $t['ref'],
        'subject'  => $t['subject'] ?: $t['type'],
        'type'     => $t['type'],
        'status'   => $t['status'],
        'priority' => $t['priority'],
        'details'  => $t['details'],
        'created'  => strtotime($t['created_at']) * 1000,
        'updated'  => $t['updated_at'] ? strtotime($t['updated_at']) * 1000 : null,
        'resolved' => $t['resolved_at'] ? strtotime($t['resolved_at']) * 1000 : null,
        'closed'   => $t['closed_at'] ? strtotime($t['closed_at']) * 1000 : null,
        'resolution' => $t['resolution'],
        'csat'     => $t['csat'] !== null ? (int)$t['csat'] : null,
    ];
}

/** سجل التذكرة العام فقط — تُفلتر الملاحظات الداخلية في الاستعلام */
function publicEntries(int $ticketId): array
{
    $s = db()->prepare("SELECT author_id,author_name,kind,body,created_at
                        FROM ticket_entries
                        WHERE ticket_id=? AND visibility='public'
                        ORDER BY created_at, id");
    $s->execute([$ticketId]);
    return array_map(fn($e) => [
        'who'  => $e['kind'] === 'system' ? 'النظام'
                 : ($e['author_id'] ? agentDisplayName($e['author_name']) : ($e['author_name'] ?: 'أنت')),
        'mine' => $e['author_id'] === null && $e['kind'] === 'reply',
        'kind' => $e['kind'],
        'body' => $e['body'],
        't'    => strtotime($e['created_at']) * 1000,
    ], $s->fetchAll());
}

function findByToken(string $token): ?array
{
    $token = preg_replace('/[^a-f0-9]/', '', $token);
    if (strlen($token) !== 32) return null;
    $s = db()->prepare('SELECT * FROM support_tickets WHERE track_token=? LIMIT 1');
    $s->execute([$token]);
    return $s->fetch() ?: null;
}

/* ---------------- اتفاقية مستوى الخدمة ---------------- */

/** ساعات العمل: الأحد–الخميس ٨ص–٦م بتوقيت الرياض (لا يتغيّر بتوقيت الخادم) */
function isBusinessHour(DateTime $t): bool
{
    $dow = (int)$t->format('N'); // 1=الاثنين ... 7=الأحد
    $isWorkday = !in_array($dow, [5, 6], true); // الجمعة والسبت عطلة
    $hour = (int)$t->format('G');
    return $isWorkday && $hour >= 8 && $hour < 18;
}

/** يضيف N ساعة عمل فعلية إلى وقت البداية، متجاوزاً عطلة نهاية الأسبوع والليل */
function addBusinessHours(DateTime $start, float $hours): DateTime
{
    $t = clone $start;
    $tz = new DateTimeZone('Asia/Riyadh');
    $t->setTimezone($tz);
    $minutesLeft = (int)round($hours * 60);
    $guard = 0;
    while ($minutesLeft > 0 && $guard < 100000) {
        $guard++;
        if (isBusinessHour($t)) {
            $t->modify('+1 minute');
            $minutesLeft--;
        } else {
            $t->modify('+1 minute');
        }
    }
    return $t;
}

/**
 * مواعيد الاستجابة والحل حسب الأولوية. الحرجة تُقاس بالساعة الفعلية (٢٤/٧)
 * لأنها تمسّ استخدام المنصة نفسه؛ البقية بساعات العمل حتى لا يُحسب تأخير
 * لا يد لأحد فيه (ليل أو عطلة) كمخالفة على الفريق.
 */
function slaDue(string $priority): array
{
    $now = new DateTime('now', new DateTimeZone('Asia/Riyadh'));
    $table = [
        'critical' => ['first' => 1,  'resolution' => 4,   'real' => true],
        'high'     => ['first' => 4,  'resolution' => 8,   'real' => false],
        'normal'   => ['first' => 8,  'resolution' => 24,  'real' => false],
        'low'      => ['first' => 16, 'resolution' => 40,  'real' => false],
    ];
    $c = $table[$priority] ?? $table['normal'];
    if ($c['real']) {
        $first = (clone $now)->modify('+' . $c['first'] . ' hours');
        $res   = (clone $now)->modify('+' . $c['resolution'] . ' hours');
    } else {
        $first = addBusinessHours($now, $c['first']);
        $res   = addBusinessHours($now, $c['resolution']);
    }
    return ['first' => $first->format('Y-m-d H:i:s'), 'resolution' => $res->format('Y-m-d H:i:s')];
}

/* ---------------- الإشعار البريدي ---------------- */

/** يُخطر كل معالجي المستوى الأول (lead/exec) بتذكرة جديدة — L1 يستقبل الكل بلا استثناء */
function notifyAgents(int $ticketId, string $ref, string $title, string $priority): void
{
    $s = db()->query("SELECT id FROM users WHERE support_level IN ('lead','exec')");
    $prLabel = ['critical' => 'حرجة', 'high' => 'عالية', 'normal' => 'عادية', 'low' => 'منخفضة'][$priority] ?? 'عادية';
    foreach ($s->fetchAll() as $row) {
        notify((int)$row['id'], 'ticket_new', $ref . ': ' . $title, 'أولوية: ' . $prLabel, '/tickets.html?id=' . $ticketId);
    }
}

function notifyNewTicket(string $ref, string $token, string $name, string $email, string $type, string $details, string $priority): void
{
    $link = rtrim(SITE_URL, '/') . '/ticket.html?token=' . $token;
    $prLabel = ['critical' => 'حرجة', 'high' => 'عالية', 'normal' => 'عادية', 'low' => 'منخفضة'][$priority] ?? 'عادية';
    $html = '<div dir="rtl" style="font-family:Tahoma,Arial,sans-serif;line-height:1.8">'
        . '<p>مرحباً ' . htmlspecialchars($name) . '،</p>'
        . '<p>وصلنا طلبك وأُعطي الرقم المرجعي <b>' . htmlspecialchars($ref) . '</b> (أولوية: ' . $prLabel . ').</p>'
        . '<p><a href="' . htmlspecialchars($link) . '">اضغط هنا لمتابعة تذكرتك في أي وقت</a></p>'
        . '<p style="color:#666;font-size:13px">احتفظ بهذا الرابط، فهو وسيلتك الوحيدة لمتابعة الطلب إن لم يكن لديك حساب.</p>'
        . '</div>';
    /* فشل البريد لا يوقف إنشاء التذكرة — التذكرة محفوظة والرابط سيصل لاحقاً
       عبر البريد الاحتياطي mail() إن كانت SMTP معطّلة، أو يمكن استرجاعه من
       لوحة المعالج. */
    try { sendMail($email, 'رقم تذكرتك في وصال: ' . $ref, $html); } catch (Throwable $e) { /* التذكرة أهم من فشل الإشعار */ }
}

/* ---------------- الإجراءات ---------------- */

switch ($action) {

    /* ===== إنشاء تذكرة — يقبل الضيف والمسجّل معاً ===== */
    case 'create': {
        rateLimit('ticket_new', 3);
        $u = currentUser();

        $type    = clean($in['type'] ?? '', 60);
        $subject = clean($in['subject'] ?? '', 140);
        $details = clean($in['details'] ?? '', 4000);
        $urgency = in_array($in['urgency'] ?? '', ['high', 'normal', 'low'], true) ? $in['urgency'] : 'normal';
        $source  = in_array($in['source'] ?? '', ['corporate', 'chat', 'internal'], true) ? $in['source'] : 'chat';

        if (!in_array($type, TICKET_TYPES, true)) fail('اختر نوع الطلب من القائمة.');
        if (mb_strlen($details) < 10)             fail('اكتب تفاصيل الطلب (10 أحرف على الأقل).');

        if ($u) {
            $name  = $u['name'];
            $email = $u['email'];
        } else {
            $name  = clean($in['name'] ?? '', 80);
            $email = mb_strtolower(clean($in['email'] ?? '', 120));
            if (mb_strlen($name) < 3)                       fail('اكتب اسمك كاملاً حتى نعرف من نخاطب.');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('اكتب بريداً إلكترونياً صحيحاً، فعليه نرسل لك رابط المتابعة.');
        }

        $priority = computePriority($type, $urgency);
        $token    = bin2hex(random_bytes(16));
        $due      = slaDue($priority);

        db()->prepare("INSERT INTO support_tickets
            (ref,track_token,user_id,guest_name,guest_email,type,subject,details,priority,
             status,source,due_first_response,due_resolution,created_at,updated_at)
            VALUES (?,?,?,?,?,?,?,?,?, 'new',?,?,?,NOW(),NOW())")
            ->execute([
                'TMP-' . bin2hex(random_bytes(6)), $token,
                $u['id'] ?? null, $u ? null : $name, $u ? null : $email,
                $type, $subject ?: mb_substr($details, 0, 80), $details, $priority,
                $source, $due['first'], $due['resolution'],
            ]);

        $id  = (int)db()->lastInsertId();
        $ref = ticketRef((int)date('Y'), $id);
        db()->prepare('UPDATE support_tickets SET ref=? WHERE id=?')->execute([$ref, $id]);

        ticketEntry($id, $u ? ['id' => $u['id'], 'name' => $u['name']] : ['id' => null, 'name' => $name],
                    'reply', 'public', $details);
        watchTicket($id, $u['id'] ?? null);

        /* التذكرة محفوظة بالفعل في هذه اللحظة. أي عطل في الإشعار (حتى لو غير
           متوقَّع) يجب ألا يمنع صاحب الطلب من معرفة رقمه — عطل بريدي هنا كاد
           يُسقط الرد كاملاً أثناء الاختبار فيظهر "فشل" لطلب نجح فعلاً. */
        try { notifyNewTicket($ref, $token, $name, $email, $type, $details, $priority); }
        catch (Throwable $e) { error_log('WESAL_TICKET_NOTIFY_FAIL: ' . $e->getMessage()); }
        notifyAgents($id, $ref, 'تذكرة جديدة (' . $type . ')', $priority);

        out(['ok' => true, 'ref' => $ref, 'token' => $token,
             'message' => 'استلمنا طلبك. رقمه ' . $ref . ' وأرسلنا رابط المتابعة على ' . $email . '.']);
    }

    /* ===== متابعة تذكرة بالرمز السرّي — بلا حساب ===== */
    case 'track': {
        rateLimit('ticket_track', 30);
        $t = findByToken((string)($in['token'] ?? ''));
        /* رسالة واحدة لكل حالات الفشل: تمييز «رمز خاطئ» عن «تذكرة محذوفة»
           يكشف أي الرموز موجودة فعلاً. */
        if (!$t) fail('رابط المتابعة غير صحيح أو انتهت صلاحيته.', 404);
        out(['ok' => true, 'ticket' => publicTicket($t), 'entries' => publicEntries((int)$t['id'])]);
    }

    /* ===== رد صاحب التذكرة — عبر الرمز أو حسابه ===== */
    case 'reply': {
        rateLimit('ticket_reply', 10);
        $body = clean($in['body'] ?? '', 4000);
        if (mb_strlen($body) < 2) fail('اكتب ردك أولاً.');

        $u = currentUser();
        if (!empty($in['token'])) {
            $t = findByToken((string)$in['token']);
            if (!$t) fail('رابط المتابعة غير صحيح أو انتهت صلاحيته.', 404);
        } else {
            if (!$u) fail('سجّل دخولك أو استخدم رابط المتابعة.', 401);
            $s = db()->prepare('SELECT * FROM support_tickets WHERE id=? AND user_id=? LIMIT 1');
            $s->execute([(int)($in['id'] ?? 0), $u['id']]);
            $t = $s->fetch();
            if (!$t) fail('التذكرة غير موجودة.', 404);
        }
        if ($t['status'] === 'closed') fail('هذه التذكرة مغلقة. افتح تذكرة جديدة وسنربطها بها.', 409);

        $author = ['id' => $t['user_id'] ? (int)$t['user_id'] : null,
                   'name' => $t['user_id'] ? ($u['name'] ?? null) : $t['guest_name']];
        ticketEntry((int)$t['id'], $author, 'reply', 'public', $body);

        /* ردّ صاحب التذكرة يرفع التجميد: ما عدنا ننتظره. لكن "التجميد" لم
           يكن له أثر فعلي — due_resolution لا يتغيّر أبداً وقت الانتظار،
           فينتظر الفريق يوماً كاملاً ويُحسب عليهم تأخيراً لم يصنعوه. هذا
           يمدّد الموعد بمقدار مدة الانتظار الفعلية، لا يجمّدها وهماً. */
        if ($t['status'] === 'waiting') {
            $waitedSeconds = max(0, time() - strtotime($t['updated_at'] ?: $t['created_at']));
            db()->prepare("UPDATE support_tickets SET status='in_progress',
                           due_resolution=DATE_ADD(due_resolution, INTERVAL ? SECOND) WHERE id=?")
                ->execute([$waitedSeconds, $t['id']]);
            ticketEntry((int)$t['id'], null, 'status', 'public', 'وصلنا ردّك ورجعت التذكرة قيد المعالجة.',
                        ['from' => 'waiting', 'to' => 'in_progress']);
        }
        if ($t['assignee_id']) {
            notify((int)$t['assignee_id'], 'ticket_reply', 'رد جديد على ' . $t['ref'],
                   mb_substr($body, 0, 140), '/tickets.html?id=' . $t['id']);
        }
        out(['ok' => true]);
    }

    /* ===== تذاكري — للمستفيد المسجّل ===== */
    case 'mine': {
        $u = currentUser();
        if (!$u) fail('سجّل دخولك أولاً.', 401);
        $s = db()->prepare('SELECT * FROM support_tickets WHERE user_id=? ORDER BY created_at DESC LIMIT 50');
        $s->execute([$u['id']]);
        out(['ok' => true, 'tickets' => array_map(fn($t) => publicTicket($t) + ['id' => (int)$t['id']], $s->fetchAll())]);
    }

    /* ===== أنواع الطلبات — مصدر واحد تقرأه كل الواجهات ===== */
    case 'types': {
        out(['ok' => true, 'types' => TICKET_TYPES]);
    }

    /* ==================== طابور المعالج ==================== */

    /** قائمة معالجي الدعم — لتعبئة قائمة الإحالة في الواجهة */
    case 'agents': {
        requireAgent();
        $s = db()->query("SELECT id,name,support_level FROM users WHERE support_level != 'none' ORDER BY
                          FIELD(support_level,'exec','lead','agent'), name");
        out(['ok' => true, 'agents' => array_map(fn($a) => [
            'id' => (int)$a['id'], 'name' => $a['name'], 'level' => $a['support_level'],
        ], $s->fetchAll())]);
    }

    case 'queue': {
        $agent  = requireAgent();
        $status = clean($in['status'] ?? '', 20);
        $mine   = !empty($in['mine']);

        $where = [];
        $args  = [];
        if (in_array($status, TICKET_STATUSES, true)) { $where[] = 't.status=?'; $args[] = $status; }
        if ($mine) { $where[] = 't.assignee_id=?'; $args[] = $agent['id']; }
        $sql = 'SELECT t.*, u.name uname, u.email uemail, a.name aname
                FROM support_tickets t
                LEFT JOIN users u ON u.id=t.user_id
                LEFT JOIN users a ON a.id=t.assignee_id';
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        /* الأقرب لمخالفة الاتفاقية أولاً — طابور حقيقي لا قائمة عشوائية */
        $sql .= ' ORDER BY (t.status NOT IN (\'closed\',\'resolved\')) DESC, t.due_resolution ASC LIMIT 300';
        $st = db()->prepare($sql);
        $st->execute($args);
        out(['ok' => true, 'tickets' => array_map(fn($t) => [
            'id' => (int)$t['id'], 'ref' => $t['ref'], 'type' => $t['type'],
            'subject' => $t['subject'] ?: $t['type'], 'priority' => $t['priority'], 'status' => $t['status'],
            'source' => $t['source'], 'name' => $t['uname'] ?: $t['guest_name'],
            'email' => $t['uemail'] ?: $t['guest_email'], 'is_guest' => $t['user_id'] === null,
            'assignee' => $t['aname'], 'assignee_id' => $t['assignee_id'] ? (int)$t['assignee_id'] : null,
            'due_first' => $t['due_first_response'] ? strtotime($t['due_first_response']) * 1000 : null,
            'due_resolution' => $t['due_resolution'] ? strtotime($t['due_resolution']) * 1000 : null,
            'overdue' => $t['due_resolution'] && !in_array($t['status'], ['closed', 'resolved'], true)
                         && strtotime($t['due_resolution']) < time(),
            'created' => strtotime($t['created_at']) * 1000,
        ], $st->fetchAll())]);
    }

    /* ===== فتح تذكرة كاملة — سجلّها العام والداخلي معاً للمعالج ===== */
    case 'open': {
        $agent = requireAgent();
        $id = (int)($in['id'] ?? 0);
        $t = db()->prepare('SELECT t.*, u.name uname, u.email uemail FROM support_tickets t LEFT JOIN users u ON u.id=t.user_id WHERE t.id=?');
        $t->execute([$id]);
        $ticket = $t->fetch();
        if (!$ticket) fail('التذكرة غير موجودة.', 404);

        $s = db()->prepare("SELECT e.*, w.name watcher_name FROM ticket_entries e
                            LEFT JOIN users w ON w.id=e.author_id
                            WHERE e.ticket_id=? ORDER BY e.created_at, e.id");
        $s->execute([$id]);
        $entries = array_map(fn($e) => [
            'id' => (int)$e['id'], 'author' => $e['author_name'] ?: ($e['kind'] === 'system' ? 'النظام' : null),
            'kind' => $e['kind'], 'visibility' => $e['visibility'], 'body' => $e['body'],
            'meta' => $e['meta'] ? json_decode($e['meta'], true) : null,
            't' => strtotime($e['created_at']) * 1000,
        ], $s->fetchAll());

        out(['ok' => true, 'ticket' => [
            'id' => (int)$ticket['id'], 'ref' => $ticket['ref'], 'type' => $ticket['type'],
            'subject' => $ticket['subject'], 'details' => $ticket['details'], 'priority' => $ticket['priority'],
            'status' => $ticket['status'], 'source' => $ticket['source'],
            'name' => $ticket['uname'] ?: $ticket['guest_name'], 'email' => $ticket['uemail'] ?: $ticket['guest_email'],
            'is_guest' => $ticket['user_id'] === null, 'assignee_id' => $ticket['assignee_id'] ? (int)$ticket['assignee_id'] : null,
            'resolution' => $ticket['resolution'], 'csat' => $ticket['csat'],
        ], 'entries' => $entries]);
    }

    /* ===== الإسناد لنفسه — أول من يفتحها ينقلها "قيد المعالجة" ===== */
    case 'claim': {
        $agent = requireAgent();
        $id = (int)($in['id'] ?? 0);
        $s = db()->prepare('SELECT * FROM support_tickets WHERE id=?'); $s->execute([$id]);
        $t = $s->fetch();
        if (!$t) fail('التذكرة غير موجودة.', 404);
        if ($t['status'] !== 'new') fail('هذه التذكرة ليست بانتظار إسناد.', 409);

        db()->prepare("UPDATE support_tickets SET assignee_id=?, status='in_progress', first_response_at=NOW() WHERE id=?")
            ->execute([$agent['id'], $id]);
        ticketEntry($id, $agent, 'assign', 'internal', $agent['name'] . ' استلم التذكرة.');
        ticketEntry($id, null, 'status', 'public', 'بدأ فريقنا معالجة طلبك.', ['from' => 'new', 'to' => 'in_progress']);
        watchTicket($id, $agent['id']);
        if ($t['user_id']) notify((int)$t['user_id'], 'ticket_status', 'بدأ العمل على ' . $t['ref'], 'استلم فريقنا طلبك.', '/dashboard#tickets');
        out(['ok' => true]);
    }

    /* ===== رد المعالج أو ملاحظته الداخلية ===== */
    case 'agent_reply': {
        $agent = requireAgent();
        $id   = (int)($in['id'] ?? 0);
        $body = clean($in['body'] ?? '', 4000);
        $vis  = ($in['visibility'] ?? 'public') === 'internal' ? 'internal' : 'public';
        if (mb_strlen($body) < 2) fail('اكتب نصّ الرد أو الملاحظة.');

        $t = requireTicketAccess($agent, $id);
        ticketEntry($id, $agent, $vis === 'internal' ? 'note' : 'reply', $vis, $body);
        /* أول رد عام من معالج = الاستجابة الأولى الفعلية إن لم تُسجَّل بعد */
        if ($vis === 'public' && !$t['first_response_at']) {
            db()->prepare('UPDATE support_tickets SET first_response_at=NOW() WHERE id=?')->execute([$id]);
        }
        if ($vis === 'public' && $t['user_id']) {
            notify((int)$t['user_id'], 'ticket_reply', 'رد جديد على ' . $t['ref'], mb_substr($body, 0, 140), '/dashboard#tickets');
        }
        out(['ok' => true]);
    }

    /* ===== طلب معلومة من صاحب التذكرة — يجمّد عدّاد الاتفاقية ===== */
    case 'wait': {
        $agent = requireAgent();
        $id   = (int)($in['id'] ?? 0);
        $body = clean($in['body'] ?? '', 4000);
        if (mb_strlen($body) < 2) fail('اكتب ما تحتاج معرفته من صاحب التذكرة.');
        $t = requireTicketAccess($agent, $id);
        if (in_array($t['status'], ['closed', 'resolved'], true)) fail('التذكرة مغلقة.', 409);

        db()->prepare("UPDATE support_tickets SET status='waiting' WHERE id=?")->execute([$id]);
        ticketEntry($id, $agent, 'reply', 'public', $body);
        ticketEntry($id, null, 'status', 'internal', 'أصبحت التذكرة بانتظار رد صاحبها، وتوقف عدّاد اتفاقية مستوى الخدمة.',
                    ['from' => $t['status'], 'to' => 'waiting']);
        out(['ok' => true]);
    }

    /* ===== الإحالة — ثلاثة حقول: لمن، لماذا، وهل يظهر السبب لصاحب التذكرة ===== */
    case 'escalate': {
        $agent = requireAgent();
        $id      = (int)($in['id'] ?? 0);
        $toId    = (int)($in['to_user_id'] ?? 0);
        $reason  = clean($in['reason'] ?? '', 1000);
        $showWhy = !empty($in['reason_visible_to_requester']);
        if ($reason === '') fail('اكتب سبب الإحالة قبل تأكيدها.');

        $t = requireTicketAccess($agent, $id);
        $s = db()->prepare('SELECT id,name,support_level FROM users WHERE id=?'); $s->execute([$toId]);
        $target = $s->fetch();
        if (!$target || supportLevel($target) === 'none') fail('اختر مُعالجاً فعلياً لإحالة التذكرة إليه.');
        if (levelRank(supportLevel($target)) < levelRank(supportLevel($agent)) && $target['id'] != $agent['id'])
            fail('لا تُحال التذكرة إلى مستوى أدنى، ويمكنك إعادتها إلى الطابور العام إن لزم.');

        db()->prepare("UPDATE support_tickets SET assignee_id=?, status='escalated' WHERE id=?")
            ->execute([$toId, $id]);
        ticketEntry($id, $agent, 'referral', 'internal', $reason,
                    ['to' => $target['name'], 'to_id' => $toId, 'visible' => $showWhy]);
        if ($showWhy) ticketEntry($id, $agent, 'referral', 'public', 'أُحيلت تذكرتك لجهة مختصة: ' . $reason);
        else ticketEntry($id, null, 'referral', 'public', 'أُحيلت تذكرتك إلى فريق مختصّ لمتابعتها.');
        watchTicket($id, $agent['id']); // المُحيل يبقى مشتركاً
        watchTicket($id, $toId);
        notify($toId, 'ticket_escalated', 'أُحيلت إليك ' . $t['ref'], $reason, '/tickets.html?id=' . $id);
        if ($t['user_id']) notify((int)$t['user_id'], 'ticket_status', 'تحديث على ' . $t['ref'], 'أُحيلت تذكرتك إلى فريق مختصّ.', '/dashboard#tickets');
        out(['ok' => true]);
    }

    /* ===== الحل — يتطلب ملخّصاً، لا إغلاق بضغطة بلا تفسير ===== */
    case 'resolve': {
        $agent = requireAgent();
        $id  = (int)($in['id'] ?? 0);
        $res = clean($in['resolution'] ?? '', 2000);
        if (mb_strlen($res) < 5) fail('اكتب ملخّص الحل، فلا يُغلق طلب دون توضيح لصاحبه.');
        $t = requireTicketAccess($agent, $id);

        db()->prepare("UPDATE support_tickets SET status='resolved', resolved_at=NOW(), resolution=? WHERE id=?")
            ->execute([$res, $id]);
        ticketEntry($id, $agent, 'reply', 'public', $res);
        ticketEntry($id, null, 'status', 'public', 'إن كان الحل يكفيك ستُغلق تذكرتك تلقائياً خلال أسبوع. اعترض خلال هذه المدة إن احتجت.',
                    ['from' => $t['status'], 'to' => 'resolved']);
        if ($t['user_id']) notify((int)$t['user_id'], 'ticket_resolved', 'حُلّت ' . $t['ref'], mb_substr($res, 0, 140), '/dashboard#tickets');
        out(['ok' => true]);
    }

    /* ===== إعادة الفتح — من صاحب التذكرة عبر الرمز أو حسابه ===== */
    case 'reopen': {
        $u = currentUser();
        if (!empty($in['token'])) { $t = findByToken((string)$in['token']); if (!$t) fail('رابط المتابعة غير صحيح.', 404); }
        elseif ($u) { $s = db()->prepare('SELECT * FROM support_tickets WHERE id=? AND user_id=?'); $s->execute([(int)($in['id'] ?? 0), $u['id']]); $t = $s->fetch(); if (!$t) fail('التذكرة غير موجودة.', 404); }
        else fail('سجّل دخولك أو استخدم رابط المتابعة.', 401);

        if (!in_array($t['status'], ['resolved', 'closed'], true)) fail('هذه التذكرة ليست مغلقة.', 409);
        $closedAt = $t['closed_at'] ?: $t['resolved_at'];
        if ($closedAt && (time() - strtotime($closedAt)) > 14 * 86400) {
            fail('مضى أكثر من 14 يوماً على إغلاقها. افتح تذكرة جديدة وسنربطها بهذه التذكرة.', 409);
        }
        db()->prepare("UPDATE support_tickets SET status='reopened', assignee_id=? WHERE id=?")
            ->execute([$t['assignee_id'], $t['id']]);
        $body = clean($in['body'] ?? '', 2000);
        ticketEntry((int)$t['id'], null, 'reply', 'public', $body !== '' ? $body : 'أعاد صاحب التذكرة فتحها.');
        ticketEntry((int)$t['id'], null, 'status', 'internal', 'أُعيد الفتح خلال مهلة الـ14 يوماً، ورجعت التذكرة إلى المعالج نفسه بأولوية أعلى.',
                    ['from' => $t['status'], 'to' => 'reopened']);
        db()->prepare("UPDATE support_tickets SET priority=? WHERE id=?")->execute([bumpPriority($t['priority']), $t['id']]);
        if ($t['assignee_id']) notify((int)$t['assignee_id'], 'ticket_reopened', 'أُعيدت ' . $t['ref'] . ' للفتح', $body, '/tickets.html?id=' . $t['id']);
        out(['ok' => true]);
    }

    /* ===== تقييم الرضا — بعد الإغلاق فقط ===== */
    case 'rate': {
        $csat = (int)($in['csat'] ?? 0);
        if ($csat < 1 || $csat > 5) fail('التقييم من 1 إلى 5.');
        $t = null;
        if (!empty($in['token'])) $t = findByToken((string)$in['token']);
        if (!$t) fail('رابط المتابعة غير صحيح.', 404);
        if ($t['status'] !== 'closed') fail('التقييم متاح بعد إغلاق التذكرة فقط.', 409);
        db()->prepare('UPDATE support_tickets SET csat=? WHERE id=?')->execute([$csat, $t['id']]);
        out(['ok' => true]);
    }

    default:
        fail('طلب غير معروف.', 404);
}

/**
 * تحقّق أن التذكرة موجودة، ويعيدها.
 * قصداً بلا فحص إضافي مرتبط بـrole: صلاحية العمل على تذاكر الدعم في هذا
 * الملف تُحكَم بـsupport_level وحده (عبر requireAgent) — وهذا هو بيت
 * القصيد من فصله عن دور المنصة، حتى يقدر مراجع محتوى يصير معالج تذاكر
 * كامل الصلاحية دون منحه صلاحيات إدارية. قيد "مراجع المحتوى يرى بلاغات
 * الدقّة فقط" ما زال قائماً في admin.php وحدها — الواجهة القديمة الضيّقة
 * التي لم تُستبدل بعد، لا في هذا النظام الجديد. */
function requireTicketAccess(array $agent, int $id): array
{
    $s = db()->prepare('SELECT * FROM support_tickets WHERE id=?');
    $s->execute([$id]);
    $t = $s->fetch();
    if (!$t) fail('التذكرة غير موجودة.', 404);
    return $t;
}
