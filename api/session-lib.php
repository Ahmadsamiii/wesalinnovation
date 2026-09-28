<?php
/* ==========================================================================
 *  وصال: الجلسة الموحدة بين المنصة ومساحة العمل
 *
 *  جلسة واحدة لكل دخول، يقرؤها النظامان:
 *    - الكوكي wesal_auth: رمز عشوائي (64 خانة) صالح للنطاق الأعلى، فيصل إلى
 *      النطاق الرئيسي وchat. وworkspace. معاً. لا يحمل هوية ولا دوراً.
 *    - الجدول auth_sessions: صف لكل جلسة فيه هاش الرمز (sha256) لا الرمز نفسه،
 *      ووقت الدخول وآخر نشاط وحدّا الخمول والمدة القصوى وقت الإنشاء، وسبب
 *      الإنهاء. هذا الصف هو المرجع الوحيد للمهلتين: كل نظام يقرؤه ويجدّد آخر
 *      نشاط فيه، فالنشاط في أحدهما يبقي الاثنين، والخروج أو الإيقاف في أحدهما
 *      يُخرج الآخر عند طلبه التالي.
 *    - جلسة PHP ($_SESSION) تبقى كما كانت للتخزين المؤقت (أرصدة، تجربة موسّعة)،
 *      وتتبع الصف: يُنهى بنهايته، وتُستعاد منه على نطاق لم تُفتح فيه.
 *
 *  المفتاح UNIFIED_SESSION في config.php (الافتراضي false). مطفأً لا يُنشأ صف ولا
 *  كوكي ويعمل الكود القديم حرفياً. مشغّلاً، أي عطل في قاعدة البيانات يعيد الطلب
 *  إلى المسار القديم بدل أن يُخرج الجميع.
 *
 *  العقد مع مساحة العمل (workspace/app/Support/PlatformSession.php): اسم الكوكي
 *  والجدول وأعمدته، ومعنى ended_at وidle_sec وmax_sec، وهامش IDLE_GRACE_SEC.
 *  تغيير أي منها يستلزم تغييرهما معاً، ويفحصه tools/check-auth-session.php.
 * ========================================================================== */

const AUTH_COOKIE = 'wesal_auth';

/** هل الجلسة الموحدة مفعّلة؟ المتغير العام لتجارب tools/check-auth-session.php وحدها. */
function unifiedSession(): bool {
    if (array_key_exists('WESAL_UNIFIED_OVERRIDE', $GLOBALS)) return (bool) $GLOBALS['WESAL_UNIFIED_OVERRIDE'];
    return defined('UNIFIED_SESSION') && UNIFIED_SESSION;
}

/**
 * نطاق الكوكي: النطاق الأعلى بنقطة في أوله (.wesalinnovation.sa) فيصل إلى كل
 * نطاقاته الفرعية. مشتق من SITE_URL بحذف www وchat وworkspace من أوله، ويُضبط
 * صراحةً بـ AUTH_COOKIE_DOMAIN. الفارغ (localhost أو عنوان IP) كوكي للمضيف وحده.
 */
function authCookieDomain(): string {
    if (defined('AUTH_COOKIE_DOMAIN')) return (string) AUTH_COOKIE_DOMAIN;
    return authCookieDomainFor(SITE_URL);
}

function authCookieDomainFor(string $siteUrl): string {
    $host = strtolower((string) parse_url($siteUrl, PHP_URL_HOST));
    $host = (string) preg_replace('/^(www|chat|workspace)\./', '', $host);
    if ($host === '' || $host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP) || substr_count($host, '.') < 1) return '';
    return '.' . $host;
}

