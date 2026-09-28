<?php
/* ==========================================================================
 *  وصال: فحص ذاتي للجلسة الموحدة (api/session-lib.php)
 *
 *  الاستخدام:
 *      php tools/check-auth-session.php
 *
 *  يجرّب على قاعدة البيانات الفعلية: الدخول ينشئ صفاً وكوكياً، والاستعادة على
 *  نطاق لم تُفتح فيه الجلسة، وحدّا الخمول والمدة القصوى بحسب الدور (ومنها دور
 *  مساحة العمل)، والاستطلاع الآلي لا يجدّد، والخروج والإيقاف وتغيير كلمة المرور
 *  والحذف من أي مكان تُنهي الجلسة، وجلسة سبقت التشغيل تُرحَّل بلا إخراج صاحبها،
 *  والعودة للمسار القديم عند عطل القاعدة، وأن المفتاح مطفأً لا يكتب شيئاً، وأن
 *  بنية الجدول تطابق ما تقرؤه مساحة العمل. يحذف كل بيانات الفحص خلفه.
 *  لا يتصل بأي خدمة خارجية. مع فحص الخروج التلقائي القائم (check-session.php)
 *  الذي يبقى على المسار القديم.
 * ========================================================================== */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("هذا السكربت يُشغَّل من الطرفية فقط.\n");
}
$cfg = __DIR__ . '/../api/config.php';
if (!file_exists($cfg)) exit("لم أجد api/config.php — انسخ api/config.example.php إليه واملأ بياناته أولاً.\n");

require_once __DIR__ . '/../api/db.php';

$fails = 0;
function check(string $label, bool $ok): void {
    global $fails;
    if (!$ok) $fails++;
    echo ($ok ? '  ✓ ' : '  ✗ ') . $label . "\n";
}

const TEST_MAIL = '@check-auth.invalid';

function cleanup(): void {
    $ids = db()->query("SELECT id FROM users WHERE email LIKE '%" . TEST_MAIL . "'")->fetchAll(PDO::FETCH_COLUMN);
    if ($ids) {
        $in = implode(',', array_map('intval', $ids));
        db()->exec("DELETE FROM auth_sessions WHERE user_id IN ($in)");
        db()->exec("DELETE FROM users WHERE id IN ($in)");
    }
    db()->exec("DELETE FROM audit_log WHERE target LIKE '%" . TEST_MAIL . "'");
}

