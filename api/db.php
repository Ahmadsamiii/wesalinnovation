<?php
require_once __DIR__ . '/config.php';

/* قيم افتراضية للثوابت — حتى يظل ملف config.php القائم على الخادم يعمل بلا
   تعديل بعد أي ترقية تضيف إعداداً جديداً. الشرح الكامل في config.example.php. */
foreach ([
    'APP_DEBUG'              => false,
    'GUEST_LIMIT'            => 5,
    'USER_TOKENS'            => 30,
    'RENEW_HOURS'            => 6,
    'CHAT_DAILY_IP_LIMIT'    => 120,
    'CHAT_DAILY_TOTAL_LIMIT' => 0,
    'TRUST_PROXY'            => false,
    'GEMINI_FALLBACKS'       => '',
    'OPENAI_KEY'             => '',
    'OPENAI_MODEL'           => 'gpt-4o-mini',
] as $k => $v) { if (!defined($k)) define($k, $v); }

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

/** هل يوجد عمود بهذا الاسم في هذا الجدول؟ */
function colExists(string $table, string $col): bool {
    $s = db()->prepare("SELECT COUNT(*) c FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
    $s->execute([$table, $col]);
    return (int)($s->fetch()['c'] ?? 0) > 0;
}

/** نوع عمود ENUM كما هو مخزّن، لمعرفة القيم المسموحة فيه حالياً */
function colType(string $table, string $col): string {
    $s = db()->prepare("SELECT COLUMN_TYPE t FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
    $s->execute([$table, $col]);
    return (string)($s->fetch()['t'] ?? '');
}

/**
 * ترقية تلقائية — تُبقي قاعدة بيانات قائمة متوافقة مع schema.sql.
 * لا تُستخدم مفاتيح أجنبية هنا (بعكس schema.sql) لأن جدولاً قديماً بترميز
 * أو محرّك مختلف قد يرفضها، فيفشل الإنشاء بصمت ونفقد الجدول كله.
 */
function ensureSchema(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        if (!colExists('users', 'avatar')) {
            db()->exec("ALTER TABLE users
                        ADD COLUMN avatar     VARCHAR(160) NULL,
                        ADD COLUMN city       VARCHAR(60)  NULL,
                        ADD COLUMN age_range  VARCHAR(20)  NULL,
                        ADD COLUMN disability VARCHAR(60)  NULL,
                        ADD COLUMN interests  VARCHAR(300) NULL,
                        ADD COLUMN bio        VARCHAR(500) NULL");
        }
        if (!colExists('users', 'name_en')) {
            db()->exec("ALTER TABLE users ADD COLUMN name_en VARCHAR(80) NULL, ADD COLUMN dob DATE NULL");
        }
        if (!colExists('users', 'is_demo')) {
            db()->exec("ALTER TABLE users ADD COLUMN is_demo TINYINT(1) NOT NULL DEFAULT 0,
                        ADD INDEX ix_demo (is_demo)");
        }
        if (!colExists('users', 'status')) {
            db()->exec("ALTER TABLE users
                        ADD COLUMN status         ENUM('active','suspended') NOT NULL DEFAULT 'active',
                        ADD COLUMN must_change_pw TINYINT(1) NOT NULL DEFAULT 0,
                        ADD COLUMN improve        TINYINT(1) NOT NULL DEFAULT 0");
        }
        // دور مراجع المحتوى — كان معرّفاً في الواجهة فقط ولا يمكن إسناده فعلياً
        $rt = colType('users', 'role');
        if ($rt !== '' && strpos($rt, 'reviewer') === false) {
            db()->exec("ALTER TABLE users MODIFY COLUMN role
                        ENUM('user','reviewer','mod','admin') NOT NULL DEFAULT 'user'");
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

        db()->exec("CREATE TABLE IF NOT EXISTS invites (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(120) NOT NULL,
            role_target ENUM('user','reviewer','mod') NOT NULL DEFAULT 'user',
            token VARCHAR(64) NOT NULL,
            invited_by INT NOT NULL,
            status ENUM('sent','accepted','revoked') NOT NULL DEFAULT 'sent',
            created_at DATETIME NOT NULL,
            accepted_at DATETIME NULL,
            UNIQUE KEY uq_email (email), INDEX (token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $it = colType('invites', 'role_target');
        if ($it !== '' && strpos($it, 'reviewer') === false) {
            db()->exec("ALTER TABLE invites MODIFY COLUMN role_target
                        ENUM('user','reviewer','mod') NOT NULL DEFAULT 'user'");
        }
        $ist = colType('invites', 'status');
        if ($ist !== '' && strpos($ist, 'revoked') === false) {
            db()->exec("ALTER TABLE invites MODIFY COLUMN status
                        ENUM('sent','accepted','revoked') NOT NULL DEFAULT 'sent'");
        }

        db()->exec("CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token_hash CHAR(64) NOT NULL,
            issued_by INT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            INDEX (token_hash), INDEX (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        db()->exec("CREATE TABLE IF NOT EXISTS site_content (
            ckey VARCHAR(80) NOT NULL PRIMARY KEY,
            cval TEXT NOT NULL,
            updated_by INT NULL,
            updated_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        db()->exec("CREATE TABLE IF NOT EXISTS audit_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            actor_id INT NULL,
            actor_name VARCHAR(80) NULL,
            action VARCHAR(40) NOT NULL,
            target VARCHAR(160) NULL,
            detail VARCHAR(400) NULL,
            ip VARCHAR(45) NULL,
            created_at DATETIME NOT NULL,
            INDEX (created_at), INDEX (actor_id), INDEX (action)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        ensureRateTable();
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
    $s = db()->prepare('SELECT id,name,name_en,dob,email,phone,pref,role,status,must_change_pw,improve,
                               is_demo,tokens,tokens_at,created_at,questions,
                               avatar,city,age_range,disability,interests,bio
                        FROM users WHERE id=? LIMIT 1');
    $s->execute([$_SESSION['uid']]);
    $u = $s->fetch();
    if (!$u) return null;
    // حساب أوقفه مدير النظام: تُنهى جلسته فوراً في أول طلب بعد الإيقاف
    if (($u['status'] ?? 'active') === 'suspended') { $_SESSION = []; return null; }
    return $u;
}

/* ---------- الأدوار ---------- */
const ROLES = ['user', 'reviewer', 'mod', 'admin'];
function isAdmin(?array $u): bool { return $u && $u['role'] === 'admin'; }

/** تسجيل عملية إدارية في سجل الخادم — السجل الوحيد الذي يُعتد به */
function audit(?array $actor, string $action, string $target = '', string $detail = ''): void {
    try {
        db()->prepare('INSERT INTO audit_log (actor_id,actor_name,action,target,detail,ip,created_at)
                       VALUES (?,?,?,?,?,?,NOW())')
            ->execute([$actor['id'] ?? null, $actor['name'] ?? null, $action,
                       mb_substr($target, 0, 160), mb_substr($detail, 0, 400), clientIp()]);
    } catch (Throwable $e) { /* السجل لا يوقف العملية */ }
}

/* ---------- محتوى الصفحات ---------- */
/** كل المحتوى المحرَّر كخريطة مفتاح ← نص */
function contentMap(): array {
    ensureSchema();
    $out = [];
    try {
        foreach (db()->query('SELECT ckey, cval FROM site_content')->fetchAll() as $r) {
            $out[$r['ckey']] = $r['cval'];
        }
    } catch (Throwable $e) { /* المحتوى الأصلي في الصفحة هو البديل */ }
    return $out;
}

/** أي عضو في الفريق: مدير نظام أو مشرف أو مراجع محتوى */
function requireStaff(): array {
    $u = currentUser();
    if (!$u || !in_array($u['role'], ['admin','mod','reviewer'], true))
        fail('غير مصرّح لك بالوصول لهذه البيانات.', 403);
    return $u;
}

/** اسم الدور بالعربية — مصدر واحد تستخدمه الرسائل والسجل والبريد */
function roleName(string $r): string {
    return ['admin' => 'مدير النظام', 'mod' => 'مشرف',
            'reviewer' => 'مراجع محتوى', 'user' => 'مستفيد'][$r] ?? 'مستفيد';
}

/** إرسال بريد HTML من عنوان المنصة */
function sendMail(string $to, string $subject, string $html): bool {
    $fname = '=?UTF-8?B?' . base64_encode(MAIL_FROM_NAME) . '?=';
    $headers = "From: $fname <" . MAIL_FROM . ">\r\nReply-To: " . MAIL_FROM . "\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $html, $headers, '-f' . MAIL_FROM);
}

/** قالب بريد الدعوة */
function inviteEmailHtml(string $inviter, string $roleTarget, string $link): string {
    $isTeam  = $roleTarget !== 'user';
    $roleTxt = $isTeam ? 'للانضمام لفريق وصال بصفة ' . roleName($roleTarget) : 'لتجربة منصة وصال';
    $btnTxt  = $isTeam ? 'قبول الدعوة وإنشاء حسابي' : 'تجربة وصال الآن';
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

/** قالب بريد إعادة تعيين كلمة المرور */
function resetEmailHtml(string $name, string $link, bool $byAdmin, int $hours): string {
    $n = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $lead = $byAdmin
        ? 'أعاد مدير النظام في وصال تعيين كلمة مرورك. اضغط الزر لاختيار كلمة مرور جديدة.'
        : 'وصلنا طلب لإعادة تعيين كلمة مرور حسابك في وصال. اضغط الزر لاختيار كلمة مرور جديدة.';
    return '<div dir="rtl" style="font-family:Tahoma,Arial,sans-serif;background:#f4f2fb;padding:32px 16px">'
        . '<div style="max-width:520px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;border:1px solid #e4ddf0">'
        . '<div style="background:linear-gradient(135deg,#814fc3,#282692);padding:26px;text-align:center;color:#fff;font-size:22px;font-weight:bold">وصــال</div>'
        . '<div style="padding:28px 26px;color:#3d3558;line-height:1.9;font-size:15px">'
        . 'أهلاً <b>' . $n . '</b>،<br>' . $lead
        . '<div style="text-align:center;margin:26px 0"><a href="' . $link . '" style="background:linear-gradient(135deg,#814fc3,#5039a8);color:#fff;text-decoration:none;padding:14px 34px;border-radius:99px;font-weight:bold;display:inline-block">تعيين كلمة مرور جديدة</a></div>'
        . '<div style="font-size:13px;color:#8a7fa3">الرابط صالح لمدة ' . $hours . ' ساعة، ويُستخدم مرة واحدة فقط.<br>'
        . 'إذا ما طلبت هذا، تجاهل الرسالة — كلمة مرورك ما تغيّرت.</div>'
        . '<div style="font-size:12px;color:#8a7fa3;margin-top:14px">لو الزر ما اشتغل انسخ الرابط:<br><span dir="ltr" style="word-break:break-all">' . $link . '</span></div>'
        . '</div></div></div>';
}

/** إنشاء رمز إعادة تعيين — يعيد الرمز الخام (يُرسل) ويخزّن هاشه فقط */
function issueResetToken(int $userId, ?int $issuedBy = null, int $hours = 2): string {
    ensureSchema();
    $token = bin2hex(random_bytes(32));
    // إبطال أي رموز سابقة لم تُستخدم بعد
    db()->prepare('UPDATE password_resets SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')
        ->execute([$userId]);
    db()->prepare('INSERT INTO password_resets (user_id,token_hash,issued_by,expires_at,created_at)
                   VALUES (?,?,?,DATE_ADD(NOW(), INTERVAL ? HOUR),NOW())')
        ->execute([$userId, hash('sha256', $token), $issuedBy, $hours]);
    return $token;
}

/** قواعد كلمة المرور — مكان واحد يستخدمه التسجيل وإعادة التعيين والتغيير */
function passwordError(string $p): ?string {
    if (mb_strlen($p) < 8) return 'كلمة المرور لازم تكون 8 أحرف فأكثر.';
    if (!preg_match('/\p{L}/u', $p) || !preg_match('/\d/', $p))
        return 'كلمة المرور لازم تحتوي حرفاً ورقماً على الأقل.';
    return null;
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

/* ==========================================================================
 *  حدود الاستخدام
 *
 *  العدّادات مخزّنة في قاعدة البيانات ومفتاحها عنوان IP — لا الجلسة.
 *  العدّاد المخزّن في الجلسة يتجاوزه حذف الكوكي، فيعود الزائر بحصة كاملة
 *  في كل طلب، وهو ما يحوّل نقطة المحادثة إلى بوابة مفتوحة على مزوّد مدفوع.
 * ========================================================================== */

/** جدول العدّادات — يُنشأ عند أول استخدام فقط، لا مع كل طلب */
function ensureRateTable(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    db()->exec("CREATE TABLE IF NOT EXISTS rate_limits (
        bucket       VARCHAR(40)  NOT NULL,
        ident        VARCHAR(45)  NOT NULL,
        window_start DATETIME     NOT NULL,
        hits         INT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (bucket, ident, window_start),
        KEY ix_window (window_start)
    ) ENGINE=InnoDB DEFAULT CHARSET=ascii COLLATE=ascii_general_ci");
}

/**
 * عنوان الزائر. ترويسات الوكيل العكسي قابلة للانتحال من أي متصفح، فلا تُقرأ
 * إلا إذا فُعّل TRUST_PROXY صراحةً لموقع خلف Cloudflare أو موازن حِمل.
 */
function clientIp(): string {
    if (TRUST_PROXY) {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR'] as $h) {
            if (empty($_SERVER[$h])) continue;
            $cand = trim(explode(',', (string)$_SERVER[$h])[0]);
            if (filter_var($cand, FILTER_VALIDATE_IP)) return $cand;
        }
    }
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

/** بداية نافذة الدقيقة الحالية */
function windowMinute(): string { return date('Y-m-d H:i:00'); }
/** بداية نافذة اليوم الحالي */
function windowDay(): string { return date('Y-m-d 00:00:00'); }

/**
 * يزيد عدّاداً ويعيد مجموعه بعد الزيادة.
 * $ident عنوان IP عادةً، و '*' يعني عدّاداً عاماً للمنصة كلها.
 */
function hitCounter(string $bucket, string $ident, string $windowStart, int $by = 1): int {
    try {
        return hitCounterRun($bucket, $ident, $windowStart, $by);
    } catch (PDOException $e) {
        // الجدول غير موجود بعد (تركيب جديد أو ترقية) — أنشئه ثم أعد المحاولة مرة واحدة
        ensureRateTable();
        return hitCounterRun($bucket, $ident, $windowStart, $by);
    }
}

function hitCounterRun(string $bucket, string $ident, string $windowStart, int $by): int {
    $d = $ident !== '' ? $ident : '0.0.0.0';
    db()->prepare('INSERT INTO rate_limits (bucket, ident, window_start, hits) VALUES (?,?,?,?)
                   ON DUPLICATE KEY UPDATE hits = hits + ?')
        ->execute([$bucket, $d, $windowStart, $by, $by]);
    $s = db()->prepare('SELECT hits FROM rate_limits WHERE bucket=? AND ident=? AND window_start=? LIMIT 1');
    $s->execute([$bucket, $d, $windowStart]);
    $row = $s->fetch();
    pruneRateLimits();
    return (int)($row['hits'] ?? $by);
}

/** تنظيف الصفوف المنتهية — احتمالياً حتى لا يُثقل كل طلب */
function pruneRateLimits(): void {
    static $done = false;
    if ($done) return;
    try {
        if (random_int(1, 200) !== 1) return;
        $done = true;
        db()->exec('DELETE FROM rate_limits WHERE window_start < (NOW() - INTERVAL 2 DAY)');
    } catch (Throwable $e) { /* غير حرج */ }
}

/** حد الطلبات في الدقيقة لكل عنوان IP */
function rateLimit(string $bucket, int $max = 20): void {
    if ($max <= 0) return;
    if (hitCounter('m:' . $bucket, clientIp(), windowMinute()) > $max)
        fail('طلبات كثيرة في وقت قصير. انتظر دقيقة وحاول مرة أخرى.', 429);
}

function publicUser(array $u): array {
    return [
        'id' => (int)$u['id'], 'name' => $u['name'], 'email' => $u['email'],
        'phone' => $u['phone'], 'pref' => $u['pref'], 'role' => $u['role'],
        'status' => $u['status'] ?? 'active',
        'must_change_pw' => (int)($u['must_change_pw'] ?? 0) === 1,
        'improve' => (int)($u['improve'] ?? 0) === 1,
        'is_demo' => (int)($u['is_demo'] ?? 0) === 1,
        'tokens' => (int)$u['tokens'], 'last' => strtotime($u['tokens_at']) * 1000,
        'created' => strtotime($u['created_at']) * 1000, 'qs' => (int)$u['questions'],
        'name_en' => $u['name_en'] ?? '', 'dob' => $u['dob'] ?? '',
        'avatar' => $u['avatar'] ?? '', 'city' => $u['city'] ?? '', 'age_range' => $u['age_range'] ?? '',
        'disability' => $u['disability'] ?? '', 'interests' => $u['interests'] ?? '', 'bio' => $u['bio'] ?? '',
    ];
}
