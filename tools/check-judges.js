#!/usr/bin/env node
/* ==========================================================================
 *  وصال: فحص بيانات صفحة «أسئلة التحكيم»
 *
 *  الاستخدام:
 *      node tools/check-judges.js
 *
 *  يتحقق من assets/judges-data.js: كل نص بلغتين وغير فارغ، ومعرّفات الأسئلة
 *  فريدة، والمجموعات معرّفة، وترتيب «أهم عشرة» متصل،
 *  ولا شرطة طويلة ولا قصيرة (قواعد README «كتابة النصوص الظاهرة للمستخدم»)،
 *  ولا عبارة جاهزة من قائمة check-copy.php.
 *  ويمنع عودة ملاحظات الفريق الداخلية إلى الملف العام: المستودع عام، فتلك
 *  الملاحظات في assets/judges-notes.js المستثنى من Git. وإن وُجد ذلك الملف
 *  محلياً يفحص مفاتيحه وقيمه أيضاً. لا يحتاج شبكة ولا قاعدة بيانات.
 * ========================================================================== */
'use strict';
const vm = require('vm'), fs = require('fs'), path = require('path');
const root = path.resolve(__dirname, '..');
const CLICHES = ['رحلتك المعرفية', 'في صميم', 'تجربة فريدة', 'قوة الذكاء الاصطناعي', 'أحدث تقنيات',
                 'ليس مجرد', 'ليست مجرد', 'لا شعارات', 'بكل سهولة ويسر', 'نؤمن بأن'];
let errors = 0, warns = 0;
const bad = m => { errors++; console.log('  ✗ ' + m); };
const warn = m => { warns++; console.log('  ! ' + m); };

function load(file, name) {
  const ctx = { window: {} };
  vm.createContext(ctx);
  vm.runInContext(fs.readFileSync(file, 'utf8'), ctx, { filename: file });
  return ctx.window[name];
}
const pair = (p, where) => {
  if (!Array.isArray(p) || p.length !== 2 || p.some(x => typeof x !== 'string' || !x.trim())) {
    bad(where + ': يلزم [عربي، إنجليزي] كلاهما نص غير فارغ'); return false;
  }
  return true;
};
function strings(o, out) {
  if (typeof o === 'string') out.push(o);
  else if (Array.isArray(o)) o.forEach(x => strings(x, out));
  else if (o && typeof o === 'object') Object.keys(o).forEach(k => strings(o[k], out));
  return out;
}
function scanCopy(label, data) {
  strings(data, []).forEach(s => {
    if (/[\u2013\u2014]/.test(s.replace(/[(«"'`]\s?[\u2013\u2014]\s?[)»"'`]/g, ''))) bad(label + ': شرطة طويلة أو قصيرة في «' + s.slice(0, 50) + '»');
    CLICHES.forEach(c => { if (s.indexOf(c) !== -1) warn(label + ': عبارة جاهزة «' + c + '»'); });
  });
}

const dataFile = path.join(root, 'assets/judges-data.js');
const raw = fs.readFileSync(dataFile, 'utf8');
if (/JUDGES_NOTES/.test(raw)) bad('judges-data.js يذكر JUDGES_NOTES: الملاحظات الداخلية لها ملفها الخاص');
const D = load(dataFile, 'JUDGES');
if (!D) { bad('لم يُعرَّف window.JUDGES'); process.exit(1); }

console.log('المجموعات:');
const cids = new Set();
D.clusters.forEach(c => {
  if (cids.has(c.id)) bad('مجموعة مكررة: ' + c.id);
  cids.add(c.id);
  pair(c.t, 'مجموعة ' + c.id + ' العنوان'); pair(c.d, 'مجموعة ' + c.id + ' الوصف');
  if (!c.icon) bad('مجموعة ' + c.id + ': بلا أيقونة');
});
console.log('  ' + D.clusters.length + ' مجموعات');

console.log('الأسئلة:');
const ids = new Set(), perC = {}, hots = [];
D.items.forEach(it => {
  const w = 'سؤال ' + it.id;
  if (ids.has(it.id)) bad('معرّف مكرر: ' + it.id);
  ids.add(it.id);
  if (!cids.has(it.c)) bad(w + ': مجموعة غير معرّفة (' + it.c + ')');
  perC[it.c] = (perC[it.c] || 0) + 1;
  pair(it.q, w + ' السؤال'); pair(it.s, w + ' الجواب المختصر');
  if (it.d) pair(it.d, w + ' التفصيل');
  if (it.v) bad(w + ': حقل v ممنوع في الملف العام، ضع الملاحظة في judges-notes.js');
  if (it.hot) hots.push(it.hot);
});
D.clusters.forEach(c => { if (!perC[c.id]) bad('مجموعة بلا أسئلة: ' + c.id); });
hots.sort((a, b) => a - b);
hots.forEach((h, i) => { if (h !== i + 1) bad('ترتيب «أهم الأسئلة» غير متصل أو مكرر عند ' + h); });
console.log('  ' + D.items.length + ' سؤالاً، منها ' + hots.length + ' في «أهم الأسئلة»');
Object.keys(perC).forEach(c => console.log('    ' + c + ': ' + perC[c]));

console.log('أرقام للحفظ:');
D.facts.forEach((f, i) => {
  pair(f.v, 'رقم ' + (i + 1) + ' القيمة'); pair(f.l, 'رقم ' + (i + 1) + ' الوصف');
});
console.log('  ' + D.facts.length + ' رقماً');

scanCopy('judges-data.js', D);

const notesFile = path.join(root, 'assets/judges-notes.js');
console.log('ملاحظات الفريق:');
if (fs.existsSync(notesFile)) {
  const N = load(notesFile, 'JUDGES_NOTES') || {};
  Object.keys(N).forEach(k => { if (!ids.has(k)) bad('ملاحظة لسؤال غير موجود: ' + k); pair(N[k], 'ملاحظة ' + k); });
  scanCopy('judges-notes.js', N);
  console.log('  ' + Object.keys(N).length + ' ملاحظة في الملف الخاص (محلي، غير مرفوع)');
} else {
  console.log('  الملف الخاص غير موجود هنا. طبيعي في المستودع، وتعمل الصفحة بلا ملاحظات.');
}

console.log(errors ? '\n✗ ' + errors + ' خطأ' : '\n✓ بيانات أسئلة التحكيم سليمة' + (warns ? ' (' + warns + ' تنبيه)' : ''));
process.exit(errors ? 1 : 0);
