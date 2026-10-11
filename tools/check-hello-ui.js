/* ==========================================================================
 *  وصال: فحص صفحة جناح الهاكاثون /hello وشاشة الجناح /hello/wall في المتصفح
 *
 *  على نسخة محلية بقاعدة تجريبية فقط (كل مسح جديد يضيف صفاً في hello_visits). من جذر المستودع:
 *      php -S 127.0.0.1:8080 &
 *      NODE_PATH="$(npm root -g)" node tools/check-hello-ui.js
 *
 *  يتحقق من:
 *    - الترحيب: الصيغة تتبدل بالصفة (اكتب/اكتبي، جرّب/جرّبي) ومحادثة الدور والاسم
 *    - المحادثة: الردود ثابتة فلا طلب شبكة غير العدّاد، وروابط التواصل الخارجية آمنة
 *    - العدّاد: المسح يُحسب مرة لكل جهاز، والزاحف والطلب الغريب لا يُحسبان
 *    - شاشة الختام: رقم الزائر ولوحة الشعار، القطعة من الرقم، والاكتمال عند 25، وتقليل الحركة
 *    - سقوط مكوّن اللوحة أو العدّاد لا يكسر الصفحة
 *    - شاشة الجناح: لوحة في المنتصف وتحتها عبارة «عدد زوار جناح وصال» ثم عدّاد يدور بجانبه «زائر»
 *      ولا نص غيرها، والعبارة والعدّاد على محور المنتصف نفسه، تقرأ العدّاد وتركّب القطع،
 *      والاكتمال عند 25 وشعار جديد، وتصفير العدّاد، وانقطاع الخادم، وبلا كوكي ولا طلب غير GET،
 *      وتناسب الشاشات الأفقية والعمودية ولو كثرت خانات العدّاد، ومؤشر الفأرة مخفي، وتطلب إبقاء
 *      الشاشة مضاءة (wakeLock مُحاكى) وتعيد الطلب إذا فلته المتصفح، ولا تتعطل إن رفضه أو لم يدعمه،
 *      وشعارات الجهات في الزاويتين العلويتين، والمؤشر وأيقونتا الليزر وملء الشاشة تختفي بعد السكون
 *    - المقاسات: لا تمرير أفقي، ولا تمرير رأسي في شاشة الختام على الجوالات الشائعة
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
const PAGE = `${BASE}/hello.html`;
const UA = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Mobile Safari/537.36';

let passed = 0, failed = 0;
const check = (name, ok, extra = '') => { ok ? passed++ : failed++; console.log(`  ${ok ? '✓' : '✗'} ${name}${extra !== '' && !ok ? '  | ' + extra : ''}`); };

const rows = () => {
  try {
    return parseInt(execFileSync('php', ['-r', `require '${ROOT}/api/db.php'; try { echo (int) db()->query('SELECT COUNT(*) FROM hello_visits')->fetchColumn(); } catch (Throwable $e) { echo 0; }`],
      { cwd: ROOT, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }), 10) || 0;
  } catch (e) { return -1; }
};

/** يفتح الصفحة كجوال، يدخل بالاسم والصفة، ثم يختم التجربة ويصل لشاشة الختام */
async function visit(browser, o = {}) {
  const ctx = await browser.newContext({ userAgent: o.ua || UA, viewport: { width: o.w || 390, height: o.h || 844 }, deviceScaleFactor: 2, reducedMotion: o.reduce ? 'reduce' : 'no-preference' });
  const page = await ctx.newPage();
  const errs = [], reqs = [];
  page.on('pageerror', e => errs.push(String(e)));
  page.on('console', m => { if (m.type() === 'error' && !/favicon|ERR_FAILED/.test(m.text())) errs.push(m.text()); });
  page.on('request', r => { const u = new URL(r.url()); if (u.pathname.startsWith('/api/')) reqs.push(r.method() + ' ' + u.pathname); });
  if (o.stub) await page.route('**/api/hello-visit.php', r => r.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, n: o.stub }) }));
  if (o.noCounter) await page.route('**/api/hello-visit.php', r => r.abort());
  if (o.noPuzzle) await page.route('**/assets/hello-puzzle.js', r => r.abort());
  await page.goto(PAGE);
  if (o.cap) await page.click(`.cap input[value="${o.cap}"]`);
  if (o.name !== undefined) await page.fill('#nm', o.name);
  if (o.skip) await page.click('#skipname'); else await page.click('.go');
  await page.waitForSelector('#endbtn', { state: 'visible' });
  return { ctx, page, errs, reqs };
}
async function finish(v, wait) {
  await v.page.waitForTimeout(700);
  await v.page.click('#endbtn');
  await v.page.waitForTimeout(wait);
}
const snap = page => page.evaluate(() => {
  const t = s => { const e = document.querySelector(s); return e ? e.textContent.trim() : null; };
  const shown = s => { const e = document.querySelector(s); return !!e && !e.hidden && e.getBoundingClientRect().height > 0; };
  const fin = document.querySelector('#fin');
  return {
    board: shown('#pzcard'), old: shown('#numcard'), on: document.querySelectorAll('.pz .pc.on').length, done: !!document.querySelector('.pz.done'),
    who: t('#pn1'), num: t('#pnum'), cardText: (document.querySelector('#pzcard') || { textContent: '' }).textContent.trim(),
    m1: t('#m1'), m2: t('#m2'), live: t('#pzlive'), label: (document.querySelector('.pz') || { getAttribute: () => '' }).getAttribute('aria-label') || '',
    go: t('#acts .go span'), goHref: document.querySelector('#acts .go').getAttribute('href'), pill: !!document.querySelector('.pill'),
    back: shown('#back'), wa: document.querySelector('#wa').getAttribute('href'), soc: [...document.querySelectorAll('#fsoc a')].map(a => a.getAttribute('aria-label')),
    over: fin.scrollHeight - fin.clientHeight, hscroll: document.documentElement.scrollWidth > innerWidth
  };
});
const msgs = page => page.evaluate(() => [...document.querySelectorAll('.msg')].map(x => x.textContent));

