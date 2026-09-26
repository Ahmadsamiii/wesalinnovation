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
 *  السؤال، وأن لا شيء يغطي زر الإرسال والإيقاف.
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
const PEN = '#chatBody .msg-user.msg-editable .msg-edit-btn';
const edit = page => page.click(PEN);   // يفتح خانة التعديل مكان السؤال
const cancelEdit = page => page.click('#chatBody .msg-editno');
async function editTo(page, text) {     // يعدّل السؤال في مكانه ويرسله بـ✓
  await edit(page);
  await page.fill('#chatBody .msg-edit-input', text);
  await page.click('#chatBody .msg-edityes');
}
const lastAiHas = (page, t) => page.waitForFunction(t => document.querySelector('#chatBody .msg-ai:last-child .msg-bubble').textContent.includes(t), t);
const state = page => page.evaluate(() => ({
  input: document.getElementById('chatInput').value,
  users: [...document.querySelectorAll('#chatBody .msg-user')].map(m => m.dataset.raw),
  ais: [...document.querySelectorAll('#chatBody .msg-ai')].slice(1).map(m => m.querySelector('.msg-bubble').textContent.trim()),
  editing: !!document.querySelector('#chatBody .msg-user.editing'),
  editValue: (document.querySelector('#chatBody .msg-edit-input') || {}).value,
  typing: !!document.getElementById('typingInd'),
  streaming: _streaming,
  sendLabel: document.getElementById('chatSendBtn').getAttribute('aria-label'),
  status: document.getElementById('chatStreamStatus').textContent,
  hist: LS.get('hist', []),
  saved: USER ? LS.get(chatKey(), []).map(c => c.q) : null,
  calls: window.__calls.map(c => c.ep),
  focused: document.activeElement && (document.activeElement.id || document.activeElement.className),
}));
const texts = h => JSON.stringify(h.map(x => x.text));

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

  await scenario('التعديل في مكان السؤال والرد قيد الوصول (الحالة في لقطة البلاغ)', async () => {
    const page = await open();
    await mock(page, { headerDelay: 1500, frames: reply('الرد الأول') });
    await ask(page, 'التقنيات المساعدة');
    await page.waitForTimeout(200);
    let s = await state(page);
    check('زر التعديل ظاهر والرد قيد الوصول', s.streaming && await page.isVisible(PEN));
    await edit(page);
    s = await state(page);
    check('خانة التعديل مكان السؤال وفيها نصه والتركيز فيها', s.editing && s.editValue === 'التقنيات المساعدة' && s.focused === 'msg-edit-input', JSON.stringify(s));
    check('خانة الكتابة أسفل الصفحة لم تتغير', s.input === '', s.input);
    check('بجانبها ✓ و✗ بأسماء لقارئ الشاشة',
      await page.getByRole('textbox', { name: 'عدّل سؤالك' }).count() === 1
      && await page.getByRole('button', { name: 'إرسال التعديل' }).count() === 1
      && await page.getByRole('button', { name: 'إلغاء التعديل' }).count() === 1);
    check('الرد يكمل أثناء التعديل', s.streaming && s.ais.length === 1, JSON.stringify(s));
    await cancelEdit(page);
    s = await state(page);
    check('✗ يعيد السؤال كما كان والتركيز إلى زر التعديل', !s.editing && s.users.join('|') === 'التقنيات المساعدة' && String(s.focused).includes('msg-edit-btn'), JSON.stringify(s));
    await idle(page);
    s = await state(page);
    check('✗ يبقي الرد الأول ويكمله', s.ais.join('|') === 'الرد الأول' && s.hist.length === 2, JSON.stringify(s));

    await mock(page, { headerDelay: 1500, frames: reply('رد لن يظهر') });
    await ask(page, 'سؤال ناقص');
    await page.waitForTimeout(200);
    await mock(page, { frames: reply('رد السؤال المعدّل') });
    await editTo(page, 'ما التقنيات المساعدة للمكفوفين؟');
    await idle(page);
    await page.waitForTimeout(1700);
    s = await state(page);
    check('✓ يضع النص الجديد في مكان السؤال', s.users.join('|') === 'التقنيات المساعدة|ما التقنيات المساعدة للمكفوفين؟', JSON.stringify(s.users));
    check('✓ يوقف الرد الجاري ويجيب عن النص الجديد', s.ais.join('|') === 'الرد الأول|رد السؤال المعدّل', JSON.stringify(s.ais));
    check('سياق النموذج بلا السؤال القديم', texts(s.hist) === JSON.stringify(['التقنيات المساعدة', 'الرد الأول', 'ما التقنيات المساعدة للمكفوفين؟', 'رد السؤال المعدّل']), texts(s.hist));
    check('لا سقوط لـchat.php بعد الإيقاف', !s.calls.includes('classic'), JSON.stringify(s.calls));
    const sent = await page.evaluate(() => window.__calls.at(-1).body);
    check('النموذج سُئل النص الجديد بسياق بلا القديم', sent.message === 'ما التقنيات المساعدة للمكفوفين؟' && texts(sent.history) === JSON.stringify(['التقنيات المساعدة', 'الرد الأول']), JSON.stringify(sent));
  });

  await scenario('التعديل أثناء كتابة الرد', async () => {
    const page = await open();
    await mock(page, { frames: slow('بداية الرد ', 'وبقيته') });
    await ask(page, 'سؤال أول');
    await lastAiHas(page, 'بداية');
    await edit(page);
    await lastAiHas(page, 'وبقيته');
    let s = await state(page);
    check('الرد يكتمل وخانة التعديل مفتوحة', s.editing && s.ais.join('|') === 'بداية الرد وبقيته', JSON.stringify(s));
    await cancelEdit(page);

    await mock(page, { frames: slow('جزء ', 'لن يكتمل') });
    await ask(page, 'سؤال ثانٍ');
    await lastAiHas(page, 'جزء');
    await mock(page, { frames: reply('رد النص الجديد') });
    await editTo(page, 'سؤال ثانٍ معدّل');
    await idle(page);
    await page.waitForTimeout(2200);
    s = await state(page);
    check('✓ أثناء البث: يختفي ما وصل ويُجاب النص الجديد', s.users.join('|') === 'سؤال أول|سؤال ثانٍ معدّل' && s.ais.join('|') === 'بداية الرد وبقيته|رد النص الجديد', JSON.stringify(s));
    check('ما وصل من الرد الموقوف لم يُحفظ في السياق', texts(s.hist) === JSON.stringify(['سؤال أول', 'بداية الرد وبقيته', 'سؤال ثانٍ معدّل', 'رد النص الجديد']), texts(s.hist));
  });

  await scenario('التعديل بعد اكتمال الرد يسحب القديم من سياق النموذج ومن المحفوظات', async () => {
    const page = await open({ user: { id: 7, name: 'سارة', tokens: 30 } });
    await mock(page, { frames: reply('رد أول') });
    await ask(page, 'سؤال سابق'); await idle(page);
    await mock(page, { frames: reply('رد على السؤال الخاطئ') });
    await ask(page, 'سؤال خاطئ'); await idle(page);
    let s = await state(page);
    check('قبل التعديل: السؤالان في السياق والمحفوظات', s.hist.length === 4 && s.saved.join('|') === 'سؤال خاطئ|سؤال سابق', JSON.stringify(s));
    await mock(page, { frames: reply('رد صحيح') });
    await editTo(page, 'سؤال مصحح');
    await idle(page);
    s = await state(page);
    check('السؤال المصحح مكان الخاطئ ورده بدل رده', s.users.join('|') === 'سؤال سابق|سؤال مصحح' && s.ais.join('|') === 'رد أول|رد صحيح', JSON.stringify(s));
    check('السياق بلا السؤال الخاطئ', texts(s.hist) === JSON.stringify(['سؤال سابق', 'رد أول', 'سؤال مصحح', 'رد صحيح']), texts(s.hist));
    check('المحفوظات بلا السؤال الخاطئ', s.saved.join('|') === 'سؤال مصحح|سؤال سابق', JSON.stringify(s.saved));
    const sent = await page.evaluate(() => window.__calls.at(-1).body.history);
    check('النموذج لا يرى السؤال القديم مع المعدّل', texts(sent) === JSON.stringify(['سؤال سابق', 'رد أول']), texts(sent));
  });

  await scenario('إعادة التوليد ثم التعديل', async () => {
    const page = await open();
    await mock(page, { frames: reply('الرد الأول') });
    await ask(page, 'سؤال'); await idle(page);
    await mock(page, { frames: reply('الرد الثاني') });
    await page.click('#chatBody .msg-ai:last-child [onclick^="regenerateMsg"]'); await idle(page);
    let s = await state(page);
    check('ردّان للسؤال نفسه', s.ais.length === 2 && s.hist.length === 4, JSON.stringify(s));
    await mock(page, { frames: reply('رد النص المعدّل') });
    await editTo(page, 'سؤال معدّل'); await idle(page);
    s = await state(page);
    check('التعديل يسحب الردين معاً من السجل والسياق', s.ais.join('|') === 'رد النص المعدّل' && texts(s.hist) === JSON.stringify(['سؤال معدّل', 'رد النص المعدّل']), JSON.stringify(s));
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
    await mock(page, { frames: reply('رد ب الجديد') });
    await editTo(page, 'سؤال ب معدّل'); await idle(page);
    const s = await state(page);
    check('تعديل «سؤال ب» لا يمس ردود «سؤال أ»', s.users.join('|') === 'سؤال أ|سؤال ب معدّل' && s.ais.join('|') === 'رد أ|رد أ المعاد|رد أ الثالث|رد ب الجديد', JSON.stringify(s));
  });

  await scenario('المسار البديل chat.php', async () => {
    const page = await open();
    await mock(page, { stream: 'fail', classicDelay: 1500, classicReply: 'رد متأخر من البديل' });
    await ask(page, 'سؤال');
    await page.waitForFunction(() => window.__calls.some(c => c.ep === 'classic'));
    let s = await state(page);
    check('الرد قيد الوصول وزر الإيقاف ظاهر', s.streaming && s.sendLabel === 'إيقاف', JSON.stringify(s));
    await edit(page);
    await cancelEdit(page);
    await idle(page);
    s = await state(page);
    check('✗ أثناء المسار البديل: يكتمل رده كما هو', s.ais.join('|') === 'رد متأخر من البديل' && s.hist.length === 2, JSON.stringify(s));

    await mock(page, { stream: 'fail', classicDelay: 1500, classicReply: 'رد لن يظهر' });
    await ask(page, 'سؤال ثانٍ');
    await page.waitForFunction(() => window.__calls.filter(c => c.ep === 'classic').length === 2);
    await mock(page, { stream: 'fail', classicReply: 'رد البديل للنص الجديد' });
    await editTo(page, 'سؤال ثانٍ معدّل');
    await idle(page);
    await page.waitForTimeout(1700);
    s = await state(page);
    check('✓ أثناء المسار البديل: يوقفه ويجيب عن النص الجديد', s.ais.join('|') === 'رد متأخر من البديل|رد البديل للنص الجديد' && s.hist.length === 4, JSON.stringify(s));

    await mock(page, { stream: 'fail', classicDelay: 1500 });
    await ask(page, 'سؤال ثالث');
    await page.waitForFunction(() => window.__calls.filter(c => c.ep === 'classic').length === 4);
    await page.click('#chatSendBtn');   // إيقاف
    await idle(page);
    await page.waitForTimeout(1700);
    s = await state(page);
    check('الإيقاف يعمل في المسار البديل', s.status === 'توقف الرد.' && s.ais.length === 3 && s.hist.length === 4, JSON.stringify(s));
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
    check('يبقى السؤال قابلاً للتعديل', await page.isVisible(PEN));
  });

  await scenario('التحية ثم التعديل قبل ردها', async () => {
    const page = await open();
    await ask(page, 'مرحبا');
    await mock(page, { frames: reply('رد السؤال بعد التحية') });
    await editTo(page, 'ما حقوقي في العمل؟');
    await idle(page);
    await page.waitForTimeout(1000);
    const s = await state(page);
    check('لا يظهر رد التحية القديم، ويُجاب النص الجديد', s.users.join('|') === 'ما حقوقي في العمل؟' && s.ais.join('|') === 'رد السؤال بعد التحية' && !s.typing, JSON.stringify(s));
  });

  await scenario('خانة التعديل: لوحة المفاتيح وحالات خاصة', async () => {
    const page = await open();
    await mock(page, { frames: slow('جزء ', 'وتكملة') });
    await ask(page, 'سؤال');
    await lastAiHas(page, 'جزء');
    await edit(page);
    await page.press('#chatBody .msg-edit-input', 'Escape');
    let s = await state(page);
    check('Esc يلغي التعديل ولا يوقف الرد', !s.editing && s.streaming, JSON.stringify(s));
    await idle(page);
    s = await state(page);
    check('والرد يكتمل', s.ais.join('|') === 'جزء وتكملة', JSON.stringify(s.ais));
    const n = s.calls.length;
    await edit(page);
    await page.click('#chatBody .msg-edityes');
    s = await state(page);
    check('نص لم يتغير: ✓ كالإلغاء بلا طلب جديد', !s.editing && s.calls.length === n && s.ais.join('|') === 'جزء وتكملة', JSON.stringify(s));
    await edit(page);
    await page.fill('#chatBody .msg-edit-input', '   ');
    await page.click('#chatBody .msg-edityes');
    s = await state(page);
    check('نص فارغ لا يُرسل وتبقى الخانة مفتوحة', s.editing && s.calls.length === n, JSON.stringify(s));
    await page.fill('#chatBody .msg-edit-input', 'سطر أول');
    await page.press('#chatBody .msg-edit-input', 'Shift+Enter');
    s = await state(page);
    check('Shift+Enter سطر جديد في خانة التعديل', s.editing && s.editValue === 'سطر أول\n', JSON.stringify(s.editValue));
    await mock(page, { frames: reply('رد بلوحة المفاتيح') });
    await page.fill('#chatBody .msg-edit-input', 'سؤال بلوحة المفاتيح');
    await page.press('#chatBody .msg-edit-input', 'Enter');
    await idle(page);
    s = await state(page);
    check('Enter يرسل التعديل', !s.editing && s.users.join('|') === 'سؤال بلوحة المفاتيح' && s.ais.join('|') === 'رد بلوحة المفاتيح', JSON.stringify(s));
    check('والتركيز يعود إلى زر التعديل', String(s.focused).includes('msg-edit-btn'), s.focused);
    await edit(page);
    await mock(page, { frames: reply('رد السؤال الجديد') });
    await ask(page, 'سؤال جديد من الخانة السفلى');
    await idle(page);
    s = await state(page);
    check('سؤال جديد من خانة الكتابة يغلق التعديل المفتوح', !s.editing && s.users.join('|') === 'سؤال بلوحة المفاتيح|سؤال جديد من الخانة السفلى', JSON.stringify(s));
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
    check('بطاقة المصدر في المسار البديل، باسم الجهة وشعارها وهي خارج القائمة', r.cards.length === 1 && r.cards[0].name === 'بنك التنمية الاجتماعية' && r.cards[0].logo.includes('sdb.gov.sa.webp'), JSON.stringify(r.cards));
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

  await scenario('زر الإرسال والإيقاف لا يغطيه شيء', async () => {
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
