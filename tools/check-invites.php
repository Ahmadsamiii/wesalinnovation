<?php
/* ==========================================================================
 *  وصال: فحص الدعوات والأدوار عبر خادم حقيقي
 *
 *  الاستخدام (على نسخة محلية بقاعدة تجريبية، لا على الإنتاج):
 *      php tools/check-invites.php
 *
 *  يشغّل خادم PHP المدمج على منفذ محلي ويجرّب بحسابات حقيقية تنشئها الأداة:
 *    - من يدعو من: مدير النظام الاثني عشر دوراً، والموارد البشرية الموظفون دون
 *      المناصب العليا، وعلاقات العملاء العميل، والمشرف المستفيد، وغيرهم مرفوض (403)
 *    - ما يُخزَّن في الدعوة، وأن المدعو يسجّل بنفسه فيأخذ دوره الفعلي (ومنه مدير
 *      النظام: admin في المنصة) ويدخل بجلسة
 *    - قوائم المستخدمين والدعوات بحسب من يسأل، وأن الموارد وعلاقات العملاء لا يبلغان
 *      إحصاءات المنصة ولا إجراءات الإدارة الأخرى
 *    - تغيير الدور: مدير النظام وحده، إلى أي من الاثني عشر، ويعود بلا فقد
 *  يحذف كل بيانات الفحص خلفه، لكنه يكتب في القاعدة ويصفّر عدّاد حدّ الدعوات
 *  (الحد الطبيعي 10 في الدقيقة)، فلا يُشغَّل على قاعدة إنتاج.
 * ========================================================================== */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("هذا السكربت يُشغَّل من الطرفية فقط.\n");
}
$cfg = __DIR__ . '/../api/config.php';
if (!file_exists($cfg)) exit("لم أجد api/config.php — انسخ api/config.example.php إليه واملأ بياناته أولاً.\n");
if (!function_exists('curl_init')) exit("يحتاج إضافة curl في PHP.\n");

require_once __DIR__ . '/../api/db.php';
ensureSchema();

$fails = 0;
function check(string $label, bool $ok, string $extra = ''): void {
    global $fails;
    if (!$ok) $fails++;
    echo ($ok ? '  ✓ ' : '  ✗ ') . $label . ($ok || $extra === '' ? '' : "\n      $extra") . "\n";
}

const MAIL = '@check-invites.invalid';
const PASS = 'Passw0rd1';

function cleanup(): void {
    $ids = db()->query("SELECT id FROM users WHERE email LIKE '%" . MAIL . "'")->fetchAll(PDO::FETCH_COLUMN);
    if ($ids) {
        $in = implode(',', array_map('intval', $ids));
        db()->exec("DELETE FROM auth_sessions WHERE user_id IN ($in)");
        db()->exec("DELETE FROM users WHERE id IN ($in)");
    }
    db()->exec("DELETE FROM invites WHERE email LIKE '%" . MAIL . "'");
    db()->exec("DELETE FROM audit_log WHERE target LIKE '%" . MAIL . "'");
}
function resetInviteLimit(): void { db()->exec("DELETE FROM rate_limits WHERE bucket IN ('m:invite','m:login','m:reg')"); }

