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

$STAFF = requireStaff();
$in    = body();
$act   = $in['action'] ?? '';

/** بعض الأقسام لا يراها مراجع المحتوى */
function needRole(array $staff, array $roles): void {
    if (!in_array($staff['role'], $roles, true))
        fail('ما عندك صلاحية لهذا الإجراء.', 403);
}

/** يجلب المستخدم الهدف مع التحقق من أن الإجراء عليه مسموح */
function targetUser(array $staff, $id, string $what): array {
    $id = (int)$id;
    $s = db()->prepare('SELECT id,name,email,role,status,tokens FROM users WHERE id=? LIMIT 1');
    $s->execute([$id]);
    $t = $s->fetch();
    if (!$t) fail('المستخدم غير موجود.');
    if ((int)$t['id'] === (int)$staff['id'])
        fail('ما تقدر ' . $what . ' حسابك بنفسك — اطلب من مدير نظام آخر.');
    return $t;
}

/** يمنع إفراغ المنصة من مديري النظام */
function guardLastAdmin(array $target): void {
    if ($target['role'] !== 'admin') return;
    $n = (int) db()->query("SELECT COUNT(*) c FROM users WHERE role='admin' AND status='active'")->fetch()['c'];
    if ($n <= 1) fail('هذا آخر مدير نظام نشط في المنصة — عيّن مديراً آخر قبل تغيير صلاحيته أو إيقافه.');
}

