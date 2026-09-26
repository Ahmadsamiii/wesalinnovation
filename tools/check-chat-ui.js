/* ==========================================================================
 *  وصال: فحص دورة السؤال في المحادثة (الإرسال والإيقاف والتعديل وإعادة التوليد)
 *
 *  على نسخة محلية فقط. من جذر المستودع:
 *      php -S 127.0.0.1:8080 &
 *      NODE_PATH="$(npm root -g)" node tools/check-chat-ui.js
 *
 *  لا يحتاج قاعدة بيانات ولا مفتاح نموذج: يستبدل fetch داخل الصفحة بخادم وهمي
 *  يبث الرد على دفعات بتوقيت يحدده كل سيناريو، ويحترم الإلغاء كما يفعل المتصفح
 *  (يرفض الطلب قبل الترويسات، ويقطع جسم البث بعدها). يتحقق أن التعديل يعمل في
 *  كل مرحلة من الرد، وأنه يسحب السؤال القديم من سياق النموذج ومن المحفوظات، وأن
 *  أي رد يصل بعد سحب سؤاله لا يظهر ولا يُحفظ، وأن لوحة المفاتيح تعمل في خانة
 *  السؤال، وأن زر إعدادات الوصول العائم لا يغطي زر الإرسال والإيقاف.
 * ========================================================================== */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const BASE = process.env.BASE || 'http://127.0.0.1:8080';
if (!/^http:\/\/(127\.0\.0\.1|localhost)(:\d+)?$/.test(BASE)) {
  console.error('يعمل على خادم محلي فقط (BASE=http://127.0.0.1:PORT).');
  process.exit(2);
}

let passed = 0, failed = 0;
const check = (name, ok, extra = '') => { ok ? passed++ : failed++; console.log(`  ${ok ? '✓' : '✗'} ${name}${extra && !ok ? '  | ' + extra : ''}`); };

/* خادم وهمي داخل الصفحة. window.__mock يضبطه كل سيناريو:
   stream: 'sse' (افتراضي) أو 'fail' (انقطاع قبل الترويسات) أو 'json' (رد json كما هو)
   headerDelay: انتظار قبل الترويسات، frames: [[event, data, waitMs], ...]
   classicDelay/classicReply/classicSources: للمسار البديل chat.php */
function fakeServer() {
  const sleep = ms => new Promise(r => setTimeout(r, ms));
  const abortErr = () => new DOMException('The user aborted a request.', 'AbortError');
  const wait = (ms, signal) => new Promise((res, rej) => {
    if (signal && signal.aborted) return rej(abortErr());
    const t = setTimeout(res, ms);
    if (signal) signal.addEventListener('abort', () => { clearTimeout(t); rej(abortErr()); });
  });
  const json = o => new Response(JSON.stringify(o), { headers: { 'Content-Type': 'application/json' } });
  window.__calls = [];
  window.__mock = {};
  window.fetch = async (url, opts = {}) => {
    const u = String(url), m = window.__mock, signal = opts.signal;
    const body = opts.body ? JSON.parse(opts.body) : null;
    if (u.includes('chat-stream.php')) {
      window.__calls.push({ ep: 'stream', body });
      if (m.stream === 'fail') throw new TypeError('Failed to fetch');
      await wait(m.headerDelay || 0, signal);
      if (m.stream === 'json') return json(m.json);
      const enc = new TextEncoder();
      const rs = new ReadableStream({
        async start(ctrl) {
          if (signal) signal.addEventListener('abort', () => { try { ctrl.error(abortErr()); } catch (e) {} });
          for (const [ev, data, ms] of (m.frames || [])) {
            await sleep(ms || 0);
            if (signal && signal.aborted) return;
            ctrl.enqueue(enc.encode(`event: ${ev}\ndata: ${JSON.stringify(data)}\n\n`));
          }
          if (!(signal && signal.aborted)) ctrl.close();
        }
      });
      return new Response(rs, { headers: { 'Content-Type': 'text/event-stream' } });
    }
    if (u.includes('chat.php')) {
      window.__calls.push({ ep: 'classic', body });
      await wait(m.classicDelay || 0, signal);
      return json({ ok: true, reply: m.classicReply || 'رد المسار البديل', sources: m.classicSources || [] });
    }
    return json({ ok: false });
  };
}

