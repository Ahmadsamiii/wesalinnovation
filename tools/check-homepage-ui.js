/* ==========================================================================
 *  وصال: فحص صفحة الشركة (/homepage/) في المتصفح
 *
 *  على نسخة محلية فقط، بلا قاعدة بيانات. من جذر المستودع:
 *      php -S 127.0.0.1:8080 &
 *      NODE_PATH="$(npm root -g)" node tools/check-homepage-ui.js
 *  ولحفظ لقطات للمراجعة البصرية: SHOTS=/مسار/مجلد node tools/check-homepage-ui.js
 *
 *  يتحقق من:
 *    - سلامة التحميل: بلا أخطاء في الطرفية ولا طلبات فاشلة، وعنوان رئيسي واحد
 *    - التنقل: كل رابط داخلي له هدف، والنقر يضع الهدف تحت الرأس لا خلفه، والقسم الحالي معلَن
 *    - الرأس الذكي: يختفي عند النزول ويعود عند الصعود وبالتركيز بلوحة المفاتيح
 *    - الخدمات الثلاث بترتيبها المتفق عليه، وقسم الأعمال والأرقام مخفي بلا رابط إليه
 *    - الحركة: تعمل افتراضياً، وتتوقف بزر الإيقاف (ويُحفظ) وبتفضيل النظام «تقليل الحركة»
 *    - الجوال: بلا تمرير أفقي من 320 إلى 1440، والقائمة تُفتح بزرها وتُغلق بـEsc وتعيد التركيز
 * ========================================================================== */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const BASE = process.env.BASE || 'http://127.0.0.1:8080';
if (!/^http:\/\/(127\.0\.0\.1|localhost)(:\d+)?$/.test(BASE)) {
  console.error('يعمل على خادم محلي فقط (BASE=http://127.0.0.1:PORT).');
  process.exit(2);
}
const URL_ = `${BASE}/homepage/`;
const SHOTS = process.env.SHOTS || '';
if (SHOTS) fs.mkdirSync(SHOTS, { recursive: true });

let passed = 0, failed = 0;
const check = (name, ok, extra = '') => { ok ? passed++ : failed++; console.log(`  ${ok ? '✓' : '✗'} ${name}${extra && !ok ? '  | ' + extra : ''}`); };
const settle = (page, ms = 700) => page.waitForTimeout(ms);
const running = page => page.evaluate(() => document.getAnimations().filter(a => a.playState === 'running').length);
/* الحركة المستمرة: رسوم جارية بلا نهاية. هي التي يوقفها الزر، أما الظهور لمرة واحدة فيكتمل وينتهي */
const looping = page => page.evaluate(() => document.getAnimations().filter(a => a.playState === 'running' && a.effect && a.effect.getComputedTiming().iterations === Infinity).length);

async function open(browser, opts = {}, url = URL_) {
  const ctx = await browser.newContext({ locale: 'ar-SA', viewport: { width: 1280, height: 800 }, ...opts });
  const page = await ctx.newPage();
  const problems = [];
  page.on('console', m => { if (m.type() === 'error') problems.push('console: ' + m.text()); });
  page.on('pageerror', e => problems.push('pageerror: ' + e.message));
  page.on('response', r => { if (r.url().startsWith(BASE) && r.status() >= 400) problems.push(`${r.status()} ${r.url()}`); });
  page.on('requestfailed', r => { if (r.url().startsWith(BASE)) problems.push('failed: ' + r.url()); });
  await page.goto(url, { waitUntil: 'load' });
  await settle(page);
  return { ctx, page, problems };
}

