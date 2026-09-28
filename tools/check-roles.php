<?php
/* ==========================================================================
 *  وصال: فحص الأدوار الموحدة (اثنا عشر دوراً، ودور واحد لكل حساب)
 *
 *  الاستخدام:
 *      php tools/check-roles.php
 *
 *  يثبت أن أدوار المنصة تطابق مساحة العمل، وأن مصفوفة الدعوات هي المتفق عليها:
 *    - مفاتيح ونصوص أدوار مساحة العمل في api/db.php = workspace/config/roles.php
 *      (المصدر الوحيد هناك)، فلا يختلف اسم دور بين النظامين
 *    - الدور الفعلي وأعمدة التخزين يعودان بلا فقد لكل دور من الاثني عشر
 *    - من يدعو من: مدير النظام أي دور، الموارد البشرية الموظفون دون المناصب العليا،
 *      علاقات العملاء العميل وحده، المشرف المستفيد، وغيرهم لا أحد
 *  لا يمس قاعدة البيانات ولا الإعدادات. يحتاج api/config.php لأن db.php يحمّله.
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
function same(array $a, array $b): bool { sort($a); sort($b); return $a === $b; }

echo "فحص الأدوار الموحدة\n";

/* ---------- التطابق مع مساحة العمل ---------- */
echo "\nالتطابق مع workspace/config/roles.php:\n";
$wsFile = __DIR__ . '/../workspace/config/roles.php';
check('ملف أدوار مساحة العمل موجود', file_exists($wsFile));
$ws = file_exists($wsFile) ? require $wsFile : [];
check('مفاتيح الأدوار هي نفسها (ORG_ROLES)', same(array_keys($ws), ORG_ROLES));
$labelsOk = true;
foreach ($ws as $key => $def) {
    if ((ROLE_LABELS[$key] ?? null) !== ($def['label'] ?? '')) {
        $labelsOk = false;
        echo "      اختلف نص «$key»: المنصة «" . (ROLE_LABELS[$key] ?? 'غير معرَّف') . "» ومساحة العمل «" . ($def['label'] ?? '') . "»\n";
    }
}
check('نصوص الأدوار حرفياً هي نفسها', $labelsOk);
check('الأدوار الاثنا عشر: ثلاثة للمنصة وتسعة لمساحة العمل', count(ALL_ROLES) === 12 && same(ALL_ROLES, array_merge(['user', 'reviewer', 'mod'], ORG_ROLES)));
check('كل دور له نص', count(array_filter(ALL_ROLES, fn($r) => isset(ROLE_LABELS[$r]))) === 12);
check('admin وsysadmin بنص واحد (يُشتق ولا يُخزَّن مرتين)', ROLE_LABELS['admin'] === ROLE_LABELS['sysadmin']);

/* ---------- الدور الفعلي وأعمدة التخزين ---------- */
echo "\nالدور الفعلي والتخزين:\n";
$roundTrip = true;
foreach (ALL_ROLES as $eff) {
    [$role, $org] = roleColumns($eff);
    if (!in_array($role, ROLES, true) || effectiveRole(['role' => $role, 'org_role' => $org]) !== $eff) {
        $roundTrip = false;
        echo "      لا يعود «$eff» من ($role, " . var_export($org, true) . ")\n";
    }
}
check('كل دور يُخزَّن ويعود هو نفسه', $roundTrip);
check('مدير النظام بلا org_role (أول حساب سُجّل) هو sysadmin', effectiveRole(['role' => 'admin', 'org_role' => null]) === 'sysadmin');
check('مدير النظام يُخزَّن admin مع sysadmin', roleColumns('sysadmin') === ['admin', 'sysadmin']);
check('أدوار المنصة الثلاثة بلا org_role', roleColumns('mod') === ['mod', null] && roleColumns('reviewer') === ['reviewer', null] && roleColumns('user') === ['user', null]);
check('صاحب دور في مساحة العمل مستفيد في المنصة', roleColumns('hr') === ['user', 'hr'] && roleColumns('client') === ['user', 'client']);
check('org_role مجهول لا يمنح شيئاً', effectiveRole(['role' => 'user', 'org_role' => 'astronaut']) === 'user');
check('org_role فارغ كالمعدوم', effectiveRole(['role' => 'mod', 'org_role' => '']) === 'mod');
check('admin يغلب أي org_role', effectiveRole(['role' => 'admin', 'org_role' => 'client']) === 'sysadmin');

/* ---------- من يدعو من ---------- */
echo "\nمصفوفة الدعوات:\n";
$actor = fn(string $eff): array => ['role' => roleColumns($eff)[0], 'org_role' => roleColumns($eff)[1]];

check('مدير النظام يدعو الاثني عشر كلها', same(invitableRoles($actor('sysadmin')), ALL_ROLES));
check('مدير الموارد البشرية: الموظفون دون المناصب العليا',
      same(invitableRoles($actor('hr')), ['team_member', 'pm', 'finance', 'medical', 'crm', 'mod', 'reviewer']));
check('ولا يدعو المدير التنفيذي ولا مدير النظام ولا مدير الموارد ولا عميلاً ولا مستفيداً',
      !canInviteRole($actor('hr'), 'executive') && !canInviteRole($actor('hr'), 'sysadmin') && !canInviteRole($actor('hr'), 'hr')
      && !canInviteRole($actor('hr'), 'client') && !canInviteRole($actor('hr'), 'user'));
check('مدير علاقات العملاء يدعو العميل وحده', invitableRoles($actor('crm')) === ['client']);
check('المشرف يدعو المستفيد وحده كما كان', invitableRoles($actor('mod')) === ['user']);
$none = ['reviewer', 'user', 'executive', 'pm', 'finance', 'medical', 'team_member', 'client'];
check('غير هؤلاء لا يدعو أحداً', count(array_filter($none, fn($r) => invitableRoles($actor($r)) !== [])) === 0);
check('حساب بلا دور (مصفوفة ناقصة) لا يدعو أحداً', invitableRoles([]) === [] && invitableRoles(['role' => 'user']) === []);
check('لا يُدعى بدور غير معرَّف', !canInviteRole($actor('sysadmin'), 'astronaut'));

echo $fails === 0 ? "\n✓ كل الفحوص ناجحة\n" : "\n✗ فشل $fails فحصاً\n";
exit($fails === 0 ? 0 : 1);