function mkUser(string $eff, string $name): int {
    static $n = 0;
    $n++;
    [$role, $org] = roleColumns($eff);
    db()->prepare('INSERT INTO users (name,email,phone,pref,pass_hash,role,org_role,tokens,tokens_at,created_at)
                   VALUES (?,?,?,?,?,?,?,30,NOW(),NOW())')
        ->execute([$name, strtolower($eff) . $n . MAIL, '05' . str_pad((string) (10000000 + $n * 7919), 8, '0', STR_PAD_LEFT),
                   'simple', password_hash(PASS, PASSWORD_DEFAULT), $role, $org]);
    return (int) db()->lastInsertId();
}

/* ---------- خادم مؤقت وعميل HTTP بجرّة كوكي لكل شخص ---------- */
$port = 20000 + random_int(0, 15000);
$root = realpath(__DIR__ . '/..');
$proc = proc_open(['php', '-S', "127.0.0.1:$port", '-t', $root], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
register_shutdown_function(function () use ($proc) { if (is_resource($proc)) { proc_terminate($proc); } });
for ($i = 0; $i < 50; $i++) {
    $c = @fsockopen('127.0.0.1', $port, $e, $s, 0.2);
    if ($c) { fclose($c); break; }
    usleep(100000);
}

function call(string $jar, string $endpoint, array $body): array {
    global $port;
    $ch = curl_init("http://127.0.0.1:$port/api/$endpoint");
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar]);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $j = json_decode((string) $raw, true);
    return ['code' => $code, 'json' => is_array($j) ? $j : [], 'raw' => (string) $raw];
}

cleanup();
resetInviteLimit();
echo "فحص الدعوات والأدوار عبر خادم حقيقي\n";

/* ---------- الأشخاص ---------- */
$people = ['sysadmin' => 'مدير', 'mod' => 'مشرف', 'reviewer' => 'مراجع', 'hr' => 'موارد', 'crm' => 'علاقات',
           'executive' => 'تنفيذي', 'team_member' => 'عضو', 'user' => 'مستفيد'];
$jars = [];
foreach ($people as $eff => $name) {
    mkUser($eff, $name);
    $jars[$eff] = tempnam(sys_get_temp_dir(), 'jar');
    $email = db()->query("SELECT email FROM users WHERE email LIKE '" . strtolower($eff) . "%" . MAIL . "' ORDER BY id DESC LIMIT 1")->fetchColumn();
    $r = call($jars[$eff], 'auth.php', ['action' => 'login', 'email' => $email, 'password' => PASS]);
    if (!($r['json']['ok'] ?? false)) { echo "  ✗ تعذّر دخول $eff: " . substr($r['raw'], 0, 120) . "\n"; $fails++; }
}
// عميل ومنسوب يظهران في قوائم الموارد وعلاقات العملاء
mkUser('client', 'عميل');
mkUser('pm', 'مدير مشاريع');

echo "\nمن يدعو من:\n";
$invite = function (string $as, string $target, string $email) use ($jars): array {
    return call($jars[$as], 'admin.php', ['action' => 'invite', 'email' => $email, 'role' => $target]);
};
$allowed = ['sysadmin' => ALL_ROLES, 'hr' => ['team_member', 'pm', 'finance', 'medical', 'crm', 'mod', 'reviewer'], 'crm' => ['client'], 'mod' => ['user']];
foreach (['sysadmin', 'hr', 'crm', 'mod'] as $as) {
    $bad = [];
    foreach (ALL_ROLES as $target) {
        if ($as === 'sysadmin' && in_array($target, ['pm', 'team_member', 'medical', 'crm', 'client'], true)) resetInviteLimit();
        $r = $invite($as, $target, "inv-$as-$target" . MAIL);
        $should = in_array($target, $allowed[$as], true);
        if ($should !== ($r['code'] === 200 && ($r['json']['ok'] ?? false))) $bad[] = "$target=" . $r['code'];
        if (!$should && $r['code'] !== 403) $bad[] = "$target ليس 403";
        if (($n = (int) db()->query("SELECT COUNT(*) FROM users WHERE email='inv-$as-$target" . MAIL . "'")->fetchColumn()) !== 0) $bad[] = "$target أنشأ حساباً";
        if ($as !== 'sysadmin') resetInviteLimit();
    }
    check("$as: يدعو المسموح ويُرفض ما عداه (403)", $bad === [], implode(' ', $bad));
}
foreach (['reviewer', 'executive', 'team_member', 'user'] as $as) {
    $r = $invite($as, 'user', "inv-$as-x" . MAIL);
    check("$as: لا يدعو أحداً (403)", $r['code'] === 403);
}
$r = $invite('sysadmin', 'astronaut', 'inv-astronaut' . MAIL);
check('دور غير معرَّف يُرفض', !($r['json']['ok'] ?? false));
$r = $invite('sysadmin', 'user', 'ليس-بريداً');
check('بريد غير صالح يُرفض', !($r['json']['ok'] ?? false));