const reply = (text, ms = 0) => [['delta', { text }, ms], ['done', { tokens: 1 }, 0]];
const slow = (first, rest) => [['delta', { text: first }, 50], ['delta', { text: rest }, 2000], ['done', {}, 0]];

let browser;
const open_pages = new Set();
async function open(opts = {}) {
  const page = await browser.newPage(opts.viewport ? { viewport: opts.viewport } : {});
  open_pages.add(page);
  page.setDefaultTimeout(5000);
  page.on('pageerror', e => check('بلا أخطاء في الصفحة', false, e.message));
  await page.addInitScript(fakeServer);
  await page.goto(BASE + '/#chat');
  await page.waitForFunction(() => typeof editLastQuestion === 'function');
  await page.evaluate(([u, l]) => { if (l) setLang(l); if (u) USER = u; go('chat'); }, [opts.user || null, opts.lang || null]);
  return page;
}
/* كل سيناريو مستقل: فشله يُسجَّل ويكمل الفحص بعده، وصفحاته تُغلق في كل حال */
async function scenario(title, fn) {
  console.log('\n' + title + ':');
  try { await fn(); }
  catch (e) { check('اكتمل السيناريو', false, String(e && e.message || e).split('\n')[0]); }
  finally { for (const p of open_pages) await p.close().catch(() => {}); open_pages.clear(); }
}
const mock = (page, m) => page.evaluate(m => { window.__mock = m; }, m);
async function ask(page, text) {
  await page.fill('#chatInput', text);
  await page.click('#chatSendBtn');
}
const idle = page => page.waitForFunction(() => !_streaming);
const edit = page => page.click('#chatBody .msg-user.msg-editable .msg-edit-btn');
const lastAiHas = (page, t) => page.waitForFunction(t => document.querySelector('#chatBody .msg-ai:last-child .msg-bubble').textContent.includes(t), t);
const state = page => page.evaluate(() => ({
  input: document.getElementById('chatInput').value,
  users: [...document.querySelectorAll('#chatBody .msg-user')].map(m => m.dataset.raw),
  ais: [...document.querySelectorAll('#chatBody .msg-ai')].slice(1).map(m => m.querySelector('.msg-bubble').textContent.trim()),
  typing: !!document.getElementById('typingInd'),
  streaming: _streaming,
  sendLabel: document.getElementById('chatSendBtn').getAttribute('aria-label'),
  status: document.getElementById('chatStreamStatus').textContent,
  hist: LS.get('hist', []),
  saved: USER ? LS.get(chatKey(), []).map(c => c.q) : null,
  calls: window.__calls.map(c => c.ep),
  focused: document.activeElement && document.activeElement.id,
}));

