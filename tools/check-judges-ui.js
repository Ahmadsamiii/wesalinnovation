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
 *    - الشريط العلوي: «الأسئلة الشائعة»، وأيقونتا التنزيل والطباعة بلا كلمات، والتنزيل
 *      يجلب ملف PDF سليماً بلغة الصفحة
 *    - الذيل: نسخة من فوتر الرئيسية (index.html) بأنماطه ومقاساته، ببيانات التحكيم
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
  /* اسم الملف العربي في سمة download يحتاج لغة UTF-8 في كروم: بيئات POSIX ترجع «download» بدل الاسم */
  const browser = await chromium.launch({ env: Object.assign({}, process.env, { LC_ALL: 'C.UTF-8', LANG: 'C.UTF-8' }) });
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
  check('الوصف تحت العنوان بالعربي', (await text('p.lead')).trim() === 'أهم الأسئلة المتوقعة من لجنة التحكيم وإجاباتها.', await text('p.lead'));
  check('دليل الرموز بلا «المجيب المقترح»', !/المجيب المقترح/.test(await p.evaluate(() => document.body.innerText)));

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
  await go('#/c/business'); await p.evaluate(() => document.querySelectorAll('details.q').forEach(d => { d.open = true; }));
  const qText = await p.evaluate(() => document.querySelector('main').innerText);
  check('لا «المجيب المقترح» ولا «المرجع» تحت أي سؤال', !/المجيب المقترح|المرجع\s*$/m.test(qText) && (await p.$$eval('details.q dl, details.q dt', e => e.length)) === 0);
  await go('#/search/' + encodeURIComponent('لمياء'));
  const byName = await p.$$eval('main details.q', e => e.length);
  check('البحث باسم عضو الفريق لا يرجع أسئلته كأنه «مجيب»', byName < 10, String(byName));

  console.log('الشائعة:');
  await go('#/faq');
  check('أرقام للحفظ', (await p.$$eval('.fact', e => e.length)) === 14);
  const factsText = await p.$$eval('.fact', e => e.map(x => x.innerText).join(' | '));
  check('بلا مصادر تحت الأرقام', (await p.$$eval('.fact small', e => e.length)) === 0 && !/عرض الهاكاثون|العرض العربي|الهيئة العامة للإحصاء/.test(factsText), factsText);
  check('بلا بطاقة «أعضاء في الفريق»', !/أعضاء في الفريق/.test(factsText));
  check('«أكثر من 40» و«أكثر من 120» بطاقتان واضحتان', /أكثر من 40/.test(factsText) && /أكثر من 120/.test(factsText) && !/\+40|\+120/.test(factsText), factsText);
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
  check('الجواب ظاهر فوراً بلا زر «أظهر الجواب»', !!(await p.$('#pcard .short')) && !(await p.$('[data-act=preveal]')) && !/أظهر الجواب/.test(await p.evaluate(() => document.body.innerText)));
  check('«الجواب التفصيلي» تحت الجواب ومطوي', (await p.getAttribute('#pdetBtn', 'aria-expanded')) === 'false' && (await text('#pdetBtn')).includes('الجواب التفصيلي') && (await p.$eval('#pdetBody', e => e.hidden)));
  const yShortBottom = await p.$eval('#pcard .short', e => e.getBoundingClientRect().bottom), yBtn = await p.$eval('#pdetBtn', e => e.getBoundingClientRect().top);
  check('زر التفصيل تحت الجواب المختصر', yBtn >= yShortBottom - 2, yBtn + ' / ' + yShortBottom);
  await p.click('#pdetBtn'); await p.waitForTimeout(120);
  check('الضغط يفتح التفصيل تحت الزر', (await p.getAttribute('#pdetBtn', 'aria-expanded')) === 'true' && !(await p.$eval('#pdetBody', e => e.hidden)) && (await p.$eval('#pdetBody', e => e.getBoundingClientRect().top)) >= (await p.$eval('#pdetBtn', e => e.getBoundingClientRect().bottom)) - 2);
  await p.keyboard.press('Space'); await p.waitForTimeout(100);
  check('Space على الزر يطويه', (await p.getAttribute('#pdetBtn', 'aria-expanded')) === 'false');
  await p.evaluate(() => document.activeElement.blur());
  await p.keyboard.press('Space'); await p.waitForTimeout(100);
  check('Space خارج الأزرار يفتح التفصيل', (await p.getAttribute('#pdetBtn', 'aria-expanded')) === 'true');
  const geo = await p.evaluate(() => { const r = s => document.querySelector(s).getBoundingClientRect(); const tr = s => getComputedStyle(document.querySelector(s + ' svg')).transform; return { nx: r('[data-act=pnext]').x, pv: r('[data-act=pprev]').x, nT: tr('[data-act=pnext]'), pT: tr('[data-act=pprev]') }; });
  check('العربية: «التالي» يمين و«السابق» يسار', geo.nx > geo.pv, JSON.stringify(geo));
  check('العربية: رمز «التالي» < ورمز «السابق» >', geo.nT === 'matrix(-1, 0, 0, 1, 0, 0)' && geo.pT === 'none', JSON.stringify(geo));
  await p.click('[data-act=pnext]'); await p.waitForTimeout(200);
  check('«التالي» يحرّك الرابط', (await p.evaluate(() => location.hash)).endsWith('/tech/1'));
  check('التفصيل المفتوح يبقى مفتوحاً في السؤال التالي', (await p.$('#pdetBtn')) ? (await p.getAttribute('#pdetBtn', 'aria-expanded')) === 'true' : true);
  await p.keyboard.press('ArrowLeft'); await p.waitForTimeout(200);
  check('السهم الأيسر هو «التالي» في العربية', (await p.evaluate(() => location.hash)).endsWith('/tech/2'));
  await p.evaluate(() => localStorage.setItem('jd-lang', 'en')); await p.reload(); await p.waitForTimeout(400);
  const geoEn = await p.evaluate(() => { const r = s => document.querySelector(s).getBoundingClientRect(); const tr = s => getComputedStyle(document.querySelector(s + ' svg')).transform; return { nx: r('[data-act=pnext]').x, pv: r('[data-act=pprev]').x, nT: tr('[data-act=pnext]'), pT: tr('[data-act=pprev]') }; });
  check('الإنجليزية: Next يمين وPrevious يسار برمزيهما المعتادين', geoEn.nx > geoEn.pv && geoEn.nT === 'none' && geoEn.pT === 'matrix(-1, 0, 0, 1, 0, 0)', JSON.stringify(geoEn));
  await p.evaluate(() => localStorage.setItem('jd-lang', 'ar')); await p.reload(); await p.waitForTimeout(400);
  await p.keyboard.press('Escape'); await p.waitForTimeout(200);
  check('Escape يخرج من العرض', !(await p.evaluate(() => document.body.classList.contains('presenting'))));

  console.log('اللغة:');
  await go('#/c/tech'); await p.click('#langBtn'); await p.waitForTimeout(300);
  check('الإنجليزية: اتجاه LTR وعنوان إنجليزي', (await p.evaluate(() => document.documentElement.dir)) === 'ltr' && (await text('h1.vt')).includes('Technology'));
  await go('#/'); 
  check('الإنجليزية: الوصف تحت العنوان', (await text('p.lead')).trim() === 'The most important questions expected from the judging committee, and their answers.', await text('p.lead'));
  await p.click('#langBtn');

  console.log('المظهر (الفاتح هو الافتراضي):');
  const dctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, colorScheme: 'dark' });
  const dp = await dctx.newPage();
  await dp.goto(PAGE); await dp.waitForTimeout(350);
  const bgOf = () => dp.evaluate(() => getComputedStyle(document.body).backgroundColor);
  const lum = c => { const m = c.match(/\d+/g).map(Number); return 0.2126 * m[0] + 0.7152 * m[1] + 0.0722 * m[2]; };
  check('الافتراضي فاتح حتى لو الجهاز داكن', lum(await bgOf()) > 200 && !(await dp.evaluate(() => document.documentElement.hasAttribute('data-theme'))), await bgOf());
  await dp.click('#themeBtn'); await dp.waitForTimeout(150);
  check('زر المظهر يحوّل للداكن', lum(await bgOf()) < 60, await bgOf());
  await dp.reload(); await dp.waitForTimeout(350);
  check('اختيار الداكن يُحفظ بعد إعادة التحميل', lum(await bgOf()) < 60, await bgOf());
  await dp.click('#themeBtn'); await dp.waitForTimeout(150);
  check('زر المظهر يرجّع الفاتح', lum(await bgOf()) > 200, await bgOf());
  await dctx.close();

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
  check('ذيل ورق وصال: ثلاث أيقونات حسابات (X ولينكد إن وإنستقرام) قبل الهاندل وبلا واتساب', (pr.match(/class="lh-ic"/g) || []).length === 3 && (pr.match(/class="lh-ic"><svg[^>]*><path/g) || []).length === 3 && pr.lastIndexOf('lh-ic') < pr.indexOf('@WesalHub'));
  check('الهاندل بحرف H كبير: @WesalHub', /<span>@WesalHub<\/span>/.test(pr) && !/@Wesalhub/.test(pr));
  check('كل الأسئلة في الطباعة', (pr.match(/class="pq1"/g) || []).length === (await p.evaluate(() => window.JUDGES.items.length)));
  check('الطباعة بلا «المجيب المقترح» ولا «المرجع» ولا «Suggested answerer»', !/المجيب المقترح|المرجع:|Suggested answerer|Reference:/.test(pr));
  check('لا سكربت داخل الطباعة', !/<script/i.test(pr));
  await p.emulateMedia({ media: 'print' });
  const printLayout = await p.evaluate(() => ({ app: getComputedStyle(document.querySelector('.top')).display, root: getComputedStyle(document.getElementById('printRoot')).display }));
  check('عند الطباعة يختفي التطبيق ويظهر ورق وصال', printLayout.app === 'none' && printLayout.root === 'block', JSON.stringify(printLayout));
  await p.emulateMedia({ media: 'screen' });

  console.log('الشريط العلوي والتنزيل:');
  await go();
  check('«الأسئلة الشائعة» في الشريط', (await text('[data-nav=faq] span')).trim() === 'الأسئلة الشائعة', await text('[data-nav=faq] span'));
  await go('#/faq');
  check('وعنوان تبويب الصفحة نفسه', (await p.title()).startsWith('الأسئلة الشائعة'), await p.title());
  await go();
  const ib = await p.evaluate(() => ['dlBtn', 'printBtn'].map(id => {
    const e = document.getElementById(id), r = e.getBoundingClientRect();
    return { id, text: e.textContent.trim(), label: e.getAttribute('aria-label'), title: e.title, svg: !!e.querySelector('svg use'), w: Math.round(r.width), h: Math.round(r.height), next: e.nextElementSibling ? e.nextElementSibling.id : '' };
  }));
  check('أيقونتا التنزيل والطباعة بلا كلمات', ib.every(x => x.text === '' && x.svg), JSON.stringify(ib));
  check('لكل أيقونة اسم مقروء وتلميح', ib[0].label === 'تنزيل PDF' && ib[0].title === 'تنزيل PDF' && ib[1].label === 'طباعة' && ib[1].title === 'طباعة', JSON.stringify(ib));
  check('الأيقونتان جنب بعض بحجم لمس كافٍ', ib[0].next === 'printBtn' && ib.every(x => x.w >= 44 && x.h >= 44), JSON.stringify(ib));
  check('لا «طباعة / PDF» نصاً في الشريط', !/طباعة\s*\/\s*PDF/.test(await text('#nav')));
  const mf = JSON.parse(fs.readFileSync(path.join(ROOT, 'assets/judges-pdf.json'), 'utf8'));
  const pdfOf = async (lang, fileName) => {
    const [dl] = await Promise.all([p.waitForEvent('download'), p.click('#dlBtn')]);
    const file = fs.readFileSync(await dl.path());
    return { name: dl.suggestedFilename(), head: file.slice(0, 5).toString(), size: file.length, expect: fileName, bytes: mf[lang].bytes };
  };
  const dAr = await pdfOf('ar', 'وصال-أسئلة-التحكيم.pdf');
  check('التنزيل بالعربية: ملف PDF سليم باسمه', dAr.name === dAr.expect && dAr.head === '%PDF-', JSON.stringify(dAr));
  check('وهو الملف الجاهز المحفوظ بحجمه', dAr.size === dAr.bytes, JSON.stringify(dAr));
  await p.evaluate(() => localStorage.setItem('jd-lang', 'en')); await p.reload(); await p.waitForTimeout(350);
  check('الإنجليزية: «Popular questions» وأسماء الأيقونتين', (await text('[data-nav=faq] span')).trim() === 'Popular questions' && (await p.getAttribute('#dlBtn', 'aria-label')) === 'Download PDF' && (await p.getAttribute('#printBtn', 'aria-label')) === 'Print');
  const dEn = await pdfOf('en', 'Wesal-Judges-QA.pdf');
  check('التنزيل بالإنجليزية: ملف PDF سليم باسمه', dEn.name === dEn.expect && dEn.head === '%PDF-' && dEn.size === dEn.bytes, JSON.stringify(dEn));
  await p.evaluate(() => localStorage.setItem('jd-lang', 'ar')); await p.reload(); await p.waitForTimeout(350);

  console.log('الذيل (نفس فوتر الرئيسية):');
  await go();
  const ft = await p.evaluate(() => {
    const t = e => (e ? e.textContent.trim() : null);
    return {
      heads: Array.from(document.querySelectorAll('.foot h4')).map(t),
      soc: Array.from(document.querySelectorAll('.foot .fsb')).map(a => ({ href: a.href, label: a.getAttribute('aria-label'), text: a.textContent.trim(), svg: !!a.querySelector('svg use') })),
      logo: !!document.querySelector('.fbrand img[alt]'),
      parts: Array.from(document.querySelectorAll('.fpart-l img')).map(i => i.alt),
      bottom: Array.from(document.querySelectorAll('.fbottom span')).map(t),
      text: document.querySelector('.foot').innerText
    };
  });
  const contact = await p.evaluate(() => window.JUDGES.contact);
  check('عنوان العمود الأول «معلومات المشروع»', ft.heads[0] === 'معلومات المشروع', ft.heads.join(' | '));
  check('بلا سطر «المرحلة»', !/المرحلة/.test(ft.text));
  check('الحسابات أيقونات: أربعة، بلا نص، باسم مقروء', ft.soc.length === 4 && ft.soc.every(x => x.text === '' && x.svg && x.label), JSON.stringify(ft.soc));
  check('روابط الحسابات من بيانات التواصل', [contact.whatsapp, contact.instagram, contact.linkedin, contact.x].every(u => ft.soc.some(x => x.href === u)), JSON.stringify(ft.soc.map(x => x.href)));
  check('الشعار وثلاثة شعارات للشركاء بنص بديل', ft.logo && ft.parts.length === 3 && ft.parts.every(Boolean), JSON.stringify(ft.parts));
  check('الشريط السفلي: الحقوق ثم معيار الوصول', ft.bottom.length === 2 && /2026/.test(ft.bottom[0]) && /WCAG 2\.2/.test(ft.bottom[1]), JSON.stringify(ft.bottom));

  /* مطابقة فوتر الرئيسية: الأنماط المحسوبة وإحداثيات أفقية لكل عنصر مقابل (المحتوى يختلف فلا تُقارن أطوال النصوص) */
  const HOME = { root: '.footer', cont: '.footer .container', grid: '.footer-grid', brandImg: '.footer-brand img', brandP: '.footer-brand p', social: '.footer-social', soc0: '.footer-social-btn:nth-child(1)', soc3: '.footer-social-btn:nth-child(4)', socIcon0: '.footer-social-btn:nth-child(1) svg', h4: '.footer-grid > div:nth-child(2) h4', h4c: '.footer-grid > div:nth-child(4) h4', cP0: '.footer-grid > div:nth-child(4) p:nth-of-type(1)', cP1: '.footer-grid > div:nth-child(4) p:nth-of-type(2)', cP2: '.footer-grid > div:nth-child(4) p:nth-of-type(3)', cA: '.footer-grid > div:nth-child(4) p a', part: '.footer-partners', partL: '.footer-partners-left', partImg0: '.footer-partners-left img', sep0: '.fp-sep', partR: '.footer-partners-right img', bottom: '.footer-bottom', bs0: '.footer-bottom span:nth-child(1)', bs1: '.footer-bottom span:nth-child(2)' };
  const JUDG = { root: '.foot', cont: '.fcont', grid: '.fgrid', brandImg: '.fbrand img', brandP: '.fbrand p', social: '.fsoc', soc0: '.fsb:nth-child(1)', soc3: '.fsb:nth-child(4)', socIcon0: '.fsb:nth-child(1) svg', h4: '.fgrid > div:nth-child(2) h4', h4c: '.fgrid > div:nth-child(4) h4', cP0: '.fgrid > div:nth-child(4) p:nth-of-type(1)', cP1: '.fgrid > div:nth-child(4) p:nth-of-type(2)', cP2: '.fgrid > div:nth-child(4) p:nth-of-type(3)', cA: '.fgrid > div:nth-child(4) p a', part: '.fpart', partL: '.fpart-l', partImg0: '.fpart-l img', sep0: '.fsep', partR: '.fpart-r img', bottom: '.fbottom', bs0: '.fbottom span:nth-child(1)', bs1: '.fbottom span:nth-child(2)' };
  const PROPS = ['fontFamily', 'fontSize', 'fontWeight', 'lineHeight', 'letterSpacing', 'color', 'backgroundColor', 'opacity', 'marginBottom', 'paddingTop', 'paddingBottom', 'paddingLeft', 'paddingRight', 'borderTopWidth', 'borderTopColor', 'borderTopStyle', 'borderLeftWidth', 'borderLeftColor', 'borderRadius', 'textDecorationLine', 'columnGap', 'gridTemplateColumns', 'maxWidth'];
  const SIZED = ['brandImg', 'soc0', 'soc3', 'socIcon0', 'partImg0', 'sep0', 'partR'];
  const TOP = ['brandImg', 'brandP', 'social', 'soc0', 'soc3', 'socIcon0', 'h4', 'h4c', 'cP0', 'cP1', 'cP2', 'cA', 'cont', 'grid'];
  const metrics = (pg, map) => pg.evaluate(({ map, PROPS }) => {
    const root = document.querySelector(map.root), rr = root.getBoundingClientRect(), rtl = getComputedStyle(root).direction === 'rtl', out = {};
    Object.keys(map).forEach(k => {
      const el = document.querySelector(map[k]);
      if (!el) { out[k] = null; return; }
      const cs = getComputedStyle(el), r = el.getBoundingClientRect(), o = { w: r.width, h: r.height, y: r.top - rr.top, x: rtl ? rr.right - r.right : r.left - rr.left };
      PROPS.forEach(q => { o[q] = cs[q]; });
      out[k] = o;
    });
    return out;
  }, { map, PROPS });
  const hp = await ctx.newPage();
  await hp.route(/\/api\//, r => r.fulfill({ status: 200, contentType: 'application/json', body: '{}' }));
  for (const w of [1440, 390]) {
    await hp.setViewportSize({ width: w, height: 900 });
    await p.setViewportSize({ width: w, height: 900 });
    await hp.goto(BASE + '/index.html'); await hp.waitForSelector('.footer', { state: 'attached' });
    await hp.evaluate(() => document.querySelector('.footer').scrollIntoView()); await hp.waitForTimeout(600);
    await go(); await p.evaluate(() => document.querySelector('.foot').scrollIntoView()); await p.waitForTimeout(900);
    const a = await metrics(hp, HOME), b = await metrics(p, JUDG), bad = [];
    Object.keys(HOME).forEach(k => {
      if (!a[k] || !b[k]) { bad.push(k + ' مفقود'); return; }
      PROPS.forEach(q => { if (a[k][q] !== b[k][q]) bad.push(k + '.' + q + ': ' + a[k][q] + ' ≠ ' + b[k][q]); });
      if (k !== 'root' && Math.abs(a[k].x - b[k].x) > 0.6) bad.push(k + '.x: ' + a[k].x.toFixed(1) + ' ≠ ' + b[k].x.toFixed(1));
      if (w > 600 && TOP.includes(k) && Math.abs(a[k].y - b[k].y) > 0.6) bad.push(k + '.y: ' + a[k].y.toFixed(1) + ' ≠ ' + b[k].y.toFixed(1));
      if (SIZED.includes(k) && (Math.abs(a[k].w - b[k].w) > 0.6 || Math.abs(a[k].h - b[k].h) > 0.6)) bad.push(k + ' الحجم: ' + a[k].w.toFixed(1) + '×' + a[k].h.toFixed(1) + ' ≠ ' + b[k].w.toFixed(1) + '×' + b[k].h.toFixed(1));
    });
    check('الذيل مطابق لفوتر الرئيسية بعرض ' + w + ' بكسل', bad.length === 0, bad.slice(0, 6).join(' | '));
  }
  await hp.close();
  await p.setViewportSize({ width: 1280, height: 900 });

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
  for (const h of ['', '#/c/tech', '#/faq', '#/train', '#/present/tech/0']) {
    await m.goto(PAGE + h); await m.waitForTimeout(350);
    if (h.startsWith('#/present')) { await m.evaluate(() => localStorage.setItem('jd-pdet', '1')); await m.reload(); await m.waitForTimeout(350); }
    const over = await m.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
    check('بلا تمرير أفقي ' + (h || '#/'), !over);
  }
  await m.evaluate(() => localStorage.setItem('jd-pdet', '0'));
  await m.goto(PAGE); await m.waitForTimeout(300);
  check('شريط التبويب السفلي', (await m.evaluate(() => getComputedStyle(document.getElementById('nav')).position)) === 'fixed');
  const mib = await m.evaluate(() => ['dlBtn', 'printBtn'].map(id => { const r = document.getElementById(id).getBoundingClientRect(); return { id, w: Math.round(r.width), h: Math.round(r.height), inView: r.left >= 0 && r.right <= innerWidth && r.bottom <= innerHeight }; }));
  check('أيقونتا التنزيل والطباعة ظاهرتان بحجم لمس كافٍ على الجوال', mib.every(x => x.w >= 44 && x.h >= 44 && x.inView), JSON.stringify(mib));
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