echo "\nما يُخزَّن في الدعوة:\n";
$row = fn(string $e) => db()->query("SELECT role_target, org_role_target, status FROM invites WHERE email='$e" . MAIL . "'")->fetch();
$x = $row('inv-sysadmin-user');      check('مستفيد: user بلا دور مساحة', $x && $x['role_target'] === 'user' && $x['org_role_target'] === null);
$x = $row('inv-sysadmin-mod');       check('مشرف: mod بلا دور مساحة', $x && $x['role_target'] === 'mod' && $x['org_role_target'] === null);
$x = $row('inv-sysadmin-reviewer');  check('مراجع محتوى: reviewer', $x && $x['role_target'] === 'reviewer' && $x['org_role_target'] === null);
$x = $row('inv-sysadmin-hr');        check('مدير الموارد: user في المنصة وhr في مساحة العمل', $x && $x['role_target'] === 'user' && $x['org_role_target'] === 'hr');
$x = $row('inv-sysadmin-sysadmin');  check('مدير النظام: user وsysadmin (يصير admin عند التسجيل)', $x && $x['role_target'] === 'user' && $x['org_role_target'] === 'sysadmin');
$x = $row('inv-hr-team_member');     check('دعوة الموارد لعضو فريق', $x && $x['org_role_target'] === 'team_member');
$x = $row('inv-crm-client');         check('دعوة علاقات العملاء لعميل', $x && $x['org_role_target'] === 'client');

echo "\nالمدعو يسجّل بنفسه فيأخذ دوره:\n";
$register = function (string $emailPrefix, int $i) {
    $tok = db()->query("SELECT token FROM invites WHERE email='$emailPrefix" . MAIL . "'")->fetchColumn();
    $jar = tempnam(sys_get_temp_dir(), 'jar');
    $info = call($jar, 'auth.php', ['action' => 'invite_info', 'invite' => $tok]);
    $reg = call($jar, 'auth.php', ['action' => 'register', 'invite' => $tok, 'first' => 'أحمد', 'last' => 'الفحص', 'first_en' => 'Ahmad', 'last_en' => 'Check',
        'email' => "$emailPrefix" . MAIL, 'phone' => '05' . str_pad((string) (50000000 + $i * 104729), 8, '0', STR_PAD_LEFT), 'dob' => '1990-01-01', 'password' => PASS, 'pref' => 'simple']);
    $me = call($jar, 'auth.php', ['action' => 'me']);
    return [$info, $reg, $me];
};
[$info, $reg, $me] = $register('inv-sysadmin-hr', 1);
check('نص الدعوة يذكر الدور', ($info['json']['role_label'] ?? '') === 'مدير الموارد البشرية', $info['raw']);
check('التسجيل بالدعوة ينجح ويدخل صاحبه', ($reg['json']['ok'] ?? false) && ($me['json']['user']['email'] ?? '') === 'inv-sysadmin-hr' . MAIL, $reg['raw']);
check('دوره الفعلي مدير الموارد وله مساحة عمل', ($me['json']['user']['eff_role'] ?? '') === 'hr' && ($me['json']['user']['workspace'] ?? false) === true && ($me['json']['user']['role'] ?? '') === 'user');
[, $reg, $me] = $register('inv-sysadmin-sysadmin', 2);
check('المدعو مدير نظام يصير admin في المنصة وsysadmin فعلياً', ($me['json']['user']['role'] ?? '') === 'admin' && ($me['json']['user']['eff_role'] ?? '') === 'sysadmin', $reg['raw']);
[, $reg, $me] = $register('inv-sysadmin-mod', 3);
check('المدعو مشرفاً يبقى دوره من المنصة بلا مساحة عمل', ($me['json']['user']['role'] ?? '') === 'mod' && ($me['json']['user']['workspace'] ?? true) === false);
[, $reg, $me] = $register('inv-crm-client', 4);
check('العميل: مستفيد في المنصة ودوره في مساحة العمل client', ($me['json']['user']['eff_role'] ?? '') === 'client' && ($me['json']['user']['workspace'] ?? false) === true);
check('الدعوة تُستهلك', $row('inv-crm-client')['status'] === 'accepted');
[$info2, $reg2] = $register('inv-crm-client', 5);
check('ولا تُستخدم مرتين', !($reg2['json']['ok'] ?? false));
$tok = db()->query("SELECT token FROM invites WHERE email='inv-hr-team_member" . MAIL . "'")->fetchColumn();
$wrong = call(tempnam(sys_get_temp_dir(), 'jar'), 'auth.php', ['action' => 'register', 'invite' => $tok, 'first' => 'أحمد', 'last' => 'الفحص', 'first_en' => 'Ahmad', 'last_en' => 'Check',
    'email' => 'someone-else' . MAIL, 'phone' => '0511111111', 'dob' => '1990-01-01', 'password' => PASS]);