(async () => {
  /* دالتان بالاسم نفسه في سكربت الصفحة: الأخيرة تحجب الأولى بصمت. هكذا حجبت
     chatKey() مفتاح المحفوظات معالج لوحة المفاتيح في خانة السؤال فتوقف Enter. */
  console.log('\nلا دالتين بالاسم نفسه في الصفحة:');
  for (const f of ['index.html', 'survey.html', 'ticket.html', 'corporate.html']) {
    const src = fs.readFileSync(path.join(__dirname, '..', f), 'utf8');
    const seen = new Set(), dup = new Set();
    for (const m of src.matchAll(/(?:^|[^.\w$])function\s+([\w$]+)\s*\(/g)) (seen.has(m[1]) ? dup : seen).add(m[1]);
    check(f, dup.size === 0, [...dup].join('، '));
  }

  browser = await chromium.launch();

  await scenario('لوحة المفاتيح في خانة السؤال', async () => {
    const page = await open();
    await mock(page, { frames: slow('جزء أول ', 'لن يصل') });
    await page.fill('#chatInput', 'سؤال بلوحة المفاتيح');
    await page.press('#chatInput', 'Enter');
    let s = await state(page);
    check('Enter يرسل السؤال', s.users.join('|') === 'سؤال بلوحة المفاتيح' && s.input === '', JSON.stringify(s));
    await lastAiHas(page, 'جزء');
    await page.press('#chatInput', 'Escape');
    await idle(page);
    s = await state(page);
    check('Esc يوقف الرد ويبقى ما وصل منه', s.ais.join('|') === 'جزء أول' && s.status === 'توقف الرد.' && s.hist.length === 0, JSON.stringify(s));
    await page.fill('#chatInput', 'سطر أول');
    await page.press('#chatInput', 'Shift+Enter');
    s = await state(page);
    check('Shift+Enter سطر جديد لا إرسال', s.input === 'سطر أول\n' && s.users.length === 1, JSON.stringify(s));
  });

  await scenario('التعديل قبل وصول أول كلمة (الحالة في لقطة البلاغ)', async () => {
    const page = await open();
    await mock(page, { headerDelay: 1500, frames: reply('رد متأخر') });
    await ask(page, 'التقنيات المساعدة');
    await page.waitForTimeout(200);
    let s = await state(page);
    check('زر التعديل ظاهر والرد قيد الوصول', s.streaming && await page.isVisible('#chatBody .msg-user.msg-editable .msg-edit-btn'));
    await edit(page);
    s = await state(page);
    check('السؤال عاد إلى خانة الكتابة', s.input === 'التقنيات المساعدة', s.input);
    check('التركيز في خانة الكتابة', s.focused === 'chatInput', s.focused);
    check('اختفى السؤال وفقاعة الرد', s.users.length === 0 && s.ais.length === 0, JSON.stringify(s));
    await idle(page);
    s = await state(page);
    check('زر الإرسال عاد «إرسال»', s.sendLabel === 'إرسال', s.sendLabel);
    check('حالة قارئ الشاشة تطلب التعديل', s.status === 'عدّل سؤالك ثم أرسله.', s.status);
    await page.waitForTimeout(1700);
    s = await state(page);
    check('لم يظهر رد بعد سحب السؤال', s.ais.length === 0, JSON.stringify(s.ais));
    check('لم يدخل شيء سياق النموذج', s.hist.length === 0, JSON.stringify(s.hist));
    check('لا سقوط لـchat.php بعد الإلغاء', !s.calls.includes('classic'), JSON.stringify(s.calls));
    await mock(page, { frames: reply('رد السؤال المعدّل') });
    await ask(page, 'ما التقنيات المساعدة للمكفوفين؟');
    await idle(page);
    s = await state(page);
    check('السؤال المعدّل يُرسل ويُجاب', s.users.join('|') === 'ما التقنيات المساعدة للمكفوفين؟' && s.ais.join('|') === 'رد السؤال المعدّل', JSON.stringify(s));
  });

  await scenario('التعديل أثناء كتابة الرد', async () => {
    const page = await open();
    await mock(page, { frames: slow('بداية الرد ', 'وبقيته') });
    await ask(page, 'سؤال أول');
    await lastAiHas(page, 'بداية');
    await edit(page);
    await idle(page);
    await page.waitForTimeout(2200);
    const s = await state(page);
    check('توقف البث وعاد السؤال للخانة', s.input === 'سؤال أول' && s.ais.length === 0 && s.users.length === 0, JSON.stringify(s));
    check('الجزء الذي وصل لم يُحفظ في السياق', s.hist.length === 0, JSON.stringify(s.hist));
  });

  await scenario('التعديل بعد اكتمال الرد يسحبه من سياق النموذج ومن المحفوظات', async () => {
    const page = await open({ user: { id: 7, name: 'سارة', tokens: 30 } });
    await mock(page, { frames: reply('رد أول') });
    await ask(page, 'سؤال سابق'); await idle(page);
    await mock(page, { frames: reply('رد على السؤال الخاطئ') });
    await ask(page, 'سؤال خاطئ'); await idle(page);
    let s = await state(page);
    check('قبل التعديل: السؤالان في السياق والمحفوظات', s.hist.length === 4 && s.saved.join('|') === 'سؤال خاطئ|سؤال سابق', JSON.stringify(s));
    await edit(page);
    s = await state(page);
    check('بقي السؤال السابق ورده وحدهما', s.users.join('|') === 'سؤال سابق' && s.ais.join('|') === 'رد أول', JSON.stringify(s));
    check('السياق بلا السؤال الخاطئ', JSON.stringify(s.hist.map(h => h.text)) === JSON.stringify(['سؤال سابق', 'رد أول']), JSON.stringify(s.hist));
    check('المحفوظات بلا السؤال الخاطئ', s.saved.join('|') === 'سؤال سابق', JSON.stringify(s.saved));
    await mock(page, { frames: reply('رد صحيح') });
    await ask(page, 'سؤال مصحح');
    await idle(page);
    const sent = await page.evaluate(() => window.__calls.at(-1).body.history.map(h => h.text));
    check('النموذج لا يرى السؤال القديم مع المعدّل', JSON.stringify(sent) === JSON.stringify(['سؤال سابق', 'رد أول']), JSON.stringify(sent));
  });

  await scenario('إعادة التوليد ثم التعديل', async () => {
    const page = await open();
    await mock(page, { frames: reply('الرد الأول') });
    await ask(page, 'سؤال'); await idle(page);
    await mock(page, { frames: reply('الرد الثاني') });
    await page.click('#chatBody .msg-ai:last-child [onclick^="regenerateMsg"]'); await idle(page);
    let s = await state(page);
    check('ردّان للسؤال نفسه', s.ais.length === 2 && s.hist.length === 4, JSON.stringify(s));
    await edit(page);
    s = await state(page);
    check('التعديل يسحب الردين معاً من السجل والسياق', s.ais.length === 0 && s.hist.length === 0, JSON.stringify(s));
  });

  await scenario('إعادة توليد رد أقدم تبقى لسؤالها', async () => {
    const page = await open();
    await mock(page, { frames: reply('رد أ') });
    await ask(page, 'سؤال أ'); await idle(page);
    await mock(page, { frames: reply('رد ب') });
    await ask(page, 'سؤال ب'); await idle(page);
    await mock(page, { frames: reply('رد أ المعاد') });
    await page.click('#chatBody .msg-ai:nth-child(3) [onclick^="regenerateMsg"]'); await idle(page);
    const q1 = await page.evaluate(() => window.__calls.at(-1).body.message);
    check('أعيد توليد رد السؤال الصحيح', q1 === 'سؤال أ', q1);
    await mock(page, { frames: reply('رد أ الثالث') });
    await page.click('#chatBody .msg-ai:last-child [onclick^="regenerateMsg"]'); await idle(page);
    const q2 = await page.evaluate(() => window.__calls.at(-1).body.message);
    check('الرد المعاد في آخر السجل يعرف سؤاله', q2 === 'سؤال أ', q2);
    await edit(page);
    const s = await state(page);
    check('تعديل «سؤال ب» لا يمس ردود «سؤال أ»', s.users.join('|') === 'سؤال أ' && s.ais.join('|') === 'رد أ|رد أ المعاد|رد أ الثالث', JSON.stringify(s));
  });

  await scenario('المسار البديل chat.php', async () => {
    const page = await open();
    await mock(page, { stream: 'fail', classicDelay: 1500, classicReply: 'رد متأخر من البديل' });
    await ask(page, 'سؤال');
    await page.waitForFunction(() => window.__calls.some(c => c.ep === 'classic'));
    let s = await state(page);
    check('الرد قيد الوصول وزر الإيقاف ظاهر', s.streaming && s.sendLabel === 'إيقاف', JSON.stringify(s));
    await edit(page);
    await idle(page);
    await page.waitForTimeout(1700);
    s = await state(page);
    check('التعديل يعمل ولا يظهر رد يتيم بعده', s.input === 'سؤال' && s.ais.length === 0 && s.hist.length === 0, JSON.stringify(s));

    await page.click('#chatSendBtn');
    await page.waitForFunction(() => window.__calls.filter(c => c.ep === 'classic').length === 2);
    await page.click('#chatSendBtn');   // إيقاف
    await idle(page);
    await page.waitForTimeout(1700);
    s = await state(page);
    check('الإيقاف يعمل في المسار البديل', s.status === 'توقف الرد.' && s.users.length === 1 && s.ais.length === 1 && s.hist.length === 0, JSON.stringify(s));

    await mock(page, { stream: 'fail', classicReply: 'رد البديل' });
    await edit(page);
    await page.click('#chatSendBtn');
    await idle(page);
    s = await state(page);
    check('البديل يكتمل ويُحفظ كالمعتاد', s.ais.join('|') === 'رد البديل' && s.hist.length === 2, JSON.stringify(s));
  });

  await scenario('زر الإيقاف أثناء البث (بلا تغيير عن السابق)', async () => {
    const page = await open();
    await mock(page, { frames: slow('جزء أول ', 'لن يصل') });
    await ask(page, 'سؤال');
    await lastAiHas(page, 'جزء');
    await page.click('#chatSendBtn');
    await idle(page);
    const s = await state(page);
    check('يبقى الجزء الذي وصل ولا يُحفظ', s.ais.join('|') === 'جزء أول' && s.hist.length === 0 && s.status === 'توقف الرد.', JSON.stringify(s));
    check('يبقى السؤال قابلاً للتعديل', await page.isVisible('#chatBody .msg-user.msg-editable .msg-edit-btn'));
  });

  await scenario('التحية ثم التعديل قبل ردها', async () => {
    const page = await open();
    await ask(page, 'مرحبا');
    await edit(page);
    await page.waitForTimeout(1000);
    const s = await state(page);
    check('لا رد يتيم ولا مؤشر كتابة', s.input === 'مرحبا' && s.ais.length === 0 && !s.typing, JSON.stringify(s));
  });

  const cardsOf = page => page.evaluate(() => {
    const m = document.querySelector('#chatBody .msg-ai:last-child');
    const box = m.querySelector('.msg-srcs');
    return {
      heading: box ? box.querySelector('.msg-srcs-h').textContent.trim() : null,
      cards: [...m.querySelectorAll('.msg-srccard')].map(c => ({
        name: c.querySelector('b').textContent, host: c.querySelector('bdi').textContent,
        href: c.getAttribute('href'), target: c.getAttribute('target'),
        logo: c.querySelector('img') ? c.querySelector('img').getAttribute('src') : 'رمز عام' })),
      order: [...m.children[1].children].map(e => e.className),
      text: m.textContent,
      raw: m.dataset.raw,
    };
  });

  await scenario('مصدر الإجابة تحت الرد المتدفق', async () => {
    const page = await open();
    const items = [
      { url: 'https://apd.gov.sa/services', host: 'apd.gov.sa', title: 'خدمات الهيئة' },
      { url: 'https://www.hrsd.gov.sa/ar/x', host: 'portal.hrsd.gov.sa', title: 'صفحة الوزارة' },
      { url: 'https://example.org/p', host: 'example.org', title: 'جهة أخرى | الرئيسية' },
      { url: 'https://moe.gov.sa/p', host: 'moe.gov.sa', title: 'رابعة' },
    ];
    await mock(page, { frames: [['delta', { text: 'نص الإجابة.\n\n' }, 0], ['suggestions', { items: ['سؤال متابعة؟'] }, 0], ['sources', { items }, 0], ['done', {}, 0]] });
    await ask(page, 'سؤال'); await idle(page);
    await page.waitForFunction(() => [...document.querySelectorAll('.msg-srclogo img')].every(i => i.complete && i.naturalWidth));
    const r = await cardsOf(page);
    check('العنوان بعدد الجهات', r.heading === 'مصادر هذه الإجابة', r.heading);
    check('ثلاث جهات على الأكثر', r.cards.length === 3, JSON.stringify(r.cards));
    check('الاسم والشعار من «مصادر البيانات» بالنطاق', r.cards[0].name === 'هيئة رعاية الأشخاص ذوي الإعاقة' && r.cards[0].logo.includes('apd.gov.sa.webp'), JSON.stringify(r.cards[0]));
    check('النطاق الفرعي يأخذ جهة نطاقه وشعارها', r.cards[1].name === 'وزارة الموارد البشرية والتنمية الاجتماعية' && r.cards[1].logo.includes('hrsd.gov.sa.webp'), JSON.stringify(r.cards[1]));
    check('جهة خارج القائمة: اسمها من عنوان صفحتها ورمز عام', r.cards[2].name === 'جهة أخرى' && r.cards[2].logo === 'رمز عام', JSON.stringify(r.cards[2]));
    check('كل بطاقة تفتح صفحتها المحددة في نافذة جديدة', r.cards.every((c, i) => c.href === items[i].url && c.target === '_blank'), JSON.stringify(r.cards));
    check('تحت الإجابة وفوق أسئلة المتابعة', r.order.join(' ') === 'msg-bubble msg-srcs msg-suggestions msg-actions', r.order.join(' '));
    check('لا سطر فارغ في آخر الإجابة', r.raw === 'نص الإجابة.', JSON.stringify(r.raw));
    check('قارئ الشاشة يقرأ الاسم ثم النطاق ثم أن الرابط يفتح نافذة جديدة',
      await page.getByRole('link', { name: /^هيئة رعاية الأشخاص ذوي الإعاقة\s*،\s*apd\.gov\.sa\s*،\s*يفتح في نافذة جديدة$/ }).count() === 1,
      await page.locator('.msg-srcs').first().ariaSnapshot());
    check('قائمة بعنوانها لقارئ الشاشة', await page.getByRole('list', { name: 'مصادر هذه الإجابة' }).count() === 1);

    // الاسم يتبع ما نشره المدير في «مصادر البيانات»
    await page.evaluate(() => { LP = { sources: { l: { items: [{ id: 'x', f: { name: { ar: 'اسم عدّله المدير', en: 'Edited' }, domain: { v: 'www.apd.gov.sa' } } }] } } }; });
    await mock(page, { frames: [['delta', { text: 'نص.' }, 0], ['sources', { items: items.slice(0, 1) }, 0], ['done', {}, 0]] });
    await ask(page, 'سؤال آخر'); await idle(page);
    const r2 = await cardsOf(page);
    check('جهة واحدة: العنوان بالمفرد والاسم المنشور', r2.heading === 'مصدر هذه الإجابة' && r2.cards[0].name === 'اسم عدّله المدير', JSON.stringify(r2));
  });

  await scenario('مصدر الإجابة في المسار البديل وبدون مصدر', async () => {
    const page = await open();
    await mock(page, { stream: 'fail', classicReply: 'رد البديل', classicSources: [{ url: 'https://sdb.gov.sa/ar/kanaf', host: 'sdb.gov.sa', title: 'منتج كنف' }] });
    await ask(page, 'سؤال'); await idle(page);
    let r = await cardsOf(page);
    check('بطاقة المصدر في المسار البديل، باسم الجهة المعروف', r.cards.length === 1 && r.cards[0].name === 'بنك التنمية الاجتماعية', JSON.stringify(r.cards));
    check('لا عبارة «مبنية على مصادر رسمية»', !r.text.includes('مبنية على مصادر'), r.text);
    await mock(page, { stream: 'fail', classicReply: 'رد بلا مصدر' });
    await ask(page, 'سؤال ثانٍ'); await idle(page);
    r = await cardsOf(page);
    check('إجابة بلا مصدر: لا شيء تحتها', r.heading === null && !r.text.includes('مبنية على مصادر') && !r.text.includes('المصدر'), r.text);
    await mock(page, { frames: [['delta', { text: 'رد متدفق بلا مصدر.' }, 0], ['done', {}, 0]] });
    await ask(page, 'سؤال ثالث'); await idle(page);
    r = await cardsOf(page);
    check('رد متدفق بلا مصدر: لا شيء تحته', r.heading === null, JSON.stringify(r));
    await mock(page, { stream: 'json', json: { ok: false, fallback: true } });
    await ask(page, 'كيف أطلع بطاقة اثبات الاعاقة'); await idle(page);
    r = await cardsOf(page);
    check('إجابة قاعدة المعرفة المحلية ببطاقة جهتها', r.cards.length === 1 && r.cards[0].name === 'وزارة الموارد البشرية والتنمية الاجتماعية' && r.cards[0].href === 'https://hrsd.gov.sa', JSON.stringify(r.cards));
  });

  await scenario('مصدر الإجابة: أمان ونسخ وبلاغ ولغة', async () => {
    const page = await open();
    await page.evaluate(() => { window.__xss = 0; navigator.clipboard.writeText = t => { window.__copied = t; return Promise.resolve(); }; });
    await mock(page, { frames: [['delta', { text: 'نص.' }, 0], ['sources', { items: [
      { url: 'javascript:window.__xss=1', host: 'evil.test', title: '<img src=x onerror="window.__xss=1">' },
      { url: 'https://apd.gov.sa/services" onmouseover="window.__xss=1', host: 'apd.gov.sa', title: 'x' },
    ] }, 0], ['done', {}, 0]] });
    await ask(page, 'سؤال'); await idle(page);
    const r = await cardsOf(page);
    await page.hover('#chatBody .msg-ai:last-child .msg-srccard >> nth=1').catch(() => {});
    check('رابط غير https لا يصير رابطاً، والعنوان نص لا HTML', r.cards[0].href === null && r.cards[0].name.startsWith('<img') && !(await page.evaluate(() => document.querySelector('.msg-srcs img[src="x"]'))), JSON.stringify(r.cards[0]));
    check('رابط فيه علامات تنصيص يُرفض', r.cards[1].href === null, JSON.stringify(r.cards[1]));
    check('لم يُنفَّذ أي سكربت', await page.evaluate(() => window.__xss === 0));

    await mock(page, { frames: [['delta', { text: 'نص الإجابة.' }, 0], ['sources', { items: [{ url: 'https://apd.gov.sa/services', host: 'apd.gov.sa', title: '' }] }, 0], ['done', {}, 0]] });
    await ask(page, 'سؤال آخر'); await idle(page);
    await page.click('#chatBody .msg-ai:last-child [onclick^="copyMsg"]');
    const copied = await page.evaluate(() => window.__copied);
    check('النسخ يضم المصدر ورابطه', copied === 'نص الإجابة.\n\nمصدر هذه الإجابة:\n• هيئة رعاية الأشخاص ذوي الإعاقة: https://apd.gov.sa/services', JSON.stringify(copied));
    await page.click('#chatBody .msg-ai:last-child [onclick^="reportMsg"]');
    await page.waitForFunction(() => (document.getElementById('cMsg') || {}).value);
    const report = await page.inputValue('#cMsg');
    check('البلاغ عن خطأ يضم المصدر ليعرف المراجع الصفحة', report.includes('• هيئة رعاية الأشخاص ذوي الإعاقة: https://apd.gov.sa/services'), report);

    const en = await open({ lang: 'en' });
    await en.evaluate(() => { LP = { sources: { l: { items: [{ id: 'apd', f: { name: { ar: 'هيئة رعاية الأشخاص ذوي الإعاقة', en: 'Authority for the Care of Persons with Disabilities' }, domain: { v: 'apd.gov.sa' } } }] } } }; });
    await mock(en, { frames: [['delta', { text: 'Text.' }, 0], ['sources', { items: [{ url: 'https://apd.gov.sa/services', host: 'apd.gov.sa', title: '' }] }, 0], ['done', {}, 0]] });
    await ask(en, 'question'); await idle(en);
    const e = await cardsOf(en);
    check('بالإنجليزية: العنوان والاسم بالإنجليزية', e.heading === 'Source for this answer' && e.cards[0].name === 'Authority for the Care of Persons with Disabilities', JSON.stringify(e));
  });

  await scenario('زر الإرسال والإيقاف لا يغطيه زر إعدادات الوصول العائم', async () => {
    for (const [w, h, lang] of [[1745, 880, 'ar'], [1280, 720, 'ar'], [390, 844, 'ar'], [1280, 720, 'en'], [390, 844, 'en']]) {
      const page = await open({ viewport: { width: w, height: h }, lang });
      await mock(page, { headerDelay: 60000 });
      await page.fill('#chatInput', 'سؤال');
      await page.evaluate(() => { send(); });
      await page.waitForTimeout(300);
      const covered = await page.evaluate(() => {
        const b = document.getElementById('chatSendBtn'), r = b.getBoundingClientRect();
        return [[.5, .5], [.25, .25], [.75, .25], [.25, .75], [.75, .75]].filter(([x, y]) => !b.contains(document.elementFromPoint(r.x + r.width * x, r.y + r.height * y))).length;
      });
      check(`${w}×${h} ${lang === 'ar' ? 'بالعربية' : 'بالإنجليزية'}`, covered === 0, covered + ' من 5 نقاط مغطاة');
      await page.close();
      open_pages.delete(page);
    }
    // حجز الزاوية مشروط بوجود الزر: إن أُزيل لا يبقى فراغ يسار خانة السؤال
    const page = await open({ viewport: { width: 1280, height: 720 } });
    const pad = () => page.evaluate(() => getComputedStyle(document.querySelector('.chat-input-area')).paddingLeft);
    if (await page.evaluate(() => !!document.getElementById('fab'))) {
      const withFab = await pad();
      await page.evaluate(() => document.getElementById('fab').remove());
      const withoutFab = await pad();
      check('بلا الزر العائم يعود هامش خانة السؤال كما كان', parseFloat(withFab) > 60 && parseFloat(withoutFab) < 30, withFab + ' ثم ' + withoutFab);
    } else {
      const p = await pad();
      check('لا زر عائم ولا فراغ يسار خانة السؤال', parseFloat(p) < 30, p);
    }
  });

  await browser.close();
  console.log(`\n${passed} نجح، ${failed} فشل`);
  process.exit(failed ? 1 : 0);
})().catch(e => { console.error(e); process.exit(1); });
