/* ==========================================================================
 *  وصال: فحص صفحة أسئلة التحكيم (judges.html) في المتصفح
 *
 *  لا يحتاج قاعدة بيانات ولا مفتاح نموذج. من جذر المستودع:
 *      php -S 127.0.0.1:8080 &
 *      NODE_PATH="$(npm root -g)" node tools/check-judges-ui.js
 *
 *  خادم PHP المدمج لا يقرأ .htaccess، فتُفتح الصفحة بـ /judges.html (ملفها الفعلي، وقاعدة
 *  /judges إلى ملفها يفحصها tools/check-routes.sh على Apache). يتحقق:
 *    - الرئيسية: عشر مجموعات، وثماني شرائح شائعة، وصفحة بلا عنوان تبويب مكرر
 *    - البحث: يتسامح مع الهمزات والتاء المربوطة، ويمسحه Escape، واختصار «/»، ونص البحث
 *      يُعرض نصاً لا شيفرة (لا ينفّذ HTML مكتوباً في الرابط)
 *    - المجموعة: التصفية، وحفظ الأسئلة المفتوحة عند تبديل «اللغتان»، ونسخ الرابط،
 *      والرابط المباشر للسؤال
 *    - التدريب: كشف الجواب وحفظ التقدم في المتصفح، والعرض: التنقل بالأزرار والأسهم
 *      بحسب اتجاه اللغة، والخروج بـ Escape
 *    - الطباعة: ورق وصال الرسمي (ترويسة وتذييل ببيانات التواصل) وكل الأسئلة
 *    - الجوال 390 بكسل: لا تمرير أفقي، وشريط تبويب سفلي، ولكل زر ورابط اسم مقروء
 *    - ملف الملاحظات الخاص: إن وُجد محلياً تظهر عناصر «يُؤكَّد من الفريق»، وإن غاب
 *      تعمل الصفحة بدونها (وتسجّل 404 متوقعة لذلك الملف وحده)
 * ========================================================================== */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const BASE = process.env.BASE || 'http://127.0.0.1:8080';
if (!/^http:\/\/(127\.0\.0\.1|localhost)(:\d+)?$/.test(BASE)) {
  console.error('يعمل على خادم محلي فقط (BASE=http://127.0.0.1:PORT).');
  process.exit(2);
}
const ROOT = path.resolve(__dirname, '..');
const PAGE = `${BASE}/judges.html`;
const HAS_NOTES = fs.existsSync(path.join(ROOT, 'assets/judges-notes.js'));

let passed = 0, failed = 0;
const check = (name, ok, extra = '') => { ok ? passed++ : failed++; console.log(`  ${ok ? '✓' : '✗'} ${name}${extra && !ok ? '  | ' + extra : ''}`); };