switch ($act) {

    /* ==================== المستخدمون ==================== */

    case 'users': {
        needRole($STAFF, ['admin', 'mod']);
        $q    = clean($in['q'] ?? '', 80);
        $sql  = 'SELECT id,name,name_en,email,phone,pref,role,status,tokens,questions,created_at,last_login
                 FROM users';
        $args = [];
        if ($q !== '') {
            $sql .= ' WHERE name LIKE ? OR email LIKE ? OR phone LIKE ?';
            $args = ["%$q%", "%$q%", "%$q%"];
        }
        $sql .= ' ORDER BY created_at DESC LIMIT 500';
        $st = db()->prepare($sql);
        $st->execute($args);
        out(['ok' => true, 'users' => array_map(fn($u) => [
            'id'      => (int)$u['id'],
            'name'    => $u['name'],
            'name_en' => $u['name_en'] ?? '',
            'email'   => $u['email'],
            'phone'   => $u['phone'],
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
        $role = (string)($in['role'] ?? '');
        if (!in_array($role, ROLES, true)) fail('دور غير معروف.');
        if ($role === 'admin' && $STAFF['role'] !== 'admin') fail('ترقية مدير نظام صلاحية لمدير النظام.', 403);
        $t = targetUser($STAFF, $in['user_id'] ?? 0, 'تعدّل صلاحية');
        if ($t['role'] === $role) out(['ok' => true, 'role' => $role]);
        if ($t['role'] === 'admin') guardLastAdmin($t);
        db()->prepare('UPDATE users SET role=? WHERE id=?')->execute([$role, $t['id']]);
        audit($STAFF, 'role', $t['email'], $t['role'] . ' ← ' . $role);
        out(['ok' => true, 'role' => $role]);
    }

    case 'set_status': {
        needRole($STAFF, ['admin']);
        $status = ($in['status'] ?? '') === 'suspended' ? 'suspended' : 'active';
        $t = targetUser($STAFF, $in['user_id'] ?? 0, 'توقف');
        if ($status === 'suspended') guardLastAdmin($t);
        db()->prepare('UPDATE users SET status=? WHERE id=?')->execute([$status, $t['id']]);
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
            audit($STAFF, 'reset_pw', $t['email'], 'كلمة مرور مؤقتة');
            out(['ok' => true, 'mode' => 'temp', 'temp_password' => $temp,
                 'message' => 'سلّم كلمة المرور المؤقتة للمستخدم عبر قناة موثوقة. سيُطالَب بتغييرها عند أول دخول.']);
        }

        $token = issueResetToken((int)$t['id'], (int)$STAFF['id']);
        $link  = SITE_URL . '/?reset=' . $token;
        $mailed = sendMail($t['email'], 'إعادة تعيين كلمة المرور في وصال',
                           resetEmailHtml($t['name'], $link, true, 2));
        audit($STAFF, 'reset_pw', $t['email'], $mailed ? 'رابط أُرسل بالبريد' : 'رابط (تعذّر إرسال البريد)');
        out(['ok' => true, 'mode' => 'link', 'link' => $link, 'mailed' => $mailed,
             'message' => $mailed
                ? 'أُرسل رابط إعادة التعيين لبريد المستخدم. الرابط صالح ساعتين.'
                : 'تعذّر إرسال البريد من الخادم — انسخ الرابط وسلّمه للمستخدم. صالح ساعتين.']);
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
        $rows = db()->query('SELECT t.id,t.type,t.details,t.status,t.created_at,u.name uname,u.email uemail
                             FROM support_tickets t JOIN users u ON u.id=t.user_id
                             ORDER BY t.created_at DESC LIMIT 300')->fetchAll();
        out(['ok' => true, 'tickets' => array_map(fn($t) => [
            'id' => (int)$t['id'], 'type' => $t['type'], 'details' => $t['details'],
            'status' => $t['status'], 'uname' => $t['uname'], 'uemail' => $t['uemail'],
            't' => strtotime($t['created_at']) * 1000,
        ], $rows)]);
    }

    case 'ticket_done': {
        $id     = (int)($in['id'] ?? 0);
        $status = ($in['status'] ?? 'done') === 'open' ? 'open' : 'done';
        db()->prepare('UPDATE support_tickets SET status=? WHERE id=?')->execute([$status, $id]);
        audit($STAFF, 'ticket', '#' . $id, $status === 'done' ? 'أُغلقت' : 'أُعيد فتحها');
        out(['ok' => true, 'status' => $status]);
    }

    /* ==================== الإحصاءات ==================== */

    case 'stats': {
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
        ]]);
    }

    /* ==================== الدعوات ==================== */

    case 'invite': {
        needRole($STAFF, ['admin', 'mod']);
        rateLimit('invite', 10);
        $email = mb_strtolower(clean($in['email'] ?? '', 120));
        $role  = in_array($in['role'] ?? 'user', ['user', 'reviewer', 'mod'], true) ? $in['role'] : 'user';
        if ($role !== 'user' && $STAFF['role'] !== 'admin')
            fail('دعوة أعضاء الفريق صلاحية لمدير النظام.', 403);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('اكتب بريداً إلكترونياً صحيحاً.');
        $s = db()->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
        $s->execute([$email]);
        if ($s->fetch()) fail('هذا البريد مسجّل في المنصة أصلاً.');

        $token = bin2hex(random_bytes(24));
        $s = db()->prepare('SELECT id FROM invites WHERE email=? LIMIT 1');
        $s->execute([$email]);
        if ($inv = $s->fetch()) {
            db()->prepare("UPDATE invites SET role_target=?, token=?, status='sent', created_at=NOW(),
                           accepted_at=NULL, invited_by=? WHERE id=?")
                ->execute([$role, $token, $STAFF['id'], $inv['id']]);
        } else {
            db()->prepare("INSERT INTO invites (email, role_target, token, invited_by, status, created_at)
                           VALUES (?,?,?,?,'sent',NOW())")
                ->execute([$email, $role, $token, $STAFF['id']]);
        }
        $link   = SITE_URL . '/?invite=' . $token;
        $mailed = sendMail($email,
            $role === 'user' ? 'دعوة لتجربة منصة وصال' : 'دعوة للانضمام لفريق وصال',
            inviteEmailHtml($STAFF['name'], $role, $link));
        audit($STAFF, 'invite', $email, roleName($role) . ($mailed ? '' : ' — تعذّر إرسال البريد'));
        out(['ok' => true, 'mailed' => $mailed, 'link' => $link]);
    }

    case 'invites': {
        needRole($STAFF, ['admin', 'mod']);
        $rows = db()->query('SELECT email, role_target, status, created_at, accepted_at
                             FROM invites ORDER BY created_at DESC LIMIT 100')->fetchAll();
        out(['ok' => true, 'invites' => array_map(fn($i) => [
            'email' => $i['email'], 'role' => $i['role_target'], 'status' => $i['status'],
            't' => strtotime($i['created_at']) * 1000,
        ], $rows)]);
    }

    case 'revoke_invite': {
        needRole($STAFF, ['admin']);
        $email = mb_strtolower(clean($in['email'] ?? '', 120));
        db()->prepare("UPDATE invites SET status='revoked' WHERE email=? AND status='sent'")->execute([$email]);
        audit($STAFF, 'invite_revoked', $email, '');
        out(['ok' => true]);
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
