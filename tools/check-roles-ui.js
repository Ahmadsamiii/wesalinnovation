/* ==========================================================================
 *  وصال: فحص واجهة الأدوار والدعوات في المتصفح
 *
 *  على نسخة محلية بقاعدة تجريبية فقط (تنشئ حسابات الفحص وتحذفها). من جذر المستودع:
 *      php -S 127.0.0.1:8080 &
 *      NODE_PATH="$(npm root -g)" node tools/check-roles-ui.js
 *
 *  يدخل بحسابات حقيقية ويتحقق مما يراه كل دور:
 *    - مدير النظام: قائمة الدور بالاثني عشر في كل صف، ودعوة بأي منها
 *    - مدير الموارد البشرية: دعوة بالمسموح له وحده، وقائمة المنسوبين بلا عملاء،
 *      وبلا إجراءات إدارة، وبلا طلب لإحصاءات المنصة
 *    - مدير علاقات العملاء: دعوة العميل وحده، وقائمة العملاء وحدهم
 *    - صاحب دور في مساحة العمل بلا صلاحيات إدارة: شارة دوره ورابط المساحة
 *    - المستفيد العادي: لا تبويب مستخدمين ولا رابط مساحة
 *    - مجموعة «مساحة العمل» في القائمة الجانبية: لمن له دور فيها، وأقسام مدير النظام له وحده،
 *      والرابط العميق #dashboard/القسم يفتح قسمه لمن يملك صلاحيته
 * ========================================================================== */

const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const path = require('path');

const BASE = process.env.BASE || 'http://127.0.0.1:8080';
if (!/^http:\/\/(127\.0\.0\.1|localhost)(:\d+)?$/.test(BASE)) {
  console.error('يعمل على خادم محلي فقط (BASE=http://127.0.0.1:PORT).');
  process.exit(2);
}
const ROOT = path.resolve(__dirname, '..');
const MAIL = '@check-roles-ui.invalid';
const PASS = 'Passw0rd1';

let passed = 0, failed = 0;
const check = (name, ok, extra = '') => { ok ? passed++ : failed++; console.log(`  ${ok ? '✓' : '✗'} ${name}${extra && !ok ? '  | ' + extra : ''}`); };

function php(code) {
  return execFileSync('php', ['-r', `require '${ROOT}/api/db.php'; ensureSchema(); ${code}`], { cwd: ROOT, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] });
}
const purge = () => php(`
  $ids = db()->query("SELECT id FROM users WHERE email LIKE '%${MAIL}'")->fetchAll(PDO::FETCH_COLUMN);
  if ($ids) { $in = implode(',', array_map('intval', $ids)); db()->exec("DELETE FROM auth_sessions WHERE user_id IN ($in)"); db()->exec("DELETE FROM users WHERE id IN ($in)"); }
  db()->exec("DELETE FROM rate_limits WHERE bucket IN ('m:login','m:reg','m:invite')");`);
const seed = (eff, name, n) => php(`
  [$role, $org] = roleColumns('${eff}');
  db()->prepare('INSERT INTO users (name,email,phone,pref,pass_hash,role,org_role,tokens,tokens_at,created_at) VALUES (?,?,?,?,?,?,?,30,NOW(),NOW())')
    ->execute(['${name}', '${eff}${MAIL}', '05' . str_pad((string)(20000000 + ${n} * 7919), 8, '0', STR_PAD_LEFT), 'simple', password_hash('${PASS}', PASSWORD_DEFAULT), $role, $org]);`);

const ALL = ['مستفيد', 'مراجع محتوى', 'مشرف', 'مدير النظام', 'المدير التنفيذي', 'مدير المشاريع', 'المدير المالي', 'المدير الطبي', 'عضو الفريق', 'العميل', 'مدير الموارد البشرية', 'مدير علاقات العملاء'];

/** حدّ الدخول 12 في الدقيقة، والفحص يسجّل دخولاً أكثر منه، فيُصفَّر عدّاده قبل كل شخص */
const resetLimits = () => php(`db()->exec("DELETE FROM rate_limits WHERE bucket IN ('m:login','m:reg','m:invite')");`);
async function as(browser, eff, fn) {
  resetLimits();
  const ctx = await browser.newContext({ locale: 'ar-SA' });
  const page = await ctx.newPage();
  const admin = [];
  page.on('request', r => { if (r.url().includes('/api/admin.php')) admin.push(r.postData() || ''); });
  const res = await page.request.post(`${BASE}/api/auth.php`, { data: { action: 'login', email: `${eff}${MAIL}`, password: PASS } });
  if (!(await res.json()).ok) { check(`دخول ${eff}`, false, await res.text()); await ctx.close(); return; }
  await page.goto(`${BASE}/#dashboard`, { waitUntil: 'domcontentloaded' });
  await page.waitForFunction(() => typeof USER !== 'undefined' && USER && document.getElementById('umName') && document.getElementById('umName').textContent);
  await fn(page, admin);
  await ctx.close();
}