check('ولا يُقبل ببريد غير بريد الدعوة', !($wrong['json']['ok'] ?? false));

echo "\nقوائم المستخدمين والدعوات بحسب من يسأل:\n";
$users = fn(string $as) => call($jars[$as], 'admin.php', ['action' => 'users']);
$emails = fn(array $r) => array_column($r['json']['users'] ?? [], 'eff');
$r = $users('sysadmin');
check('مدير النظام يرى الكل وأدوار الدعوة الاثني عشر', count($r['json']['users'] ?? []) >= 8 && same_set($r['json']['invitable'] ?? [], ALL_ROLES));
$r = $users('hr');
$effs = $emails($r);
check('الموارد ترى المنسوبين وفريق المنصة دون العملاء والمستفيدين', $r['code'] === 200 && in_array('pm', $effs, true) && in_array('mod', $effs, true) && !in_array('client', $effs, true) && !in_array('user', $effs, true), implode(',', $effs));
$r = $users('crm');
check('علاقات العملاء ترى العملاء وحدهم', $r['code'] === 200 && $emails($r) !== [] && count(array_unique($emails($r))) === 1 && $emails($r)[0] === 'client', implode(',', $emails($r)));
$r = $users('mod');
check('المشرف يرى الكل كما كان ويدعو المستفيد وحده', $r['code'] === 200 && ($r['json']['invitable'] ?? []) === ['user']);
foreach (['reviewer', 'executive', 'team_member', 'user'] as $as) check("$as: لا يفتح قائمة المستخدمين (403)", $users($as)['code'] === 403);
$mine = call($jars['hr'], 'admin.php', ['action' => 'invites']);
$emailsIn = array_column($mine['json']['invites'] ?? [], 'email');
check('الموارد ترى دعواتها وحدها', $mine['code'] === 200 && $emailsIn !== [] && !array_filter($emailsIn, fn($e) => strpos($e, 'inv-hr-') !== 0), implode(',', $emailsIn));
$all = call($jars['sysadmin'], 'admin.php', ['action' => 'invites']);
check('ومدير النظام يرى الكل بأدوارها الفعلية', in_array('hr', array_column($all['json']['invites'] ?? [], 'role'), true));
resetInviteLimit();
$again = $invite('hr', 'pm', 'inv-hr-revoke' . MAIL);
$rv = call($jars['crm'], 'admin.php', ['action' => 'revoke_invite', 'email' => 'inv-hr-revoke' . MAIL]);
check('علاقات العملاء لا تُلغي دعوة غيرها', $row('inv-hr-revoke')['status'] === 'sent');
$rv = call($jars['hr'], 'admin.php', ['action' => 'revoke_invite', 'email' => 'inv-hr-revoke' . MAIL]);
check('والموارد تُلغي دعوتها', $row('inv-hr-revoke')['status'] === 'revoked');