(async () => {
  const browser = await chromium.launch();

  console.log('\nالتحميل والبنية:');
  {
    const { ctx, page, problems } = await open(browser);
    check('بلا أخطاء طرفية ولا طلبات فاشلة', problems.length === 0, problems.join(' | '));
    check('عنوان رئيسي واحد وعنوان الصفحة يذكر الاسم', (await page.locator('h1').count()) === 1 && (await page.title()).includes('وصال'));
    check('لغة الصفحة عربية واتجاهها من اليمين', (await page.getAttribute('html', 'lang')) === 'ar' && (await page.getAttribute('html', 'dir')) === 'rtl');
    check('رابط تخطي إلى المحتوى أول ما يُركَّز', await (async () => { await page.keyboard.press('Tab'); return page.evaluate(() => document.activeElement.className === 'skip'); })());
    const canonical = await page.getAttribute('link[rel=canonical]', 'href');
    check('canonical على /homepage/ قبل التبديل', canonical === 'https://wesalinnovation.sa/homepage/', canonical);
    const h2 = await page.$$eval('main section h2', hs => hs.filter(h => h.getClientRects().length).map(h => h.textContent.trim()));
    check('خمسة أقسام ظاهرة على الأقل بعناوينها', h2.length >= 5, h2.join(' | '));
    if (SHOTS) await page.screenshot({ path: path.join(SHOTS, 'home-desktop-top.png') });
    await ctx.close();
  }

  console.log('\nالخدمات والأقسام المخفية:');
  {
    const { ctx, page } = await open(browser);
    const svc = await page.$$eval('#services h3', hs => hs.map(h => h.textContent.trim()));
    check('الخدمات الثلاث بترتيبها: مساعد وصال أولاً', svc.length >= 3 && svc[0].includes('مساعد وصال') && svc[1].includes('تطوير المنصات الرقمية') && svc[2].includes('التدريب التقني'), svc.join(' | '));
    check('قسم الأعمال والأرقام مخفي', await page.locator('#proof').isHidden());
    const toProof = await page.$$eval('a[href="#proof"]', as => as.length);
    check('ولا رابط يشير إليه', toProof === 0);
    const stats = await page.evaluate(() => (Array.from(document.querySelectorAll('main section:not([hidden])')).map(s => s.textContent).join(' ').match(/[0-9٠-٩]+\s*(%|٪|\+)|(\+|\u200f\+)\s*[0-9٠-٩]+/g)) || []);
    check('لا نسب ولا «+رقم» في الأقسام الظاهرة (لا أرقام غير موثقة)', stats.length === 0, stats.join(' | '));
    const hrefs = await page.$$eval('a[href]', as => as.map(a => a.getAttribute('href')));
    check('روابط المنتجات: /chat و/login و/workspace/', ['/chat', '/login', '/workspace/'].every(h => hrefs.includes(h)));
    const missing = await page.evaluate(() => Array.from(document.querySelectorAll('a[href^="#"]')).map(a => a.getAttribute('href')).filter(h => h.length > 1 && !document.getElementById(h.slice(1))));
    check('كل رابط داخلي له هدف موجود', missing.length === 0, missing.join(' '));
    await ctx.close();
  }

  console.log('\nالتنقل والرأس الذكي:');
  {
    const { ctx, page } = await open(browser);
    const hdr = page.locator('#hdr');
    await page.click('.nav-links a[href="#services"]');
    await settle(page, 1500);
    const gap = await page.evaluate(() => document.getElementById('services').getBoundingClientRect().top - document.getElementById('hdr').getBoundingClientRect().bottom);
    check('النقر على «الخدمات» يضع القسم تحت الرأس لا خلفه', gap >= -2 && gap < 260, `gap=${gap}`);
    check('الرابط الحالي معلَن aria-current', (await page.getAttribute('.nav-links a[href="#services"]', 'aria-current')) === 'location');
    check('الرأس صلب بعد النزول', await hdr.evaluate(e => e.classList.contains('is-solid')));

    await page.mouse.wheel(0, 900); await settle(page, 900);
    check('الرأس يختفي عند النزول', await hdr.evaluate(e => e.classList.contains('is-hidden')));
    await page.mouse.wheel(0, -300); await settle(page, 900);
    check('ويعود عند الصعود', !(await hdr.evaluate(e => e.classList.contains('is-hidden'))));
    await page.mouse.wheel(0, 900); await settle(page, 900);
    await page.focus('.nav-actions a');
    await settle(page, 400);
    check('ويعود بتركيز لوحة المفاتيح على عنصر فيه', !(await hdr.evaluate(e => e.classList.contains('is-hidden'))));

    await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight)); await settle(page, 700);
    check('شريط التقدم يبلغ آخر الصفحة', await page.evaluate(() => { const p = document.getElementById('progress'); const b = p.getBoundingClientRect(); return b.width > 0 || getComputedStyle(p).transform !== 'none' || parseFloat(p.style.width || '0') > 90 || (p.style.transform || '').includes('scaleX(1'); }));
    if (SHOTS) { await page.evaluate(() => window.scrollTo(0, 0)); await settle(page, 500); await page.evaluate(() => document.getElementById('services').scrollIntoView()); await settle(page, 1200); await page.screenshot({ path: path.join(SHOTS, 'home-desktop-services.png') }); }
    await ctx.close();
  }

  console.log('\nالحركة:');
  {
    const { ctx, page } = await open(browser);
    const on = await looping(page);
    check('الحركة المستمرة تعمل افتراضياً', on > 0, `looping=${on}`);
    await page.click('#moBtn');
    await settle(page, 500);
    check('زر الإيقاف يوقفها ويعلن الحالة', (await page.locator('html').evaluate(e => e.classList.contains('mo-paused'))) && (await page.getAttribute('#moBtn', 'aria-pressed')) === 'true');
    check('ولا حركة مستمرة جارية بعد الإيقاف', (await looping(page)) === 0, `looping=${await looping(page)}`);
    await settle(page, 5000);
    check('وما بقي من الظهور لمرة واحدة ينتهي كله خلال ثوانٍ', (await running(page)) === 0, `running=${await running(page)}`);
    await page.reload({ waitUntil: 'load' }); await settle(page);
    check('والإيقاف محفوظ بعد إعادة التحميل', await page.locator('html').evaluate(e => e.classList.contains('mo-paused')) && (await looping(page)) === 0 && (await page.getAttribute('#moBtn', 'aria-pressed')) === 'true');
    await page.click('#moBtn'); await settle(page, 500);
    check('والزر نفسه يعيد الحركة المستمرة', !(await page.locator('html').evaluate(e => e.classList.contains('mo-paused'))) && (await looping(page)) > 0);
    await ctx.close();
  }
  {
    const { ctx, page } = await open(browser, { reducedMotion: 'reduce' });
    check('«تقليل الحركة» في النظام: لا رسوم جارية', (await running(page)) === 0, `running=${await running(page)}`);
    await page.click('.nav-links a[href="#about"]'); await settle(page, 500);
    const t = await page.evaluate(() => document.getElementById('about').getBoundingClientRect().top);
    check('والتنقل فوري بلا تمرير متحرك', t < 200, `top=${t}`);
    await ctx.close();
  }

  console.log('\nالعرض والجوال:');
  for (const w of [320, 375, 768, 1024, 1440]) {
    const { ctx, page } = await open(browser, { viewport: { width: w, height: 800 } });
    const o = await page.evaluate(() => ({ sw: document.documentElement.scrollWidth, cw: document.documentElement.clientWidth }));
    check(`عرض ${w} بلا تمرير أفقي`, o.sw <= o.cw, `${o.sw}>${o.cw}`);
    await ctx.close();
  }
  {
    const { ctx, page } = await open(browser, { viewport: { width: 390, height: 800 }, isMobile: true, hasTouch: true });
    check('زر القائمة ظاهر والقائمة مغلقة', await page.locator('#menuBtn').isVisible() && (await page.getAttribute('#menuBtn', 'aria-expanded')) === 'false');
    await page.tap('#menuBtn'); await settle(page, 600);
    check('يفتحها فتُعلن (aria-expanded) وتظهر الروابط', (await page.getAttribute('#menuBtn', 'aria-expanded')) === 'true' && await page.locator('.nav-links a[href="#services"]').isVisible());
    if (SHOTS) await page.screenshot({ path: path.join(SHOTS, 'home-mobile-menu.png') });
    await page.keyboard.press('Escape'); await settle(page, 500);
    check('Esc يغلقها ويعيد التركيز إلى زرها', (await page.getAttribute('#menuBtn', 'aria-expanded')) === 'false' && (await page.evaluate(() => document.activeElement.id)) === 'menuBtn');
    await page.tap('#menuBtn'); await settle(page, 500);
    await page.tap('.nav-links a[href="#method"]'); await settle(page, 1200);
    check('اختيار رابط يغلق القائمة ويصل القسم', (await page.getAttribute('#menuBtn', 'aria-expanded')) === 'false' && (await page.evaluate(() => document.getElementById('method').getBoundingClientRect().top)) < 400);
    if (SHOTS) { await page.evaluate(() => window.scrollTo(0, 0)); await settle(page, 500); await page.screenshot({ path: path.join(SHOTS, 'home-mobile-top.png') }); }
    await ctx.close();
  }

  await browser.close();
  console.log(`\n${failed === 0 ? '✓ كل الفحوص ناجحة' : '✗ فشل ' + failed}  (${passed} نجح)`);
  process.exit(failed === 0 ? 0 : 1);
})().catch(e => { console.error(e); process.exit(1); });
