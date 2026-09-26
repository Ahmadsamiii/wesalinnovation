<?php
/* ==========================================================================
 *  وصال — فحص ذاتي للخروج التلقائي (مهلة الخمول والحد الأقصى للجلسة)
 *
 *  الاستخدام:
 *      php tools/check-session.php
 *
 *  يفحص منطق الخادم في api/db.php بجلسة طرفية معزولة: المدد لكل دور، وهامش
 *  الخمول، وأن الاستطلاع الآلي لا يجدّد الجلسة، والحد الأقصى، واكتشاف الجلسة
 *  المفقودة، ثم يتحقق أن الواجهة وسياسة الخصوصية تطابقان الإعدادات. لا يكتب
 *  في قاعدة البيانات شيئاً (يستخدم معرّف حساب غير موجود)، فهو آمن على الإنتاج.
 *  سلوك المتصفح نفسه (التنبيه والتبويبات) يفحصه tools/check-session-ui.js.
 * ========================================================================== */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("هذا السكربت يُشغَّل من الطرفية فقط.\n");
}

$cfg = __DIR__ . '/../api/config.php';
if (!file_exists($cfg)) {
    exit("لم أجد api/config.php — انسخ api/config.example.php إليه واملأ بياناته أولاً.\n");
}

require_once __DIR__ . '/../api/db.php';

$fails = 0;
function check(string $label, bool $ok): void {
    global $fails;
    if (!$ok) $fails++;
    echo ($ok ? '  ✓ ' : '  ✗ ') . $label . "\n";
}

/** جلسة حساب بدأت قبل $authAgo ثانية وآخر نشاط فيها قبل $seenAgo ثانية */
function fakeSession(string $role, int $seenAgo, int $authAgo = 0): void {
    $_SESSION = ['uid' => PHP_INT_MAX, 'role' => $role,   // حساب غير موجود: لا سطر في سجل العمليات
                 'seen_at' => time() - $seenAgo, 'auth_at' => time() - max($authAgo, $seenAgo)];
}
function request(array $headers = []): string {
    unset($_SERVER['HTTP_X_WESAL_IDLE'], $_SERVER['HTTP_X_WESAL_USER']);
    foreach ($headers as $k => $v) $_SERVER[$k] = $v;
    return enforceSessionTimeouts();
}

$user  = sessionLimits('user');
$staff = sessionLimits('admin');
$grace = IDLE_GRACE_SEC;

echo "فحص الخروج التلقائي\n\n";

/* ---------- الإعدادات ---------- */
check('المدد موجبة', IDLE_MINUTES_USER > 0 && IDLE_MINUTES_STAFF > 0 && SESSION_MAX_HOURS_USER > 0 && SESSION_MAX_HOURS_STAFF > 0);
check('مهلة فريق المنصة لا تزيد عن مهلة المستفيد', IDLE_MINUTES_STAFF <= IDLE_MINUTES_USER);
check('هامش الخادم لا يقل عن فترة الاستطلاع (دقيقة)، فلا يسبق تنبيهَ الواجهة', IDLE_GRACE_SEC >= 60);
check('المشرف ومراجع المحتوى بمهلة الفريق', sessionLimits('mod') === $staff && sessionLimits('reviewer') === $staff);
check('الدور المجهول يأخذ مهلة المستفيد', sessionLimits('') === $user);
check('الواجهة تتلقى المهلة من الخادم (idle_min)',
      publicUser(['id' => 1, 'name' => '', 'email' => '', 'phone' => '', 'pref' => '', 'role' => 'mod', 'tokens' => 0,
                  'tokens_at' => 'now', 'created_at' => 'now', 'questions' => 0])['idle_min'] === IDLE_MINUTES_STAFF);

/* ---------- مهلة الخمول ---------- */
fakeSession('user', $user['idle'] + $grace - 5);
check('داخل الهامش: الجلسة باقية', request() === '' && !empty($_SESSION['uid']));
check('وطلب من المستخدم يجدّد آخر نشاط', time() - $_SESSION['seen_at'] <= 1);

fakeSession('user', 600);
request(['HTTP_X_WESAL_IDLE' => '1']);
check('الاستطلاع الآلي بلا حركة لا يجدّد آخر نشاط', time() - $_SESSION['seen_at'] >= 599);

fakeSession('user', $user['idle'] + $grace + 5);
$old = session_id();
check('بعد المهلة والهامش: تنتهي الجلسة (idle)', request(['HTTP_X_WESAL_IDLE' => '1']) === 'idle' && empty($_SESSION['uid']));
check('ومعرّف الجلسة تغيّر', session_id() !== $old);

fakeSession('admin', $staff['idle'] + $grace + 5);
check('فريق المنصة ينتهي على مهلته الأقصر', request() === 'idle');
fakeSession('user', $staff['idle'] + $grace + 5);
check('والمستفيد لا ينتهي على مهلة الفريق', request() === '');

/* ---------- الحد الأقصى ---------- */
fakeSession('user', 30, $user['max'] + 5);
check('بلغت الجلسة حدها الأقصى رغم النشاط (max)', request() === 'max' && empty($_SESSION['uid']));
fakeSession('reviewer', 30, $staff['max'] + 5);
check('وحد الفريق الأقصى أقصر', request() === 'max');

/* ---------- جلسات قديمة ومفقودة ---------- */
$_SESSION = ['uid' => PHP_INT_MAX, 'role' => 'user'];
check('جلسة فُتحت قبل الميزة: يبدأ عدّها الآن ولا تنتهي', request() === '' && isset($_SESSION['seen_at'], $_SESSION['auth_at']));
$_SESSION = [];
check('صفحة تعرض حساباً بلا جلسة: gone', request(['HTTP_X_WESAL_USER' => '7']) === 'gone');
check('وزائر عادي لا يُمَسّ', request() === '');

session_destroy();

/* ---------- الواجهة وسياسة الخصوصية ---------- */
$html = (string)@file_get_contents(__DIR__ . '/../index.html');
check('نافذة التنبيه موجودة بدور alertdialog', str_contains($html, 'id="idleWarn" role="alertdialog" aria-modal="true"'));
check('الاستطلاع الآلي يرسل X-Wesal-Idle', str_contains($html, "'X-Wesal-Idle':'1'"));
check('لا خانة «أبقني مسجّلاً» في صفحة الدخول', !str_contains($html, 'liRemember'));
check('سياسة الخصوصية تذكر المدتين كما في الإعدادات',
      str_contains($html, 'إذا مرّت ' . IDLE_MINUTES_USER . ' دقيقة دون أي نشاط منك')
      && str_contains($html, 'لحسابات فريق المنصة ' . IDLE_MINUTES_STAFF . ' دقيقة'));

echo "\n" . ($fails ? "✗ فشل $fails فحصاً\n" : "✓ كل الفحوص ناجحة\n");
exit($fails ? 1 : 0);
