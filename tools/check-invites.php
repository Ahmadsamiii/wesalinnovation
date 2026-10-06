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
 *    - الدعوة بالاسم الكامل بالعربية والجوال، وصلاحيتها INVITE_TTL_DAYS، وإعادة إرسالها
 *      وحذفها بصلاحيات من يدعو، والدعوة المقبولة لا تُمس
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
/** جوال ثابت لكل بريد، لا يتكرر بين دعوات الفحص */
function invPhone(string $email): string { return '058' . str_pad((string) (crc32($email) % 10000000), 7, '0', STR_PAD_LEFT); }
const INV_NAME = 'محمد عبدالله الفاحص';
$invite = function (string $as, string $target, string $email, array $extra = []) use ($jars): array {
    return call($jars[$as], 'admin.php', array_merge(['action' => 'invite', 'email' => $email, 'role' => $target,
        'name' => INV_NAME, 'phone' => invPhone($email)], $extra));
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

echo "\nالاسم والجوال في الدعوة:\n";
resetInviteLimit();
$bad = [];
foreach ([['name' => ''], ['name' => 'محمد العتيبي'], ['name' => 'Mohammed Abdullah Check'], ['name' => 'محمد عبدالله 3'],
          ['phone' => ''], ['phone' => '0123'], ['phone' => '06' . '12345678']] as $k => $over) {
    $r = call($jars['sysadmin'], 'admin.php', array_merge(['action' => 'invite', 'email' => "inv-bad$k" . MAIL, 'role' => 'user',
        'name' => INV_NAME, 'phone' => invPhone("inv-bad$k")], $over));
    if ($r['json']['ok'] ?? false) $bad[] = json_encode($over, JSON_UNESCAPED_UNICODE);
}
check('اسم ناقص أو بغير العربية أو جوال غير صحيح يُرفض', $bad === [], implode(' ', $bad));
resetInviteLimit();
$taken = db()->query("SELECT phone FROM users WHERE email LIKE '%" . MAIL . "' AND phone IS NOT NULL LIMIT 1")->fetchColumn();
$r = $invite('sysadmin', 'user', 'inv-takenphone' . MAIL, ['phone' => $taken]);
check('جوال حساب قائم يُرفض', !($r['json']['ok'] ?? false) && str_contains($r['json']['error'] ?? '', 'الجوال'), $r['raw']);
$r = $invite('sysadmin', 'user', 'inv-dupphone' . MAIL, ['phone' => invPhone('inv-sysadmin-pm' . MAIL)]);
check('وجوال دعوة أخرى بانتظار القبول يُرفض', !($r['json']['ok'] ?? false), $r['raw']);
$r = $invite('sysadmin', 'pm', 'inv-name' . MAIL, ['name' => '  سارة   خالد    الفاحصة ', 'phone' => '+966 58 111 2233']);
$x = db()->query("SELECT name, phone, created_at, expires_at FROM invites WHERE email='inv-name" . MAIL . "'")->fetch();
check('يُخزَّن الاسم بمسافات موحّدة والجوال بصيغة 05', $x && $x['name'] === 'سارة خالد الفاحصة' && $x['phone'] === '0581112233', json_encode($x, JSON_UNESCAPED_UNICODE));
$ttl = (int) INVITE_TTL_DAYS * 86400;
check('وتنتهي صلاحيتها بعد ' . INVITE_TTL_DAYS . ' يوماً من الإرسال', $x && abs(strtotime($x['expires_at']) - strtotime($x['created_at']) - $ttl) <= 5
      && abs(($r['json']['expires'] ?? 0) / 1000 - strtotime($x['expires_at'])) <= 5);

$tok = fn(string $e) => db()->query("SELECT token FROM invites WHERE email='$e" . MAIL . "'")->fetchColumn();
$info = call(tempnam(sys_get_temp_dir(), 'jar'), 'auth.php', ['action' => 'invite_info', 'invite' => $tok('inv-name')]);
check('معلومات الدعوة تعطي المدعو اسمه وجواله ونهاية صلاحيتها', ($info['json']['name'] ?? '') === 'سارة خالد الفاحصة'
      && ($info['json']['phone'] ?? '') === '0581112233' && ($info['json']['expires'] ?? 0) > time() * 1000, $info['raw']);
$jar = tempnam(sys_get_temp_dir(), 'jar');
$reg = call($jar, 'auth.php', ['action' => 'register', 'invite' => $tok('inv-name'), 'first' => '', 'last' => '', 'first_en' => 'Sara', 'last_en' => 'Check',
    'email' => 'inv-name' . MAIL, 'phone' => '0599999999', 'dob' => '1992-02-02', 'password' => PASS]);
$u = db()->query("SELECT name, phone FROM users WHERE email='inv-name" . MAIL . "'")->fetch();
check('التسجيل يعتمد اسم الدعوة وجوالها لا ما في النموذج', ($reg['json']['ok'] ?? false) && $u && $u['name'] === 'سارة خالد الفاحصة' && $u['phone'] === '0581112233', $reg['raw']);

echo "\nالصلاحية وإعادة الإرسال والحذف:\n";
resetInviteLimit();
$invite('hr', 'team_member', 'inv-life' . MAIL);
$id = (int) db()->query("SELECT id FROM invites WHERE email='inv-life" . MAIL . "'")->fetchColumn();
$listed = function (string $as, string $e) use ($jars): ?array {
    foreach (call($jars[$as], 'admin.php', ['action' => 'invites'])['json']['invites'] ?? [] as $i) if ($i['email'] === $e . MAIL) return $i;
    return null;
};
$l = $listed('hr', 'inv-life');
check('القائمة تعطي الاسم والجوال ونهاية الصلاحية وإمكان الإدارة', $l && $l['name'] === INV_NAME && $l['phone'] === invPhone('inv-life' . MAIL)
      && $l['status'] === 'sent' && $l['manage'] === true && $l['expires'] > $l['t'], json_encode($l, JSON_UNESCAPED_UNICODE));

db()->exec("UPDATE invites SET expires_at = NOW() - INTERVAL 1 MINUTE WHERE id=$id");
$oldTok = $tok('inv-life');
$info = call(tempnam(sys_get_temp_dir(), 'jar'), 'auth.php', ['action' => 'invite_info', 'invite' => $oldTok]);
check('الدعوة المنتهية تُرفض برسالة انتهاء الصلاحية', !($info['json']['ok'] ?? true) && ($info['json']['error'] ?? '') === INVITE_EXPIRED_MSG, $info['raw']);
$reg = call(tempnam(sys_get_temp_dir(), 'jar'), 'auth.php', ['action' => 'register', 'invite' => $oldTok, 'first' => '', 'last' => '', 'first_en' => 'Old', 'last_en' => 'Link',
    'email' => 'inv-life' . MAIL, 'phone' => '0599999998', 'dob' => '1990-01-01', 'password' => PASS]);
check('ولا يُسجَّل بها', !($reg['json']['ok'] ?? true) && (int) db()->query("SELECT COUNT(*) FROM users WHERE email='inv-life" . MAIL . "'")->fetchColumn() === 0);
check('وتظهر في القائمة منتهية ويمكن إدارتها', ($listed('hr', 'inv-life')['status'] ?? '') === 'expired' && ($listed('hr', 'inv-life')['manage'] ?? false) === true);

$r = call($jars['crm'], 'admin.php', ['action' => 'resend_invite', 'id' => $id]);
check('علاقات العملاء لا تعيد إرسال دعوة غيرها (403)', $r['code'] === 403 && $tok('inv-life') === $oldTok);
$r = call($jars['mod'], 'admin.php', ['action' => 'delete_invite', 'id' => $id]);
check('والمشرف لا يحذف دعوة عضو فريق (403)', $r['code'] === 403 && $tok('inv-life') === $oldTok);
$r = call($jars['hr'], 'admin.php', ['action' => 'resend_invite', 'id' => $id]);
$y = db()->query("SELECT token, status, created_at, expires_at FROM invites WHERE id=$id")->fetch();
check('إعادة الإرسال: رابط جديد بصلاحية كاملة من اليوم', ($r['json']['ok'] ?? false) && $y['token'] !== $oldTok && $y['status'] === 'sent'
      && abs(strtotime($y['expires_at']) - time() - $ttl) <= 5, $r['raw']);
$info = call(tempnam(sys_get_temp_dir(), 'jar'), 'auth.php', ['action' => 'invite_info', 'invite' => $oldTok]);
check('والرابط السابق يتوقف', !($info['json']['ok'] ?? true));
$info = call(tempnam(sys_get_temp_dir(), 'jar'), 'auth.php', ['action' => 'invite_info', 'invite' => $y['token']]);
check('والجديد يعمل', ($info['json']['ok'] ?? false) && ($listed('hr', 'inv-life')['status'] ?? '') === 'sent');
check('وتُسجَّل في سجل العمليات', (int) db()->query("SELECT COUNT(*) FROM audit_log WHERE action='invite_resent' AND target='inv-life" . MAIL . "'")->fetchColumn() === 1);

$r = call($jars['hr'], 'admin.php', ['action' => 'delete_invite', 'id' => $id]);
check('الحذف يزيل الدعوة ويوقف رابطها', ($r['json']['ok'] ?? false) && (int) db()->query("SELECT COUNT(*) FROM invites WHERE id=$id")->fetchColumn() === 0
      && !(call(tempnam(sys_get_temp_dir(), 'jar'), 'auth.php', ['action' => 'invite_info', 'invite' => $y['token']])['json']['ok'] ?? true));
check('ويُسجَّل في سجل العمليات', (int) db()->query("SELECT COUNT(*) FROM audit_log WHERE action='invite_deleted' AND target='inv-life" . MAIL . "'")->fetchColumn() === 1);
$r = call($jars['hr'], 'admin.php', ['action' => 'delete_invite', 'id' => $id]);
check('وحذف ما حُذف يعطي رسالة واضحة', !($r['json']['ok'] ?? true) && str_contains($r['json']['error'] ?? '', 'غير موجودة'));

$acc = (int) db()->query("SELECT id FROM invites WHERE email='inv-name" . MAIL . "'")->fetchColumn();
$r1 = call($jars['sysadmin'], 'admin.php', ['action' => 'resend_invite', 'id' => $acc]);
$r2 = call($jars['sysadmin'], 'admin.php', ['action' => 'delete_invite', 'id' => $acc]);
check('الدعوة المقبولة لا يُعاد إرسالها ولا تُحذف', !($r1['json']['ok'] ?? true) && !($r2['json']['ok'] ?? true)
      && db()->query("SELECT status FROM invites WHERE id=$acc")->fetchColumn() === 'accepted');
check('ولا تظهر لها إجراءات في القائمة', ($listed('sysadmin', 'inv-name')['manage'] ?? true) === false && ($listed('sysadmin', 'inv-name')['status'] ?? '') === 'accepted');
check('المشرف يدير دعوات المستفيدين وحدها', ($listed('mod', 'inv-sysadmin-user')['manage'] ?? false) === true && ($listed('mod', 'inv-sysadmin-pm')['manage'] ?? true) === false);

db()->exec("UPDATE invites SET expires_at=NULL, created_at = NOW() - INTERVAL " . ((int) INVITE_TTL_DAYS + 1) . " DAY WHERE email='inv-sysadmin-finance" . MAIL . "'");
db()->exec("UPDATE invites SET expires_at=NULL, created_at = NOW() - INTERVAL 2 DAY WHERE email='inv-sysadmin-medical" . MAIL . "'");
check('دعوة قديمة بلا تاريخ انتهاء تُحسب صلاحيتها من إرسالها', ($listed('sysadmin', 'inv-sysadmin-finance')['status'] ?? '') === 'expired'
      && ($listed('sysadmin', 'inv-sysadmin-medical')['status'] ?? '') === 'sent');

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