(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || undefined });

  console.log('\nالترحيب والصيغة:');
  {
    const ctx = await browser.newContext({ userAgent: UA, viewport: { width: 390, height: 844 } });
    const p = await ctx.newPage();
    await p.goto(PAGE);
    const lab = async () => [await p.textContent('#lbl'), await p.textContent('#goTxt')];
    let [l, g] = await lab();
    check('بلا صفة: «اكتب» و«جرّب وصال»', l.startsWith('اكتب لنا اسمك') && g === 'جرّب وصال', l + ' | ' + g);
    await p.click('.cap input[value="jf"]');
    [l, g] = await lab();
    check('محكّمة: «اكتبي» و«جرّبي وصال»', l.startsWith('اكتبي لنا اسمك') && g === 'جرّبي وصال', l + ' | ' + g);
    await p.click('.cap input[value="vm"]');
    [l, g] = await lab();
    check('زائر: ترجع صيغة المذكر', l.startsWith('اكتب لنا اسمك') && g === 'جرّب وصال', l + ' | ' + g);
    check('سطر الخصوصية يذكر أن الزيارة تُحسب رقماً', /زيارتك تُحسب كرقم/.test(await p.textContent('#priv')));
    check('زر التخطي «ابدأ بدون اسم»', (await p.textContent('#skipname')).trim() === 'ابدأ بدون اسم');
    check('سطر رعاية المؤتمر بصيغته المعتمدة', (await p.textContent('.foot')).replace(/\u00a0/g, ' ').trim() === 'من فعاليات المؤتمر الدولي السابع لذوي الإعاقة، تحت رعاية كريمة من خادم الحرمين الشريفين الملك سلمان بن عبدالعزيز آل سعود حفظه الله');
    await ctx.close();
  }

  console.log('\nالمحادثة: ردود ثابتة وروابط آمنة:');
  {
    const v = await visit(browser, { cap: 'jf', name: 'سارة', stub: 57 });
    const p = v.page;
    await p.waitForSelector('.chips', { timeout: 20000 });
    for (const part of ['مصادرك', 'أتابعكم']) { await p.click(`.chip:has-text("${part}")`); await p.waitForTimeout(part === 'مصادرك' ? 4200 : 1800); }
    const m = await msgs(p);
    check('تحية المحكّمة بصيغة المؤنث والاسم', /حيّاكِ الله سارة، شرّفتينا في جناح وصال/.test(m[0] || ''), m[0]);
    check('الرد عن المصادر يذكر الجهات الرسمية', m.some(x => x.includes('هيئة رعاية الأشخاص ذوي الإعاقة') && x.includes('طاقات')));
    const links = await p.$$eval('.card .soc', as => as.map(a => ({ href: a.getAttribute('href'), target: a.getAttribute('target'), rel: a.getAttribute('rel') || '' })));
    check('خمس وسائل تواصل', links.length === 5, JSON.stringify(links.map(l => l.href)));
    check('الروابط الخارجية بنافذة جديدة و noopener', links.filter(l => /^https?:/.test(l.href)).every(l => l.target === '_blank' && /noopener/.test(l.rel)));
    check('لا طلب شبكة في المحادثة غير العدّاد', v.reqs.every(r => r.includes('hello-visit.php')), v.reqs.join(', '));
    check('بلا أخطاء برمجية', v.errs.length === 0, v.errs.join(' | '));
    await v.ctx.close();

    const w = await visit(browser, { cap: 'vm', name: 'Ahmad', stub: 57 });
    await w.page.waitForSelector('.chips', { timeout: 20000 });
    const mw = await msgs(w.page);
    check('تحية الزائر بصيغة المذكر ومعها الاسم اللاتيني', /حيّاك الله Ahmad، نوّرتنا في جناح وصال/.test(mw[0] || ''), mw[0]);
    await w.ctx.close();

    const n = await visit(browser, { skip: true, stub: 57 });
    await n.page.waitForSelector('.chips', { timeout: 20000 });
    const mn = await msgs(n.page);
    check('بلا اسم ولا صفة: تحية عامة', /حيّاك الله في جناح وصال\./.test(mn[0] || ''), mn[0]);
    await n.ctx.close();
  }

  console.log('\nالعدّاد:');
  {
    const before = rows();
    const A = await browser.newContext({ userAgent: UA, viewport: { width: 390, height: 844 } });
    const a = await A.newPage();
    await a.goto(PAGE);
    await a.waitForTimeout(2800);
    const stored = await a.evaluate(() => localStorage.getItem('wesal_hello_visit'));
    check('المسح وحده يُحسب فوراً ويحفظ الجهاز رقمه', before >= 0 && rows() === before + 1 && parseInt(stored, 10) > 0, `${before} -> ${rows()}, ${stored}`);
    await a.reload();
    await a.waitForTimeout(2800);
    check('إعادة فتح الصفحة على الجهاز نفسه لا تُحسب مرة ثانية', rows() === before + 1, String(rows()));
    const B = await browser.newContext({ userAgent: UA, viewport: { width: 390, height: 844 } });
    const b = await B.newPage();
    await b.goto(PAGE);
    await b.waitForTimeout(2800);
    const storedB = await b.evaluate(() => localStorage.getItem('wesal_hello_visit'));
    check('جهاز جديد يأخذ رقماً أكبر', parseInt(storedB, 10) > parseInt(stored, 10) && rows() === before + 2, `${stored} -> ${storedB}`);
    const bot = await B.request.post(`${BASE}/api/hello-visit.php`, { headers: { 'X-Hello': '1', 'User-Agent': 'Mozilla/5.0 (compatible; Googlebot/2.1)' } });
    check('الزاحف لا يُحسب', (await bot.json()).n === 0 && rows() === before + 2);
    const raw = await B.request.post(`${BASE}/api/hello-visit.php`, { headers: { 'User-Agent': UA } });
    check('الطلب بلا الترويسة المخصصة يُرفض', raw.status() === 405 && rows() === before + 2, String(raw.status()));
    const get = await B.request.get(`${BASE}/api/hello-visit.php`, { headers: { 'User-Agent': UA } });
    const gj = await get.json();
    check('GET يعيد آخر رقم ولا يغيّر شيئاً', get.status() === 200 && gj.ok === true && gj.n >= parseInt(storedB, 10) && rows() === before + 2, JSON.stringify(gj));
    const put = await B.request.put(`${BASE}/api/hello-visit.php`, { headers: { 'X-Hello': '1', 'User-Agent': UA } });
    check('بقية الطرق تُرفض', put.status() === 405, String(put.status()));
    const setCookie = [...(await bot.headersArray()), ...(await get.headersArray())].filter(h => h.name.toLowerCase() === 'set-cookie');
    check('العدّاد لا يضع كوكي', setCookie.length === 0, JSON.stringify(setCookie));
    await A.close(); await B.close();
  }

  console.log('\nشاشة الختام: رقم الزائر ولوحة الشعار:');
  {
    const v = await visit(browser, { cap: 'jf', name: 'سارة' });
    await finish(v, 6800);
    const s = await snap(v.page);
    const n = parseInt(s.num, 10), k = ((n - 1) % 25) + 1;
    check('الرقم من الخادم', n > 0, s.num);
    check('القطع المركّبة تساوي قطعة الزائر', s.on === k, `${s.on} != ${k}`);
    check('بطاقة اللوحة ظاهرة وبطاقة الرقم القديمة مخفية', s.board && !s.old);
    check('لا عبارة «قطعتك» ظاهرة في البطاقة', !/قطعتك/.test(s.cardText), s.cardText);
    check('«أنتِ الزائرة رقم» بصيغة المؤنث', s.who === 'أنتِ الزائرة رقم', s.who);
    check('الجملة المعتمدة', s.m2 === 'أنتِ ساهمتِ في دعم فكرة وصال: أن توصل المعلومة الصحيحة لكل شخص من ذوي الإعاقة يحتاجها، بأبسط أسلوب وأسرع طريقة.', s.m2);
    check('«وهذا مو مجرد رقم زيارة.»', s.m1 === 'وهذا مو مجرد رقم زيارة.', s.m1);
    check('الزر الأساسي «انتقل للصفحة الرئيسية» إلى قسم المحادثة', s.go === 'انتقل للصفحة الرئيسية' && s.goHref === 'https://wesalinnovation.sa/#chat', s.go + ' ' + s.goHref);
    check('لا زر مكرر، والرجوع للمحادثة وواتساب موجودان', !s.pill && s.back && /^https:\/\/wa\.me\/966500039204\?text=/.test(s.wa));
    check('وسائل التواصل في الختام بلا واتساب (له زره)', s.soc.length === 4 && !s.soc.includes('WhatsApp'), s.soc.join(','));
    check('إعلان واحد للقارئ الشاشي بالرقم كاملاً', s.live === `أنتِ الزائرة رقم ${n} في جناح وصال`, s.live);
    check('وصف اللوحة للقارئ الشاشي', /لوحة شعار وصال/.test(s.label) && s.label.includes(`قطعتك رقم ${k}`), s.label);
    check('طلب واحد للعدّاد، بلا تمرير أفقي ولا أخطاء', v.reqs.length === 1 && !s.hscroll && v.errs.length === 0, v.reqs.join(',') + ' ' + v.errs.join(' | '));
    await v.ctx.close();
  }
  {
    console.log('\nالقطعة 25، وتقليل الحركة، وحساب الدورة:');
    for (const [n, k, lap] of [[1, 1, 1], [26, 1, 2], [57, 7, 3], [75, 25, 3], [100, 25, 4]]) {
      const v = await visit(browser, { stub: n, reduce: true, cap: 'vm', name: 'خالد' });
      await finish(v, 900);
      const s = await snap(v.page);
      check(`الزائر ${n}: القطعة ${k} من الشعار ${lap}`, s.num === String(n) && s.on === k && s.label.includes(`قطعتك رقم ${k} من 25 في الشعار رقم ${lap}`) && s.done === (k === 25), `${s.num} ${s.on} ${s.done} | ${s.label}`);
      await v.ctx.close();
    }
    const v = await visit(browser, { stub: 100 });
    await finish(v, 10800);
    const s = await snap(v.page);
    check('بالحركة الكاملة: القطعة 25 تكمل الشعار وتلتحم القطع', s.done && s.on === 25 && !s.hscroll && v.errs.length === 0, `${s.done} ${s.on} ${v.errs.join(' | ')}`);
    await v.ctx.close();
  }

  console.log('\nعند سقوط جزء:');
  {
    const v = await visit(browser, { stub: 57, noPuzzle: true });
    await finish(v, 2500);
    const s = await snap(v.page);
    check('سقوط مكوّن اللوحة: يظهر الرقم ببطاقته القديمة', s.old && !s.board && /57/.test(await v.page.textContent('#num')));
    await v.ctx.close();
    const w = await visit(browser, { noCounter: true, name: 'خالد' });
    await finish(w, 2500);
    const q = await snap(w.page);
    check('سقوط العدّاد: شكر بلا رقم ولا لوحة', !q.board && !q.old && q.m1 === 'نشكرك على وقتك معنا.', q.m1);
    check('وبلا أخطاء برمجية', w.errs.length === 0, w.errs.join(' | '));
    await w.ctx.close();
  }

  console.log('\nشاشة الجناح /hello/wall:');
  {
    const open = async (o = {}) => {
      const ctx = await browser.newContext({ viewport: { width: o.w || 1920, height: o.h || 1080 }, reducedMotion: o.reduce === false ? 'no-preference' : 'reduce' });
      const page = await ctx.newPage();
      const st = { n: o.n === undefined ? 0 : o.n, fail: false, hits: [], errs: [], paths: [] };
      page.on('pageerror', e => st.errs.push(String(e)));
      page.on('console', m => { if (m.type() === 'error' && !/favicon|ERR_FAILED/.test(m.text())) st.errs.push(m.text()); });
      page.on('request', r => { const u = new URL(r.url()); if (u.pathname.startsWith('/api/')) st.paths.push(r.method() + ' ' + u.pathname); });
      await page.route('**/api/hello-visit.php', r => { st.hits.push(r.request().method()); return st.fail ? r.abort() : r.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, n: st.n }) }); });
      await page.goto(`${BASE}/hello-wall.html`);
      await page.waitForTimeout(o.settle || 900);
      return { ctx, page, st };
    };
    const wsnap = page => page.evaluate(() => {
      const r = s => document.querySelector(s).getBoundingClientRect();
      const c = s => { const b = r(s); return { x: (b.left + b.right) / 2, y: (b.top + b.bottom) / 2, l: b.left, r: b.right }; };
      /* المحاذاة: العبارة والعدّاد على محور المنتصف، و«زائر» بجانب العدّاد وعلى منتصفه رأسياً */
      const align = (() => {
        const cap = c('.cap'), odo = c('#odo'), unit = c('#unit'), mid = innerWidth / 2;
        return { cap: Math.abs(cap.x - mid) < 3, odo: Math.abs(odo.x - mid) < 3, unitMid: Math.abs(unit.y - odo.y) < 3,
          unitBeside: unit.r <= odo.l, capAbove: cap.y < odo.y, inside: odo.l >= 0 && odo.r <= innerWidth && unit.l >= 0 };
      })();
      const lines = ['::before', '::after'].map(p => getComputedStyle(document.querySelector('.cap'), p).content);
      /* الرقم الظاهر فعلاً في العدّاد: موضع شريط كل خانة */
      const digits = [...document.querySelectorAll('#odo .strip')].map(t => (parseInt((t.style.transform.match(/-?(\d+)em/) || [0, 0])[1], 10) % 10)).join('');
      /* كل نص ظاهر في الصفحة خارج العدّاد وخارج نص القارئ الشاشي */
      const texts = [...document.body.querySelectorAll('*')].filter(e => !e.closest('#odo') && !e.closest('.sr') && !['SCRIPT', 'STYLE'].includes(e.tagName) && [...e.childNodes].some(c => c.nodeType === 3 && c.textContent.trim()))
        .map(e => e.textContent.trim());
      return {
        txt: document.querySelector('#bigtxt').textContent, digits, texts, align, lines,
        on: document.querySelectorAll('.pz .pc.on').length, done: !!document.querySelector('.pz.done'),
        bad: document.querySelector('#stat').classList.contains('bad'),
        fits: document.documentElement.scrollWidth <= innerWidth && document.documentElement.scrollHeight <= innerHeight && r('.card').top >= 0 && r('.counter').bottom <= innerHeight,
        centered: Math.abs((r('.card').left + r('.card').right) / 2 - innerWidth / 2) < 3,
        robots: (document.querySelector('meta[name=robots]') || {}).content || ''
      };
    });
    const poll = 3300;

    const w = await open({ n: 66 });
    let s = await wsnap(w.page);
    check('تبدأ من آخر رقم: 66 هو القطعة 16 من الشعار 3', s.txt === '66 زائر' && s.digits === '66' && s.on === 16 && !s.done && !s.bad, JSON.stringify(s));
    check('اللوحة في المنتصف، ولا نص في الشاشة غير العبارة و«زائر»', s.centered && s.texts.length === 2 && s.texts[0] === 'عدد زوار جناح وصال' && s.texts[1] === 'زائر', JSON.stringify(s.texts));
    check('العبارة والعدّاد على محور المنتصف، و«زائر» بجانب العدّاد وعلى منتصفه رأسياً', s.align.cap && s.align.odo && s.align.unitMid && s.align.unitBeside && s.align.capAbove, JSON.stringify(s.align));
    check('العبارة بلا خطّين جانبيين', s.lines.every(x => x === 'none' || x === 'normal'), JSON.stringify(s.lines));
    check('الشاشة لا تُفهرس (noindex)', /noindex/.test(s.robots), s.robots);
    w.st.n = 67; await w.page.waitForTimeout(poll); s = await wsnap(w.page);
    check('زائر جديد: العدّاد 67 وتركّبت القطعة 17', s.txt === '67 زائر' && s.digits === '67' && s.on === 17, JSON.stringify(s));
    w.st.n = 75; await w.page.waitForFunction(() => !!document.querySelector('.pz.done'), null, { timeout: 12000 }).catch(() => {}); s = await wsnap(w.page);
    check('الزائر 75 يكمّل الشعار: الرمز كاملاً والعدّاد 75', s.on === 25 && s.done && s.txt === '75 زائر' && s.digits === '75', JSON.stringify(s));
    w.st.n = 76; await w.page.waitForTimeout(poll); s = await wsnap(w.page);
    check('الزائر 76 يبدأ شعاراً جديداً والعدّاد يكمل', !s.done && s.on === 1 && s.txt === '76 زائر' && s.digits === '76', JSON.stringify(s));
    w.st.n = 100; await w.page.waitForTimeout(poll); s = await wsnap(w.page);
    check('عدّاد بثلاث خانات: 100، وما زال في المنتصف', s.txt === '100 زائر' && s.digits === '100' && s.texts.length === 2 && s.align.odo && s.align.cap && s.align.unitMid, JSON.stringify(s));
    w.st.n = 0; await w.page.waitForTimeout(poll); s = await wsnap(w.page);
    check('تصفير العدّاد: لوحة فارغة والعدّاد 0', s.txt === '0 زائر' && s.digits === '0' && s.on === 0 && !s.done, JSON.stringify(s));
    w.st.fail = true; await w.page.waitForTimeout(poll); s = await wsnap(w.page);
    check('انقطاع الخادم: نقطة الحالة تتغير واللوحة باقية', s.bad && s.txt === '0 زائر', JSON.stringify(s));
    w.st.fail = false; w.st.n = 3; await w.page.waitForTimeout(poll); s = await wsnap(w.page);
    check('عودة الخادم: تكمل من الرقم الجديد', !s.bad && s.digits === '3' && s.on === 3, JSON.stringify(s));
    check('الشاشة لا تطلب إلا GET للعدّاد، بلا كوكي ولا أخطاء', w.st.paths.every(p => p === 'GET /api/hello-visit.php') && (await w.ctx.cookies()).length === 0 && w.st.errs.length === 0, w.st.paths.slice(0, 3).join(',') + ' ' + w.st.errs.join(' | '));
    await w.ctx.close();

    const m = await open({ n: 18, reduce: false });
    check('بالحركة: 18 قطعة مركّبة والعدّاد 18 عند الفتح', (await wsnap(m.page)).on === 18 && (await wsnap(m.page)).digits === '18');
    m.st.n = 19; await m.page.waitForTimeout(poll + 2400); s = await wsnap(m.page);
    check('بالحركة: العدّاد يدور إلى 19 وتستقر القطعة 19', s.digits === '19' && s.on === 19, JSON.stringify(s));
    m.st.n = 25;
    const sawDone = await m.page.waitForFunction(() => !!document.querySelector('.pz.done'), null, { timeout: 25000 }).then(() => true).catch(() => false);
    s = await wsnap(m.page);
    check('بالحركة الكاملة: تتابع القطع حتى 25 فيكتمل الشعار والعدّاد 25', sawDone && s.on === 25 && s.digits === '25' && m.st.errs.length === 0, `${sawDone} ${JSON.stringify(s)} ${m.st.errs.join(' | ')}`);
    await m.ctx.close();

    for (const [vw, vh] of [[1920, 1080], [2560, 1440], [1366, 768], [1280, 720], [1440, 900], [1080, 1920], [768, 1024]]) {
      const z = await open({ n: 67, w: vw, h: vh });
      const q = await wsnap(z.page);
      check(`${vw}×${vh}: اللوحة والعدّاد داخل الشاشة بلا تمرير وعلى المنتصف`, q.fits && q.on === 17 && q.centered && q.align.cap && q.align.odo && q.align.inside && q.align.unitMid, JSON.stringify(q));
      await z.ctx.close();
    }
    /* الشاشة لا تنام: wakeLock مُحاكى لأن المتصفح الآلي بلا شاشة حقيقية */
    {
      const trial = async (stub, n) => {
        const ctx = await browser.newContext({ viewport: { width: 1920, height: 1080 }, reducedMotion: 'reduce' });
        await ctx.addInitScript(stub);
        const page = await ctx.newPage(); const errs = [];
        page.on('pageerror', e => errs.push(String(e)));
        page.on('console', m => { if (m.type() === 'error' && !/favicon|ERR_FAILED/.test(m.text())) errs.push(m.text()); });
        await page.route('**/api/hello-visit.php', r => r.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, n }) }));
        await page.goto(`${BASE}/hello-wall.html`); await page.waitForTimeout(900);
        return { ctx, page, errs };
      };
      const fake = () => {
        window.__wl = { calls: 0, last: null };
        Object.defineProperty(navigator, 'wakeLock', { configurable: true, value: { request: async t => { window.__wl.calls++; const l = new EventTarget(); l.type = t; window.__wl.last = l; return l; } } });
      };
      let t = await trial(fake, 5);
      const c1 = await t.page.evaluate(() => ({ calls: window.__wl.calls, type: window.__wl.last && window.__wl.last.type, cursor: getComputedStyle(document.documentElement).cursor }));
      check('الشاشة تطلب إبقاءها مضاءة مرة واحدة عند الفتح', c1.calls === 1 && c1.type === 'screen', JSON.stringify(c1));
      check('المؤشر ظاهر عند الفتح', c1.cursor !== 'none', c1.cursor);
      await t.page.evaluate(() => { window.__wl.last.dispatchEvent(new Event('release')); document.dispatchEvent(new Event('visibilitychange')); });
      await t.page.waitForTimeout(300);
      const c2 = await t.page.evaluate(() => window.__wl.calls);
      check('بعد أن يفلت المتصفح القفل تطلبه الصفحة من جديد', c2 === 2, String(c2));
      check('وبلا أخطاء برمجية', t.errs.length === 0, t.errs.join(' | '));
      await t.ctx.close();
      t = await trial(() => Object.defineProperty(navigator, 'wakeLock', { configurable: true, value: { request: () => Promise.reject(new DOMException('no', 'NotAllowedError')) } }), 5);
      let q = await wsnap(t.page);
      check('إن رفض المتصفح القفل تعمل الشاشة بلا خطأ', q.on === 5 && q.digits === '5' && t.errs.length === 0, JSON.stringify([q.on, q.digits, t.errs]));
      await t.ctx.close();
      t = await trial(() => Object.defineProperty(navigator, 'wakeLock', { configurable: true, value: undefined }), 5);
      q = await wsnap(t.page);
      check('إن لم يدعمه المتصفح تعمل الشاشة بلا خطأ', q.on === 5 && q.digits === '5' && t.errs.length === 0, JSON.stringify([q.on, q.digits, t.errs]));
      await t.ctx.close();
    }
    /* شعارات الجهات، والمؤشر يختفي بعد السكون، وأيقونتا الليزر وملء الشاشة بلا نصوص */
    {
      const ctx = await browser.newContext({ viewport: { width: 1920, height: 1080 }, reducedMotion: 'reduce' });
      await ctx.addInitScript(() => {
        window.__fs = 0;
        Element.prototype.requestFullscreen = function () { window.__fs++; Object.defineProperty(document, 'fullscreenElement', { configurable: true, value: this }); document.dispatchEvent(new Event('fullscreenchange')); return Promise.resolve(); };
        document.exitFullscreen = function () { Object.defineProperty(document, 'fullscreenElement', { configurable: true, value: null }); document.dispatchEvent(new Event('fullscreenchange')); return Promise.resolve(); };
      });
      const page = await ctx.newPage(); const errs = [];
      page.on('pageerror', e => errs.push(String(e)));
      page.on('console', m => { if (m.type() === 'error' && !/favicon|ERR_FAILED/.test(m.text())) errs.push(m.text()); });
      await page.route('**/api/hello-visit.php', r => r.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, n: 57 }) }));
      await page.goto(`${BASE}/hello-wall.html`); await page.waitForTimeout(900);
      const lg = await page.evaluate(() => {
        const rc = e => { const b = e.getBoundingClientRect(); return { l: b.left, r: b.right, t: b.top, b: b.bottom, h: b.height }; };
        const img = [...document.querySelectorAll('.logos img')].map(i => ({ src: i.getAttribute('src').split('?')[0], ok: i.naturalWidth > 0, ...rc(i) }));
        const card = rc(document.querySelector('.card')), mid = innerWidth / 2;
        const side = n => { const m = img.find(x => x.src.endsWith(n)); return m && (m.l + m.r) / 2 > mid ? 'right' : 'left'; };
        const hit = img.some(x => x.l < card.r && x.r > card.l && x.t < card.b && x.b > card.t);
        return { img, kscdr: side('kscdr.png'), hack: side('hackathon.png'), year: side('ai-year.png'), hit, hMax: Math.max(...img.map(x => x.h)), topMax: Math.max(...img.map(x => x.b)), u: innerHeight / 1080 };
      });
      check('الهاكاثون والمركز أعلى اليمين وعام الذكاء الاصطناعي أعلى اليسار', lg.img.length === 3 && lg.kscdr === 'right' && lg.hack === 'right' && lg.year === 'left' && lg.img.every(x => x.ok), JSON.stringify(lg));
      check('الشعارات صغيرة (حتى 72 بكسلاً على شاشة 1080) ولا تلمس اللوحة', lg.hMax <= 72 * lg.u + 1 && !lg.hit, JSON.stringify([lg.hMax, lg.hit]));
      const tl = await page.evaluate(() => ({ btns: [...document.querySelectorAll('.tools button')].map(b => ({ label: b.getAttribute('aria-label'), text: b.textContent.trim(), vis: getComputedStyle(b).display !== 'none' })), pos: (() => { const b = document.querySelector('.tools').getBoundingClientRect(); return { left: b.left, bottom: innerHeight - b.bottom }; })(), op: getComputedStyle(document.querySelector('.tools')).opacity, cursor: getComputedStyle(document.body).cursor }));
      check('أيقونتان أسفل اليسار (ليزر وملء الشاشة) بلا نصوص ظاهرة', tl.btns.length === 2 && tl.btns.every(b => b.text === '' && b.label && b.vis) && tl.pos.left < 60 && tl.pos.bottom < 60, JSON.stringify(tl));
      check('الأيقونتان والمؤشر ظاهران عند الفتح', tl.op === '1' && tl.cursor !== 'none', JSON.stringify([tl.op, tl.cursor]));
      await page.waitForTimeout(3500);
      let st = await page.evaluate(() => ({ op: getComputedStyle(document.querySelector('.tools')).opacity, cursor: getComputedStyle(document.body).cursor, idle: document.body.classList.contains('idle') }));
      check('بعد السكون يختفي المؤشر وتختفي الأيقونتان', st.idle && st.cursor === 'none' && st.op === '0', JSON.stringify(st));
      await page.mouse.move(400, 400); await page.waitForTimeout(500);
      st = await page.evaluate(() => ({ op: getComputedStyle(document.querySelector('.tools')).opacity, cursor: getComputedStyle(document.body).cursor, idle: document.body.classList.contains('idle') }));
      check('حركة الماوس تُظهرهما من جديد', !st.idle && st.cursor !== 'none' && st.op === '1', JSON.stringify(st));
      await page.waitForTimeout(3500);
      check('ثم يختفيان مرة ثانية بعد السكون', await page.evaluate(() => document.body.classList.contains('idle')));
      /* مؤشر الليزر: نقطة تتبع الماوس والمؤشر العادي مخفي */
      await page.mouse.move(500, 500); await page.waitForTimeout(300);
      await page.click('#laserBtn');
      await page.mouse.move(700, 450); await page.waitForTimeout(300);
      const lz = await page.evaluate(() => { const b = document.querySelector('#laser').getBoundingClientRect(); return { on: document.querySelector('#laser').classList.contains('on'), x: (b.left + b.right) / 2, y: (b.top + b.bottom) / 2, pressed: document.querySelector('#laserBtn').getAttribute('aria-pressed'), cursor: getComputedStyle(document.body).cursor }; });
      check('الليزر: النقطة تتبع الماوس والمؤشر العادي مخفي', lz.on && Math.abs(lz.x - 700) < 3 && Math.abs(lz.y - 450) < 3 && lz.pressed === 'true' && lz.cursor === 'none', JSON.stringify(lz));
      await page.click('#laserBtn'); await page.waitForTimeout(200);
      const lz2 = await page.evaluate(() => ({ on: document.querySelector('#laser').classList.contains('on'), pressed: document.querySelector('#laserBtn').getAttribute('aria-pressed') }));
      check('الضغط الثاني يطفئ الليزر', !lz2.on && lz2.pressed === 'false', JSON.stringify(lz2));
      /* ملء الشاشة: يدخل ويخرج، والأيقونة تتبدل */
      await page.click('#fsBtn'); await page.waitForTimeout(200);
      const f1 = await page.evaluate(() => ({ calls: window.__fs, pressed: document.querySelector('#fsBtn').getAttribute('aria-pressed'), on: document.querySelector('#fsBtn').classList.contains('on') }));
      await page.click('#fsBtn'); await page.waitForTimeout(200);
      const f2 = await page.evaluate(() => ({ pressed: document.querySelector('#fsBtn').getAttribute('aria-pressed'), on: document.querySelector('#fsBtn').classList.contains('on') }));
      check('ملء الشاشة: الزر يدخل ثم يخرج وتتبدل الأيقونة', f1.calls === 1 && f1.pressed === 'true' && f1.on && f2.pressed === 'false' && !f2.on, JSON.stringify([f1, f2]));
      check('بلا أخطاء برمجية', errs.length === 0, errs.join(' | '));
      await ctx.close();
    }
    for (const [vw, vh] of [[1080, 1920], [1920, 1080]]) {
      const z = await open({ n: 123456, w: vw, h: vh });
      const q = await wsnap(z.page);
      check(`${vw}×${vh}: ست خانات (123456) يصغر العدّاد وحده ويبقى كله داخل الشاشة وفي المنتصف`, q.digits === '123456' && q.align.inside && q.align.odo && q.align.cap && q.align.unitMid && q.fits, JSON.stringify(q));
      await z.ctx.close();
    }
  }

  console.log('\nالمقاسات (شاشة الختام):');
  for (const [w, h, tall] of [[320, 568, false], [360, 640, true], [375, 667, true], [360, 740, true], [360, 800, true], [390, 844, true], [412, 915, true], [1440, 900, true]]) {
    const v = await visit(browser, { w, h, stub: 57, reduce: true });
    await finish(v, 900);
    const s = await snap(v.page);
    check(`${w}×${h}: بلا تمرير أفقي${tall ? ' ولا رأسي' : ''}`, !s.hscroll && (!tall || s.over <= 0), `over=${s.over}`);
    await v.ctx.close();
  }

  await browser.close();
  console.log(`\n${failed === 0 ? '✓ كل الفحوص ناجحة' : '✗ فشل ' + failed}  (${passed} نجح)`);
  process.exit(failed === 0 ? 0 : 1);
})().catch(e => { console.error(e); process.exit(1); });
