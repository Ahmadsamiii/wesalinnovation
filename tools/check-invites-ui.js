/* ==========================================================================
 *  وصال: فحص الدعوات في المتصفح (تبويب «المستخدمون والأدوار» والتسجيل بالدعوة)
 *
 *  على نسخة محلية بقاعدة تجريبية فقط (تنشئ حسابات الفحص ودعواته وتحذفها). من جذر المستودع:
 *      php -S 127.0.0.1:8080 &
 *      NODE_PATH="$(npm root -g)" node tools/check-invites-ui.js
 *
 *  يتحقق من:
 *    - نموذج الدعوة: الاسم الكامل بالعربية والجوال والبريد والدور، وأخطاؤه تُعلَّم على حقلها
 *    - الجدول: المدعو وجواله ودوره وحالته وتاريخ الإرسال و«صالحة حتى» و«المتبقي» بصيغ
 *      العدد الصحيحة، وإعادة الإرسال والحذف بتأكيد، والدعوة المقبولة بلا إجراءات
 *    - رابط الدعوة: الاسم والجوال والبريد مقفلة من الدعوة، والحساب يُنشأ بها
 *    - عنوان التبويب ومسميات «المستخدمون والأدوار» و«تذاكر الدعم الفني»
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
const MAIL = '@check-invites-ui.invalid';
const PASS = 'Passw0rd1';

let passed = 0, failed = 0;
const check = (name, ok, extra = '') => { ok ? passed++ : failed++; console.log(`  ${ok ? '✓' : '✗'} ${name}${extra && !ok ? '  | ' + extra : ''}`); };

const php = code => execFileSync('php', ['-r', `require '${ROOT}/api/db.php'; ensureSchema(); ${code}`], { cwd: ROOT, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] });
const purge = () => php(`
  $ids = db()->query("SELECT id FROM users WHERE email LIKE '%${MAIL}'")->fetchAll(PDO::FETCH_COLUMN);
  if ($ids) { $in = implode(',', array_map('intval', $ids)); db()->exec("DELETE FROM auth_sessions WHERE user_id IN ($in)"); db()->exec("DELETE FROM users WHERE id IN ($in)"); }
  db()->exec("DELETE FROM invites WHERE email LIKE '%${MAIL}'");
  db()->exec("DELETE FROM audit_log WHERE target LIKE '%${MAIL}'");
  db()->exec("DELETE FROM rate_limits WHERE bucket IN ('m:login','m:reg','m:invite')");`);
const sql = q => php(`echo json_encode(db()->query(${JSON.stringify(q)})->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);`);
/** دعوة مباشرة في القاعدة بحالة ونهاية صلاحية محددتين (ثوانٍ من الآن) */
const seedInvite = (key, name, phone, status, expiresIn) => php(`
  db()->prepare("INSERT INTO invites (name,phone,email,role_target,org_role_target,token,invited_by,status,created_at,expires_at)
                 VALUES (?,?,?,'user','team_member',?,1,?,NOW(),FROM_UNIXTIME(?))")
    ->execute(['${name}','${phone}','${key}${MAIL}',bin2hex(random_bytes(24)),'${status}',time()+(${expiresIn})]);`);
const setExpiry = (key, secs) => php(`db()->exec("UPDATE invites SET expires_at=FROM_UNIXTIME(" . (time()+(${secs})) . ") WHERE email='${key}${MAIL}'");`);

async function rowOf(page, email) {
  return page.evaluate(e => {
    const tr = Array.from(document.querySelectorAll('#invBody tr')).find(t => (t.querySelector('.c-e') || {}).textContent === e);
    if (!tr) return null;
    const td = Array.from(tr.children).map(c => c.textContent.trim());
    return { name: tr.querySelector('.c-n').textContent, cells: td, actions: Array.from(tr.querySelectorAll('.c-act button')).map(b => b.getAttribute('aria-label')) };
  }, email);
}
async function reloadInvites(page) {
  await page.evaluate(() => loadInvites());
  await page.waitForTimeout(400);
}

