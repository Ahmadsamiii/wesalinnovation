/* ==========================================================================
 *  وصال — فحص الخروج التلقائي في المتصفح (index.html)
 *
 *  على نسخة محلية فقط، لا الإنتاج. من جذر المستودع:
 *      php -S 127.0.0.1:8080                 # خادم محلي بقاعدة تجريبية
 *      npm i -g playwright && npx playwright install chromium   # مرة واحدة
 *      NODE_PATH="$(npm root -g)" node tools/check-session-ui.js
 *
 *  ينشئ الحسابات التجريبية بكلمات مرور جديدة (php tools/seed-demo.php)، ثم
 *  يشغّل الصفحة بساعة يتحكم بها: الحركة تعيد العدّ، والتنبيه قبل دقيقتين
 *  وتمديده بزر أو حركة، والخروج ومسح بيانات الحساب، ومهلة الفريق الأقصر،
 *  والتبويبات، والاستطلاع الآلي، ونوم الجهاز، ولوحة المفاتيح، والقراءة
 *  الصوتية، والجلسة المفقودة، وفصل المحادثات بين الحسابات، والإنجليزية.
 *  منطق الخادم نفسه يفحصه tools/check-session.php.
 * ========================================================================== */

const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const path = require('path');

const BASE = process.env.BASE || 'http://127.0.0.1:8080';
if (!/^http:\/\/(127\.0\.0\.1|localhost)(:\d+)?$/.test(BASE)) {
  console.error('يعمل على خادم محلي فقط (BASE=http://127.0.0.1:PORT).');
  process.exit(2);
}

const seed = execFileSync('php', [path.join(__dirname, 'seed-demo.php')], { encoding: 'utf8' });
const pass = {};
for (const m of seed.matchAll(/البريد:\s+(\S+)\s*\n\s*كلمة المرور:\s+(\S+)/gu)) pass[m[1]] = m[2];
const USER = 'demo.user@wesalinnovation.sa', STAFF = 'demo.admin@wesalinnovation.sa', OTHER = 'demo.mod@wesalinnovation.sa';
if (!pass[USER] || !pass[STAFF] || !pass[OTHER]) {
  console.error('تعذّر إنشاء الحسابات التجريبية:\n' + seed);
  process.exit(2);
}

const MIN = 60_000;
let passed = 0, failed = 0;
const check = (name, ok, extra = '') => { ok ? passed++ : failed++; console.log(`  ${ok ? '✓' : '✗'} ${name}${extra && !ok ? '  | ' + extra : ''}`); };

