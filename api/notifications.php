<?php
/**
 * إشعارات المستخدم المسجَّل — عامة لأي حدث يخصّه، لا تذاكر الدعم وحدها.
 * كل إجراء ينشئ إشعاراً عبر notify() في db.php؛ هذا الملف يقرأها ويُعلِّمها
 * مقروءة فقط، ولا يُنشئ إشعارات بنفسه.
 */
require_once __DIR__ . '/db.php';
ensureSchema();

$in     = body();
$action = $in['action'] ?? '';
$u      = currentUser();
if (!$u) fail('سجّل دخولك أولاً.', 401);

switch ($action) {

    case 'list': {
        $before = (int)($in['before_id'] ?? 0);
        $sql = 'SELECT id,type,title,body,link,read_at,created_at FROM notifications WHERE user_id=?';
        $args = [$u['id']];
        if ($before > 0) { $sql .= ' AND id<?'; $args[] = $before; }
        $sql .= ' ORDER BY id DESC LIMIT 30';
        $s = db()->prepare($sql);
        $s->execute($args);
        out(['ok' => true, 'notifications' => array_map(fn($n) => [
            'id' => (int)$n['id'], 'type' => $n['type'], 'title' => $n['title'], 'body' => $n['body'],
            'link' => $n['link'], 'read' => $n['read_at'] !== null, 't' => strtotime($n['created_at']) * 1000,
        ], $s->fetchAll())]);
    }

    case 'unread_count': {
        $s = db()->prepare('SELECT COUNT(*) c FROM notifications WHERE user_id=? AND read_at IS NULL');
        $s->execute([$u['id']]);
        out(['ok' => true, 'count' => (int)$s->fetch()['c']]);
    }

    case 'mark_read': {
        $id = (int)($in['id'] ?? 0);
        db()->prepare('UPDATE notifications SET read_at=NOW() WHERE id=? AND user_id=? AND read_at IS NULL')
            ->execute([$id, $u['id']]);
        out(['ok' => true]);
    }

    case 'mark_all_read': {
        db()->prepare('UPDATE notifications SET read_at=NOW() WHERE user_id=? AND read_at IS NULL')->execute([$u['id']]);
        out(['ok' => true]);
    }

    default:
        fail('طلب غير معروف.', 404);
}