(async () => {
  purge();
  php(`[$r,$o]=roleColumns('sysadmin'); db()->prepare('INSERT INTO users (name,email,phone,pref,pass_hash,role,org_role,tokens,tokens_at,created_at) VALUES (?,?,?,?,?,?,?,30,NOW(),NOW())')
    ->execute(['مدير الفحص','admin${MAIL}','0577000001','simple',password_hash('${PASS}',PASSWORD_DEFAULT),$r,$o]);`);
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ locale: 'ar-SA', viewport: { width: 1280, height: 900 } });
  const page = await ctx.newPage();
  const dialogs = [];
  page.on('dialog', d => { dialogs.push(d.message()); d.accept(); });
  await page.request.post(`${BASE}/api/auth.php`, { data: { action: 'login', email: `admin${MAIL}`, password: PASS } });
  await page.goto(`${BASE}/#dashboard`, { waitUntil: 'domcontentloaded' });
  await page.waitForFunction(() => typeof USER !== 'undefined' && USER && document.getElementById('umName') && document.getElementById('umName').textContent);

  console.log('\nالعنوان والمسميات:');
  check('عنوان التبويب «وصال | نفهمك ونسهل وصولك»', (await page.title()) === 'وصال | نفهمك ونسهل وصولك', await page.title());
  check('الوصف ما زال يحمل الكلمات المفتاحية', (await page.getAttribute('meta[name=description]', 'content')).includes('الأشخاص ذوي الإعاقة'));
  const tab = page.locator('.dash-tab[data-tab="users"]');
  check('تبويب «المستخدمون والأدوار»', (await tab.locator('.sb-label').textContent()) === 'المستخدمون والأدوار');
  await tab.click();
  await page.waitForTimeout(500);
  check('وعنوان الصفحة في الشريط العلوي يتبعه', (await page.textContent('#dashTitle')) === 'المستخدمون والأدوار');
  check('رأس طابور التذاكر «تذاكر الدعم الفني»', await page.locator('h3', { hasText: 'تذاكر الدعم الفني' }).count() === 1 && await page.locator('h3', { hasText: 'طابور تذاكر الدعم' }).count() === 0);

  console.log('\nنموذج الدعوة:');
  await page.locator('#dp-users .tbl-actions button', { hasText: 'دعوة' }).click();
  check('الحقول الأربعة بعناوينها', await page.getByLabel('الاسم الكامل بالعربية').count() === 1 && await page.getByLabel('رقم الجوال', { exact: true }).count() >= 1
        && await page.locator('#invEmail').count() === 1 && await page.locator('#invRoleSel').count() === 1);
  check('التركيز يبدأ من الاسم', (await page.evaluate(() => document.activeElement.id)) === 'invName');
  check('ملاحظة الصلاحية ظاهرة', (await page.textContent('#invTtlNote')).includes('15 يوماً'));
  await page.fill('#invName', 'محمد العتيبي');
  await page.fill('#invPhone', '0581234567');
  await page.fill('#invEmail', `new${MAIL}`);
  await page.click('#invSendBtn');
  check('اسم من كلمتين يُرفض ويُعلَّم حقله', (await page.getAttribute('#invName', 'aria-invalid')) === 'true' && (await page.evaluate(() => document.activeElement.id)) === 'invName');
  await page.fill('#invName', 'محمد عبدالله العتيبي');
  await page.fill('#invPhone', '0123');
  await page.click('#invSendBtn');
  check('جوال غير صحيح يُرفض ويُعلَّم حقله', (await page.getAttribute('#invPhone', 'aria-invalid')) === 'true');
  await page.fill('#invPhone', '+966 58 123 4567');
  await page.selectOption('#invRoleSel', 'team_member');
  await page.click('#invSendBtn');
  await page.waitForFunction(m => Array.from(document.querySelectorAll('#invBody .c-e')).some(e => e.textContent === m), `new${MAIL}`, { timeout: 15000 })
    .catch(async e => { console.log('    toasts:', await page.$$eval('.toast', ts => ts.map(t => t.textContent).join(' / '))); throw e; });
  let r = await rowOf(page, `new${MAIL}`);
  check('الدعوة الجديدة في الجدول بالاسم والجوال والدور', r && r.name === 'محمد عبدالله العتيبي' && r.cells[1] === '0581234567' && r.cells[2] === 'عضو الفريق', JSON.stringify(r));
  check('حالتها «بانتظار القبول» والمتبقي «15 يوماً»', r && r.cells[3] === 'بانتظار القبول' && r.cells[6] === '15 يوماً', JSON.stringify(r && r.cells));
  check('وتاريخ «صالحة حتى» بعد 15 يوماً', r && r.cells[5] === await page.evaluate(() => fmtDate(Date.now() + 15 * 864e5)), JSON.stringify(r && r.cells));
  check('وإجراءا إعادة الإرسال والحذف باسم المدعو', r && r.actions.join('|') === 'إعادة إرسال الدعوة: محمد عبدالله العتيبي|حذف الدعوة: محمد عبدالله العتيبي', JSON.stringify(r && r.actions));
  check('والنموذج يُفرَّغ ويُغلق', (await page.inputValue('#invName')) === '' && !(await page.locator('#inviteBox').isVisible()));
  const heads = await page.$$eval('#invBody', b => Array.from(b[0].closest('table').querySelectorAll('th')).map(t => t.textContent));
  check('رؤوس الجدول', heads.join('،') === 'المدعو،الجوال،الدور،الحالة،تاريخ الإرسال،صالحة حتى،المتبقي،إجراءات', heads.join('،'));

  console.log('\nالمتبقي والحالات:');
  seedInvite('two', 'سعد خالد الفاحص', '0581000002', 'sent', 2 * 86400 + 600);
  seedInvite('one', 'منى علي الفاحصة', '0581000003', 'sent', 86400 + 600);
  seedInvite('hours', 'هند سعد الفاحصة', '0581000004', 'sent', 5 * 3600);
  seedInvite('five', 'فهد ناصر الفاحص', '0581000005', 'sent', 5 * 86400);
  seedInvite('old', 'ريم فهد الفاحصة', '0581000006', 'sent', -3600);
  seedInvite('done', 'نورة سالم الفاحصة', '0581000007', 'accepted', 5 * 86400);
  await reloadInvites(page);
  const left = async k => ((await rowOf(page, k + MAIL)) || { cells: [] }).cells[6];
  check('يومان', (await left('two')) === 'يومان', await left('two'));
  check('يوم واحد', (await left('one')) === 'يوم واحد', await left('one'));
  check('أقل من يوم', (await left('hours')) === 'أقل من يوم', await left('hours'));
  check('5 أيام', (await left('five')) === '5 أيام', await left('five'));
  r = await rowOf(page, `old${MAIL}`);
  check('المنتهية: «منتهية» بلا متبقٍ، ويمكن إعادة إرسالها وحذفها', r && r.cells[3] === 'منتهية' && r.cells[6] === '-' && r.actions.length === 2, JSON.stringify(r));
  r = await rowOf(page, `done${MAIL}`);
  check('المقبولة: «مقبولة» بلا صلاحية ولا متبقٍ ولا إجراءات', r && r.cells[3] === 'مقبولة' && r.cells[5] === '-' && r.cells[6] === '-' && r.actions.length === 0 && r.cells[7] === '-', JSON.stringify(r));

  console.log('\nإعادة الإرسال والحذف:');
  const oldTok = JSON.parse(sql(`SELECT token FROM invites WHERE email='old${MAIL}'`))[0].token;
  dialogs.length = 0;
  await page.click(`#invBody button[aria-label="إعادة إرسال الدعوة: ريم فهد الفاحصة"]`);
  await page.waitForFunction(m => { const t = Array.from(document.querySelectorAll('#invBody tr')).find(x => (x.querySelector('.c-e') || {}).textContent === m); return t && t.children[3].textContent === 'بانتظار القبول'; }, `old${MAIL}`, { timeout: 15000 });
  r = await rowOf(page, `old${MAIL}`);
  check('تُطلب الموافقة قبل إعادة الإرسال', dialogs.length === 1 && dialogs[0].includes('15 يوماً') && dialogs[0].includes('يتوقف الرابط السابق'), dialogs.join(' / '));
  check('وبعدها تعود «بانتظار القبول» بـ15 يوماً ورابط جديد', r && r.cells[6] === '15 يوماً' && JSON.parse(sql(`SELECT token FROM invites WHERE email='old${MAIL}'`))[0].token !== oldTok, JSON.stringify(r));
  dialogs.length = 0;
  await page.click(`#invBody button[aria-label="حذف الدعوة: سعد خالد الفاحص"]`);
  await page.waitForFunction(m => !Array.from(document.querySelectorAll('#invBody .c-e')).some(e => e.textContent === m), `two${MAIL}`, { timeout: 15000 });
  check('الحذف بعد موافقة يزيل الصف والدعوة', dialogs.length === 1 && dialogs[0].startsWith('حذف دعوة سعد خالد الفاحص') && sql(`SELECT id FROM invites WHERE email='two${MAIL}'`) === '[]');

  console.log('\nالتسجيل برابط الدعوة:');
  const tok = JSON.parse(sql(`SELECT token FROM invites WHERE email='new${MAIL}'`))[0].token;
  const ctx2 = await browser.newContext({ locale: 'ar-SA', viewport: { width: 390, height: 844 } });
  const p2 = await ctx2.newPage();
  await p2.goto(`${BASE}/?invite=${tok}`, { waitUntil: 'domcontentloaded' });
  await p2.waitForFunction(() => document.getElementById('rgInvName') && document.getElementById('rgInvName').value);
  check('الاسم الكامل ظاهر ومقفل، وخانتا الاسم الأول والأخير مخفيتان', (await p2.inputValue('#rgInvName')) === 'محمد عبدالله العتيبي'
        && await p2.getAttribute('#rgInvName', 'readonly') !== null && !(await p2.locator('#rgFirst').isVisible()));
  check('الجوال والبريد من الدعوة ومقفلان', (await p2.inputValue('#rgPhone')) === '0581234567' && (await p2.inputValue('#rgEmail')) === `new${MAIL}`
        && await p2.evaluate(() => document.getElementById('rgPhone').readOnly && document.getElementById('rgEmail').readOnly));
  check('بلا تمرير أفقي على الجوال', await p2.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth));
  await p2.fill('#rgFirstEn', 'Mohammed');
  await p2.fill('#rgLastEn', 'Alotaibi');
  await p2.fill('#rgDob', '1991-03-04');
  await p2.fill('#rgPass', PASS);
  await p2.fill('#rgPass2', PASS);
  await p2.check('#rgTerms');
  await p2.click('#rgBtn');
  await p2.waitForFunction(() => typeof USER !== 'undefined' && USER && USER.email, null, { timeout: 15000 }).catch(() => {});
  const u = JSON.parse(sql(`SELECT name, phone, org_role FROM users WHERE email='new${MAIL}'`))[0];
  check('يُنشأ الحساب باسم الدعوة وجوالها ودورها', u && u.name === 'محمد عبدالله العتيبي' && u.phone === '0581234567' && u.org_role === 'team_member', JSON.stringify(u));
  await ctx2.close();
  await reloadInvites(page);
  r = await rowOf(page, `new${MAIL}`);
  check('وتصير في الجدول «مقبولة» بلا إجراءات', r && r.cells[3] === 'مقبولة' && r.actions.length === 0, JSON.stringify(r));

  console.log('\nرابط منتهٍ:');
  setExpiry('five', -60);
  const tok5 = JSON.parse(sql(`SELECT token FROM invites WHERE email='five${MAIL}'`))[0].token;
  const ctx3 = await browser.newContext({ locale: 'ar-SA' });
  const p3 = await ctx3.newPage();
  await p3.goto(`${BASE}/?invite=${tok5}`, { waitUntil: 'domcontentloaded' });
  await p3.waitForSelector('.toast', { timeout: 10000 }).catch(() => {});
  const toasts = await p3.$$eval('.toast', ts => ts.map(t => t.textContent).join(' / '));
  check('رسالة واضحة: انتهت الصلاحية واطلب إعادة الإرسال', toasts.includes('انتهت صلاحية هذه الدعوة'), toasts);
  await ctx3.close();

  await browser.close();
  purge();
  console.log(`\n${failed === 0 ? '✓ كل الفحوص ناجحة' : '✗ فشل ' + failed}  (${passed} نجح)`);
  process.exit(failed === 0 ? 0 : 1);
})().catch(e => { console.error(e); try { purge(); } catch (_) {} process.exit(1); });
