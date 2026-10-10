/* ==========================================================================
 *  وصال: توليد ملفي PDF لأسئلة التحكيم (عربي وإنجليزي)
 *
 *  أيقونة التنزيل في judges.html تحمّل هذين الملفين الجاهزين. يُولَّدان من الصفحة
 *  نفسها: كل الأسئلة بالتفصيل على ورق وصال الرسمي، وبلا ملاحظات الفريق الخاصة أبداً
 *  (يُحجب assets/judges-notes.js أثناء التوليد، ويفشل السكربت لو ظهرت).
 *
 *  من جذر المستودع، بعد أي تعديل على الأسئلة أو على ورق الطباعة:
 *      NODE_PATH="$(npm root -g)" node tools/build-judges-pdf.js
 *  المتطلبات: Playwright وكروم. إن لم يجد كروم فاضبط CHROMIUM=/مسار/كروم.
 *
 *  الخرج: assets/judges-qa-ar.pdf وassets/judges-qa-en.pdf وassets/judges-pdf.json
 *  (بصمة المحتوى). tools/check-judges.js يفشل لو تغيّر المحتوى بعد آخر توليد.
 * ========================================================================== */

const fs = require('fs');
const path = require('path');
const http = require('http');
const { contentHash } = require('./judges-pdf-hash');

const root = path.join(__dirname, '..');
let chromium;
try { chromium = require('playwright').chromium; } catch (e) {
  console.error('يلزم Playwright: NODE_PATH="$(npm root -g)" node tools/build-judges-pdf.js');
  process.exit(2);
}

const MIME = {
  '.html': 'text/html; charset=utf-8', '.js': 'text/javascript; charset=utf-8', '.css': 'text/css; charset=utf-8',
  '.png': 'image/png', '.webp': 'image/webp', '.woff2': 'font/woff2', '.otf': 'font/otf', '.svg': 'image/svg+xml',
  '.ico': 'image/x-icon', '.json': 'application/json'
};

/* خادم ثابت مؤقت على المنفذ الحر: الصفحة تطلب مواردها بمسارات مطلقة (/assets، /fonts) */
function serve() {
  return new Promise(resolve => {
    const s = http.createServer((req, res) => {
      const f = path.join(root, decodeURIComponent(req.url.split('?')[0]));
      if (!f.startsWith(root + path.sep) || !fs.existsSync(f) || fs.statSync(f).isDirectory()) { res.writeHead(404); res.end(); return; }
      res.writeHead(200, { 'Content-Type': MIME[path.extname(f)] || 'application/octet-stream' });
      fs.createReadStream(f).pipe(res);
    });
    s.listen(0, '127.0.0.1', () => resolve(s));
  });
}

(async () => {
  const sha = contentHash(root);
  const server = await serve();
  const base = 'http://127.0.0.1:' + server.address().port;
  const browser = await chromium.launch(process.env.CHROMIUM ? { executablePath: process.env.CHROMIUM } : {});
  const manifest = { sha: sha, built: new Date().toISOString().slice(0, 10) };
  try {
    for (const lang of ['ar', 'en']) {
      const ctx = await browser.newContext({ viewport: { width: 1200, height: 900 }, locale: lang === 'ar' ? 'ar-SA' : 'en-US' });
      await ctx.route('**/assets/judges-notes.js', r => r.abort());
      const page = await ctx.newPage();
      await page.addInitScript(l => { try { localStorage.setItem('jd-lang', l); localStorage.setItem('jd-theme', 'light'); } catch (e) { /* بلا تخزين */ } }, lang);
      await page.goto(base + '/judges.html', { waitUntil: 'load' });
      await page.evaluate(() => document.fonts.ready);
      if (await page.evaluate(() => !!window.JUDGES_NOTES)) throw new Error('ظهرت ملاحظات الفريق الخاصة أثناء التوليد، والملف العام لا يحملها أبداً');
      /* نفس معالج الطباعة في الصفحة: يبني كل الأسئلة بالتفصيل بلغة الصفحة */
      await page.evaluate(() => window.dispatchEvent(new Event('beforeprint')));
      await page.waitForFunction(() => {
        const im = Array.from(document.querySelectorAll('#printRoot img'));
        return im.length > 0 && im.every(i => i.complete && i.naturalWidth > 0);
      });
      const n = await page.evaluate(() => document.querySelectorAll('#printRoot .pq1').length);
      const total = await page.evaluate(() => window.JUDGES.items.length);
      if (n !== total) throw new Error('ورق الطباعة فيه ' + n + ' سؤالاً من ' + total);
      const buf = await page.pdf({ preferCSSPageSize: true, printBackground: true });
      const file = 'judges-qa-' + lang + '.pdf';
      fs.writeFileSync(path.join(root, 'assets', file), buf);
      const pages = (buf.toString('latin1').match(/\/Type\s*\/Page(?![A-Za-z])/g) || []).length;
      manifest[lang] = { file: file, bytes: buf.length, pages: pages, questions: n };
      console.log('  ✓ ' + file + ': ' + n + ' سؤالاً، ' + pages + ' صفحة، ' + Math.round(buf.length / 1024) + ' كيلوبايت');
      await ctx.close();
    }
  } finally {
    await browser.close();
    server.close();
  }
  fs.writeFileSync(path.join(root, 'assets/judges-pdf.json'), JSON.stringify(manifest, null, 2) + '\n');
  console.log('  ✓ assets/judges-pdf.json (البصمة ' + sha.slice(0, 12) + ')');
})().catch(e => { console.error('✗ ' + e.message); process.exit(1); });
