/* ==========================================================================
 *  وصال — محرر «محتوى صفحة الهبوط»
 *
 *  يُحمَّل من index.html (loadContentAdmin) لمدير النظام فقط، ويعتمد على
 *  دوال الصفحة: api() و toast() و lpPublished().
 *
 *  المبدأ: المحرر يعمل على المستند «الفعّال» الكامل كما يرسله الخادم
 *  (api/content.php)، والخادم وحده يحوّله إلى فروقات عن الافتراضي ويتحقق منه.
 *  كل تعديل: يُرسَل للمعاينة فوراً، ويُحفظ في المسودة المشتركة بعد لحظة
 *  سكون (الأقسام المعدّلة فقط)، ولا يصل للزوار إلا بزر «نشر».
 *
 *  المعاينة iframe للصفحة نفسها بوضع ?lp-preview، تتلقى المستند عبر
 *  postMessage على نفس النطاق فقط، وترد بمسار أي نص يُنقر عليه.
 * ========================================================================== */
(function () {
'use strict';

const LANGS = [['ar', 'العربية'], ['en', 'English']];
const SAVE_DELAY = 900;
const S = {
  root: null, el: {}, schema: [], icons: {}, secMap: {},
  live: null, draft: null, etag: '', liveEtag: '', hasDraft: false,
  draftBy: null, draftAt: 0, liveBy: null, liveAt: 0,
  sec: 'hero', lang: 'ar', pvLang: 'ar', q: '', preview: true, device: 'desktop',
  dirty: new Set(), ver: {}, saving: false, again: false, saveTimer: 0, saveErr: '',
  open: {}, drag: null, frameReady: false, pvTimer: 0, focusTimer: 0, busy: false,
};

/* ---------------------------------------------------------------- أدوات */

function h(tag, attrs, ...kids) {
  const e = document.createElement(tag);
  for (const k in attrs || {}) {
    const v = attrs[k];
    if (v == null || v === false) continue;
    if (k === 'class') e.className = v;
    else if (k === 'text') e.textContent = v;
    else if (k.startsWith('on')) e.addEventListener(k.slice(2), v);
    else if (k === 'dataset') Object.assign(e.dataset, v);
    else e.setAttribute(k, v === true ? '' : v);
  }
  for (const c of kids.flat()) {
    if (c == null || c === false) continue;
    e.append(c instanceof Node ? c : document.createTextNode(String(c)));
  }
  return e;
}
function ico(name) {
  const s = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
  s.setAttribute('aria-hidden', 'true');
  s.setAttribute('focusable', 'false');
  const u = document.createElementNS('http://www.w3.org/2000/svg', 'use');
  u.setAttribute('href', '#ic-' + name);
  s.appendChild(u);
  return s;
}
const clone = o => JSON.parse(JSON.stringify(o));
const isMono = t => t === 'url' || t === 'email' || t === 'tel' || t === 'domain';
const tv = v => String(v == null ? '' : v).trim();
const langsOf = fd => isMono(fd.t) ? [['v', '']] : LANGS;
function normQ(s) {
  return String(s || '').toLowerCase()
    .replace(/[ً-ٰٟـ]/g, '').replace(/[أإآٱ]/g, 'ا').replace(/ى/g, 'ي').replace(/ة/g, 'ه');
}
function rid() { return ('c' + Math.random().toString(36).slice(2, 11)).padEnd(10, '0'); }
function plural(n) {
  if (n === 1) return 'تغيير واحد غير منشور';
  if (n === 2) return 'تغييران غير منشورين';
  if (n <= 10) return n + ' تغييرات غير منشورة';
  return n + ' تغييراً غير منشور';
}
function relTime(ts) {
  if (!ts) return '';
  const s = Math.max(0, (Date.now() - ts) / 1000);
  if (s < 45) return 'الآن';
  if (s < 3600) { const m = Math.round(s / 60); return 'قبل ' + (m === 1 ? 'دقيقة' : m === 2 ? 'دقيقتين' : m + (m <= 10 ? ' دقائق' : ' دقيقة')); }
  if (s < 86400) { const x = Math.round(s / 3600); return 'قبل ' + (x === 1 ? 'ساعة' : x === 2 ? 'ساعتين' : x + (x <= 10 ? ' ساعات' : ' ساعة')); }
  try { return new Date(ts).toLocaleDateString('ar-SA-u-ca-gregory-nu-latn', { day: 'numeric', month: 'long', year: 'numeric' }); }
  catch (e) { return ''; }
}
function monoError(t, v) {
  v = tv(v);
  if (!v) return '';
  if (t === 'url' && !/^https:\/\/[^\s/]+\.[^\s]+$/i.test(v)) return 'الرابط لازم يبدأ بـ https:// ويكون صحيحاً';
  if (t === 'email' && !/^[^\s@]+@[^\s@]+\.[a-z]{2,}$/i.test(v)) return 'بريد إلكتروني غير صحيح';
  if (t === 'tel' && !/^\+?[0-9][0-9\s\-()]{5,22}$/.test(v)) return 'أرقام ومسافات و+ فقط';
  if (t === 'domain' && !/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+(\/\S*)?$/i.test(v)) return 'نطاق غير صحيح — مثال: moh.gov.sa';
  return '';
}

/* ------------------------------------------------ القيم والافتراضي والفروق */

/** القيمة كما ستظهر: الفارغ في حقل له افتراضي يعني الافتراضي */
function ev(fd, val, def, l) {
  const k = isMono(fd.t) ? 'v' : l;
  return tv(val && val[k]) || (def ? tv(def[k]) : '');
}
function fEq(fd, a, b, def) {
  return langsOf(fd).every(([l]) => ev(fd, a, def, l) === ev(fd, b, def, l));
}
function defItem(ld, it) {
  if (!it || it.custom) return null;
  return ld.items.find(d => d.id === it.id) || null;
}
function itemEq(ld, x, y) {
  if (!!x.hidden !== !!y.hidden) return false;
  if (ld.icon && x.icon !== y.icon) return false;
  const d = defItem(ld, x);
  return ld.fields.every(fd => fEq(fd, x.f[fd.k], y.f[fd.k], d && d.f[fd.k]));
}
function listOf(doc, secId, k) { const s = doc[secId]; return (s && s.l && s.l[k]) || []; }

/** عدد التغييرات غير المنشورة في قسم — كل حقل أو بطاقة تغيّرت = واحد، والترتيب واحد */
function secChanges(id) {
  const sec = S.secMap[id], d = S.draft[id], l = S.live[id];
  let n = !!d.hidden !== !!l.hidden ? 1 : 0;
  sec.fields.forEach(fd => { if (!fEq(fd, d.f[fd.k], l.f[fd.k], fd.d)) n++; });
  sec.lists.forEach(ld => {
    const a = listOf(S.draft, id, ld.k), b = listOf(S.live, id, ld.k);
    const bm = new Map(b.map(x => [x.id, x]));
    a.forEach(x => { const y = bm.get(x.id); if (!y || !itemEq(ld, x, y)) n++; });
    b.forEach(y => { if (!a.some(x => x.id === y.id)) n++; });
    const ao = a.map(x => x.id).filter(i => bm.has(i));
    const bo = b.map(x => x.id).filter(i => ao.includes(i));
    if (ao.join() !== bo.join()) n++;
  });
  return n;
}
function totalChanges() { return S.schema.reduce((n, s) => n + secChanges(s.id), 0); }

/** الأخطاء الحالية في كل المستند (روابط وبريد…): مسار ← {قسم، رسالة} */
function allErrors() {
  const out = new Map();
  S.schema.forEach(sec => {
    const d = S.draft[sec.id];
    sec.fields.forEach(fd => {
      if (!isMono(fd.t)) return;
      const e = monoError(fd.t, d.f[fd.k] && d.f[fd.k].v);
      if (e) out.set(sec.id + '.' + fd.k, { sec: sec.id, msg: e, label: sec.label + ' ← ' + fd.l });
    });
    sec.lists.forEach(ld => listOf(S.draft, sec.id, ld.k).forEach((it, i) => ld.fields.forEach(fd => {
      if (!isMono(fd.t)) return;
      const e = monoError(fd.t, it.f[fd.k] && it.f[fd.k].v);
      if (e) out.set([sec.id, ld.k, it.id, fd.k].join('.'), { sec: sec.id, msg: e, label: sec.label + ' ← ' + ld.item + ' ' + (i + 1) + ' ← ' + fd.l });
    })));
  });
  return out;
}

/** نسخة المسودة للمعاينة: الفارغ يُملأ بالافتراضي كما سيفعل الخادم */
function previewDoc() {
  const out = clone(S.draft);
  const fill = (fd, v, d) => {
    if (!v || !d) return;
    langsOf(fd).forEach(([l]) => { if (!tv(v[l])) v[l] = d[l]; });
  };
  S.schema.forEach(sec => {
    const o = out[sec.id];
    sec.fields.forEach(fd => fill(fd, o.f[fd.k], fd.d));
    sec.lists.forEach(ld => listOf(out, sec.id, ld.k).forEach(it => {
      const d = defItem(ld, it);
      ld.fields.forEach(fd => fill(fd, it.f[fd.k], d && d.f[fd.k]));
    }));
  });
  return out;
}

function blankValue(fd) { return isMono(fd.t) ? { v: '' } : { ar: '', en: '' }; }
function defaultItem(ld, d) {
  const f = {};
  ld.fields.forEach(fd => { f[fd.k] = Object.assign(blankValue(fd), clone(d.f[fd.k] || {})); });
  return { id: d.id, custom: false, hidden: false, icon: ld.icon ? (d.i || 'star') : null, f };
}
function defaultSection(sec) {
  const f = {}, l = {};
  sec.fields.forEach(fd => { f[fd.k] = clone(fd.d); });
  sec.lists.forEach(ld => { l[ld.k] = ld.items.map(d => defaultItem(ld, d)); });
  return { hidden: false, f, l };
}

/* ------------------------------------------------------------ الحفظ */

function touch(secId) {
  S.dirty.add(secId);
  S.ver[secId] = (S.ver[secId] || 0) + 1;
  clearTimeout(S.saveTimer);
  S.saveTimer = setTimeout(saveNow, SAVE_DELAY);
  pushPreview();
  paintCounts();
}

async function saveNow() {
  clearTimeout(S.saveTimer);
  if (S.saving) { S.again = true; return; }
  const bad = new Set([...allErrors().values()].map(e => e.sec));
  const ids = [...S.dirty].filter(id => !bad.has(id));
  if (!ids.length) { paintBar(); return; }
  S.saving = true; paintBar();
  const sent = {}, sections = {};
  ids.forEach(id => { sent[id] = S.ver[id]; sections[id] = S.draft[id]; });
  const r = await api('content.php', { action: 'save_draft', etag: S.etag, sections });
  S.saving = false;
  if (r && r.ok) {
    S.etag = r.etag || '';
    S.hasDraft = !!r.hasDraft;
    S.draftBy = r.draftBy || null;
    S.draftAt = Date.now();
    S.saveErr = '';
    ids.forEach(id => { if (S.ver[id] === sent[id]) S.dirty.delete(id); });
    if (r.conflict) {
      // مدير آخر حفظ بعد آخر مزامنة: أقسامه تدخل، وأقسامك المعدّلة تبقى كما هي
      const changed = [];
      Object.keys(r.draft || {}).forEach(id => {
        if (S.dirty.has(id) || ids.includes(id) || !S.secMap[id]) return;
        if (JSON.stringify(S.draft[id]) !== JSON.stringify(r.draft[id])) { S.draft[id] = r.draft[id]; changed.push(id); }
      });
      toast('عدّل ' + (r.by || 'مدير آخر') + ' المسودة أيضاً — دمجنا تعديلاته مع تعديلاتك.', 'info', 6500);
      if (changed.length) { renderMain(); pushPreview(true); }
    }
    if (r.liveEtag && r.liveEtag !== S.liveEtag) {
      // نُشر شيء من مكان آخر: نحدّث مرجع «المنشور» حتى يصدق العدّاد
      const p = await api('content.php', { action: 'editor' });
      if (p && p.ok) { S.live = p.live; S.liveEtag = p.liveEtag; S.liveBy = p.liveBy; S.liveAt = p.liveAt; }
    }
  } else {
    S.saveErr = (r && r.error) || 'تعذّر الاتصال بالخادم';
    if (r && r.offline) S.saveTimer = setTimeout(saveNow, 5000);
  }
  paintCounts();
  if (S.again || (S.dirty.size && !S.saveErr && ids.some(id => S.dirty.has(id)))) {
    S.again = false;
    S.saveTimer = setTimeout(saveNow, 300);
  }
}

async function flush() {
  clearTimeout(S.saveTimer);
  for (let i = 0; i < 100 && S.saving; i++) await new Promise(r => setTimeout(r, 60));
  if (S.dirty.size) await saveNow();
  for (let i = 0; i < 100 && S.saving; i++) await new Promise(r => setTimeout(r, 60));
}

/* ------------------------------------------------------------ المعاينة */

function pvLang() { return S.lang === 'both' ? S.pvLang : S.lang; }
function pvUrl() { return location.pathname + '?lp-preview=1&lang=' + pvLang(); }
function post(msg) {
  const f = S.el.frame;
  if (!f || !S.frameReady || !f.contentWindow) return;
  f.contentWindow.postMessage(Object.assign({ src: 'wesal-lp' }, msg), location.origin);
}
function pushPreview(now) {
  clearTimeout(S.pvTimer);
  const go = () => post({ type: 'doc', doc: previewDoc(), lang: pvLang() });
  if (now) go(); else S.pvTimer = setTimeout(go, 120);
}
function previewFocus(path) {
  clearTimeout(S.focusTimer);
  S.focusTimer = setTimeout(() => post({ type: 'focus', path }), 160);
}
function fitPreview() {
  const st = S.el.stage, fr = S.el.frameBox;
  if (!st || !fr || !st.clientWidth) return;
  const W = st.clientWidth, H = st.clientHeight;
  if (S.device === 'desktop') {
    const vw = 1280, sc = Math.min(1, W / vw);
    fr.style.width = vw + 'px';
    fr.style.height = Math.ceil(H / sc) + 'px';
    fr.style.transform = 'translateX(-50%) scale(' + sc + ')';
  } else {
    const vw = 390, vh = 844, pad = 20, sc = Math.min(1, (H - 28) / (vh + pad), (W - 24) / (vw + pad));
    fr.style.width = (vw + pad) + 'px';
    fr.style.height = (vh + pad) + 'px';
    fr.style.transform = 'translateX(-50%) scale(' + sc + ')';
  }
}
function onMessage(e) {
  const f = S.el.frame;
  if (e.origin !== location.origin || !f || e.source !== f.contentWindow) return;
  const m = e.data || {};
  if (m.src !== 'wesal-lp') return;
  if (m.type === 'ready') {
    S.frameReady = true;
    pushPreview(true);
    if (!S.q) post({ type: 'section', id: S.sec });
  } else if (m.type === 'pick' && m.path) {
    pick(String(m.path));
  }
}
function reloadFrame() {
  S.frameReady = false;
  if (S.el.frame) S.el.frame.src = pvUrl();
}

/** نقرة على نص في المعاينة: افتح قسمه ووسّع بطاقته وركّز حقله */
function pick(path) {
  const p = path.split('.');
  if (!S.secMap[p[0]]) return;
  S.q = '';
  if (S.el.search) S.el.search.value = '';
  S.sec = p[0];
  if (p.length >= 3) S.open[p.slice(0, 3).join('.')] = true;
  paintChips();
  renderMain();
  const want = S.lang === 'both' ? S.pvLang : S.lang;
  const sel = '[data-path="' + CSS.escape(path) + '"]';
  const inp = S.root.querySelector(sel + '[data-lang="' + want + '"]') || S.root.querySelector(sel)
           || (p.length === 3 && S.root.querySelector('[data-item="' + CSS.escape(path) + '"] .lpe-item-main'));
  if (!inp) return;
  inp.scrollIntoView({ block: 'center', behavior: 'smooth' });
  inp.focus({ preventScroll: true });
  const w = inp.closest('.lpe-field');
  if (w) { w.classList.add('flash'); setTimeout(() => w.classList.remove('flash'), 1400); }
}

/* ------------------------------------------------------------ نافذة التأكيد */

function ask(o) {
  return new Promise(res => {
    const prev = document.activeElement;
    const back = h('div', { class: 'lpe-modal-back lpe', dir: 'rtl', lang: 'ar' });
    const dlg = h('div', { class: 'lpe-modal', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'lpeModalT', 'aria-describedby': 'lpeModalD' });
    dlg.append(h('h3', { id: 'lpeModalT', text: o.title }), h('p', { id: 'lpeModalD', text: o.text }));
    let choice = o.options ? o.options[0].value : true;
    if (o.options) {
      const box = h('fieldset', { class: 'lpe-opts' }, h('legend', { class: 'lpe-sr', text: o.title }));
      o.options.forEach((op, i) => {
        box.append(h('label', { class: 'lpe-opt' },
          h('input', { type: 'radio', name: 'lpeOpt', value: op.value, checked: i === 0, onchange: () => { choice = op.value; } }),
          h('span', {}, h('b', { text: op.label }), op.desc ? h('small', { text: op.desc }) : null)));
      });
      dlg.append(box);
    }
    const close = v => {
      back.remove();
      document.removeEventListener('keydown', key, true);
      if (prev && prev.focus) prev.focus({ preventScroll: true });
      res(v);
    };
    const cancel = h('button', { type: 'button', class: 'lpe-btn', text: o.cancel || 'إلغاء', onclick: () => close(false) });
    const ok = h('button', { type: 'button', class: 'lpe-btn ' + (o.danger ? 'danger-solid' : 'primary'), text: o.confirm || 'تأكيد', onclick: () => close(choice) });
    dlg.append(h('div', { class: 'lpe-modal-actions' }, cancel, ok));
    back.append(dlg);
    back.addEventListener('mousedown', e => { if (e.target === back) close(false); });
    const key = e => {
      if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(false); return; }
      if (e.key !== 'Tab') return;
      const f = [...dlg.querySelectorAll('button,input')].filter(x => !x.disabled);
      const i = f.indexOf(document.activeElement);
      if (e.shiftKey && i <= 0) { e.preventDefault(); f[f.length - 1].focus(); }
      else if (!e.shiftKey && i === f.length - 1) { e.preventDefault(); f[0].focus(); }
    };
    document.addEventListener('keydown', key, true);
    document.body.append(back);
    // الإجراء الخطِر يبدأ التركيز على «إلغاء» حتى لا يُنفَّذ بضغطة Enter عابرة
    (dlg.querySelector('input:checked') || (o.danger ? cancel : ok)).focus();
  });
}

/* ------------------------------------------------------------ مكوّن الحقل */

/**
 * حقل واحد بلغته أو لغتيه. val كائن القيمة نفسه داخل المسودة ويُعدَّل في مكانه.
 * o: {fd, val, def, liveVal, path, secId, crumb, onChange}
 */
function fieldRow(o) {
  const { fd } = o;
  const mono = isMono(fd.t);
  const langs = mono ? [['v', '']] : (S.lang === 'both' ? LANGS : LANGS.filter(([l]) => l === S.lang));
  const wrap = h('div', { class: 'lpe-field' });
  if (o.crumb) wrap.append(o.crumb);
  const baseId = 'lpe-' + o.path.replace(/[^a-z0-9]/gi, '-');
  const head = h('div', { class: 'lpe-field-head' }, h('label', { for: baseId + '-' + langs[0][0], text: fd.l }));
  const pend = h('span', { class: 'lpe-badge pending', text: 'غير منشور', hidden: true });
  head.append(pend);
  wrap.append(head);
  const box = h('div', { class: 'lpe-langs' + (langs.length > 1 ? ' two' : '') });
  const parts = [];
  langs.forEach(([l, ln]) => {
    const multi = fd.t === 'area';
    const inp = h(multi ? 'textarea' : 'input', {
      id: baseId + '-' + l, maxlength: fd.max, rows: multi ? 3 : null,
      type: multi ? null : ({ url: 'url', email: 'email', tel: 'tel' }[fd.t] || 'text'),
      dir: l === 'ar' ? 'rtl' : 'ltr', lang: l === 'v' ? null : l,
      'aria-label': langs.length > 1 ? fd.l + ' — ' + ln : null,
      placeholder: o.def ? (o.def[l] || '') : (l === 'en' ? 'اختياري — يظهر النص العربي إن بقي فارغاً' : ''),
      dataset: { path: o.path, lang: l },
    });
    inp.value = o.val[l] || '';
    const cnt = h('span', { class: 'lpe-cnt', 'aria-hidden': 'true' });
    const rst = o.def ? h('button', {
      type: 'button', class: 'lpe-reset',
      title: 'النص الأصلي: ' + (o.def[l] || ''),
      'aria-label': 'إرجاع «' + fd.l + '»' + (ln ? ' ' + ln : '') + ' للنص الأصلي',
    }, ico('refresh'), 'الأصل') : null;
    const err = h('div', { class: 'lpe-err', role: 'alert', hidden: true });
    const cell = h('div', { class: 'lpe-in' },
      langs.length > 1 ? h('span', { class: 'lpe-tag', 'aria-hidden': 'true', text: ln }) : null,
      inp, err, h('div', { class: 'lpe-in-foot' }, rst || h('span'), cnt));
    const sync = () => {
      const n = (inp.value || '').length;
      cnt.textContent = n + '/' + fd.max;
      cnt.className = 'lpe-cnt' + (n >= fd.max ? ' full' : n >= fd.max * 0.9 ? ' warn' : '');
      if (rst) rst.disabled = ev(fd, o.val, o.def, l) === tv(o.def[l]);
      const pendL = !o.liveVal || ev(fd, o.val, o.def, l) !== ev(fd, o.liveVal, o.def, l);
      cell.classList.toggle('pend', pendL);
      const e = mono ? monoError(fd.t, inp.value) : '';
      err.textContent = e; err.hidden = !e;
      cell.classList.toggle('bad', !!e);
      inp.setAttribute('aria-invalid', e ? 'true' : 'false');
    };
    inp.addEventListener('input', () => {
      o.val[l] = inp.value;
      sync(); syncHead();
      if (o.onChange) o.onChange();
      touch(o.secId);
    });
    inp.addEventListener('focus', () => {
      if (S.lang === 'both' && l !== 'v' && S.pvLang !== l) { S.pvLang = l; paintPvLang(); pushPreview(true); }
      previewFocus(o.path);
    });
    if (rst) rst.addEventListener('click', () => {
      o.val[l] = o.def[l] || '';
      inp.value = o.val[l];
      sync(); syncHead();
      if (o.onChange) o.onChange();
      touch(o.secId);
      inp.focus();
    });
    parts.push(sync);
    box.append(cell);
  });
  const syncHead = () => { pend.hidden = !!o.liveVal && fEq(fd, o.val, o.liveVal, o.def); };
  parts.forEach(f => f());
  syncHead();
  wrap.append(box);
  return wrap;
}

/* ------------------------------------------------------------ مكوّن القائمة */

function itemTitle(ld, it) {
  const tf = ld.fields.find(f => !isMono(f.t));
  const d = defItem(ld, it);
  const l = S.lang === 'en' ? 'en' : 'ar';
  return (tf && ev(tf, it.f[tf.k], d && d.f[tf.k], l)) || (tf && ev(tf, it.f[tf.k], d && d.f[tf.k], l === 'ar' ? 'en' : 'ar')) || 'بدون عنوان';
}

function listBlock(sec, ld) {
  const items = S.draft[sec.id].l[ld.k];
  const liveItems = listOf(S.live, sec.id, ld.k);
  const wrap = h('div', { class: 'lpe-list' });
  const count = h('small', { text: items.length + ' من ' + ld.max });
  wrap.append(h('div', { class: 'lpe-list-head' }, h('h4', { text: ld.l }), count));
  const ol = h('ol', { class: 'lpe-items', 'aria-label': ld.l });
  const listKey = sec.id + '.' + ld.k;
  const moveTo = (from, to, focusSel) => {
    if (to < 0 || to >= items.length || from === to) return;
    const [x] = items.splice(from, 1);
    items.splice(to, 0, x);
    touch(sec.id);
    renderMain();
    const li = S.root.querySelector('[data-item="' + CSS.escape(listKey + '.' + x.id) + '"]');
    const b = li && (li.querySelector(focusSel) || li.querySelector('.lpe-item-main'));
    if (b) b.focus();
  };
  items.forEach((it, idx) => {
    const key = listKey + '.' + it.id;
    const d = defItem(ld, it);
    const liveIt = liveItems.find(x => x.id === it.id) || null;
    const li = h('li', { class: 'lpe-item' + (S.open[key] ? ' open' : '') + (it.hidden ? ' is-off' : ''), dataset: { item: key } });
    const bodyId = 'lpe-body-' + key.replace(/[^a-z0-9]/gi, '-');
    const tTxt = h('b', {});
    const badges = h('em', {});
    const paintHead = () => {
      tTxt.textContent = itemTitle(ld, it);
      const b = [];
      if (it.custom && !liveIt) b.push('جديدة');
      if (it.hidden) b.push('مخفية عن الزوار');
      if (liveIt ? !itemEq(ld, it, liveIt) : true) b.push('غير منشورة');
      badges.textContent = b.join(' · ');
      li.classList.toggle('is-off', !!it.hidden);
      if (rstBtn) rstBtn.disabled = !d || itemEq(ld, it, defaultItem(ld, d));
    };
    const handle = h('span', { class: 'lpe-handle', title: 'اسحب لإعادة الترتيب', 'aria-hidden': 'true' }, ico('menu'));
    const icoBox = ld.icon ? h('span', { class: 'lpe-item-ico' }, ico(it.icon || 'star')) : null;
    const main = h('button', {
      type: 'button', class: 'lpe-item-main', 'aria-expanded': S.open[key] ? 'true' : 'false', 'aria-controls': bodyId,
      onclick: () => { S.open[key] = !S.open[key]; renderMain(); const b = S.root.querySelector('[data-item="' + CSS.escape(key) + '"] .lpe-item-main'); if (b) b.focus(); },
    }, h('span', { class: 'lpe-item-no', text: String(idx + 1) }), icoBox, h('span', { class: 'lpe-item-txt' }, tTxt, badges), (() => { const c = ico('edit'); c.classList.add('lpe-chev'); return c; })());
    const btn = (cls, icon, label, fn, dis) => h('button', { type: 'button', class: 'lpe-btn icon ' + cls, title: label, 'aria-label': label + ': ' + itemTitle(ld, it), disabled: dis, onclick: fn }, ico(icon));
    const up = btn('lpe-up', 'chevron-down', 'تحريك لأعلى', () => moveTo(idx, idx - 1, '.lpe-up'), idx === 0);
    const down = btn('lpe-down', 'chevron-down', 'تحريك لأسفل', () => moveTo(idx, idx + 1, '.lpe-down'), idx === items.length - 1);
    const hide = btn('lpe-hide' + (it.hidden ? ' on' : ''), 'eye', it.hidden ? 'إظهار البطاقة' : 'إخفاء البطاقة', () => {
      it.hidden = !it.hidden; touch(sec.id); renderMain();
      const b = S.root.querySelector('[data-item="' + CSS.escape(key) + '"] .lpe-hide'); if (b) b.focus();
    });
    hide.setAttribute('aria-pressed', it.hidden ? 'true' : 'false');
    const rstBtn = d ? btn('lpe-rst', 'refresh', 'إرجاع البطاقة للأصل', () => {
      Object.assign(it, defaultItem(ld, d)); touch(sec.id); renderMain();
    }) : null;
    const del = btn('lpe-del', 'trash', 'حذف', async () => {
      const title = itemTitle(ld, it);
      const ok = await ask({
        title: 'حذف «' + title + '»؟',
        text: d ? 'تختفي من الصفحة بعد النشر، وتقدر ترجعها لاحقاً من «إرجاع القسم للأصل».'
                : 'تُحذف هذه البطاقة المضافة. لو كانت منشورة تختفي من الصفحة بعد النشر.',
        confirm: 'حذف', danger: true,
      });
      if (!ok) return;
      items.splice(items.indexOf(it), 1);
      delete S.open[key];
      touch(sec.id); renderMain();
      const next = S.root.querySelector('.lpe-list [data-item^="' + CSS.escape(listKey) + '."] .lpe-item-main');
      if (next) next.focus();
    });
    li.append(h('div', { class: 'lpe-item-head' }, handle, main, h('div', { class: 'lpe-item-tools' }, h('span', { class: 'lpe-move' }, up, down), hide, rstBtn, del)));
    if (S.open[key]) {
      const body = h('div', { class: 'lpe-item-body', id: bodyId });
      ld.fields.forEach(fd => {
        if (!it.f[fd.k]) it.f[fd.k] = blankValue(fd);
        body.append(fieldRow({
          fd, val: it.f[fd.k], def: d && d.f[fd.k], liveVal: liveIt && liveIt.f[fd.k],
          path: key + '.' + fd.k, secId: sec.id, onChange: paintHead,
        }));
      });
      if (ld.icon) body.append(iconPicker(it, key, () => {
        if (icoBox) { icoBox.textContent = ''; icoBox.append(ico(it.icon)); }
        paintHead(); touch(sec.id);
      }));
      li.append(body);
    }
    paintHead();
    // السحب والإفلات بالمؤشر — الأزرار أعلاه هي البديل للوحة المفاتيح واللمس
    handle.addEventListener('pointerdown', () => { li.draggable = true; });
    li.addEventListener('dragstart', e => {
      S.drag = { list: listKey, from: idx };
      li.classList.add('dragging');
      e.dataTransfer.effectAllowed = 'move';
      try { e.dataTransfer.setData('text/plain', key); } catch (x) { /* بعض المتصفحات */ }
    });
    li.addEventListener('dragend', () => {
      li.draggable = false; li.classList.remove('dragging'); S.drag = null;
      ol.querySelectorAll('.drop-before,.drop-after').forEach(x => x.classList.remove('drop-before', 'drop-after'));
    });
    li.addEventListener('dragover', e => {
      if (!S.drag || S.drag.list !== listKey) return;
      e.preventDefault();
      const r = li.getBoundingClientRect(), before = e.clientY < r.top + r.height / 2;
      ol.querySelectorAll('.drop-before,.drop-after').forEach(x => x.classList.remove('drop-before', 'drop-after'));
      li.classList.add(before ? 'drop-before' : 'drop-after');
    });
    li.addEventListener('drop', e => {
      if (!S.drag || S.drag.list !== listKey) return;
      e.preventDefault();
      const r = li.getBoundingClientRect();
      let to = idx + (e.clientY < r.top + r.height / 2 ? 0 : 1);
      const from = S.drag.from;
      if (to > from) to--;
      S.drag = null;
      moveTo(from, to);
    });
    ol.append(li);
  });
  wrap.append(ol);
  const full = items.length >= ld.max;
  wrap.append(h('button', {
    type: 'button', class: 'lpe-btn dashed', disabled: full, title: full ? 'وصلت للحد الأقصى (' + ld.max + ')' : null,
    onclick: () => {
      const it = { id: rid(), custom: true, hidden: false, icon: ld.icon ? 'star' : null, f: {} };
      ld.fields.forEach(fd => { it.f[fd.k] = blankValue(fd); });
      items.push(it);
      const key = listKey + '.' + it.id;
      S.open[key] = true;
      touch(sec.id); renderMain();
      const inp = S.root.querySelector('[data-path^="' + CSS.escape(key + '.') + '"]');
      if (inp) { inp.scrollIntoView({ block: 'center', behavior: 'smooth' }); inp.focus({ preventScroll: true }); }
    },
  }, ico('sparkles'), 'إضافة ' + ld.item));
  return wrap;
}

function iconPicker(it, key, onPick) {
  const fs = h('fieldset', { class: 'lpe-icons' }, h('legend', { text: 'الأيقونة' }));
  const name = 'lpe-ico-' + key.replace(/[^a-z0-9]/gi, '-');
  Object.keys(S.icons).forEach(k => {
    fs.append(h('label', { class: 'lpe-ico', title: S.icons[k] },
      h('input', { type: 'radio', name, value: k, checked: it.icon === k, 'aria-label': S.icons[k],
        onchange: () => { it.icon = k; onPick(); } }),
      h('span', {}, ico(k))));
  });
  return fs;
}

/* ------------------------------------------------------------ عرض القسم */

function renderSection() {
  const sec = S.secMap[S.sec], d = S.draft[sec.id], lv = S.live[sec.id];
  const card = h('div', { class: 'lpe-card' });
  const tools = h('div', { class: 'lpe-sec-tools' });
  if (sec.hideable) {
    const cb = h('input', { type: 'checkbox', checked: !d.hidden, onchange: () => { d.hidden = !cb.checked; touch(sec.id); renderMain(); } });
    tools.append(h('label', { class: 'lpe-switch' }, cb, h('i'), 'ظاهر للزوار'));
  }
  tools.append(h('button', { type: 'button', class: 'lpe-btn', onclick: () => resetScope([sec.id], '«' + sec.label + '»') }, ico('refresh'), 'إرجاع القسم للأصل'));
  card.append(h('div', { class: 'lpe-sec-head' }, h('div', {}, h('h3', { text: sec.label }), h('p', { text: sec.desc })), tools));
  if (d.hidden) {
    card.append(h('p', { class: 'lpe-note', role: 'note' }, ico('info'),
      'هذا القسم مخفي عن الزوار' + (lv.hidden ? '' : ' بعد النشر') + ' — نصوصه محفوظة، وتقدر تظهره متى شئت.'));
  }
  if (sec.fields.length) {
    const fb = h('div', { class: 'lpe-fields' });
    let g = null;
    sec.fields.forEach(fd => {
      if (fd.g && fd.g !== g) { g = fd.g; fb.append(h('div', { class: 'lpe-group', text: g })); }
      fb.append(fieldRow({ fd, val: d.f[fd.k], def: fd.d, liveVal: lv.f[fd.k], path: sec.id + '.' + fd.k, secId: sec.id }));
    });
    card.append(fb);
  }
  sec.lists.forEach(ld => card.append(listBlock(sec, ld)));
  return card;
}

function renderSearch() {
  const q = normQ(S.q.trim());
  const card = h('div', { class: 'lpe-card' });
  const hits = [];
  const has = (...xs) => xs.some(x => normQ(x).includes(q));
  S.schema.forEach(sec => {
    const d = S.draft[sec.id], lv = S.live[sec.id];
    sec.fields.forEach(fd => {
      const v = d.f[fd.k];
      if (has(fd.l, sec.label, v.ar, v.en, v.v, fd.d.ar, fd.d.en, fd.d.v)) {
        hits.push({ sec, crumb: [sec.label, fd.g, fd.l].filter(Boolean), o: { fd, val: v, def: fd.d, liveVal: lv.f[fd.k], path: sec.id + '.' + fd.k, secId: sec.id } });
      }
    });
    sec.lists.forEach(ld => d.l[ld.k].forEach((it, i) => {
      const di = defItem(ld, it), li = listOf(S.live, sec.id, ld.k).find(x => x.id === it.id);
      ld.fields.forEach(fd => {
        const v = it.f[fd.k] || (it.f[fd.k] = blankValue(fd)), dv = di && di.f[fd.k];
        if (has(v.ar, v.en, v.v, dv && dv.ar, dv && dv.en, dv && dv.v)) {
          hits.push({ sec, crumb: [sec.label, ld.item + ' ' + (i + 1), fd.l],
            o: { fd, val: v, def: dv, liveVal: li && li.f[fd.k], path: [sec.id, ld.k, it.id, fd.k].join('.'), secId: sec.id } });
        }
      });
    }));
  });
  card.append(h('div', { class: 'lpe-results-head', role: 'status' }, hits.length ? hits.length + ' نتيجة لـ «' + S.q.trim() + '»' : 'لا نتائج'));
  if (!hits.length) {
    card.append(h('p', { class: 'lpe-empty', text: 'ما لقينا نصاً يطابق «' + S.q.trim() + '» — جرّب كلمة أخرى، أو امسح البحث وتصفّح الأقسام.' }));
    return card;
  }
  hits.slice(0, 80).forEach(x => {
    const crumb = h('div', { class: 'lpe-crumb' },
      h('button', { type: 'button', text: x.crumb[0], onclick: () => { S.q = ''; S.el.search.value = ''; S.sec = x.sec.id; paintChips(); renderMain(); post({ type: 'section', id: S.sec }); } }),
      x.crumb.slice(1).map(c => ['←', c]).flat().join(' '));
    card.append(h('div', { class: 'lpe-hit' }, fieldRow(Object.assign({ crumb }, x.o))));
  });
  return card;
}

/** إعادة بناء عمود التحرير مع إبقاء التركيز ومكان المؤشر */
function renderMain() {
  const a = document.activeElement;
  const keep = a && S.root && S.root.contains(a) && a.dataset && a.dataset.path
    ? { path: a.dataset.path, lang: a.dataset.lang, s: a.selectionStart, e: a.selectionEnd } : null;
  const box = S.el.main;
  box.textContent = '';
  box.append(S.q.trim() ? renderSearch() : renderSection());
  if (keep) {
    const n = box.querySelector('[data-path="' + CSS.escape(keep.path) + '"][data-lang="' + keep.lang + '"]');
    if (n) { n.focus({ preventScroll: true }); try { n.setSelectionRange(keep.s, keep.e); } catch (x) { /* email/url */ } }
  }
  paintCounts();
}

/* ------------------------------------------------------------ الإرجاع للأصل */

async function resetScope(ids, what) {
  const scope = await ask({
    title: 'إرجاع ' + what + ' للأصل؟',
    text: 'يرجع المحتوى المختار للنص الأصلي في المسودة. الزوار لا يرون شيئاً حتى تنشر، وتقدر تتجاهل المسودة للتراجع.',
    options: [
      { value: 'all', label: 'كل شيء', desc: 'النصوص باللغتين، والبطاقات المضافة والمحذوفة وترتيبها وأيقوناتها، وإظهار الأقسام.' },
      { value: 'ar', label: 'النصوص العربية فقط', desc: 'الإنجليزي والبطاقات وترتيبها تبقى كما هي.' },
      { value: 'en', label: 'النصوص الإنجليزية فقط', desc: 'العربي والبطاقات وترتيبها تبقى كما هي.' },
    ],
    confirm: 'إرجاع للأصل', danger: true,
  });
  if (!scope) return;
  ids.forEach(id => {
    const sec = S.secMap[id];
    if (scope === 'all') { S.draft[id] = defaultSection(sec); }
    else {
      const d = S.draft[id];
      sec.fields.forEach(fd => { if (!isMono(fd.t)) d.f[fd.k][scope] = fd.d[scope]; });
      sec.lists.forEach(ld => d.l[ld.k].forEach(it => {
        const di = defItem(ld, it);
        if (di) ld.fields.forEach(fd => { if (!isMono(fd.t)) it.f[fd.k][scope] = di.f[fd.k][scope]; });
      }));
    }
    touch(id);
  });
  renderMain();
  toast('رجع ' + what + ' للأصل في المسودة — انشر ليراه الزوار.', 'ok');
}

/* ------------------------------------------------------------ النشر والتجاهل */

async function publish() {
  const errs = allErrors();
  if (errs.size) {
    const first = errs.values().next().value;
    toast('صحّح «' + first.label + '» قبل النشر: ' + first.msg, 'err', 7000);
    S.sec = first.sec; S.q = ''; S.el.search.value = ''; paintChips(); renderMain();
    return;
  }
  S.busy = true; paintBar();
  await flush();
  S.busy = false;
  if (S.dirty.size || S.saveErr) { paintBar(); return toast('تعذّر حفظ آخر تعديلاتك — ' + (S.saveErr || 'حاول مرة ثانية.'), 'err', 7000); }
  const n = totalChanges();
  if (!S.hasDraft || !n) { paintBar(); return toast('ما فيه تغييرات غير منشورة.', 'info'); }
  const secs = S.schema.filter(s => secChanges(s.id)).map(s => s.label);
  const empties = [];
  S.schema.forEach(sec => sec.lists.forEach(ld => listOf(S.draft, sec.id, ld.k).forEach(it => {
    if (it.custom && !it.hidden && ld.fields.every(fd => !tv(it.f[fd.k] && (it.f[fd.k].ar || it.f[fd.k].en || it.f[fd.k].v)))) empties.push(sec.label);
  })));
  const ok = await ask({
    title: 'نشر التغييرات للزوار؟',
    text: 'يرى كل الزوار ' + (n === 1 ? 'هذا التغيير' : 'هذه التغييرات (' + n + ')') + ' فور النشر، في: ' + secs.join('، ') + '.' + (empties.length ? ' تنبيه: فيه بطاقة مضافة فارغة في ' + [...new Set(empties)].join('، ') + ' ستظهر بلا نص.' : ''),
    confirm: 'نشر الآن',
  });
  if (!ok) return;
  S.busy = true; paintBar();
  const r = await api('content.php', { action: 'publish', etag: S.etag });
  S.busy = false;
  if (!r || !r.ok) {
    toast((r && r.error) || 'تعذّر النشر — حاول مرة ثانية.', 'err', 8000);
    await refresh(true);
    return;
  }
  applyPayload(r);
  if (typeof window.lpPublished === 'function') window.lpPublished(r.live, r.liveEtag);
  renderMain(); pushPreview(true);
  toast('نُشرت التغييرات — يراها الزوار الآن.', 'ok', 6000);
}

async function discard() {
  const ok = await ask({
    title: 'تجاهل المسودة؟',
    text: 'تُحذف كل التعديلات غير المنشورة (لك ولأي مدير آخر) ويرجع المحرر للنسخة المنشورة. لا يمكن التراجع عن هذا.',
    confirm: 'تجاهل المسودة', danger: true,
  });
  if (!ok) return;
  clearTimeout(S.saveTimer);
  S.busy = true; paintBar();
  for (let i = 0; i < 100 && S.saving; i++) await new Promise(r => setTimeout(r, 60));
  const r = await api('content.php', { action: 'discard' });
  S.busy = false;
  if (!r || !r.ok) { paintBar(); return toast((r && r.error) || 'تعذّر تجاهل المسودة.', 'err'); }
  applyPayload(r);
  renderMain(); pushPreview(true);
  toast('رجعنا للنسخة المنشورة.', 'ok');
}

/* ------------------------------------------------------------ الرسم الثابت */

function paintChips() {
  S.schema.forEach(sec => {
    const c = S.el.chips[sec.id];
    if (!c) return;
    const n = secChanges(sec.id);
    c.num.textContent = n; c.num.hidden = !n;
    c.sr.textContent = n ? '، ' + plural(n) : '';
    c.off.hidden = !S.draft[sec.id].hidden;
    if (!S.q.trim() && S.sec === sec.id) c.btn.setAttribute('aria-current', 'true');
    else c.btn.removeAttribute('aria-current');
  });
}

function paintBar() {
  const b = S.el.bar;
  if (!b) return;
  const n = totalChanges(), errs = allErrors();
  let dot = 'ok', title, sub;
  if (S.saving || S.busy) { dot = 'saving'; title = S.busy ? 'لحظة…' : 'جارٍ حفظ المسودة…'; sub = 'لا تغلق الصفحة قبل انتهاء الحفظ.'; }
  else if (errs.size) { dot = 'err'; title = 'فيه حقل غير صحيح'; sub = errs.values().next().value.label + ': ' + errs.values().next().value.msg + ' — التعديلات في هذا القسم لا تُحفظ حتى تُصحَّح.'; }
  else if (S.saveErr) { dot = 'err'; title = 'تعذّر حفظ المسودة'; sub = S.saveErr + ' — نعيد المحاولة تلقائياً مع أي تعديل.'; }
  else if (!n) { title = 'لا تغييرات غير منشورة'; sub = S.liveAt ? 'آخر نشر ' + relTime(S.liveAt) + (S.liveBy ? ' — ' + S.liveBy : '') : 'الصفحة تعرض النصوص الأصلية.'; }
  else {
    dot = 'pending'; title = plural(n);
    sub = S.dirty.size ? 'بانتظار الحفظ…' : 'المسودة محفوظة' + (S.draftAt ? ' ' + relTime(S.draftAt) : '') + (S.draftBy ? ' — آخر تعديل: ' + S.draftBy : '') + ' · الزوار يرون المنشور حتى تنشر.';
  }
  b.dot.className = 'lpe-dot' + (dot === 'ok' ? '' : ' ' + dot);
  b.title.textContent = title;
  b.sub.textContent = sub;
  b.discard.disabled = S.busy || (!S.hasDraft && !S.dirty.size);
  b.publish.disabled = S.busy || !n || errs.size > 0;
}

function paintCounts() { paintChips(); paintBar(); }

function paintPvLang() {
  (S.el.pvLangBtns || []).forEach(b => b.setAttribute('aria-checked', b.dataset.v === pvLang() ? 'true' : 'false'));
}

function seg(label, opts, cur, onPick, cls) {
  const g = h('div', { class: 'lpe-seg' + (cls ? ' ' + cls : ''), role: 'radiogroup', 'aria-label': label });
  const btns = opts.map(([v, t, icon]) => h('button', {
    type: 'button', role: 'radio', 'aria-checked': v === cur ? 'true' : 'false', dataset: { v },
    onclick: () => { btns.forEach(b => b.setAttribute('aria-checked', b.dataset.v === v ? 'true' : 'false')); onPick(v); },
  }, icon ? ico(icon) : null, t));
  g.addEventListener('keydown', e => {
    const i = btns.indexOf(document.activeElement);
    if (i < 0 || !['ArrowLeft', 'ArrowRight'].includes(e.key)) return;
    e.preventDefault();
    const rtl = getComputedStyle(g).direction === 'rtl';
    const step = (e.key === 'ArrowLeft') === rtl ? 1 : -1;
    const n = btns[(i + step + btns.length) % btns.length];
    n.focus(); n.click();
  });
  g.append(...btns);
  return { g, btns };
}

function buildShell() {
  const root = S.root;
  root.textContent = '';
  const hero = h('header', { class: 'lpe-hero' },
    h('h2', { text: 'محتوى صفحة الهبوط' }),
    h('p', { text: 'حرّر كل نصوص الصفحة الرئيسية وبطاقاتها بالعربي والإنجليزي، وشاهد أثر كل حرف حيّاً على شاشة الحاسوب والجوال قبل أن يراه الزوار.' }));

  const search = h('input', {
    type: 'search', placeholder: 'ابحث في نصوص الصفحة كلها — بالعربي أو الإنجليزي', 'aria-label': 'ابحث في نصوص الصفحة',
    oninput: () => { S.q = search.value; paintChips(); renderMain(); },
  });
  S.el.search = search;
  const langSeg = seg('لغة التحرير', [['ar', 'العربية'], ['en', 'English'], ['both', 'الاثنتان']], S.lang, v => {
    S.lang = v;
    if (v !== 'both') S.pvLang = v;
    paintPvLang(); renderMain(); pushPreview(true);
  });
  const pvBtn = h('button', { type: 'button', class: 'lpe-btn', 'aria-pressed': 'false' });
  const paintPvBtn = () => {
    pvBtn.textContent = '';
    pvBtn.append(ico('eye'), S.preview ? 'إخفاء المعاينة' : 'إظهار المعاينة');
    pvBtn.setAttribute('aria-pressed', S.preview ? 'false' : 'true');
    S.el.grid.classList.toggle('no-pv', !S.preview);
    try { localStorage.setItem('wesal_lpePreview', S.preview ? '1' : '0'); } catch (e) { /* خاص */ }
    if (S.preview) requestAnimationFrame(fitPreview);
  };
  pvBtn.addEventListener('click', () => { S.preview = !S.preview; paintPvBtn(); });
  const resetAll = h('button', { type: 'button', class: 'lpe-btn danger', onclick: () => resetScope(S.schema.map(s => s.id), 'الصفحة كلها') }, ico('refresh'), 'إرجاع الصفحة كلها');
  const toolbar = h('div', { class: 'lpe-card lpe-toolbar' },
    h('div', { class: 'lpe-search' }, ico('search'), search), langSeg.g, pvBtn, resetAll,
    h('p', { class: 'lpe-hint', text: 'اختر قسماً، وحرّر نصوصه، وشاهد أثرها في المعاينة جانبه — أو اضغط أي نص في المعاينة ليفتح حقله. تعديلاتك مسودة تُحفظ تلقائياً، ولا يراها الزوار حتى تضغط «نشر».' }));

  const chips = h('nav', { class: 'lpe-chips', 'aria-label': 'أقسام الصفحة' });
  S.el.chips = {};
  S.schema.forEach(sec => {
    const num = h('span', { class: 'lpe-num', 'aria-hidden': 'true', hidden: true });
    const sr = h('span', { class: 'lpe-sr' });
    const off = h('span', { class: 'lpe-off', text: '(مخفي)', hidden: true });
    const btn = h('button', {
      type: 'button', class: 'lpe-chip',
      onclick: () => { S.q = ''; search.value = ''; S.sec = sec.id; paintChips(); renderMain(); post({ type: 'section', id: sec.id }); },
    }, sec.label, off, num, sr);
    S.el.chips[sec.id] = { btn, num, sr, off };
    chips.append(btn);
  });

  const main = h('section', { class: 'lpe-main', 'aria-label': 'تحرير القسم' });
  S.el.main = main;

  // المعاينة: تُبنى مرة واحدة ويبقى الإطار حياً بين إعادة الرسم
  const devSeg = seg('حجم الشاشة', [['desktop', 'حاسوب', 'grid'], ['mobile', 'جوال', 'phone']], S.device, v => {
    S.device = v; stage.classList.toggle('mobile', v === 'mobile'); fitPreview();
  }, 'small');
  const pvLangSeg = seg('لغة المعاينة', [['ar', 'ع'], ['en', 'EN']], pvLang(), v => {
    S.pvLang = v;
    if (S.lang !== 'both') { S.lang = v; langSeg.btns.forEach(b => b.setAttribute('aria-checked', b.dataset.v === v ? 'true' : 'false')); renderMain(); }
    pushPreview(true);
  }, 'small');
  S.el.pvLangBtns = pvLangSeg.btns;
  const frame = h('iframe', { title: 'معاينة صفحة الهبوط', src: pvUrl(), loading: 'eager' });
  S.el.frame = frame;
  const frameBox = h('div', { class: 'lpe-frame' }, frame);
  S.el.frameBox = frameBox;
  const stage = h('div', { class: 'lpe-stage' + (S.device === 'mobile' ? ' mobile' : '') }, frameBox);
  S.el.stage = stage;
  const pv = h('aside', { class: 'lpe-card lpe-pv', 'aria-label': 'المعاينة الحية' },
    h('div', { class: 'lpe-pv-bar' }, devSeg.g,
      h('div', { class: 'lpe-pv-tools' }, pvLangSeg.g,
        h('button', { type: 'button', class: 'lpe-btn icon', title: 'تحديث المعاينة', 'aria-label': 'تحديث المعاينة', onclick: reloadFrame }, ico('refresh')),
        h('button', { type: 'button', class: 'lpe-btn icon', title: 'فتح المعاينة في تبويب جديد', 'aria-label': 'فتح المعاينة في تبويب جديد',
          onclick: () => { saveNow(); window.open(pvUrl(), '_blank', 'noopener'); } }, ico('arrow-left')))),
    stage,
    h('p', { class: 'lpe-pv-note' }, ico('info'), 'المعاينة تعرض مسودتك — الزوار يرون النسخة المنشورة حتى تضغط «نشر».'));

  const grid = h('div', { class: 'lpe-grid' }, main, pv);
  S.el.grid = grid;

  const bar = {
    dot: h('span', { class: 'lpe-dot', 'aria-hidden': 'true' }),
    title: h('b'), sub: h('span'),
    discard: h('button', { type: 'button', class: 'lpe-btn', onclick: discard }, ico('x'), 'تجاهل المسودة'),
    publish: h('button', { type: 'button', class: 'lpe-btn primary', onclick: publish }, ico('send'), 'نشر'),
  };
  S.el.bar = bar;
  const barEl = h('div', { class: 'lpe-card lpe-bar' },
    h('div', { class: 'lpe-bar-status', role: 'status', 'aria-live': 'polite' }, bar.dot, h('div', { class: 'lpe-bar-text' }, bar.title, bar.sub)),
    h('div', { class: 'lpe-bar-actions' }, bar.discard, bar.publish));

  root.append(hero, toolbar, chips, grid, barEl);
  try { S.preview = localStorage.getItem('wesal_lpePreview') !== '0'; } catch (e) { /* خاص */ }
  paintPvBtn();
  if ('ResizeObserver' in window) new ResizeObserver(fitPreview).observe(stage);
  requestAnimationFrame(fitPreview);
}

/* ------------------------------------------------------------ التحميل */

function applyPayload(r) {
  if (r.schema) {
    S.schema = r.schema.map(s => Object.assign({ lists: [], fields: [] }, s));
    S.icons = r.icons || {};
    S.secMap = {};
    S.schema.forEach(s => { S.secMap[s.id] = s; });
  }
  const norm = doc => { S.schema.forEach(s => { const x = doc[s.id]; if (!x.l || Array.isArray(x.l)) x.l = {}; }); return doc; };
  S.live = norm(r.live);
  S.draft = norm(r.draft);
  S.etag = r.draftEtag || '';
  S.liveEtag = r.liveEtag || '';
  S.hasDraft = !!r.hasDraft;
  S.draftBy = r.draftBy || null; S.draftAt = r.draftAt || 0;
  S.liveBy = r.liveBy || null; S.liveAt = r.liveAt || 0;
  S.dirty.clear(); S.saveErr = '';
}

async function refresh(withSchema) {
  const r = await api('content.php', { action: 'editor' });
  if (!r || !r.ok) return r || { ok: false };
  if (!withSchema) delete r.schema;
  applyPayload(r);
  if (S.el.main) { renderMain(); pushPreview(true); }
  return r;
}

async function open(root) {
  if (S.root === root && S.el.main && root.contains(S.el.main)) {
    // رجوع للتبويب: نحدّث من الخادم ما لم تكن عندك تعديلات بانتظار الحفظ
    if (!S.dirty.size && !S.saving) await refresh(false);
    fitPreview();
    return;
  }
  S.root = root;
  root.textContent = '';
  root.append(h('p', { class: 'lpe-loading', role: 'status', text: 'جارٍ تحميل المحرر…' }));
  const r = await api('content.php', { action: 'editor' });
  if (!r || !r.ok) {
    root.textContent = '';
    root.append(h('div', { class: 'lpe-fail', role: 'alert' },
      h('p', { text: (r && r.error) || 'تعذّر تحميل المحتوى — تأكد من اتصالك.' }),
      h('button', { type: 'button', class: 'lpe-btn', onclick: () => open(root) }, ico('refresh'), 'إعادة المحاولة')));
    return;
  }
  applyPayload(r);
  if (!S.secMap[S.sec]) S.sec = S.schema[0].id;
  buildShell();
  renderMain();
}

/* تحذير قبل إغلاق الصفحة وتعديلات لم تُحفظ بعد، واختصار Ctrl+S للحفظ الفوري */
window.addEventListener('beforeunload', e => {
  if (S.dirty.size || S.saving) { e.preventDefault(); e.returnValue = ''; }
});
document.addEventListener('keydown', e => {
  if (!(e.ctrlKey || e.metaKey) || e.key.toLowerCase() !== 's') return;
  if (!S.root || !S.root.offsetParent) return;
  e.preventDefault();
  saveNow();
});
window.addEventListener('message', onMessage);
setInterval(() => { if (S.root && S.root.offsetParent && !S.saving) paintBar(); }, 30000);

window.LPE = { open };
})();
