<?php
/* ==========================================================================
 *  وصال — لوحة الإدارة
 *
 *  الأدوار وصلاحياتها:
 *    admin     مدير النظام  — كل ما في هذا الملف، ومحتوى الصفحات في content.php
 *    mod       مشرف         — عرض المستخدمين، الرسائل، التذاكر، دعوة مستفيدين
 *    reviewer  مراجع محتوى  — التذاكر فقط (بلاغات دقة المعلومات)
 *
 *  كل عملية تغيّر حالة تُسجَّل في audit_log باسم من نفّذها وعنوانه.
 * ========================================================================== */
require_once __DIR__ . '/db.php';
ensureSchema();

$STAFF = requireUserManager();
$in    = body();
$act   = $in['action'] ?? '';

/** بعض الأقسام لا يراها مراجع المحتوى */
function needRole(array $staff, array $roles): void {
    if (!in_array($staff['role'], $roles, true))
        fail('ليست لديك صلاحية لهذا الإجراء.', 403);
}

/** يجلب المستخدم الهدف مع التحقق من أن الإجراء عليه مسموح */
function targetUser(array $staff, $id, string $what): array {
    $id = (int)$id;
    $s = db()->prepare('SELECT id,name,email,role,org_role,status,tokens FROM users WHERE id=? LIMIT 1');
    $s->execute([$id]);
    $t = $s->fetch();
    if (!$t) fail('المستخدم غير موجود.');
    if ((int)$t['id'] === (int)$staff['id'])
        fail('لا يمكنك أن ' . $what . ' حسابك بنفسك، فاطلب ذلك من مدير نظام آخر.');
    return $t;
}

/** يمنع إفراغ المنصة من مديري النظام */
function guardLastAdmin(array $target): void {
    if ($target['role'] !== 'admin') return;
    $n = (int) db()->query("SELECT COUNT(*) c FROM users WHERE role='admin' AND status='active'")->fetch()['c'];
    if ($n <= 1) fail('هذا آخر مدير نظام نشط في المنصة، فعيّن مديراً آخر قبل تغيير صلاحيته أو إيقافه.');
}

