/* ==========================================================================
 *  وصال: فحص شاشة الدخول الموحدة (login.html) في المتصفح
 *
 *  على نسخة محلية بقاعدة تجريبية فقط (تنشئ حسابات الفحص وتحذفها). من جذر المستودع:
 *      php -S 127.0.0.1:8080 &
 *      NODE_PATH="$(npm root -g)" node tools/check-login-ui.js
 *
 *  خادم PHP المدمج لا يقرأ .htaccess، فتُفتح الصفحة بـ /login.html (ملفها الفعلي، وقاعدة
 *  /login إلى ملفها يفحصها tools/check-routes.sh على Apache). يتحقق:
 *    - أخطاء النموذج مقروءة ومعلنة (role=alert) ويذهب إليها التركيز، وإظهار كلمة المرور
 *    - الوجهة بعد الدخول: عادي إلى المحادثة، صاحب دور في مساحة العمل إليها، مدير النظام يختار
 *      قبل تفعيل الدخول الموحد ويدخل مساحة العمل مباشرة بعده (نُحاكي ردّ unified من الخادم)،
 *      وكلمة المرور المؤقتة إلى المحادثة دائماً
 *    - next لا يُقبل إلا على هذا الموقع (مسار أو مضيف واحد)، وأي رابط غريب أو بمخطط
 *      javascript: يُهمَل فلا تتحول الشاشة إلى بابٍ يوجّه الناس إلى موقع آخر بعد دخولهم
 *    - جلسة قائمة تعود فوراً إلى next، والخروج من الشاشة، وانقطاع الاتصال، وعرض 320 بكسل
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
const MAIL = '@check-login-ui.invalid';
const PASS = 'Passw0rd1';
const PAGE = `${BASE}/login.html`;

let passed = 0, failed = 0;
const check = (name, ok, extra = '') => { ok ? passed++ : failed++; console.log(`  ${ok ? '✓' : '✗'} ${name}${extra && !ok ? '  | ' + extra : ''}`); };

const php = code => execFileSync('php', ['-r', `require '${ROOT}/api/db.php'; ensureSchema(); ${code}`], { cwd: ROOT, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] });
const purge = () => php(`
  $ids = db()->query("SELECT id FROM users WHERE email LIKE '%${MAIL}'")->fetchAll(PDO::FETCH_COLUMN);
  if ($ids) { $in = implode(',', array_map('intval', $ids)); db()->exec("DELETE FROM auth_sessions WHERE user_id IN ($in)"); db()->exec("DELETE FROM users WHERE id IN ($in)"); }
  db()->exec("DELETE FROM rate_limits WHERE bucket IN ('m:login','m:reg')");`);
const resetLimits = () => php(`db()->exec("DELETE FROM rate_limits WHERE bucket IN ('m:login','m:reg')");`);
const seed = (key, eff, n, mustChange = 0) => php(`
  [$role, $org] = roleColumns('${eff}');
  db()->prepare('INSERT INTO users (name,email,phone,pref,pass_hash,role,org_role,must_change_pw,tokens,tokens_at,created_at) VALUES (?,?,?,?,?,?,?,?,30,NOW(),NOW())')
    ->execute(['صاحب ${key}', '${key}${MAIL}', '05' . str_pad((string)(30000000 + ${n} * 7919), 8, '0', STR_PAD_LEFT), 'simple', password_hash('${PASS}', PASSWORD_DEFAULT), $role, $org, ${mustChange}]);`);

/** صفحة بمتصفح جديد. وجهات الانتقال تُعترَض فلا تحتاج مساحة العمل ولا المحادثة الفعلية. */
async function open(browser, url, viewport = { width: 1000, height: 800 }) {
  const ctx = await browser.newContext({ locale: 'ar-SA', viewport });
  const page = await ctx.newPage();
  const landed = [];
  await page.route(u => /^\/(workspace|chat)(\/|$)/.test(u.pathname) && u.pathname !== '/login.html', route => { landed.push(route.request().url()); route.fulfill({ status: 200, contentType: 'text/html', body: '<title>وجهة</title>' }); });
  await page.route(u => u.port === '8081', route => { landed.push(route.request().url()); route.fulfill({ status: 200, contentType: 'text/html', body: '<title>نطاق فرعي</title>' }); });
  await page.route(u => /evil\.example$/.test(u.hostname), route => { landed.push(route.request().url()); route.fulfill({ status: 200, contentType: 'text/html', body: 'evil' }); });
  await page.goto(url, { waitUntil: 'domcontentloaded' });
  return { ctx, page, landed };
}
async function login(page, key) {
  await page.fill('#email', `${key}${MAIL}`);
  await page.fill('#pass', PASS);
  await page.click('#go');
}
const finalPath = page => { const u = new URL(page.url()); return u.pathname + u.search; };

