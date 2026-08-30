<?php
require_once __DIR__ . '/config.php';

if (!APP_DEBUG) { ini_set('display_errors', '0'); error_reporting(0); }

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

session_set_cookie_params([
    'lifetime' => 60 * 60 * 24 * 30,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
]);
session_start();

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
             PDO::ATTR_EMULATE_PREPARES => false]
        );
    }
    return $pdo;
}

/** ترقية تلقائية — تضيف أعمدة الملف الشخصي مرة واحدة فقط */
function ensureSchema(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $c = db()->query("SELECT COUNT(*) c FROM information_schema.COLUMNS
                          WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='avatar'")->fetch();
        if ((int)$c['c'] === 0) {
            db()->exec("ALTER TABLE users
                        ADD COLUMN avatar     VARCHAR(160) NULL,
                        ADD COLUMN city       VARCHAR(60)  NULL,
                        ADD COLUMN age_range  VARCHAR(20)  NULL,
                        ADD COLUMN disability VARCHAR(60)  NULL,
                        ADD COLUMN interests  VARCHAR(300) NULL,
                        ADD COLUMN bio        VARCHAR(500) NULL");
        }
        $c2 = db()->query("SELECT COUNT(*) c FROM information_schema.COLUMNS
                           WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='name_en'")->fetch();
        if ((int)$c2['c'] === 0) {
            db()->exec("ALTER TABLE users ADD COLUMN name_en VARCHAR(80) NULL, ADD COLUMN dob DATE NULL");
        }
        db()->exec("CREATE TABLE IF NOT EXISTS support_tickets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            type VARCHAR(60) NOT NULL,
            details TEXT NOT NULL,
            status ENUM('open','done') NOT NULL DEFAULT 'open',
            created_at DATETIME NOT NULL,
            INDEX (user_id), INDEX (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $rt = db()->query("SELECT COLUMN_TYPE t FROM information_schema.COLUMNS
                           WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='role'")->fetch();
        if ($rt && strpos($rt['t'], 'mod') === false) {
            db()->exec("ALTER TABLE users MODIFY COLUMN role ENUM('user','mod','admin') NOT NULL DEFAULT 'user'");
        }
        db()->exec("CREATE TABLE IF NOT EXISTS invites (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(120) NOT NULL,
            role_target ENUM('user','mod') NOT NULL DEFAULT 'user',
            token VARCHAR(64) NOT NULL,
            invited_by INT NOT NULL,
            status ENUM('sent','accepted') NOT NULL DEFAULT 'sent',
            created_at DATETIME NOT NULL,
            accepted_at DATETIME NULL,
            UNIQUE KEY uq_email (email), INDEX (token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) { /* غير حرج */ }
}

function body(): array {
    $raw = file_get_contents('php://input');
    $j = json_decode($raw, true);
    return is_array($j) ? $j : [];
}

function out(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function fail(string $msg, int $code = 400): void { out(['ok' => false, 'error' => $msg], $code); }

function clean($v, int $max = 2000): string {
    return mb_substr(trim(strip_tags((string)$v)), 0, $max, 'UTF-8');
}

function currentUser(): ?array {
    if (empty($_SESSION['uid'])) return null;
    ensureSchema();
    $s = db()->prepare('SELECT id,name,name_en,dob,email,phone,pref,role,tokens,tokens_at,created_at,questions,avatar,city,age_range,disability,interests,bio FROM users WHERE id=? LIMIT 1');
    $s->execute([$_SESSION['uid']]);
    $u = $s->fetch();
    return $u ?: null;
}

/** مشرف عام أو مشرف */
function requireStaff(): array {
    $u = currentUser();
    if (!$u || !in_array($u['role'], ['admin','mod'], true)) fail('غير مصرّح لك بالوصول لهذه البيانات.', 403);
    return $u;
}

/** إرسال بريد HTML من عنوان المنصة */
function sendMail(string $to, string $subject, string $html): bool {
    $fname = '=?UTF-8?B?' . base64_encode(MAIL_FROM_NAME) . '?=';
    $headers = "From: $fname <" . MAIL_FROM . ">\r\nReply-To: " . MAIL_FROM . "\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $html, $headers, '-f' . MAIL_FROM);
}

/** قالب بريد الدعوة */
function inviteEmailHtml(string $inviter, string $roleTarget, string $link): string {
    $roleTxt = $roleTarget === 'mod' ? 'للانضمام كمشرف في منصة وصال' : 'لتجربة منصة وصال';
    $btnTxt  = $roleTarget === 'mod' ? 'قبول الدعوة وإنشاء حسابي' : 'تجربة وصال الآن';
    $i = htmlspecialchars($inviter, ENT_QUOTES, 'UTF-8');
    return '<div dir="rtl" style="font-family:Tahoma,Arial,sans-serif;background:#f4f2fb;padding:32px 16px">'
        . '<div style="max-width:520px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;border:1px solid #e4ddf0">'
        . '<div style="background:linear-gradient(135deg,#814fc3,#282692);padding:26px;text-align:center;color:#fff;font-size:22px;font-weight:bold">وصــال</div>'
        . '<div style="padding:28px 26px;color:#3d3558;line-height:1.9;font-size:15px">'
        . 'السلام عليكم،<br><b>' . $i . '</b> يدعوك ' . $roleTxt . ' — أول منصة ذكاء اصطناعي سعودية مصممة لخدمة الأشخاص ذوي الإعاقة.'
        . '<div style="text-align:center;margin:26px 0"><a href="' . $link . '" style="background:linear-gradient(135deg,#814fc3,#5039a8);color:#fff;text-decoration:none;padding:14px 34px;border-radius:99px;font-weight:bold;display:inline-block">' . $btnTxt . '</a></div>'
        . '<div style="font-size:12px;color:#8a7fa3">لو الزر ما اشتغل انسخ الرابط:<br><span dir="ltr" style="word-break:break-all">' . $link . '</span></div>'
        . '</div></div></div>';
}

function requireAdmin(): array {
    $u = currentUser();
    if (!$u || $u['role'] !== 'admin') fail('غير مصرّح لك بالوصول لهذه البيانات.', 403);
    return $u;
}

/** تجديد الرصيد كل RENEW_HOURS ساعات */
function refreshTokens(array $u): array {
    $last = strtotime($u['tokens_at'] ?: 'now');
    $span = RENEW_HOURS * 3600;
    if (time() - $last >= $span) {
        $cycles = (int) floor((time() - $last) / $span);
        $newAt  = date('Y-m-d H:i:s', $last + $cycles * $span);
        db()->prepare('UPDATE users SET tokens=?, tokens_at=? WHERE id=?')
            ->execute([USER_TOKENS, $newAt, $u['id']]);
        $u['tokens'] = USER_TOKENS;
        $u['tokens_at'] = $newAt;
    }
    return $u;
}

/** حد بسيط لمنع الإساءة: عدد الطلبات لكل IP في الدقيقة */
function rateLimit(string $bucket, int $max = 20): void {
    $k = 'rl_' . $bucket;
    $now = time();
    if (!isset($_SESSION[$k]) || $_SESSION[$k]['t'] < $now - 60) $_SESSION[$k] = ['t' => $now, 'n' => 0];
    if (++$_SESSION[$k]['n'] > $max) fail('طلبات كثيرة في وقت قصير. انتظر دقيقة وحاول مرة أخرى.', 429);
}

function publicUser(array $u): array {
    return [
        'id' => (int)$u['id'], 'name' => $u['name'], 'email' => $u['email'],
        'phone' => $u['phone'], 'pref' => $u['pref'], 'role' => $u['role'],
        'tokens' => (int)$u['tokens'], 'last' => strtotime($u['tokens_at']) * 1000,
        'created' => strtotime($u['created_at']) * 1000, 'qs' => (int)$u['questions'],
        'name_en' => $u['name_en'] ?? '', 'dob' => $u['dob'] ?? '',
        'avatar' => $u['avatar'] ?? '', 'city' => $u['city'] ?? '', 'age_range' => $u['age_range'] ?? '',
        'disability' => $u['disability'] ?? '', 'interests' => $u['interests'] ?? '', 'bio' => $u['bio'] ?? '',
    ];
}