echo "\nما لا يبلغه الموارد وعلاقات العملاء:\n";
foreach (['hr', 'crm'] as $as) {
    $bad = [];
    foreach (['stats', 'messages', 'tickets', 'audit', 'set_role', 'set_status', 'reset_password', 'delete_user', 'grant_tokens', 'demo_seed'] as $act) {
        $r = call($jars[$as], 'admin.php', ['action' => $act, 'user_id' => 1, 'role' => 'user']);
        if ($r['code'] !== 403) $bad[] = "$act=" . $r['code'];
    }
    check("$as: إحصاءات المنصة وإجراءات الإدارة الأخرى كلها 403", $bad === [], implode(' ', $bad));
}
foreach (['sysadmin', 'mod', 'reviewer'] as $as) {
    $r = call($jars[$as], 'admin.php', ['action' => 'stats']);
    check("$as: إحصاءات المنصة كما كانت (200)", $r['code'] === 200);
}

echo "\nتغيير الدور:\n";
$targetId = mkUser('user', 'هدف');
$targetEmail = db()->query("SELECT email FROM users WHERE id=$targetId")->fetchColumn();
$cols = fn() => db()->query("SELECT role, org_role FROM users WHERE id=$targetId")->fetch();
$bad = [];
foreach (ALL_ROLES as $eff) {
    $r = call($jars['sysadmin'], 'admin.php', ['action' => 'set_role', 'user_id' => $targetId, 'role' => $eff]);
    $c = $cols();
    [$wantRole, $wantOrg] = roleColumns($eff);
    if (!($r['json']['ok'] ?? false) || $c['role'] !== $wantRole || $c['org_role'] !== $wantOrg) $bad[] = "$eff => " . json_encode($c);
}
check('مدير النظام يحوّل الحساب إلى كل دور من الاثني عشر بأعمدته الصحيحة', $bad === [], implode(' | ', $bad));
$r = call($jars['sysadmin'], 'admin.php', ['action' => 'set_role', 'user_id' => $targetId, 'role' => 'user']);
check('ويعود مستفيداً بلا دور في مساحة العمل', ($c = $cols()) && $c['role'] === 'user' && $c['org_role'] === null);
foreach (['hr', 'crm', 'mod', 'reviewer', 'executive'] as $as) {
    $r = call($jars[$as], 'admin.php', ['action' => 'set_role', 'user_id' => $targetId, 'role' => 'pm']);
    if ($r['code'] !== 403) { $bad[] = $as; }
}
check('وغيره لا يغيّر دوراً (403)', !array_intersect($bad, ['hr', 'crm', 'mod', 'reviewer', 'executive']));
$r = call($jars['sysadmin'], 'admin.php', ['action' => 'set_role', 'user_id' => $targetId, 'role' => 'astronaut']);
check('دور غير معرَّف يُرفض', !($r['json']['ok'] ?? false));

/** مجموعتان بلا اعتبار الترتيب */
function same_set(array $a, array $b): bool { sort($a); sort($b); return $a === $b; }

cleanup();
resetInviteLimit();
foreach ($jars as $j) @unlink($j);
check('تنظيف بيانات الفحص', (int) db()->query("SELECT COUNT(*) FROM users WHERE email LIKE '%" . MAIL . "'")->fetchColumn() === 0
      && (int) db()->query("SELECT COUNT(*) FROM invites WHERE email LIKE '%" . MAIL . "'")->fetchColumn() === 0);

echo $fails === 0 ? "\n✓ كل الفحوص ناجحة\n" : "\n✗ فشل $fails فحصاً\n";
exit($fails === 0 ? 0 : 1);