const api = (page, body) => page.evaluate(async (b) =>
  (await fetch('api/auth.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(b) })).json(), body);
async function open(ctx) {
  const page = await ctx.newPage();
  await page.clock.install();
  await page.goto(BASE + '/');
  await page.waitForFunction(() => typeof idleTick === 'function');
  return page;
}
async function signIn(ctx, email) {
  const page = await open(ctx);
  await api(page, { action: 'login', email, password: pass[email] });
  await page.reload();
  await page.waitForFunction(() => document.getElementById('navUser')?.classList.contains('on'));
  return page;
}
const warned = (p) => p.evaluate(() => !document.getElementById('idleWarn').hidden);
const signedIn = (p) => p.evaluate(() => !!document.getElementById('navUser')?.classList.contains('on'));
async function loginNotice(p) {
  await p.waitForFunction(() => document.getElementById('pg-login')?.classList.contains('active')
    && (document.getElementById('loginOk')?.textContent || '').length > 0, null, { timeout: 8000 }).catch(() => {});
  return (await p.textContent('#loginOk').catch(() => '')) || '';
}

(async () => {
  const browser = await chromium.launch();
  const context = () => browser.newContext({ locale: 'ar-SA' });
  let ctx, p;
  console.log('فحص الخروج التلقائي في المتصفح\n');

  // المستفيد: 30 دقيقة
  ctx = await context();
  p = await signIn(ctx, USER);
  await p.evaluate(() => { go('dashboard'); localStorage.setItem('wesal_hist', '[{"role":"user","text":"x"}]'); });
  await p.clock.runFor(20 * MIN);
  await p.mouse.move(200, 200); await p.mouse.move(260, 240);
  await p.clock.runFor(20 * MIN);
  check('تحريك الفأرة يعيد العدّ (40 دقيقة منذ الدخول، 20 منذ الحركة)', !(await warned(p)) && await signedIn(p));
  await p.clock.runFor(8 * MIN + 2000);
  const d = await p.evaluate(() => ({ role: idleWarn.getAttribute('role'), focus: document.activeElement.id, desc: idleDesc.textContent }));
  check('التنبيه عند الدقيقة 28، والتركيز على «أبقني متصلاً»', await warned(p) && d.role === 'alertdialog' && d.focus === 'idleStay', JSON.stringify(d));
  check('التنبيه يذكر المدة الفعلية', /بعد 30 دقيقة دون نشاط/.test(d.desc), d.desc);
  await p.keyboard.press('Enter'); await p.waitForTimeout(300);
  await p.clock.runFor(27 * MIN);
  check('Enter على الزر يمدّد الجلسة', !(await warned(p)) && await signedIn(p));
  await p.clock.runFor(1 * MIN + 2000);
  await p.mouse.move(420, 300); await p.waitForTimeout(300);
  check('تحريك الفأرة أثناء التنبيه يمدّدها أيضاً', !(await warned(p)) && await signedIn(p));
  await p.clock.runFor(29 * MIN + 32_000);
  check('قارئ الشاشة يسمع تنبيه الثلاثين ثانية', /30 ثانية/.test(await p.textContent('#idleLive')));
  await p.clock.runFor(30_000);
  const notice = await loginNotice(p);
  const left = await p.evaluate(() => ({ s: localStorage.getItem('wesal_session'), h: localStorage.getItem('wesal_hist') }));
  const me = await api(p, { action: 'me' });
  check('الخروج عند الدقيقة 30 وصفحة الدخول تذكر السبب', /بعد 30 دقيقة دون نشاط/.test(notice), notice);
  check('مُسحت بيانات الحساب من المتصفح', left.s === null && left.h === null);
  check('وانتهت الجلسة على الخادم فعلاً', me.ok === false && me.guest === true);
  await ctx.close();

  // فريق المنصة: 15 دقيقة
  ctx = await context();
  p = await signIn(ctx, STAFF);
  await p.clock.runFor(13 * MIN + 2000);
  check('مدير النظام: التنبيه عند الدقيقة 13 ويذكر 15', await warned(p) && /15 دقيقة/.test(await p.textContent('#idleDesc')));
  await ctx.close();

  // تبويبان
  ctx = await context();
  const a = await signIn(ctx, USER);
  const b = await open(ctx);
  await b.waitForFunction(() => document.getElementById('navUser')?.classList.contains('on'));
  for (let i = 0; i < 4; i++) { await a.mouse.move(100 + i * 10, 100); await a.clock.runFor(10 * MIN); await b.clock.runFor(10 * MIN); }
  check('العمل في تبويب يُبقي الآخر متصلاً', !(await warned(b)) && await signedIn(b));
  await a.evaluate(() => doLogout());
  await b.waitForFunction(() => !document.getElementById('navUser')?.classList.contains('on'), null, { timeout: 8000 }).catch(() => {});
  check('الخروج من تبويب يُخرج الآخر', !(await signedIn(b)));
  await ctx.close();

  // الاستطلاع الآلي، والجلسة المفقودة
  ctx = await context();
  p = await signIn(ctx, USER);
  const polls = [];
  p.on('request', (r) => { if (r.url().includes('notifications.php')) polls.push(r.headers()['x-wesal-idle'] || '-'); });
  await p.clock.runFor(3 * MIN + 1000);
  await p.mouse.move(300, 300);
  await p.clock.runFor(1 * MIN);
  check('الاستطلاع بلا حركة يرسل X-Wesal-Idle، وبعد الحركة لا', polls.slice(0, 3).every((v) => v === '1') && polls[3] === '-', polls.join(','));
  await ctx.clearCookies();
  await p.clock.runFor(61_000);
  const gone = await loginNotice(p);
  check('جلسة فُقدت على الخادم تُكتشف خلال دقيقة', /انتهت جلستك/.test(gone) && !(await signedIn(p)), gone);
  await ctx.close();

  // نوم الجهاز، ولوحة المفاتيح، والقراءة الصوتية
  ctx = await context();
  p = await signIn(ctx, USER);
  await p.clock.fastForward(45 * MIN);
  await p.clock.runFor(1500);
  check('بعد نوم الجهاز أطول من المهلة يخرج مع أول ثانية', /دون نشاط/.test(await loginNotice(p)));
  await ctx.close();
  ctx = await context();
  p = await signIn(ctx, USER);
  for (let i = 0; i < 5; i++) { await p.clock.runFor(10 * MIN); await p.keyboard.press('Tab'); }
  check('لوحة المفاتيح وحدها تُبقي الجلسة', !(await warned(p)) && await signedIn(p));
  await p.evaluate(() => Object.defineProperty(window.speechSynthesis, 'speaking', { get: () => true, configurable: true }));
  await p.clock.runFor(40 * MIN);
  check('الاستماع لإجابة تُقرأ صوتياً نشاط', !(await warned(p)) && await signedIn(p));
  await ctx.close();

  // المحادثات المحفوظة لكل حساب
  ctx = await context();
  p = await signIn(ctx, USER);
  await p.evaluate(() => saveChatEntry('سؤال خاص بالحساب الأول', 'جواب'));
  await p.evaluate(() => doLogout());
  await p.waitForFunction(() => !document.getElementById('navUser')?.classList.contains('on'), null, { timeout: 8000 }).catch(() => {});
  await api(p, { action: 'login', email: OTHER, password: pass[OTHER] });
  await p.reload();
  await p.waitForFunction(() => document.getElementById('navUser')?.classList.contains('on'));
  const other = await p.evaluate(() => { go('dashboard'); openDashTab('chats'); return document.getElementById('chatsList')?.textContent || ''; });
  check('حساب آخر على المتصفح نفسه لا يرى محادثات الأول', !/الحساب الأول/.test(other));
  await ctx.close();

  // الزائر، والإنجليزية، وإطار المعاينة
  ctx = await context();
  p = await open(ctx);
  check('لا خانة «أبقني مسجّلاً» في صفحة الدخول', await p.evaluate(() => !document.getElementById('liRemember')));
  await p.clock.runFor(40 * MIN);
  check('الزائر لا يرى التنبيه', !(await warned(p)));
  await ctx.close();
  ctx = await context();
  p = await signIn(ctx, USER);
  await p.evaluate(() => setLang('en'));
  await p.clock.runFor(28 * MIN + 2000);
  const en = await p.evaluate(() => [idleTitle.textContent, idleDesc.textContent, idleStay.textContent]);
  check('التنبيه بالإنجليزية حين تكون الواجهة بها', en[0] === 'Are you still there?' && /30 minutes/.test(en[1]) && en[2] === 'Keep me signed in', en.join(' / '));
  await ctx.close();
  ctx = await context();
  p = await signIn(ctx, STAFF);
  await p.evaluate(() => { const f = document.createElement('iframe'); f.id = 'pv'; f.src = '/?lp-preview=1'; f.style.cssText = 'position:fixed;top:0;left:0;width:600px;height:400px'; document.body.appendChild(f); });
  await p.waitForFunction(() => document.getElementById('pv')?.contentWindow?.lpPreviewBoot);
  await p.clock.runFor(10 * MIN);
  await p.mouse.move(100, 100); await p.mouse.move(150, 160);
  await p.clock.runFor(10 * MIN);
  check('العمل داخل إطار معاينة صفحة الهبوط نشاط', !(await warned(p)) && await signedIn(p));
  await ctx.close();

  await browser.close();
  console.log(`\n${failed ? `✗ فشل ${failed} من ${passed + failed}` : `✓ كل الفحوص ناجحة (${passed})`}`);
  process.exit(failed ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
