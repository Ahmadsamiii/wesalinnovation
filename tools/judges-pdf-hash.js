/* ==========================================================================
 *  وصال: بصمة محتوى ملفي PDF الجاهزين لأسئلة التحكيم
 *
 *  يشترك فيها tools/build-judges-pdf.js (يكتبها) وtools/check-judges.js (يقارنها).
 *  البصمة تغطي ما يغيّر شكل الـPDF: ملف الأسئلة، وترتيب الورق في judges.html
 *  (أنماط الطباعة ودالة بناء الورق)، وشعار الترويسة. ما عدا ذلك من الصفحة لا يلزم
 *  إعادة التوليد عند تغييره.
 * ========================================================================== */

const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

function slice(text, from, to, label) {
  const i = text.indexOf(from);
  const j = i < 0 ? -1 : text.indexOf(to, i);
  if (i < 0 || j < 0) throw new Error('judges.html: لم أجد حدود «' + label + '» لحساب بصمة الـPDF');
  return text.slice(i, j + to.length);
}

function contentHash(root) {
  const html = fs.readFileSync(path.join(root, 'judges.html'), 'utf8');
  const css = slice(html, '/* نافذة الطباعة */', '</style>', 'أنماط الطباعة');
  const js = slice(html, 'function buildPrint(o){', "pendingPrint=false});", 'دالة بناء ورق الطباعة');
  const h = crypto.createHash('sha256');
  [
    fs.readFileSync(path.join(root, 'assets/judges-data.js')),
    Buffer.from(css),
    Buffer.from(js),
    fs.readFileSync(path.join(root, 'assets/logo-color.png'))
  ].forEach(part => { h.update(part); h.update('\u0000'); });
  return h.digest('hex');
}

module.exports = { contentHash };