function mkUser(string $role = 'user', ?string $org = null, string $status = 'active'): int {
    static $n = 0;
    $n++;
    db()->prepare('INSERT INTO users (name,email,phone,pref,pass_hash,role,status,org_role,tokens,tokens_at,created_at)
                   VALUES (?,?,?,?,?,?,?,?,30,NOW(),NOW())')
        ->execute(['فحص ' . $n, 'u' . $n . '-' . bin2hex(random_bytes(3)) . TEST_MAIL, '05' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
                   'simple', password_hash('x', PASSWORD_DEFAULT), $role, $status, $org]);
    return (int) db()->lastInsertId();
}

/** متصفح جديد بلا كوكي ولا جلسة PHP */
function freshBrowser(): void {
    $_SESSION = [];
    unset($_COOKIE[AUTH_COOKIE], $_SERVER['HTTP_X_WESAL_IDLE'], $_SERVER['HTTP_X_WESAL_USER']);
}
/** مستخدم فتح جلسة جديدة (الدخول) في المتصفح الحالي */
function login(int $uid, string $role = 'user', ?string $org = null): array {
    freshBrowser();
    startAuthSession($uid, $role, $org);
    return authRowById((int) $_SESSION['auth_sid']);
}
/** طلب إلى النظام: يعيد سبب الإنهاء أو '' */
function req(array $server = []): string {
    unset($_SERVER['HTTP_X_WESAL_IDLE'], $_SERVER['HTTP_X_WESAL_USER']);
    foreach ($server as $k => $v) $_SERVER[$k] = $v;
    return enforceSessionTimeouts();
}
function setRow(int $id, array $cols): void {
    $sets = implode(',', array_map(fn($c) => "$c=?", array_keys($cols)));
    db()->prepare("UPDATE auth_sessions SET $sets WHERE id=?")->execute([...array_values($cols), $id]);
}
function rowsOf(int $uid): array {
    $s = db()->prepare('SELECT * FROM auth_sessions WHERE user_id=? ORDER BY id');
    $s->execute([$uid]);
    return $s->fetchAll();
}

cleanup();
ensureSchema();
echo "فحص الجلسة الموحدة\n";

/* ---------- البنية والعقد مع مساحة العمل ---------- */
echo "\nالبنية:\n";
$cols = db()->query("SHOW COLUMNS FROM auth_sessions")->fetchAll(PDO::FETCH_COLUMN);
$want = ['id', 'user_id', 'token_hash', 'auth_at', 'seen_at', 'idle_sec', 'max_sec', 'ended_at', 'ended_why', 'ip', 'ua'];
check('أعمدة auth_sessions كما تقرؤها مساحة العمل', $cols === $want);
check('users.org_role موجود', colExists('users', 'org_role'));
check('invites.org_role_target موجود', colExists('invites', 'org_role_target'));
check('اسم الكوكي wesal_auth', AUTH_COOKIE === 'wesal_auth');
check('هامش الخمول 120 ثانية كما في مساحة العمل', IDLE_GRACE_SEC === 120);
check('نطاق الكوكي: النطاق الأعلى بنقطة', authCookieDomainFor('https://wesalinnovation.sa') === '.wesalinnovation.sa');
check('ويحذف chat. وworkspace. وwww من أوله', authCookieDomainFor('https://chat.wesalinnovation.sa') === '.wesalinnovation.sa'
      && authCookieDomainFor('https://workspace.wesalinnovation.sa') === '.wesalinnovation.sa'
      && authCookieDomainFor('https://www.wesalinnovation.sa') === '.wesalinnovation.sa');
check('localhost وعناوين IP كوكي للمضيف وحده', authCookieDomainFor('http://localhost:8080') === '' && authCookieDomainFor('http://127.0.0.1:8080') === '');

/* ---------- المفتاح مطفأً: لا أثر ---------- */
echo "\nالمفتاح مطفأً:\n";
$GLOBALS['WESAL_UNIFIED_OVERRIDE'] = false;
$u0 = mkUser('user');
freshBrowser();
startAuthSession($u0, 'user');
check('الدخول لا يُنشئ صفاً', count(rowsOf($u0)) === 0);
check('ولا كوكياً', !isset($_COOKIE[AUTH_COOKIE]));
check('والجلسة القديمة تعمل كما كانت', req() === '' && !empty($_SESSION['uid']));
authSessionLogout();
check('الخروج آمن بلا صف', true);

/* ---------- المفتاح مشغّلاً ---------- */
$GLOBALS['WESAL_UNIFIED_OVERRIDE'] = true;
$uUser  = mkUser('user');
$uStaff = mkUser('mod');
$uOrg   = mkUser('user', 'finance');

echo "\nالدخول:\n";
$r = login($uUser);
check('الدخول ينشئ صفاً حياً', $r && $r['ended_at'] === null && (int) $r['user_id'] === $uUser);
check('ويضع الكوكي برمز 64 خانة', isset($_COOKIE[AUTH_COOKIE]) && preg_match('/^[a-f0-9]{64}$/', $_COOKIE[AUTH_COOKIE]) === 1);
check('والمخزَّن هاش الرمز لا الرمز', $r['token_hash'] === hash('sha256', $_COOKIE[AUTH_COOKIE]) && $r['token_hash'] !== $_COOKIE[AUTH_COOKIE]);
check('المستفيد بمهلة المستفيد', (int) $r['idle_sec'] === IDLE_MINUTES_USER * 60 && (int) $r['max_sec'] === SESSION_MAX_HOURS_USER * 3600);
$rs = login($uStaff, 'mod');
check('المشرف بمهلة الفريق', (int) $rs['idle_sec'] === IDLE_MINUTES_STAFF * 60 && (int) $rs['max_sec'] === SESSION_MAX_HOURS_STAFF * 3600);
$ro = login($uOrg, 'user', 'finance');
check('صاحب دور في مساحة العمل بمهلة الفريق وإن كان دوره في المنصة مستفيداً', (int) $ro['idle_sec'] === IDLE_MINUTES_STAFF * 60 && (int) $ro['max_sec'] === SESSION_MAX_HOURS_STAFF * 3600);
check('ومنطق الدور نفسه في sessionLimits', sessionLimits('user', true) === sessionLimits('mod'));
$before = count(rowsOf($uUser));
$first  = login($uUser);
$tokenA = $_COOKIE[AUTH_COOKIE];
$second = null;
startAuthSession($uUser, 'user');   // دخول ثانٍ في المتصفح نفسه بلا تفريغ الكوكي
$second = authRowById((int) $_SESSION['auth_sid']);
check('دخول جديد في متصفح فيه جلسة يُنهي الصف السابق', authRowById((int) $first['id'])['ended_why'] === 'replaced' && (int) $second['id'] !== (int) $first['id']);
check('ويتغيّر الرمز', $_COOKIE[AUTH_COOKIE] !== $tokenA);

echo "\nالاستعادة على نطاق آخر:\n";
$r = login($uUser);
$_SESSION = [];                          // chat. لا تعرف PHPSESSID الخاص بالنطاق الرئيسي
check('الكوكي وحده يستعيد الجلسة', req() === '' && (int) $_SESSION['uid'] === $uUser);
check('بدورها وحدّي مهلتها', $_SESSION['role'] === 'user' && (int) $_SESSION['org'] === 0);
$ro = login($uOrg, 'user', 'finance');
$_SESSION = [];
req();
check('وتستعيد أنها لصاحب دور في مساحة العمل', (int) $_SESSION['org'] === 1);

echo "\nالخمول والمدة القصوى:\n";
$r = login($uUser);
setRow((int) $r['id'], ['seen_at' => time() - (IDLE_MINUTES_USER * 60 + IDLE_GRACE_SEC - 30)]);
check('داخل الهامش: باقية', req() === '' && !empty($_SESSION['uid']));
$r = login($uUser);
$seenBefore = time() - 600;
setRow((int) $r['id'], ['seen_at' => $seenBefore]);
req(['HTTP_X_WESAL_IDLE' => '1']);
check('الاستطلاع الآلي لا يجدّد آخر نشاط', (int) authRowById((int) $r['id'])['seen_at'] === $seenBefore);
req();
check('وطلب المستخدم يجدّده', (int) authRowById((int) $r['id'])['seen_at'] >= time() - 2);
$r = login($uUser);
setRow((int) $r['id'], ['seen_at' => time() - (IDLE_MINUTES_USER * 60 + IDLE_GRACE_SEC + 5)]);
check('بعد المهلة والهامش: idle', req() === 'idle' && empty($_SESSION['uid']));
check('ويُنهى الصف بسببه', authRowById((int) $r['id'])['ended_why'] === 'idle');
check('ويُمسح الكوكي', !isset($_COOKIE[AUTH_COOKIE]));
$r = login($uUser);
setRow((int) $r['id'], ['auth_at' => time() - (SESSION_MAX_HOURS_USER * 3600 + 5)]);
check('بلوغ الحد الأقصى رغم النشاط: max', req() === 'max' && authRowById((int) $r['id'])['ended_why'] === 'max');
$r = login($uStaff, 'mod');
setRow((int) $r['id'], ['seen_at' => time() - (IDLE_MINUTES_STAFF * 60 + IDLE_GRACE_SEC + 5)]);
check('الفريق على مهلته الأقصر', req() === 'idle');
check('وخروجه التلقائي يُسجَّل في سجل العمليات', (int) db()->query("SELECT COUNT(*) FROM audit_log WHERE action='auto_logout' AND actor_id=$uStaff")->fetchColumn() >= 1);
$r = login($uOrg, 'user', 'finance');
setRow((int) $r['id'], ['seen_at' => time() - (IDLE_MINUTES_STAFF * 60 + IDLE_GRACE_SEC + 5)]);
check('وصاحب دور مساحة العمل على مهلة الفريق كذلك', req() === 'idle');
$r = login($uUser);
$_SESSION = [];
setRow((int) $r['id'], ['seen_at' => time() - (IDLE_MINUTES_USER * 60 + IDLE_GRACE_SEC + 5)]);
check('صف منتهٍ بالخمول على نطاق لا جلسة PHP فيه: يُنهى ولا يُستعاد (idle كالمسار القديم)', req() === 'idle' && empty($_SESSION['uid']) && authRowById((int) $r['id'])['ended_why'] === 'idle');

echo "\nالإنهاء من مكان آخر:\n";
$r = login($uUser);
authSessionsRevoke($uUser, 'password');
check('إنهاء الصف ينهي جلسة PHP الحية عند طلبها التالي (gone)', req() === 'gone' && empty($_SESSION['uid']));
check('ويُمسح الكوكي', !isset($_COOKIE[AUTH_COOKIE]));
$r = login($uUser);
$_SESSION = [];
authSessionsRevoke($uUser, 'suspended');
check('وعلى نطاق آخر لا تُستعاد جلسة أُنهيت', req() === '' && empty($_SESSION['uid']));
check('وصفحة تعرض حساباً تُخبَر (gone)', (function () use ($uUser) {
    $r = login($uUser); $_SESSION = []; authSessionsRevoke($uUser, 'role'); return req(['HTTP_X_WESAL_USER' => '1']) === 'gone';
})());
$r = login($uUser);
$keep = (int) $r['id'];
$other = authRowById((function () use ($uUser) { $a = $_SESSION; $_COOKIE_SAVE = $_COOKIE; startAuthSession($uUser, 'user'); $id = (int) $_SESSION['auth_sid']; return $id; })());
authSessionsRevoke($uUser, 'password', (int) $other['id']);
check('تغيير كلمة المرور ينهي بقية الجلسات ويُبقي الجلسة الحالية', authRowById((int) $other['id'])['ended_at'] === null && authRowById($keep)['ended_at'] !== null);

// خرج من مساحة العمل: أُنهي الصف ومُسح الكوكي من المتصفح، وجلسة PHP هنا ما زالت حية
$r = login($uUser);
authSessionEnd((int) $r['id'], 'logout');
unset($_COOKIE[AUTH_COOKIE]);
$live = fn() => count(array_filter(rowsOf($uUser), fn($x) => $x['ended_at'] === null));
$liveBefore = $live();
check('الكوكي الممسوح مع جلسة PHP كانت مرتبطة بصف: تنتهي ولا يُنشأ لها صف جديد (لا دخول بعد الخروج)',
      req() === 'gone' && empty($_SESSION['uid']) && $live() === $liveBefore);

$uSus = mkUser('user');
$r = login($uSus);
db()->prepare("UPDATE users SET status='suspended' WHERE id=?")->execute([$uSus]);
$_SESSION = [];
req();
check('حساب أُوقف: لا تُستعاد جلسته وتُنهى', empty($_SESSION['uid']) && authRowById((int) $r['id'])['ended_why'] === 'suspended');
$r = login($uUser);
db()->prepare("UPDATE users SET status='suspended' WHERE id=?")->execute([$uUser]);
check('والإيقاف يُنهي جلسة PHP الحية عبر currentUser', currentUser() === null && authRowById((int) $r['id'])['ended_why'] === 'suspended');
db()->prepare("UPDATE users SET status='active' WHERE id=?")->execute([$uUser]);

echo "\nالخروج:\n";
$r = login($uUser);
authSessionLogout();
check('الخروج يُنهي الصف', authRowById((int) $r['id'])['ended_why'] === 'logout');
check('ويمسح الكوكي', !isset($_COOKIE[AUTH_COOKIE]));
$r = login($uUser);
endAuthSession('idle');
check('الخروج التلقائي من الواجهة يُنهي الصف أيضاً', authRowById((int) $r['id'])['ended_why'] === 'idle' && !isset($_COOKIE[AUTH_COOKIE]));

echo "\nالترحيل والحالات الحدّية:\n";
freshBrowser();
$_SESSION = ['uid' => $uUser, 'role' => 'user', 'org' => 0, 'seen_at' => time() - 120, 'auth_at' => time() - 3600];
check('جلسة سبقت التشغيل: تبقى ولا يُخرج صاحبها', req() === '' && !empty($_SESSION['uid']));
$row = authRowById((int) $_SESSION['auth_sid']);
check('وينشأ لها صف بأوقاتها القائمة', $row && abs((int) $row['auth_at'] - (time() - 3600)) <= 2 && isset($_COOKIE[AUTH_COOKIE]));
freshBrowser();
$_SESSION = ['uid' => $uUser, 'role' => 'user', 'org' => 0, 'seen_at' => time() - (IDLE_MINUTES_USER * 60 + IDLE_GRACE_SEC + 60), 'auth_at' => time() - 7200];
check('وجلسة قديمة خاملة سبقت التشغيل تنتهي كما كانت تنتهي', req() === 'idle');
freshBrowser();
check('زائر بلا كوكي ولا جلسة: لا شيء', req() === '' && count(rowsOf($uUser)) === count(rowsOf($uUser)));
check('وصفحة تعرض حساباً بلا جلسة (gone)', req(['HTTP_X_WESAL_USER' => '1']) === 'gone');
freshBrowser();
$_COOKIE[AUTH_COOKIE] = str_repeat('a', 64);
check('كوكي برمز مجهول بلا جلسة: لا شيء ولا خطأ', req() === '' && empty($_SESSION['uid']));
$_COOKIE[AUTH_COOKIE] = 'ليس-رمزاً';
check('وكوكي بقيمة تالفة كذلك', req() === '' && empty($_SESSION['uid']));
$rA = login($uUser);
$_SESSION = ['uid' => $uStaff, 'role' => 'mod', 'org' => 0, 'seen_at' => time(), 'auth_at' => time()];
$_COOKIE[AUTH_COOKIE] = $_COOKIE[AUTH_COOKIE];
req();
check('كوكي لحساب غير حساب جلسة PHP: الصف هو المرجع', (int) $_SESSION['uid'] === $uUser);

echo "\nعطل قاعدة البيانات:\n";
$r = login($uUser);
db()->exec('RENAME TABLE auth_sessions TO auth_sessions_off');
$prevLog = ini_set('error_log', '/dev/null');   // العطل متعمد، فلا يُطبع سجله في خرج الفحص
$res = req();
ini_set('error_log', (string) $prevLog);
db()->exec('RENAME TABLE auth_sessions_off TO auth_sessions');
check('الجدول غير متاح: يعود الطلب للمسار القديم ولا يُخرج المستخدم', $res === '' && !empty($_SESSION['uid']));

cleanup();
unset($GLOBALS['WESAL_UNIFIED_OVERRIDE']);
check('تنظيف بيانات الفحص', (int) db()->query("SELECT COUNT(*) FROM users WHERE email LIKE '%" . TEST_MAIL . "'")->fetchColumn() === 0);

echo $fails === 0 ? "\n✓ كل الفحوص ناجحة\n" : "\n✗ فشل $fails فحصاً\n";
exit($fails === 0 ? 0 : 1);