async function openUsers(page) {
  const tab = page.locator('.dash-tab[data-tab="users"]');
  if (await tab.count() === 0 || !(await tab.isVisible())) return false;
  await tab.click();
  await page.waitForFunction(() => document.querySelector('#usersBody tr'));
  return true;
}
const inviteOptions = async page => {
  await page.locator('#dp-users .tbl-actions button', { hasText: 'دعوة' }).first().click();
  return page.$$eval('#invRoleSel option', os => os.map(o => o.textContent));
};

(async () => {
  purge();
  const people = [['sysadmin', 'مدير'], ['hr', 'موارد'], ['crm', 'علاقات'], ['team_member', 'عضو'], ['user', 'مستفيد'], ['client', 'عميل'], ['pm', 'مدير مشاريع']];
  people.forEach(([eff, name], i) => seed(eff, name, i + 1));

  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || undefined });

  console.log('\nمدير النظام:');
  await as(browser, 'sysadmin', async (page) => {
    check('يفتح تبويب المستخدمين', await openUsers(page));
    const opts = await inviteOptions(page);
    check('يدعو بالاثني عشر دوراً', opts.length === 12 && ALL.every(l => opts.includes(l)), opts.join('،'));
    const rowOpts = await page.$$eval('#usersBody select:first-of-type option', os => os.map(o => o.textContent)).catch(() => []);
    check('قائمة الدور في كل صف بالاثني عشر', rowOpts.length >= 12 && ALL.every(l => rowOpts.includes(l)));
    check('شارته مدير النظام', (await page.textContent('#umRolePill')) === 'مدير النظام');
    check('رابط مساحة العمل ظاهر', await page.locator('#userMenu .ws-only').evaluate(e => !e.hidden));
    check('ويصل /workspace/', (await page.getAttribute('#userMenu .ws-only', 'onclick')).includes('/workspace/'));
  });

  console.log('\nمجموعة مساحة العمل في القائمة الجانبية:');
  const wsLinks = async page => page.$$eval('#sbWs a.sb-link', as => as.filter(a => a.getClientRects().length).map(a => a.getAttribute('href')));
  const WS_ADMIN = ['/workspace/content', '/workspace/system/health', '/workspace/system/ai', '/workspace/system/mail', '/workspace/audit-log', '/workspace/system/deployment', '/workspace/reports/technical'];
  await as(browser, 'sysadmin', async page => {
    const l = await wsLinks(page);
    check('مدير النظام: لوحة مساحة العمل وأقسامه السبعة', l[0] === '/workspace/dashboard' && WS_ADMIN.every(h => l.includes(h)) && l.length === 8, l.join(' '));
  });
  await as(browser, 'team_member', async page => {
    const l = await wsLinks(page);
    check('صاحب دور عادي: رابط لوحة مساحة العمل وحده', l.join(' ') === '/workspace/dashboard', l.join(' '));
  });
  await as(browser, 'user', async page => {
    check('المستفيد: المجموعة كلها مخفية', (await wsLinks(page)).length === 0 && await page.locator('#sbWs').evaluate(e => e.hidden));
  });
  await as(browser, 'hr', async page => {
    check('الموارد البشرية: لوحة مساحة العمل وحدها بلا أقسام مدير النظام', (await wsLinks(page)).join(' ') === '/workspace/dashboard');
  });
  {
    const open = async (eff, hash) => {
      resetLimits();
      const ctx = await browser.newContext({ locale: 'ar-SA' });
      const page = await ctx.newPage();
      await page.request.post(`${BASE}/api/auth.php`, { data: { action: 'login', email: `${eff}${MAIL}`, password: PASS } });
      await page.goto(`${BASE}/#${hash}`, { waitUntil: 'domcontentloaded' });
      await page.waitForFunction(() => typeof USER !== 'undefined' && USER && document.getElementById('umName') && document.getElementById('umName').textContent);
      await page.waitForTimeout(600);
      const tab = await page.evaluate(() => (document.querySelector('.dash-tab.on') || {}).dataset?.tab);
      await ctx.close();
      return tab;
    };
    check('الرابط العميق #dashboard/users يفتح «المستخدمون والأدوار» لمدير النظام', (await open('sysadmin', 'dashboard/users')) === 'users');
    check('و#dashboard/audit يفتح سجل العمليات', (await open('sysadmin', 'dashboard/audit')) === 'audit');
    check('والموارد البشرية تفتح users وتُرفض من audit فتبقى في لوحة المعلومات', (await open('hr', 'dashboard/users')) === 'users' && (await open('hr', 'dashboard/audit')) === 'overview');
    check('وقسم غير موجود يبقى في لوحة المعلومات', (await open('sysadmin', 'dashboard/nothing')) === 'overview');
  }

  console.log('\nمدير الموارد البشرية:');
  await as(browser, 'hr', async (page, admin) => {
    check('يفتح تبويب المستخدمين', await openUsers(page));
    const opts = await inviteOptions(page);
    check('يدعو الموظفين دون المناصب العليا وحدهم', opts.length === 7 && !['المدير التنفيذي', 'مدير النظام', 'مدير الموارد البشرية', 'العميل', 'مستفيد'].some(l => opts.includes(l)) && ['عضو الفريق', 'مدير المشاريع', 'المدير المالي', 'المدير الطبي', 'مدير علاقات العملاء', 'مشرف', 'مراجع محتوى'].every(l => opts.includes(l)), opts.join('،'));
    const rows = await page.$$eval('#usersBody tr', trs => trs.map(t => t.querySelector('.c-role').textContent));
    check('يرى المنسوبين ولا يرى العملاء ولا المستفيدين', rows.length >= 3 && !rows.includes('العميل') && !rows.includes('مستفيد'), rows.join('،'));
    check('بلا قوائم تعديل الدور ولا أزرار إدارة', await page.locator('#usersBody select').count() === 0 && await page.locator('#usersBody .icon-btn-sm[title="حذف الحساب نهائياً"]').count() === 0);
    check('شارته مدير الموارد البشرية', (await page.textContent('#umRolePill')) === 'مدير الموارد البشرية');
    check('لا يطلب إحصاءات المنصة', !admin.some(b => b.includes('"stats"')));
    check('لا يرى تبويبات الرسائل والتذاكر والاستبيانات', await page.locator('.dash-tab[data-tab="messages"]:visible, .dash-tab[data-tab="experience"]:visible, .dash-tab[data-tab="audit"]:visible').count() === 0);
    check('رابط مساحة العمل ظاهر', await page.locator('#userMenu .ws-only').evaluate(e => !e.hidden));
  });

  console.log('\nمدير علاقات العملاء:');
  await as(browser, 'crm', async (page) => {
    check('يفتح تبويب المستخدمين', await openUsers(page));
    const opts = await inviteOptions(page);
    check('يدعو العميل وحده', opts.length === 1 && opts[0] === 'العميل', opts.join('،'));
    const rows = await page.$$eval('#usersBody tr', trs => trs.map(t => t.querySelector('.c-role').textContent));
    check('يرى العملاء وحدهم', rows.length >= 1 && rows.every(r => r === 'العميل'), rows.join('،'));
    check('شارته مدير علاقات العملاء', (await page.textContent('#umRolePill')) === 'مدير علاقات العملاء');
  });

  console.log('\nعضو فريق (بلا صلاحيات إدارة):');
  await as(browser, 'team_member', async (page) => {
    check('لا تبويب مستخدمين', !(await openUsers(page)));
    check('شارته عضو الفريق', (await page.textContent('#umRolePill')) === 'عضو الفريق');
    check('رابط مساحة العمل ظاهر', await page.locator('#userMenu .ws-only').evaluate(e => !e.hidden));
  });

  console.log('\nمستفيد عادي:');
  await as(browser, 'user', async (page) => {
    check('لا تبويب مستخدمين', !(await openUsers(page)));
    check('شارته مستفيد', (await page.textContent('#umRolePill')) === 'مستفيد');
    check('لا رابط لمساحة العمل', await page.locator('#userMenu .ws-only').evaluate(e => e.hidden));
  });

  await browser.close();
  purge();
  console.log(`\n${failed === 0 ? '✓ كل الفحوص ناجحة' : '✗ فشل ' + failed}  (${passed} نجح)`);
  process.exit(failed === 0 ? 0 : 1);
})().catch(e => { console.error(e); try { purge(); } catch (_) {} process.exit(1); });
