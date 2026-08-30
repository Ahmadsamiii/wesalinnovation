<?php
require_once __DIR__ . '/db.php';
$STAFF = requireStaff();

$in = body();
switch ($in['action'] ?? '') {

    case 'users': {
        $rows = db()->query('SELECT id,name,email,phone,pref,role,tokens,questions,created_at,last_login
                             FROM users ORDER BY created_at DESC LIMIT 500')->fetchAll();
        $out = array_map(fn($u) => [
            'id' => (int)$u['id'], 'name' => $u['name'], 'email' => $u['email'],
            'phone' => $u['phone'], 'role' => $u['role'], 'qs' => (int)$u['questions'],
            'created' => strtotime($u['created_at']) * 1000,
        ], $rows);
        out(['ok' => true, 'users' => $out]);
    }

    case 'messages': {
        $rows = db()->query('SELECT id,name,email,subject,message,is_read,created_at
                             FROM messages ORDER BY created_at DESC LIMIT 300')->fetchAll();
        $out = array_map(fn($m) => [
            'id' => (int)$m['id'], 'name' => $m['name'], 'email' => $m['email'],
            'subject' => $m['subject'], 'message' => $m['message'],
            'read' => (bool)$m['is_read'], 't' => strtotime($m['created_at']) * 1000,
        ], $rows);
        out(['ok' => true, 'messages' => $out]);
    }

    case 'read': {
        $id = (int)($in['id'] ?? 0);
        db()->prepare('UPDATE messages SET is_read=1 WHERE id=?')->execute([$id]);
        out(['ok' => true]);
    }

    case 'tickets': {
        $rows = db()->query('SELECT t.id,t.type,t.details,t.status,t.created_at,u.name uname,u.email uemail
                             FROM support_tickets t JOIN users u ON u.id=t.user_id
                             ORDER BY t.created_at DESC LIMIT 300')->fetchAll();
        $out = array_map(fn($t) => ['id'=>(int)$t['id'],'type'=>$t['type'],'details'=>$t['details'],
            'status'=>$t['status'],'uname'=>$t['uname'],'uemail'=>$t['uemail'],
            't'=>strtotime($t['created_at'])*1000], $rows);
        out(['ok' => true, 'tickets' => $out]);
    }

    case 'ticket_done': {
        $id = (int)($in['id'] ?? 0);
        db()->prepare("UPDATE support_tickets SET status='done' WHERE id=?")->execute([$id]);
        out(['ok' => true]);
    }

    case 'stats': {
        $d = db();
        out(['ok' => true, 'stats' => [
            'users'    => (int)$d->query('SELECT COUNT(*) c FROM users')->fetch()['c'],
            'messages' => (int)$d->query('SELECT COUNT(*) c FROM messages')->fetch()['c'],
            'chats'    => (int)$d->query('SELECT COUNT(*) c FROM chat_logs')->fetch()['c'],
            'today'    => (int)$d->query('SELECT COUNT(*) c FROM chat_logs WHERE DATE(created_at)=CURDATE()')->fetch()['c'],
        ]]);
    }

    case 'set_role': {
        if ($STAFF['role'] !== 'admin') fail('هذه الصلاحية للمشرف العام فقط.', 403);
        $id   = (int)($in['user_id'] ?? 0);
        $role = ($in['role'] ?? '') === 'mod' ? 'mod' : 'user';
        $s = db()->prepare('SELECT id,role FROM users WHERE id=? LIMIT 1');
        $s->execute([$id]);
        $t = $s->fetch();
        if (!$t)                       fail('المستخدم غير موجود.');
        if ((int)$t['id'] === (int)$STAFF['id']) fail('ما تقدر تعدّل صلاحيتك بنفسك.');
        if ($t['role'] === 'admin')    fail('ما يمكن تعديل صلاحية المشرف العام.');
        db()->prepare('UPDATE users SET role=? WHERE id=?')->execute([$role, $id]);
        out(['ok' => true, 'role' => $role]);
    }

    case 'invite': {
        rateLimit('invite', 10);
        $email = mb_strtolower(clean($in['email'] ?? '', 120));
        $role  = ($in['role'] ?? 'user') === 'mod' ? 'mod' : 'user';
        if ($role === 'mod' && $STAFF['role'] !== 'admin') fail('دعوة المشرفين صلاحية للمشرف العام فقط.', 403);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('اكتب بريداً إلكترونياً صحيحاً.');
        $s = db()->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
        $s->execute([$email]);
        if ($s->fetch()) fail('هذا البريد مسجّل في المنصة أصلاً.');
        $token = bin2hex(random_bytes(24));
        $s = db()->prepare('SELECT id FROM invites WHERE email=? LIMIT 1');
        $s->execute([$email]);
        if ($inv = $s->fetch()) {
            db()->prepare("UPDATE invites SET role_target=?, token=?, status='sent', created_at=NOW(), accepted_at=NULL, invited_by=? WHERE id=?")
                ->execute([$role, $token, $STAFF['id'], $inv['id']]);
        } else {
            db()->prepare('INSERT INTO invites (email, role_target, token, invited_by, status, created_at) VALUES (?,?,?,?,\'sent\',NOW())')
                ->execute([$email, $role, $token, $STAFF['id']]);
        }
        $link = SITE_URL . '/?invite=' . $token;
        $mailed = sendMail($email, $role === 'mod' ? 'دعوة للانضمام كمشرف في وصال' : 'دعوة لتجربة منصة وصال',
                           inviteEmailHtml($STAFF['name'], $role, $link));
        out(['ok' => true, 'mailed' => $mailed]);
    }

    case 'invites': {
        $rows = db()->query('SELECT email, role_target, status, created_at, accepted_at FROM invites ORDER BY created_at DESC LIMIT 100')->fetchAll();
        out(['ok' => true, 'invites' => array_map(fn($i) => [
            'email' => $i['email'], 'role' => $i['role_target'], 'status' => $i['status'],
            't' => strtotime($i['created_at']) * 1000,
        ], $rows)]);
    }

    default: fail('طلب غير معروف.');
}