(async () => {
  purge();
  seed('plain', 'user', 1);
  seed('team', 'team_member', 2);
  seed('admin', 'sysadmin', 3);
  seed('temp', 'team_member', 4, 1);

  const browser = await chromium.launch();

  console.log('\nالنموذج والأخطاء:');
  {
    const { ctx, page } = await open(browser, PAGE);
    check('العنوان والوسم noindex', (await page.title()).includes('تسجيل الدخول') && (await page.getAttribute('meta[name=robots]', 'content')) === 'noindex');
    check('الحقلان بعنوانيهما', await page.getByLabel('البريد الإلكتروني').count() === 1 && await page.getByLabel('كلمة المرور', { exact: true }).count() === 1);
    await page.click('#go');
    check('بريد فارغ: خطأ معلَن ومركَّز', (await page.textContent('#err')).includes('بريداً إلكترونياً صحيحاً') && (await page.evaluate(() => document.activeElement.id)) === 'err' && (await page.getAttribute('#err', 'role')) === 'alert');
    await page.fill('#email', `plain${MAIL}`);
    await page.click('#go');
    check('كلمة مرور فارغة: خطأ', (await page.textContent('#err')).includes('اكتب كلمة المرور'));
    await page.fill('#pass', 'خطأ-في-كلمة-المرور');
    await page.click('#go');
    await page.waitForFunction(() => document.getElementById('err').textContent.includes('غير صحيحة'));
    check('بيانات خاطئة: رسالة الخادم بلا كشف ما هو الخطأ', (await page.textContent('#err')).includes('البريد أو كلمة المرور غير صحيحة'));
    check('الزر يعود متاحاً بعد الخطأ', await page.locator('#go').isEnabled() && (await page.getAttribute('#go', 'aria-busy')) === 'false');
    await page.click('#showPw');
    check('إظهار كلمة المرور يغيّر النوع ويعلن الحالة', (await page.getAttribute('#pass', 'type')) === 'text' && (await page.getAttribute('#showPw', 'aria-pressed')) === 'true');
    await page.click('#showPw');
    check('وإخفاؤها يعيدها', (await page.getAttribute('#pass', 'type')) === 'password');
    const links = await page.$$eval('.links a', as => as.map(a => a.getAttribute('href')));
    check('روابط النسيان والتسجيل والعودة', links.join(' ') === '/chat#forgot /chat#register /homepage/', links.join(' '));
    await ctx.close();
  }

  console.log('\nالوجهة بعد الدخول:');
  for (const [key, want, label] of [['plain', '/chat', 'المستفيد العادي إلى المحادثة'], ['team', '/workspace/', 'صاحب دور في مساحة العمل إليها'], ['temp', '/chat', 'كلمة المرور المؤقتة إلى المحادثة ولو كان صاحب دور']]) {
    const { ctx, page } = await open(browser, PAGE);
    await login(page, key);
    await page.waitForURL(u => !u.pathname.endsWith('login.html'), { timeout: 15000 }).catch(() => {});
    check(label, finalPath(page) === want, finalPath(page));
    await ctx.close();
  }
  {
    const { ctx, page } = await open(browser, PAGE);
    await login(page, 'admin');
    await page.waitForSelector('#signedIn:not([hidden])');
    check('مدير النظام يختار: يبقى وأمامه الوجهتان واسم دوره', !(await page.locator('#toWorkspace').isHidden()) && (await page.textContent('#whoRole')) === 'مدير النظام' && (await page.getAttribute('#toWorkspace', 'href')) === '/workspace/' && (await page.getAttribute('#toChat', 'href')) === '/chat');
    check('والتركيز على أول وجهة', (await page.evaluate(() => document.activeElement.id)) === 'toWorkspace');
    await page.click('#logout');
    await page.waitForSelector('#form:not([hidden])');
    check('الخروج يعيد النموذج', await page.locator('#signedIn').isHidden());
    await ctx.close();
  }
  {
    // الدخول الموحد فعّال: الخادم يرسل unified:true، فيدخل مدير النظام مساحة العمل بلا شاشة اختيار
    const { ctx, page } = await open(browser, PAGE);
    await page.route('**/api/auth.php', async route => {
      const res = await route.fetch();
      const j = await res.json().catch(() => null);
      if (j && j.user) j.user.unified = true;
      await route.fulfill({ response: res, json: j || {} });
    });
    await login(page, 'admin');
    await page.waitForURL(u => u.pathname.endsWith('/workspace/'), { timeout: 15000 }).catch(() => {});
    check('مدير النظام مع الدخول الموحد يذهب إلى مساحة العمل مباشرة', finalPath(page) === '/workspace/', finalPath(page));
    await ctx.close();
  }
  resetLimits();

  resetLimits();
  console.log('\nوجهة العودة next:');
  const nextCase = async (label, nextParam, want, key = 'plain') => {
    const { ctx, page, landed } = await open(browser, `${PAGE}?next=${encodeURIComponent(nextParam)}`);
    await login(page, key);
    await page.waitForURL(u => !u.pathname.endsWith('login.html'), { timeout: 8000 }).catch(() => {});
    const to = page.url();
    check(label, want(to, landed), to);
    await ctx.close();
  };
  await nextCase('مسار على الموقع يُقبل', '/workspace/projects?x=1', to => to === `${BASE}/workspace/projects?x=1`);
  await nextCase('رابط كامل على المضيف نفسه (منفذ آخر) يُقبل', `http://127.0.0.1:8081/dashboard`, to => to.startsWith('http://127.0.0.1:8081/dashboard'), 'team');
  await nextCase('موقع غريب يُهمَل فلا يُوجَّه إليه', 'https://evil.example/steal', (to, landed) => !to.includes('evil.example') && !landed.some(l => l.includes('evil.example')));
  await nextCase('رابط بلا مخطط بداية // يُهمَل', '//evil.example/x', (to, landed) => !to.includes('evil.example') && !landed.some(l => l.includes('evil.example')));
  await nextCase('مخطط javascript: يُهمَل', 'javascript:alert(1)', to => !to.startsWith('javascript:') && to.endsWith('/chat'));
  await nextCase('مضيف يشبه المضيف (سابقة) يُهمَل', 'http://127.0.0.1.evil.example/x', (to, landed) => !to.includes('evil.example') && !landed.some(l => l.includes('evil.example')));
  await nextCase('كلمة المرور المؤقتة تُقدَّم على next', '/workspace/projects', to => to.endsWith('/chat'), 'temp');

  resetLimits();
  console.log('\nجلسة قائمة:');
  {
    const { ctx, page } = await open(browser, PAGE);
    await login(page, 'team');
    await page.waitForURL(u => !u.pathname.endsWith('login.html'), { timeout: 15000 }).catch(() => {});
    await page.goto(`${PAGE}?next=${encodeURIComponent('/workspace/tasks')}`, { waitUntil: 'domcontentloaded' });
    await page.waitForURL(u => u.pathname === '/workspace/tasks', { timeout: 15000 }).catch(() => {});
    check('من جاء وهو داخل يعود إلى next فوراً', finalPath(page) === '/workspace/tasks', finalPath(page));
    await ctx.close();
  }

  console.log('\nالانقطاع والعرض:');
  {
    const { ctx, page } = await open(browser, PAGE);
    await page.route('**/api/auth.php', route => route.abort());
    await login(page, 'plain');
    await page.waitForFunction(() => document.getElementById('err').textContent.includes('تعذّر الاتصال'));
    check('انقطاع الاتصال برسالة واضحة والنص باقٍ', (await page.inputValue('#email')).includes('plain') && await page.locator('#go').isEnabled());
    await ctx.close();
  }
  for (const w of [320, 390, 1280]) {
    const { ctx, page } = await open(browser, PAGE, { width: w, height: 800 });
    const o = await page.evaluate(() => ({ sw: document.documentElement.scrollWidth, cw: document.documentElement.clientWidth }));
    check(`عرض ${w} بلا تجاوز أفقي`, o.sw <= o.cw, `${o.sw}>${o.cw}`);
    await ctx.close();
  }
  {
    const { ctx, page } = await open(browser, `${PAGE}?ended=idle`);
    check('رسالة انتهاء الجلسة بالخمول تظهر معلَنة', (await page.textContent('#note')).includes('سجّلنا خروجك تلقائياً') && (await page.getAttribute('#note', 'role')) === 'status');
    await ctx.close();
  }

  await browser.close();
  purge();
  console.log(`\n${failed === 0 ? '✓ كل الفحوص ناجحة' : '✗ فشل ' + failed}  (${passed} نجح)`);
  process.exit(failed === 0 ? 0 : 1);
})().catch(e => { console.error(e); try { purge(); } catch (_) {} process.exit(1); });