switch ($act) {

    /* ==================== المستخدمون ==================== */

    case 'users': {
        /* مدير الموارد البشرية يرى منسوبي المنشأة (كل صاحب دور في مساحة العمل عدا العملاء) ومن يحق
           له دعوته من فريق المنصة، ومدير علاقات العملاء عملاءه وحدهم. غيرهم كما كان: كل الحسابات. */
        $mgr = effectiveRole($STAFF);
        if (!in_array($STAFF['role'], ['admin', 'mod'], true) && !in_array($mgr, ['hr', 'crm'], true))
            fail('ليست لديك صلاحية لهذا الإجراء.', 403);
        $q    = clean($in['q'] ?? '', 80);
        $sql  = 'SELECT id,name,name_en,email,phone,pref,role,org_role,status,tokens,questions,created_at,last_login
                 FROM users';
        $args = [];
        $where = [];
        if ($mgr === 'hr')  $where[] = "(role IN ('mod','reviewer') OR (org_role IS NOT NULL AND org_role<>'client'))";
        if ($mgr === 'crm') $where[] = "org_role='client'";
        if ($q !== '') {
            $where[] = '(name LIKE ? OR email LIKE ? OR phone LIKE ?)';
            $args = ["%$q%", "%$q%", "%$q%"];
        }
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY created_at DESC LIMIT 500';
        $st = db()->prepare($sql);
        $st->execute($args);
        out(['ok' => true, 'invitable' => invitableRoles($STAFF), 'users' => array_map(fn($u) => [
            'id'      => (int)$u['id'],
            'name'    => $u['name'],
            'name_en' => $u['name_en'] ?? '',
            'email'   => $u['email'],
            'phone'   => $u['phone'],
            'eff'     => effectiveRole($u),
            'role'    => $u['role'],
            'status'  => $u['status'] ?? 'active',
            'tokens'  => (int)$u['tokens'],
            'qs'      => (int)$u['questions'],
            'created' => strtotime($u['created_at']) * 1000,
            'last'    => $u['last_login'] ? strtotime($u['last_login']) * 1000 : 0,
        ], $st->fetchAll())]);
    }

    case 'set_role': {
        needRole($STAFF, ['admin']);
        /* الدور واحد من الاثني عشر (ALL_ROLES). 'admin' القديم يُقبل اسماً لمدير النظام. */
        $eff = (string)($in['role'] ?? '');
        if ($eff === 'admin') $eff = 'sysadmin';
        if (!in_array($eff, ALL_ROLES, true)) fail('دور غير معروف.');
        $t = targetUser($STAFF, $in['user_id'] ?? 0, 'تعدّل صلاحية');
        $before = effectiveRole($t);
        if ($before === $eff) out(['ok' => true, 'role' => $eff]);
        if ($t['role'] === 'admin') guardLastAdmin($t);
        [$role, $org] = roleColumns($eff);
        db()->prepare('UPDATE users SET role=?, org_role=? WHERE id=?')->execute([$role, $org, $t['id']]);
        authSessionsRevoke((int)$t['id'], 'role');   // حدّا مهلته يتغيران بدوره، فيدخل من جديد
        audit($STAFF, 'role', $t['email'], $before . ' ← ' . $eff);
        out(['ok' => true, 'role' => $eff]);
    }

    case 'set_status': {
        needRole($STAFF, ['admin']);
        $status = ($in['status'] ?? '') === 'suspended' ? 'suspended' : 'active';
        $t = targetUser($STAFF, $in['user_id'] ?? 0, 'توقف');
        if ($status === 'suspended') guardLastAdmin($t);
        db()->prepare('UPDATE users SET status=? WHERE id=?')->execute([$status, $t['id']]);
        if ($status === 'suspended') authSessionsRevoke((int)$t['id'], 'suspended');   // يخرج من النظامين فوراً
        audit($STAFF, 'status', $t['email'], $status === 'suspended' ? 'إيقاف' : 'تفعيل');
        out(['ok' => true, 'status' => $status]);
    }

    case 'reset_password': {
        needRole($STAFF, ['admin']);
        rateLimit('adm_reset', 20);
        $t    = targetUser($STAFF, $in['user_id'] ?? 0, 'تعيد تعيين كلمة مرور');
        $mode = ($in['mode'] ?? 'link') === 'temp' ? 'temp' : 'link';

        if ($mode === 'temp') {
            // كلمة مرور مؤقتة: تُسلَّم للمستخدم ويُطالَب بتغييرها عند أول دخول
            $temp = 'Ws' . bin2hex(random_bytes(3)) . random_int(10, 99);
            db()->prepare('UPDATE users SET pass_hash=?, must_change_pw=1 WHERE id=?')
                ->execute([password_hash($temp, PASSWORD_DEFAULT), $t['id']]);
            db()->prepare('UPDATE password_resets SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')
                ->execute([$t['id']]);
            authSessionsRevoke((int)$t['id'], 'password');
            audit($STAFF, 'reset_pw', $t['email'], 'كلمة مرور مؤقتة');
            out(['ok' => true, 'mode' => 'temp', 'temp_password' => $temp,
                 'message' => 'سلّم كلمة المرور المؤقتة للمستخدم عبر قناة موثوقة. سيُطالَب بتغييرها عند أول دخول.']);
        }

        $token = issueResetToken((int)$t['id'], (int)$STAFF['id']);
        $link  = CHAT_URL . '/?reset=' . $token;
        $mailed = sendMail($t['email'], 'إعادة تعيين كلمة المرور في وصال',
                           resetEmailHtml($t['name'], $link, true, 2));
        audit($STAFF, 'reset_pw', $t['email'], $mailed ? 'رابط أُرسل بالبريد' : 'رابط (تعذّر إرسال البريد)');
        out(['ok' => true, 'mode' => 'link', 'link' => $link, 'mailed' => $mailed,
             'message' => $mailed
                ? 'أُرسل رابط إعادة التعيين لبريد المستخدم. الرابط صالح ساعتين.'
                : 'تعذّر إرسال البريد من الخادم، فانسخ الرابط وسلّمه للمستخدم. الرابط صالح لمدة ساعتين.']);
    }

    case 'grant_tokens': {
        needRole($STAFF, ['admin']);
        $n = (int)($in['tokens'] ?? 0);
        if ($n < 0 || $n > 100000) fail('اكتب رصيداً بين صفر و100000.');
        $t = targetUser($STAFF, $in['user_id'] ?? 0, 'تعدّل رصيد');
        db()->prepare('UPDATE users SET tokens=?, tokens_at=NOW() WHERE id=?')->execute([$n, $t['id']]);
        audit($STAFF, 'tokens', $t['email'], $t['tokens'] . ' ← ' . $n);
        out(['ok' => true, 'tokens' => $n]);
    }

    case 'delete_user': {
        needRole($STAFF, ['admin']);
        $t = targetUser($STAFF, $in['user_id'] ?? 0, 'تحذف');
        guardLastAdmin($t);
        $s = db()->prepare('SELECT avatar FROM users WHERE id=? LIMIT 1');
        $s->execute([$t['id']]);
        $av = $s->fetch()['avatar'] ?? '';
        if ($av && strpos($av, 'uploads/avatars/') === 0)
            @unlink(dirname(__DIR__) . '/uploads/avatars/' . basename($av));

        db()->beginTransaction();
        try {
            db()->prepare('UPDATE chat_logs SET user_id=NULL WHERE user_id=?')->execute([$t['id']]);
            db()->prepare('DELETE FROM support_tickets WHERE user_id=?')->execute([$t['id']]);
            db()->prepare('DELETE FROM password_resets WHERE user_id=?')->execute([$t['id']]);
            db()->prepare('DELETE FROM users WHERE id=?')->execute([$t['id']]);
            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            fail(APP_DEBUG ? $e->getMessage() : 'تعذّر حذف الحساب. حاول مرة أخرى.', 500);
        }
        authSessionsRevoke((int)$t['id'], 'deleted');
        audit($STAFF, 'delete_user', $t['email'], 'حذف إداري');
        out(['ok' => true]);
    }

    /* ==================== الرسائل ==================== */

    case 'messages': {
        needRole($STAFF, ['admin', 'mod']);
        $rows = db()->query('SELECT id,name,email,subject,message,is_read,created_at
                             FROM messages ORDER BY created_at DESC LIMIT 300')->fetchAll();
        out(['ok' => true, 'messages' => array_map(fn($m) => [
            'id' => (int)$m['id'], 'name' => $m['name'], 'email' => $m['email'],
            'subject' => $m['subject'], 'message' => $m['message'],
            'read' => (bool)$m['is_read'], 't' => strtotime($m['created_at']) * 1000,
        ], $rows)]);
    }

    case 'read': {
        needRole($STAFF, ['admin', 'mod']);
        $id   = (int)($in['id'] ?? 0);
        $read = array_key_exists('read', $in) ? (!empty($in['read']) ? 1 : 0) : 1;
        db()->prepare('UPDATE messages SET is_read=? WHERE id=?')->execute([$read, $id]);
        out(['ok' => true, 'read' => (bool)$read]);
    }

    /* ==================== طلبات الدعم ==================== */

    case 'tickets': {
        needRole($STAFF, ['admin', 'mod', 'reviewer']);
        /* مراجع المحتوى يرى بلاغات دقّة المعلومة فقط. الفلترة هنا في الخادم
           لا في المتصفح: الإخفاء في الواجهة يُتجاوَز بفتح أدوات المطوّر،
           فكانت كل تذاكر المستخدمين تصل المراجع فعلياً. */
        $sql  = 'SELECT t.id,t.type,t.details,t.status,t.created_at,u.name uname,u.email uemail
                 FROM support_tickets t JOIN users u ON u.id=t.user_id';
        $args = [];
        if (($STAFF['role'] ?? '') === 'reviewer') { $sql .= ' WHERE t.type=?'; $args[] = TICKET_TYPE_ACCURACY; }
        $sql .= ' ORDER BY t.created_at DESC LIMIT 300';
        $st = db()->prepare($sql);
        $st->execute($args);
        $rows = $st->fetchAll();
        out(['ok' => true, 'tickets' => array_map(fn($t) => [
            'id' => (int)$t['id'], 'type' => $t['type'], 'details' => $t['details'],
            'status' => $t['status'], 'uname' => $t['uname'], 'uemail' => $t['uemail'],
            't' => strtotime($t['created_at']) * 1000,
        ], $rows)]);
    }

    case 'ticket_done': {
        needRole($STAFF, ['admin', 'mod', 'reviewer']);
        $id     = (int)($in['id'] ?? 0);
        $status = ($in['status'] ?? 'done') === 'open' ? 'open' : 'done';
        /* تحقّق من وجود التذكرة قبل التحديث: كان أي رقم يُرسَل يُسجَّل في سجل
           التدقيق كإغلاق ناجح، فيمتلئ السجل بإغلاقات لتذاكر لا وجود لها. */
        $st = db()->prepare('SELECT id,type FROM support_tickets WHERE id=?');
        $st->execute([$id]);
        $ticket = $st->fetch();
        if (!$ticket) fail('التذكرة غير موجودة.', 404);
        /* والمراجع لا يغلق إلا ما يراه — كان يقدر يغلق أي تذكرة برقمها */
        if (($STAFF['role'] ?? '') === 'reviewer' && $ticket['type'] !== TICKET_TYPE_ACCURACY) {
            fail('ليست لديك صلاحية على هذه التذكرة.', 403);
        }
        db()->prepare('UPDATE support_tickets SET status=? WHERE id=?')->execute([$status, $id]);
        audit($STAFF, 'ticket', '#' . $id, $status === 'done' ? 'أُغلقت' : 'أُعيد فتحها');
        out(['ok' => true, 'status' => $status]);
    }

    /* ==================== الإحصاءات ==================== */

    case 'stats': {
        needRole($STAFF, ['admin', 'mod', 'reviewer']);   // كما كان: فريق المنصة وحده
        require_once __DIR__ . '/chat-shared.php';   // chatStreamStats وragKbStats
        $d = db();
        out(['ok' => true, 'stats' => [
            'users'     => (int)$d->query('SELECT COUNT(*) c FROM users')->fetch()['c'],
            'active'    => (int)$d->query("SELECT COUNT(*) c FROM users WHERE status='active'")->fetch()['c'],
            'staff'     => (int)$d->query("SELECT COUNT(*) c FROM users WHERE role IN ('admin','mod','reviewer')")->fetch()['c'],
            'messages'  => (int)$d->query('SELECT COUNT(*) c FROM messages')->fetch()['c'],
            'unread'    => (int)$d->query('SELECT COUNT(*) c FROM messages WHERE is_read=0')->fetch()['c'],
            'tickets'   => (int)$d->query("SELECT COUNT(*) c FROM support_tickets WHERE status='open'")->fetch()['c'],
            'chats'     => (int)$d->query('SELECT COUNT(*) c FROM chat_logs')->fetch()['c'],
            'today'     => (int)$d->query('SELECT COUNT(*) c FROM chat_logs WHERE DATE(created_at)=CURDATE()')->fetch()['c'],
            'new7'      => (int)$d->query('SELECT COUNT(*) c FROM users WHERE created_at >= (NOW() - INTERVAL 7 DAY)')->fetch()['c'],
            /* زمن الاستجابة — آخر ٧ أيام، مسار الرد الفوري المتدفق */
            'avgTtfb'   => (int)$d->query("SELECT ROUND(AVG(ttfb_ms)) v FROM chat_logs
                                            WHERE stream=1 AND ttfb_ms IS NOT NULL
                                              AND created_at >= (NOW() - INTERVAL 7 DAY)")->fetch()['v'],
            'avgTotal'  => (int)$d->query("SELECT ROUND(AVG(total_ms)) v FROM chat_logs
                                            WHERE total_ms IS NOT NULL
                                              AND created_at >= (NOW() - INTERVAL 7 DAY)")->fetch()['v'],
            'streamPct' => (int)$d->query("SELECT ROUND(100*AVG(stream)) v FROM chat_logs
                                            WHERE created_at >= (NOW() - INTERVAL 7 DAY)")->fetch()['v'],
            /* تشخيص المحادثة: صحة البث وحال قاعدة المعرفة (مصادر الإجابات) */
            'stream'    => chatStreamStats(7),
            'kb'        => ragKbStats(),
        ]]);
    }

    /* ==================== الدعوات ==================== */

    case 'invite': {
        /* من يدعو من: invitableRoles() في db.php، والخادم هو الحكم. الداعي يكتب الاسم الكامل
           بالعربية والجوال والبريد، والمدعو يكمل اسمه بالإنجليزية وتاريخ ميلاده وكلمة المرور. */
        if (invitableRoles($STAFF) === []) fail('ليست لديك صلاحية لهذا الإجراء.', 403);
        rateLimit('invite', 10);
        $name  = cleanFullNameAr(clean($in['name'] ?? '', 120));
        $phone = normPhone(clean($in['phone'] ?? '', 20));
        $email = mb_strtolower(clean($in['email'] ?? '', 120));
        $eff   = (string)($in['role'] ?? 'user');
        if ($eff === 'admin') $eff = 'sysadmin';
        if (!in_array($eff, ALL_ROLES, true)) fail('دور غير معروف.');
        if (!canInviteRole($STAFF, $eff))
            fail($STAFF['role'] === 'mod' ? 'دعوة أعضاء الفريق من صلاحيات مدير النظام فقط.'
                                          : 'لا يحق لك دعوة أحد بهذا الدور.', 403);
        [$role, $org] = roleColumns($eff);
        $roleCol = $org === null ? $role : 'user';   // عمود role_target قديم: user وreviewer وmod فقط
        if (!validFullNameAr($name))                    fail('اكتب اسم المدعو الكامل بالحروف العربية، ثلاثة أسماء على الأقل.');
        if (!validPhone($phone))                        fail('اكتب جوال المدعو بالصيغة 05XXXXXXXX.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('اكتب بريداً إلكترونياً صحيحاً.');
        $s = db()->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
        $s->execute([$email]);
        if ($s->fetch()) fail('هذا البريد مسجّل في المنصة أصلاً.');
        $s = db()->prepare('SELECT id FROM users WHERE phone=? LIMIT 1');
        $s->execute([$phone]);
        if ($s->fetch()) fail('هذا الجوال مسجّل في المنصة لحساب آخر.');
        $s = db()->prepare("SELECT email FROM invites WHERE phone=? AND email<>? AND status='sent' AND expires_at > NOW() LIMIT 1");
        $s->execute([$phone, $email]);
        if ($s->fetch()) fail('هذا الجوال في دعوة أخرى بانتظار القبول. احذفها أولاً أو استخدم جوالاً آخر.');

        $token   = bin2hex(random_bytes(24));
        $expires = time() + (int)INVITE_TTL_DAYS * 86400;
        $s = db()->prepare('SELECT id FROM invites WHERE email=? LIMIT 1');
        $s->execute([$email]);
        if ($inv = $s->fetch()) {
            db()->prepare("UPDATE invites SET name=?, phone=?, role_target=?, org_role_target=?, token=?, status='sent', created_at=NOW(),
                           expires_at=FROM_UNIXTIME(?), accepted_at=NULL, invited_by=? WHERE id=?")
                ->execute([$name, $phone, $roleCol, $org, $token, $expires, $STAFF['id'], $inv['id']]);
        } else {
            db()->prepare("INSERT INTO invites (name, phone, email, role_target, org_role_target, token, invited_by, status, created_at, expires_at)
                           VALUES (?,?,?,?,?,?,?,'sent',NOW(),FROM_UNIXTIME(?))")
                ->execute([$name, $phone, $email, $roleCol, $org, $token, $STAFF['id'], $expires]);
        }
        $link   = CHAT_URL . '/?invite=' . $token;
        $mailed = sendMail($email, inviteSubject($eff), inviteEmailHtml($STAFF['name'], $eff, $link, $name, $expires));
        audit($STAFF, 'invite', $email, $name . '، ' . roleName($eff) . ($mailed ? '' : ' (تعذّر إرسال البريد)'));
        out(['ok' => true, 'mailed' => $mailed, 'link' => $link, 'expires' => $expires * 1000]);
    }

    case 'invites': {
        if (invitableRoles($STAFF) === []) fail('ليست لديك صلاحية لهذا الإجراء.', 403);
        /* مدير الموارد وعلاقات العملاء يريان دعواتهما وحدها، والمشرف ومدير النظام كلها */
        $own  = in_array(effectiveRole($STAFF), ['hr', 'crm'], true);
        $st   = db()->prepare('SELECT id, name, phone, email, role_target, org_role_target, status, invited_by,
                                      created_at, expires_at, accepted_at
                               FROM invites ' . ($own ? 'WHERE invited_by=? ' : '') . 'ORDER BY created_at DESC LIMIT 100');
        $st->execute($own ? [(int)$STAFF['id']] : []);
        out(['ok' => true, 'ttl_days' => (int)INVITE_TTL_DAYS, 'invites' => array_map(function ($i) use ($STAFF) {
            $state = inviteState($i);
            return [
                'id' => (int)$i['id'], 'email' => $i['email'], 'name' => $i['name'], 'phone' => $i['phone'],
                'role' => ($i['org_role_target'] ?: $i['role_target']), 'status' => $state,
                't' => strtotime($i['created_at']) * 1000, 'expires' => inviteExpiresTs($i) * 1000,
                'manage' => $state !== 'accepted' && canManageInvite($STAFF, $i),
            ];
        }, $st->fetchAll())]);
    }

    case 'resend_invite':
    case 'delete_invite': {
        /* إعادة الإرسال: رابط جديد بصلاحية كاملة من اليوم، ويتوقف الرابط السابق.
           الحذف: يُحذف السجل فيتوقف رابطه فوراً. الدعوة المقبولة سجلٌّ لحساب قائم فلا تُمس. */
        $s = db()->prepare('SELECT * FROM invites WHERE id=? LIMIT 1');
        $s->execute([(int)($in['id'] ?? 0)]);
        $inv = $s->fetch();
        if (!$inv) fail('الدعوة غير موجودة، ولعلها حُذفت.');
        if (!canManageInvite($STAFF, $inv)) fail('ليست لديك صلاحية على هذه الدعوة.', 403);
        if ($inv['status'] === 'accepted') fail('قُبلت هذه الدعوة وأصبح لصاحبها حساب، فلا تُعدَّل.');
        $eff = ($inv['org_role_target'] ?? '') !== '' ? $inv['org_role_target'] : $inv['role_target'];

        if ($act === 'delete_invite') {
            db()->prepare('DELETE FROM invites WHERE id=?')->execute([(int)$inv['id']]);
            audit($STAFF, 'invite_deleted', $inv['email'], (string)($inv['name'] ?? ''));
            out(['ok' => true]);
        }

        rateLimit('invite', 10);
        $s = db()->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
        $s->execute([$inv['email']]);
        if ($s->fetch()) fail('هذا البريد مسجّل في المنصة الآن، فلا حاجة للدعوة. احذفها.');
        $token   = bin2hex(random_bytes(24));
        $expires = time() + (int)INVITE_TTL_DAYS * 86400;
        db()->prepare("UPDATE invites SET token=?, status='sent', created_at=NOW(), expires_at=FROM_UNIXTIME(?) WHERE id=?")
            ->execute([$token, $expires, (int)$inv['id']]);
        $link   = CHAT_URL . '/?invite=' . $token;
        $mailed = sendMail($inv['email'], inviteSubject($eff),
            inviteEmailHtml($STAFF['name'], $eff, $link, (string)($inv['name'] ?? ''), $expires));
        audit($STAFF, 'invite_resent', $inv['email'], roleName($eff) . ($mailed ? '' : ' (تعذّر إرسال البريد)'));
        out(['ok' => true, 'mailed' => $mailed, 'expires' => $expires * 1000]);
    }

    case 'revoke_invite': {
        if ($STAFF['role'] !== 'admin' && !in_array(effectiveRole($STAFF), ['hr', 'crm'], true))
            fail('ليست لديك صلاحية لهذا الإجراء.', 403);
        $email = mb_strtolower(clean($in['email'] ?? '', 120));
        $mine  = $STAFF['role'] === 'admin' ? '' : ' AND invited_by=' . (int)$STAFF['id'];
        db()->prepare("UPDATE invites SET status='revoked' WHERE email=? AND status='sent'" . $mine)->execute([$email]);
        audit($STAFF, 'invite_revoked', $email, '');
        out(['ok' => true]);
    }

    /* ==================== الحسابات التجريبية ====================
       حسابات حقيقية في قاعدة البيانات، بكلمات مرور تُولَّد عشوائياً وتُعرض
       مرة واحدة، ومعلَّمة بـ is_demo لتُحذف كلها بأمان. لا يُزرع شيء تلقائياً
       ولا في متصفح أحد — الإنشاء بطلب صريح من مدير النظام فقط. */

    case 'demo_status': {
        needRole($STAFF, ['admin']);
        require_once __DIR__ . '/demo.php';
        out(['ok' => true, 'count' => demoCount(), 'total' => count(demoAccounts())]);
    }

    case 'demo_seed': {
        needRole($STAFF, ['admin']);
        rateLimit('demo', 6);
        require_once __DIR__ . '/demo.php';
        try {
            $accounts = seedDemoAccounts($STAFF);
        } catch (Throwable $e) {
            fail(APP_DEBUG ? $e->getMessage() : 'تعذّر إنشاء الحسابات التجريبية. حاول مرة أخرى.', 500);
        }
        out(['ok' => true, 'accounts' => $accounts,
             'message' => 'تُعرض كلمات المرور هذه المرة فقط، فانسخها الآن. وإذا فقدتها فأعد الإنشاء لتتولد كلمات جديدة.']);
    }

    case 'demo_purge': {
        needRole($STAFF, ['admin']);
        require_once __DIR__ . '/demo.php';
        try {
            $n = purgeDemoAccounts($STAFF);
        } catch (Throwable $e) {
            fail(APP_DEBUG ? $e->getMessage() : 'تعذّر حذف الحسابات التجريبية.', 500);
        }
        out(['ok' => true, 'deleted' => $n,
             'message' => $n ? 'حُذفت الحسابات التجريبية وبيانات الأمثلة.' : 'لا توجد حسابات تجريبية.']);
    }

    /* ==================== سجل العمليات ==================== */

    case 'audit': {
        needRole($STAFF, ['admin']);
        $rows = db()->query('SELECT actor_name, action, target, detail, ip, created_at
                             FROM audit_log ORDER BY created_at DESC LIMIT 300')->fetchAll();
        out(['ok' => true, 'audit' => array_map(fn($a) => [
            'by' => $a['actor_name'] ?: 'النظام', 'act' => $a['action'],
            'target' => $a['target'] ?: '', 'detail' => $a['detail'] ?: '',
            'ip' => $a['ip'] ?: '', 't' => strtotime($a['created_at']) * 1000,
        ], $rows)]);
    }

    default: fail('طلب غير معروف.');
}