function authCookieOptions(int $expires): array {
    $opts = ['expires' => $expires, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
             'secure'  => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')];
    $d = authCookieDomain();
    if ($d !== '') $opts['domain'] = $d;
    return $opts;
}

function authCookieSet(string $token): void {
    $_COOKIE[AUTH_COOKIE] = $token;
    if (!headers_sent()) setcookie(AUTH_COOKIE, $token, authCookieOptions(time() + 60 * 60 * 24 * 30));
}

function authCookieClear(): void {
    unset($_COOKIE[AUTH_COOKIE]);
    if (!headers_sent()) setcookie(AUTH_COOKIE, '', authCookieOptions(time() - 3600));
}

function authTokenHash(string $token): string { return hash('sha256', $token); }

/** جدول الجلسات. إضافي: لا يمس أي جدول قائم. أعمدته جزء من العقد مع مساحة العمل. */
function ensureAuthTable(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    db()->exec("CREATE TABLE IF NOT EXISTS auth_sessions (
        id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id    INT          NOT NULL,
        token_hash CHAR(64)     NOT NULL,                  -- sha256 للرمز في الكوكي
        auth_at    INT UNSIGNED NOT NULL,                  -- لحظة الدخول (unix)
        seen_at    INT UNSIGNED NOT NULL,                  -- آخر نشاط للمستخدم (unix)
        idle_sec   INT UNSIGNED NOT NULL,                  -- مهلة الخمول وقت الإنشاء
        max_sec    INT UNSIGNED NOT NULL,                  -- أقصى مدة للجلسة وقت الإنشاء
        ended_at   INT UNSIGNED NULL,                      -- NULL = حية
        ended_why  VARCHAR(16)  NULL,                      -- logout idle max suspended password role replaced deleted
        ip         VARCHAR(45)  NULL,
        ua         VARCHAR(160) NULL,
        UNIQUE KEY uq_token (token_hash),
        KEY ix_user (user_id, ended_at),
        KEY ix_ended (ended_at, seen_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/** الصف الذي يشير إليه الكوكي الحالي (حياً كان أو منتهياً)، أو null */
function authRowFromCookie(): ?array {
    $t = (string) ($_COOKIE[AUTH_COOKIE] ?? '');
    if (!preg_match('/^[a-f0-9]{64}$/', $t)) return null;
    $s = db()->prepare('SELECT * FROM auth_sessions WHERE token_hash=? LIMIT 1');
    $s->execute([authTokenHash($t)]);
    return $s->fetch() ?: null;
}

function authRowById(int $id): ?array {
    $s = db()->prepare('SELECT * FROM auth_sessions WHERE id=? LIMIT 1');
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

/** حالة الصف الآن: live أو idle أو max أو ended */
function authRowState(array $r, int $now): string {
    if ($r['ended_at'] !== null) return 'ended';
    if ($now - (int) $r['seen_at'] > (int) $r['idle_sec'] + IDLE_GRACE_SEC) return 'idle';
    if ($now - (int) $r['auth_at'] > (int) $r['max_sec']) return 'max';
    return 'live';
}

/** ينشئ صفاً للجلسة الحالية ويضع كوكيها. الحدّان من دور الحساب وقت الإنشاء ويُخزَّنان معه. */
function authSessionCreate(int $uid, string $role, bool $org, int $authAt, int $seenAt): void {
    ensureAuthTable();
    $lim   = sessionLimits($role, $org);
    $token = bin2hex(random_bytes(32));
    db()->prepare('INSERT INTO auth_sessions (user_id,token_hash,auth_at,seen_at,idle_sec,max_sec,ip,ua)
                   VALUES (?,?,?,?,?,?,?,?)')
        ->execute([$uid, authTokenHash($token), $authAt, $seenAt, $lim['idle'], $lim['max'],
                   clientIp(), mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 160)]);
    $_SESSION['auth_sid'] = (int) db()->lastInsertId();
    authCookieSet($token);
}

function authSessionEnd(int $id, string $why): void {
    db()->prepare('UPDATE auth_sessions SET ended_at=?, ended_why=? WHERE id=? AND ended_at IS NULL')
        ->execute([time(), mb_substr($why, 0, 16), $id]);
}

/**
 * يُنهي كل جلسات حساب (عدا جلسة واحدة اختيارياً). يُستدعى عند الإيقاف وتغيير
 * الدور وإعادة تعيين كلمة المرور والحذف. لا يتوقف على المفتاح: لو أُطفئ بعد
 * تشغيله فلا تبقى جلسة لحساب أُوقف. الجدول الغائب (لم يُشغَّل قط) ليس خطأ.
 */
function authSessionsRevoke(int $uid, string $why, int $exceptId = 0): void {
    try {
        db()->prepare('UPDATE auth_sessions SET ended_at=?, ended_why=? WHERE user_id=? AND ended_at IS NULL AND id<>?')
            ->execute([time(), mb_substr($why, 0, 16), $uid, $exceptId]);
    } catch (Throwable $e) {
        if (stripos($e->getMessage(), "doesn't exist") === false) error_log('WESAL_AUTH_REVOKE_FAIL: ' . $e->getMessage());
    }
}

/** خروج المستخدم بنفسه: يُنهي صف جلسته ويمسح الكوكي. آمن بلا مفتاح ولا جدول. */
function authSessionLogout(): void {
    try {
        if (!empty($_SESSION['auth_sid'])) authSessionEnd((int) $_SESSION['auth_sid'], 'logout');
        elseif ($r = authRowFromCookie()) authSessionEnd((int) $r['id'], 'logout');
    } catch (Throwable $e) { /* الخروج لا يتعطل بسبب الجدول */ }
    if (isset($_COOKIE[AUTH_COOKIE])) authCookieClear();
}

/**
 * المسار الموحد لـ enforceSessionTimeouts(). يعيد سبب إنهاء الجلسة في هذا الطلب
 * (idle أو max أو gone) أو '' إن بقيت. الصف المرجع، وجلسة PHP تابعة له:
 *   - لا جلسة PHP وللكوكي صف حي: تُستعاد منه (المتصفح جاء من نطاق لم تُفتح فيه)
 *   - جلسة PHP بلا صف (سبقت التشغيل أو ضاع الكوكي): يُنشأ لها صف بأوقاتها
 *   - الصف لحساب آخر: الصف هو المرجع
 *   - الصف انتهى (خروج أو إيقاف من مكان آخر): تنتهي جلسة PHP وتُمسح
 */
function enforceUnifiedSession(): string {
    $now = time();
    if (empty($_COOKIE[AUTH_COOKIE]) && empty($_SESSION['uid'])) {
        // زائر بلا جلسة: لا شيء يُقرأ من قاعدة البيانات. صفحة تعرض حساباً تُخبَر أن جلستها انتهت.
        if (empty($_SERVER['HTTP_X_WESAL_USER'])) return '';
        header('X-Session-Ended: gone');
        return 'gone';
    }
    ensureSchema();      // يضيف org_role إن لم يُضَف بعد، ويُنشئ جدول الجلسات
    ensureAuthTable();
    $row = authRowFromCookie();

    if ($row && !empty($_SESSION['uid']) && (int) $row['user_id'] !== (int) $_SESSION['uid']) {
        $_SESSION = [];
        session_regenerate_id(true);
    }

    if (empty($_SESSION['uid'])) {
        if ($row && $row['ended_at'] === null) {
            $s = db()->prepare('SELECT id, role, org_role, status FROM users WHERE id=? LIMIT 1');
            $s->execute([(int) $row['user_id']]);
            $u = $s->fetch();
            if ($u && ($u['status'] ?? 'active') !== 'suspended') {
                $_SESSION['uid']      = (int) $u['id'];
                $_SESSION['role']     = (string) $u['role'];
                $_SESSION['org']      = empty($u['org_role']) ? 0 : 1;
                $_SESSION['auth_sid'] = (int) $row['id'];
                $_SESSION['auth_at']  = (int) $row['auth_at'];
                $_SESSION['seen_at']  = (int) $row['seen_at'];
            } else {
                authSessionEnd((int) $row['id'], $u ? 'suspended' : 'deleted');
                $row = authRowById((int) $row['id']);
            }
        }
        if (empty($_SESSION['uid'])) {
            // لا جلسة هنا ولا صف حي. صفحة ما زالت تعرض حساباً تُخبَر أن جلستها انتهت.
            if ($row) authCookieClear();
            if (empty($_SERVER['HTTP_X_WESAL_USER'])) return '';
            header('X-Session-Ended: gone');
            return 'gone';
        }
    } elseif ($row === null || $row['ended_at'] !== null) {
        if ($row !== null || !empty($_SESSION['auth_sid'])) {
            // انتهت من مكان آخر (خروج أو إيقاف أو تغيير كلمة المرور): تنتهي هنا أيضاً. الكوكي
            // الغائب مع جلسة سبق أن ارتبطت بصف (auth_sid) هو خروج من مساحة العمل الذي يمسحه من
            // المتصفح، لا جلسة قديمة تُرحَّل: لو أُنشئ لها صف جديد لبقي الدخول بعد الخروج.
            $_SESSION = [];
            session_regenerate_id(true);
            if ($row !== null) authCookieClear();
            header('X-Session-Ended: gone');
            return 'gone';
        }
        // جلسة سبقت التشغيل ولم ترتبط بصف قط: تُنشأ لها بأوقاتها القائمة فلا يتغير عليها شيء
        $authAt = (int) ($_SESSION['auth_at'] ?? $now);
        $seenAt = (int) ($_SESSION['seen_at'] ?? $now);
        authSessionCreate((int) $_SESSION['uid'], (string) ($_SESSION['role'] ?? 'user'), !empty($_SESSION['org']), $authAt, $seenAt);
        $row = authRowById((int) $_SESSION['auth_sid']);
    }

    $state = authRowState($row, $now);
    if ($state === 'idle' || $state === 'max') {
        $_SESSION['auth_sid'] = (int) $row['id'];
        endAuthSession($state);
        header('X-Session-Ended: ' . $state);
        return $state;
    }

    // حية: آخر نشاط يتجدد بفعل المستخدم، لا باستطلاع الإشعارات الآلي
    $_SESSION['auth_sid'] = (int) $row['id'];
    $_SESSION['auth_at']  = (int) $row['auth_at'];
    if (($_SERVER['HTTP_X_WESAL_IDLE'] ?? '') !== '1') {
        db()->prepare('UPDATE auth_sessions SET seen_at=? WHERE id=? AND ended_at IS NULL AND seen_at<?')
            ->execute([$now, (int) $row['id'], $now]);
        $_SESSION['seen_at'] = $now;
    } else {
        $_SESSION['seen_at'] = (int) $row['seen_at'];
    }
    return '';
}