(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, locale: 'ar-SA', permissions: ['clipboard-read', 'clipboard-write'] });
  const p = await ctx.newPage();
  const jsErrors = [];
  p.on('pageerror', e => jsErrors.push(e.message));
  const go = async (hash = '') => { await p.goto(PAGE + hash); await p.waitForTimeout(350); };
  const text = sel => p.textContent(sel);

  console.log('الرئيسية:');
  await go();
  check('عشر بطاقات مجموعات', (await p.$$eval('.ccard', e => e.length)) === 10);
  check('ثماني شرائح شائعة', (await p.$$eval('#chips .chip', e => e.length)) === 8);
  check('عنوان التبويب بالفاصل |', (await p.title()) === 'أسئلة التحكيم | وصال');
  check('الصفحة بلا فهرسة', (await p.getAttribute('meta[name=robots]', 'content')).includes('noindex'));

  console.log('البحث:');
  await p.fill('#q', 'الخصوصيه'); await p.waitForTimeout(450);
  check('التاء المربوطة والهاء سواء في البحث', /\d+ نتائج|نتيجة/.test(await text('#rcount')) && !/لا نتائج/.test(await text('#rcount')), await text('#rcount'));
  await p.keyboard.press('Escape'); await p.waitForTimeout(300);
  check('Escape يمسح البحث ويرجع للرئيسية', ['', '#/'].includes(await p.evaluate(() => location.hash)) && (await p.inputValue('#q')) === '');
  await p.evaluate(() => document.activeElement.blur());
  await p.keyboard.press('/');
  check('اختصار «/» يضع التركيز على البحث', (await p.evaluate(() => document.activeElement.id)) === 'q');
  await go('#/search/' + encodeURIComponent('<img src=x onerror="window.__xss=1">'));
  check('نص البحث لا يُنفَّذ كشيفرة', (await p.evaluate(() => window.__xss)) === undefined && (await p.$$eval('main img', e => e.length)) === 0);
  check('نص البحث يظهر كما كُتب', (await text('h1.vt')).includes('<img src=x'));

  console.log('المجموعة:');
  await go('#/c/business');
  const total = await p.$$eval('details.q', e => e.length);
  await p.click('[data-filter=hard]'); await p.waitForTimeout(100);
  const hard = await p.$$eval('details.q:not([hidden])', e => e.length);
  check('تصفية «الصعبة» تضيّق القائمة', hard > 0 && hard < total, hard + '/' + total);
  await p.click('[data-filter=all]');
  check('«تحتاج تأكيداً» تظهر مع ملف الملاحظات فقط', (await p.$$eval('[data-filter=v]', e => e.length)) === (HAS_NOTES ? 1 : 0));
  await p.click('details.q >> nth=0 >> summary');
  const firstId = await p.getAttribute('details.q >> nth=0', 'data-id');
  await p.check('#biChk'); await p.waitForTimeout(300);
  check('تبديل «اللغتان» يحفظ السؤال المفتوح', await p.$eval('#q-' + firstId, e => e.open));
  check('لغتان داخل الجواب', (await p.$$eval('#q-' + firstId + ' .ans', e => e.length)) === 2);
  await p.uncheck('#biChk');
  await p.click('#q-' + firstId + ' [data-copy]'); await p.waitForTimeout(200);
  check('نسخ رابط السؤال', (await p.evaluate(() => navigator.clipboard.readText())).endsWith('#/q/' + firstId));
  await go('#/q/ai1');
  check('الرابط المباشر يفتح السؤال ومجموعته', (await p.$eval('#q-ai1', e => e.open)) && (await text('h1.vt')).includes('التقنية'));

  console.log('الشائعة:');
  await go('#/faq');
  check('أرقام للحفظ', (await p.$$eval('.fact', e => e.length)) === 14);
  check('أهم عشرة أسئلة', (await p.$$eval('#qs details.q', e => e.length)) === 10);

  console.log('التدريب والعرض:');
  await p.evaluate(() => localStorage.removeItem('jd-prog'));
  await go('#/train');
  await p.click('[data-act=reveal]'); await p.waitForTimeout(100);
  check('الجواب يُكشف بالضغط', !!(await p.$('#tans')));
  await p.click('[data-act=tknow]'); await p.waitForTimeout(150);
  check('التقدم يُحفظ في المتصفح', Object.keys(await p.evaluate(() => JSON.parse(localStorage.getItem('jd-prog')))).length === 1);
  await go('#/present/tech/0');
  check('وضع العرض يخفي الرأس', await p.evaluate(() => document.body.classList.contains('presenting')) && !(await p.isVisible('.top')));
  await p.click('[data-act=preveal]'); await p.waitForTimeout(100);
  check('كشف الجواب في العرض', !!(await p.$('#pcard')));
  await p.click('[data-act=pnext]'); await p.waitForTimeout(200);
  check('«التالي» يحرّك الرابط', (await p.evaluate(() => location.hash)).endsWith('/tech/1'));
  await p.keyboard.press('ArrowLeft'); await p.waitForTimeout(200);
  check('السهم الأيسر هو «التالي» في العربية', (await p.evaluate(() => location.hash)).endsWith('/tech/2'));
  await p.keyboard.press('Escape'); await p.waitForTimeout(200);
  check('Escape يخرج من العرض', !(await p.evaluate(() => document.body.classList.contains('presenting'))));

  console.log('اللغة:');
  await go('#/c/tech'); await p.click('#langBtn'); await p.waitForTimeout(300);
  check('الإنجليزية: اتجاه LTR وعنوان إنجليزي', (await p.evaluate(() => document.documentElement.dir)) === 'ltr' && (await text('h1.vt')).includes('Technology'));
  await p.click('#langBtn');

  console.log('الطباعة على ورق وصال:');
  await go();
  await p.click('#printBtn'); await p.waitForTimeout(200);
  check('خيار ملاحظات التأكيد يظهر مع ملف الملاحظات فقط', (await p.$eval('#pVerifyRow', e => e.hidden)) === !HAS_NOTES);
  await p.selectOption('#pLang', 'both'); await p.selectOption('#pScope', 'all');
  await p.evaluate(() => { window.__printed = 0; window.print = () => { window.__printed++; }; });
  await p.click('#pGo'); await p.waitForTimeout(500);
  const pr = await p.$eval('#printRoot', e => e.innerHTML);
  check('طُلبت الطباعة مرة واحدة', (await p.evaluate(() => window.__printed)) === 1);
  check('ترويسة وتذييل ورق وصال', /lh-head/.test(pr) && /lh-foot/.test(pr) && /المملكة العربية السعودية/.test(pr) && /شركة وصال/.test(pr));
  check('بيانات التواصل المنشورة في الصفحة الرئيسية', /info@wesalinnovation\.sa/.test(pr) && /\+966 50 112 0161/.test(pr) && !/gmail/.test(pr));
  check('كل الأسئلة في الطباعة', (pr.match(/class="pq1"/g) || []).length === (await p.evaluate(() => window.JUDGES.items.length)));
  check('لا سكربت داخل الطباعة', !/<script/i.test(pr));
  await p.emulateMedia({ media: 'print' });
  const printLayout = await p.evaluate(() => ({ app: getComputedStyle(document.querySelector('.top')).display, root: getComputedStyle(document.getElementById('printRoot')).display }));
  check('عند الطباعة يختفي التطبيق ويظهر ورق وصال', printLayout.app === 'none' && printLayout.root === 'block', JSON.stringify(printLayout));
  await p.emulateMedia({ media: 'screen' });

  console.log('سؤال مكرر (عدّاد الأسئلة اللي تكرر سؤالها من اللجنة):');
  await p.evaluate(() => localStorage.removeItem('jd-asked'));
  await go('#/c/business');
  const ids = await p.$$eval('details.q', e => e.map(x => x.getAttribute('data-id')));
  const A = ids[2], B = ids[4];
  check('بلا عدّاد في البداية', (await p.$$eval('.askn', e => e.length)) === 0 && !(await p.isVisible('#askedBtn')));
  await p.click(`#q-${A} [data-ask]`); await p.waitForTimeout(200);
  check('الضغط على الزر لا يفتح السؤال', (await p.$$eval('details.q[open]', e => e.length)) === 0);
  check('أول ضغطة تجعل العدّاد 1', (await text(`#q-${A} .askn`)) === '1');
  check('السؤال يطلع أول القائمة', (await p.getAttribute('details.q >> nth=0', 'data-id')) === A);
  await p.click(`#q-${B} [data-ask]`); await p.waitForTimeout(150);
  await p.click(`#q-${B} [data-ask]`); await p.waitForTimeout(150);
  check('كل ضغطة تزيد العدّاد', (await text(`#q-${B} .askn`)) === '2');
  check('الأعلى عدداً أول القائمة', (await p.getAttribute('details.q >> nth=0', 'data-id')) === B);
  check('زر الرأس يعدّ الأسئلة المكررة', (await p.isVisible('#askedBtn')) && (await text('#askedN')) === '2');
  await p.click('.tundo'); await p.waitForTimeout(150);
  check('«تراجع» في الإشعار ينقص واحداً', (await text(`#q-${B} .askn`)) === '1');
  await p.click(`#q-${B} [data-ask]`); await p.waitForTimeout(150);
  await p.click(`#q-${B} [data-ask]`); await p.waitForTimeout(150);
  check('العدّاد 3 بعد ثلاث ضغطات صافية', (await text(`#q-${B} .askn`)) === '3');
  await p.click(`#q-${B} summary`);
  await p.click(`#q-${B} [data-act=askminus]`); await p.waitForTimeout(150);
  check('«نقص واحد» من داخل السؤال', (await text(`#q-${B} .askn`)) === '2');
  await p.click(`#q-${B} [data-ask]`); await p.waitForTimeout(150);
  check('الترتيب يحفظ المفتوح', await p.$eval(`#q-${B}`, e => e.open));
  check('تصفية «المكررة» تظهر وتضيّق', await p.$$eval('[data-filter=asked]', e => e.length) === 1);
  await p.click('[data-filter=asked]'); await p.waitForTimeout(100);
  check('تعرض المكررة فقط', (await p.$$eval('details.q:not([hidden])', e => e.length)) === 2);
  await p.reload(); await p.waitForTimeout(350);
  check('العدّاد يبقى بعد إعادة التحميل', (await text(`#q-${B} .askn`)) === '3' && (await text(`#q-${A} .askn`)) === '1');
  await go();
  check('أعلى الرئيسية قسم «الأسئلة المكررة»', (await p.$$eval('.askhome .akrow', e => e.length)) === 2);
  check('الأعلى عدداً أول صف', (await p.$$eval('.askhome .akrow .akn', e => e.map(x => x.textContent))).join() === '3,1');
  await go('#/asked');
  check('شاشة «الأسئلة المكررة» مرتّبة', (await p.getAttribute('#qs details.q >> nth=0', 'data-id')) === B);
  await go('#/present/asked/0');
  check('العرض يبدأ بالأكثر تكراراً', (await text('#pq')) === (await p.evaluate(id => window.JUDGES.items.find(i => i.id === id).q[0], B)));
  await go('#/train');
  check('نطاق «المكررة» في التدريب', (await p.$$eval('#tScope option[value=asked]', e => e.length)) === 1);
  await go('#/asked');
  await p.click('[data-act=asksharelink]'); await p.waitForTimeout(200);
  const link = await p.evaluate(() => navigator.clipboard.readText());
  check('رابط الفريق يحمل العدّادات', /#\/import\/.+\.3/.test(link), link);
  const fresh = await browser.newContext({ viewport: { width: 1280, height: 900 }, locale: 'ar-SA' });
  const f = await fresh.newPage();
  await f.goto(link); await f.waitForTimeout(400);
  check('الرابط يعرض القائمة قبل الدمج', (await f.$$eval('.akrow', e => e.length)) === 2);
  check('لا شي يُحفظ قبل الموافقة', (await f.evaluate(() => localStorage.getItem('jd-asked'))) === null);
  await f.click('[data-act=askmerge]'); await f.waitForTimeout(400);
  check('الدمج يفتح «الأسئلة المكررة» بالعدّادات نفسها', (await f.evaluate(() => location.hash)) === '#/asked' && (await f.$$eval('.askn', e => e.map(x => x.textContent))).join() === '3,1');
  await f.goto(PAGE + '#/import/zzz.9,' + encodeURIComponent('<b>x</b>.2')); await f.waitForTimeout(300);
  check('رابط دمج فاسد لا يُقبل', (await f.$$eval('.akrow', e => e.length)) === 0 && /ما فيه أسئلة صالحة/.test(await f.textContent('main')));
  await fresh.close();
  p.once('dialog', d => d.accept());
  await p.click('[data-act=askclear]'); await p.waitForTimeout(250);
  check('التصفير يخفي القسم والزر', !(await p.isVisible('#askedBtn')) && (await p.evaluate(() => localStorage.getItem('jd-asked'))) === '{}');

  console.log('الجوال (390 بكسل):');
  const m = await ctx.newPage();
  await m.setViewportSize({ width: 390, height: 844 });
  for (const h of ['', '#/c/tech', '#/faq', '#/train']) {
    await m.goto(PAGE + h); await m.waitForTimeout(350);
    const over = await m.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
    check('بلا تمرير أفقي ' + (h || '#/'), !over);
  }
  await m.goto(PAGE); await m.waitForTimeout(300);
  check('شريط التبويب السفلي', (await m.evaluate(() => getComputedStyle(document.getElementById('nav')).position)) === 'fixed');
  const unnamed = await m.evaluate(() => [...document.querySelectorAll('main a, main button, header a, header button, nav a, nav button')].filter(e => {
    const n = (e.getAttribute('aria-label') || e.textContent || '').trim(); return !n && !e.querySelector('img[alt]:not([alt=""])');
  }).map(e => e.outerHTML.slice(0, 80)));
  check('لكل زر ورابط اسم مقروء', unnamed.length === 0, unnamed.join(' | '));
  check('عنوان رئيسي واحد في الصفحة', (await m.$$eval('h1', e => e.length)) === 1);

  check('لا أخطاء JavaScript', jsErrors.length === 0, jsErrors.join(' | '));
  await browser.close();
  console.log(`\n${failed ? '✗' : '✓'} ${passed} نجح، ${failed} فشل`);
  process.exit(failed ? 1 : 0);
})().catch(e => { console.error('تعطّل الفحص:', e.message); process.exit(1); });
