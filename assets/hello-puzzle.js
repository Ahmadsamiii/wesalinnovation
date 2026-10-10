/* ==========================================================================
 *  لوحة وصال
 *
 *  رمز وصال الرسمي مقسوم إلى 25 قطعة: 24 حلقة تتبع خط الرمز من قدمه إلى طرفه، ثم
 *  النقطة. الزائر رقم n يركّب القطعة ((n-1) mod 25)+1 من الشعار رقم floor((n-1)/25)+1.
 *  كل شيء يُحسب من الرقم نفسه، فلا حالة تُخزَّن ولا يلزم جدول جديد.
 *
 *  دليل الهوية يمنع تدوير الشعار وتمديده وتغيير ألوانه، لذلك لا تدور القطع ولا
 *  تتشوه أثناء الحركة: تنزلق وتكبر وتصغر بنسبة واحدة فقط، وعند الاكتمال تلتحم بلا
 *  فواصل ويظهر الرمز الرسمي كاملاً كما هو.
 * ========================================================================== */
(function (root) {
  'use strict';

  var COUNT = 25;
  var SYM = [1130, 814];       /* أبعاد الرمز الأصلي */
  var SHEET = [1280, 682];     /* أبعاد صورة القطع المجمّعة */
  /* x, y, w, h داخل الرمز، ثم موضع القطعة في الصورة المجمّعة، ثم مركز ثقلها */
  var CELLS = [[0,599,144,158,363,365,68,678],[32,513,166,164,179,196,123,585],[83,429,166,163,520,196,174,501],[133,344,167,164,349,196,225,416],[185,261,165,163,690,196,276,332],[237,177,166,162,1059,196,328,248],[286,93,167,162,0,365,378,164],[338,0,169,176,471,0,427,79],[457,0,125,192,0,0,512,99],[456,115,175,165,0,196,552,205],[510,198,175,139,509,531,602,278],[616,413,183,154,690,365,714,486],[673,491,163,173,809,0,764,582],[668,605,165,147,0,531,740,678],[569,651,188,160,171,365,654,739],[464,673,164,141,341,531,546,748],[373,625,161,174,644,0,450,700],[338,525,156,179,311,0,405,607],[340,440,168,144,169,531,429,509],[401,360,181,166,976,0,503,432],[505,318,191,149,1048,365,608,385],[635,288,167,152,877,365,717,357],[737,248,175,157,511,365,827,320],[842,200,196,163,859,196,925,284],[952,103,178,181,129,0,1041,193]];

  var cfg = { atlas: '/assets/hello-puzzle.webp', full: '/assets/hello-puzzle-mark.webp' };
  var COLORS = ['#282692', '#814fc3', '#52bdf9', '#3e9eed', '#5039a8'];
  var SHADOW = 'drop-shadow(0 14px 20px rgba(32,19,79,.42))';
  var NOSHADOW = 'drop-shadow(0 0 0 rgba(32,19,79,0))';

  function reduced() { return !!(root.matchMedia && root.matchMedia('(prefers-reduced-motion: reduce)').matches); }
  var CAN = typeof Element !== 'undefined' && !!Element.prototype.animate;

  function lapOf(n) { return Math.floor((n - 1) / COUNT) + 1; }
  function pieceOf(n) { return ((n - 1) % COUNT) + 1; }

  /* القطعة عنصر بخلفية مقتطعة من الصورة المجمّعة، بنسب مئوية فتكبر مع اللوحة بلا حساب */
  function sprite(c) {
    var w = c[2], h = c[3];
    return 'background-image:url(' + cfg.atlas + ');background-size:' + (SHEET[0] / w * 100).toFixed(3) + '% ' +
      (SHEET[1] / h * 100).toFixed(3) + '%;background-position:' + (c[4] / (SHEET[0] - w) * 100).toFixed(3) + '% ' +
      (c[5] / (SHEET[1] - h) * 100).toFixed(3) + '%';
  }
  function place(c) {
    return 'left:' + (c[0] / SYM[0] * 100).toFixed(4) + '%;top:' + (c[1] / SYM[1] * 100).toFixed(4) + '%;width:' +
      (c[2] / SYM[0] * 100).toFixed(4) + '%;height:' + (c[3] / SYM[1] * 100).toFixed(4) + '%;';
  }
  function mk(tag, cls, css) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (css) e.style.cssText = css;
    return e;
  }

  var CSS = [
    '.pz{position:relative;width:100%;direction:ltr;-webkit-user-select:none;user-select:none}',
    '.pz::before{content:"";display:block;padding-top:' + (SYM[1] / SYM[0] * 100).toFixed(4) + '%}',
    '.pz .pcs,.pz .full,.pz .sheen,.pz .fx{position:absolute;left:0;top:0;width:100%;height:100%;pointer-events:none}',
    '.pz i{font-style:normal}',
    '.pz .pcs{transition:opacity .3s ease}',
    '.pz .pc{position:absolute;background-repeat:no-repeat;opacity:.13;transition:opacity .5s ease}',
    '.pz .pc.on{opacity:1}',
    '.pz .full{opacity:0;transition:opacity .6s ease}',
    '.pz.done .full{opacity:1}',
    '.pz.done .pcs{opacity:0;transition:opacity .2s linear .62s}',
    '.pz .sheen{opacity:0;background-repeat:no-repeat;background-size:300% 100%;background-position:100% 0;' +
      'background-image:linear-gradient(105deg,rgba(255,255,255,0) 36%,rgba(255,255,255,.92) 50%,rgba(255,255,255,0) 64%);' +
      '-webkit-mask-repeat:no-repeat;mask-repeat:no-repeat;-webkit-mask-size:100% 100%;mask-size:100% 100%}',
    '.pz.done .sheen{opacity:1}',
    '.pz .fx{z-index:3;overflow:visible}',
    '.pz .anc{position:absolute;width:0;height:0;transform-origin:0 0;z-index:4}',
    '.pz .big{position:absolute;display:block;background-repeat:no-repeat}',
    '.pz .pn{position:absolute;transform:translate(-50%,-50%);white-space:nowrap;direction:ltr;font-family:"Thmanyah Sans","Segoe UI",system-ui,sans-serif;' +
      'font-style:normal;font-weight:800;line-height:1;letter-spacing:0;color:#fff;font-variant-numeric:tabular-nums;text-shadow:0 2px 12px rgba(32,19,79,.6)}',
    '.pz .ring{position:absolute;border-radius:50%;border:3px solid #52bdf9;box-sizing:border-box}',
    '.pz .cf{position:absolute}'
  ].join('');

  function injectCss() {
    if (document.getElementById('pz-css')) return;
    var s = document.createElement('style');
    s.id = 'pz-css';
    s.textContent = CSS;
    document.head.appendChild(s);
  }

  function Board(host) {
    injectCss();
    var self = this;
    this.pz = mk('div', 'pz');
    this.pz.setAttribute('role', 'img');
    this.pcs = [];
    this.on = [];
    this.timers = [];
    var layer = mk('div', 'pcs');
    CELLS.forEach(function (c) {
      var p = mk('i', 'pc', place(c) + sprite(c));
      layer.appendChild(p);
      self.pcs.push(p);
      self.on.push(false);
    });
    this.full = mk('img', 'full');
    this.full.alt = '';
    this.full.setAttribute('aria-hidden', 'true');
    this.full.src = cfg.full;
    var m = 'url(' + cfg.full + ')';
    this.sheen = mk('i', 'sheen', '-webkit-mask-image:' + m + ';mask-image:' + m);
    this.fx = mk('div', 'fx');
    this.pz.appendChild(layer);
    this.pz.appendChild(this.full);
    this.pz.appendChild(this.sheen);
    this.pz.appendChild(this.fx);
    host.appendChild(this.pz);
  }

  Board.prototype.later = function (fn, ms) {
    var t = setTimeout(fn, ms);
    this.timers.push(t);
    return t;
  };
  Board.prototype.label = function (text) { this.pz.setAttribute('aria-label', text); };
  Board.prototype.count = function () { return this.on.filter(Boolean).length; };

  Board.prototype.put = function (i) {
    this.on[i] = true;
    this.pcs[i].classList.add('on');
  };

  /* القطع من 1 إلى upto تُركَّب، والباقي أشباح بخطوط خفيفة */
  Board.prototype.fill = function (upto, animate, done) {
    var self = this, list = [];
    for (var i = 0; i < upto; i++) if (!this.on[i]) list.push(i);
    if (!animate || reduced() || !CAN || !list.length) {
      list.forEach(function (i) { self.put(i); });
      if (done) this.later(done, 0);
      return;
    }
    var step = Math.min(36, 760 / list.length);
    list.forEach(function (i, j) {
      var a = self.pcs[i].animate(
        [{ opacity: 0.13, transform: 'translateY(10px) scale(.9)' }, { opacity: 1, transform: 'none' }],
        { duration: 440, delay: j * step, easing: 'cubic-bezier(.2,.8,.24,1)', fill: 'both' });
      a.onfinish = function () { self.put(i); a.cancel(); };
    });
    if (done) this.later(done, list.length * step + 460);
  };

  /* قطعة الزائر: تظهر كبيرة بوسط اللوحة وعليها رقمه، ثم تنزلق إلى مكانها */
  Board.prototype.land = function (k, o) {
    o = o || {};
    var self = this, i = k - 1, c = CELLS[i], done = o.done || function () {};
    var box = this.pz.getBoundingClientRect(), W = box.width, H = W * SYM[1] / SYM[0];
    if (!CAN || reduced() || !W) { this.put(i); this.ring(i); done(); return; }

    var u = W / SYM[0], pw = c[2] * u, ph = c[3] * u;
    var S = Math.max(2.2, Math.min(6, W * (o.hero || 0.36) / pw));
    var ox = (c[0] + c[2] / 2) * u, oy = (c[1] + c[3] / 2) * u;
    var sx = W / 2 - ox, sy = H / 2 - oy;                  /* من الخانة إلى وسط اللوحة */

    var anc = mk('div', 'anc', 'left:' + ox + 'px;top:' + oy + 'px');
    var big = mk('i', 'big', 'width:' + (pw * S) + 'px;height:' + (ph * S) + 'px;left:' + (-pw * S / 2) + 'px;top:' + (-ph * S / 2) +
      'px;filter:' + SHADOW + ';' + sprite(c));
    var txt = String(o.num != null ? o.num : k), f = txt.length <= 2 ? 1 : (txt.length === 3 ? 0.78 : 0.62);
    var num = mk('b', 'pn', 'left:' + ((c[6] - c[0]) / c[2] * 100) + '%;top:' + ((c[7] - c[1]) / c[3] * 100) + '%;font-size:' + (ph * S * 0.42 * f) + 'px');
    num.textContent = txt;
    big.appendChild(num);
    anc.appendChild(big);
    this.fx.appendChild(anc);

    var T = 2400 * (o.speed || 1);
    /* مراحل الحركة بالمللي ثانية: ظهور، تحليق، انزلاق على قوس، استقرار */
    var POP = 420, HOV = 1150, FLY = 2000, SET = 2400;
    function at(ms) { return Math.min(1, ms / SET); }
    function tr(x, y, s) { return 'translate(' + x.toFixed(2) + 'px,' + y.toFixed(2) + 'px) scale(' + s.toFixed(4) + ')'; }
    var mx = sx * 0.5 - sy * 0.30, my = sy * 0.5 + sx * 0.30;   /* نقطة تحكم القوس */
    function bez(t) { var a = (1 - t) * (1 - t), b = 2 * (1 - t) * t; return [a * sx + b * mx, a * sy + b * my]; }
    function ease(t) { return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2; }
    var end = 1 / S;
    var kf = [
      { transform: tr(sx, sy, 0.5), opacity: 0, offset: 0, easing: 'cubic-bezier(.2,1.5,.4,1)' },
      { transform: tr(sx, sy, 1), opacity: 1, offset: at(POP), easing: 'ease-in-out' },
      { transform: tr(sx, sy - 7, 1.035), opacity: 1, offset: at((POP + HOV) / 2), easing: 'ease-in-out' },
      { transform: tr(sx, sy, 1), opacity: 1, offset: at(HOV), easing: 'linear' }
    ];
    var N = 14;
    for (var j = 1; j <= N; j++) {
      var t = j / N, e = ease(t), p = bez(e), s = 1 + (end - 1) * e;
      kf.push({ transform: tr(p[0], p[1], s), opacity: 1, offset: at(HOV + (FLY - HOV) * t), easing: 'linear' });
    }
    kf.push({ transform: tr(0, 0, end * 1.17), opacity: 1, offset: at(FLY + 150), easing: 'ease-out' });
    kf.push({ transform: tr(0, 0, end), opacity: 1, offset: 1 });

    var a = anc.animate(kf, { duration: T, fill: 'forwards' });
    big.animate([{ filter: SHADOW }, { filter: SHADOW, offset: at(HOV + (FLY - HOV) * 0.55) }, { filter: NOSHADOW, offset: at(FLY) }, { filter: NOSHADOW }],
      { duration: T, fill: 'forwards' });
    num.animate([{ opacity: 1 }, { opacity: 1, offset: at(HOV + (FLY - HOV) * 0.35) }, { opacity: 0, offset: at(FLY - 80) }, { opacity: 0 }],
      { duration: T, fill: 'forwards' });
    a.onfinish = function () {
      self.put(i);
      anc.parentNode && anc.parentNode.removeChild(anc);
      self.ring(i);
      self.pulse(i - 1);
      self.pulse(i + 1);
      /* الاهتزاز لا يُسمح به قبل أن يلمس المستخدم الصفحة (شاشة الجناح لا يلمسها أحد) */
      if (navigator.vibrate && (!navigator.userActivation || navigator.userActivation.hasBeenActive)) { try { navigator.vibrate(28); } catch (e) { /* بعض المتصفحات لا تدعمه */ } }
      done();
    };
    return a;
  };

  Board.prototype.pulse = function (i) {
    if (i < 0 || i >= COUNT || !CAN || reduced() || !this.on[i]) return;
    this.pcs[i].animate([{ filter: 'brightness(1)' }, { filter: 'brightness(1.3)' }, { filter: 'brightness(1)' }],
      { duration: 560, easing: 'ease-in-out' });
  };

  Board.prototype.ring = function (i) {
    if (!CAN || reduced()) return;
    var c = CELLS[i], u = this.pz.getBoundingClientRect().width / SYM[0], d = Math.max(c[2], c[3]) * u * 1.1;
    var r = mk('i', 'ring', 'left:' + (c[6] * u - d / 2) + 'px;top:' + (c[7] * u - d / 2) + 'px;width:' + d + 'px;height:' + d + 'px');
    this.fx.appendChild(r);
    var a = r.animate([{ transform: 'scale(.5)', opacity: 0.95 }, { transform: 'scale(1.9)', opacity: 0 }],
      { duration: 900, easing: 'cubic-bezier(.2,.7,.3,1)', fill: 'forwards' });
    a.onfinish = function () { r.parentNode && r.parentNode.removeChild(r); };
  };

  /* اكتمال الشعار: موجة ضوء من القدم إلى النقطة، ثم تلتحم القطع، ثم لمعة وجزيئات */
  Board.prototype.celebrate = function (o) {
    o = o || {};
    var self = this, done = o.done || function () {};
    if (!CAN || reduced()) { this.pz.classList.add('done'); this.later(done, 0); return; }
    for (var i = 0; i < COUNT; i++) {
      (function (i) {
        self.pcs[i].animate([{ filter: 'brightness(1)' }, { filter: 'brightness(1.42)' }, { filter: 'brightness(1)' }],
          { duration: 560, delay: i * 36, easing: 'ease-in-out' });
      })(i);
    }
    this.later(function () {
      self.pz.classList.add('done');
      self.sheen.animate([{ backgroundPosition: '100% 0' }, { backgroundPosition: '0% 0' }],
        { duration: 1200, delay: 250, easing: 'cubic-bezier(.4,0,.2,1)' });
      self.confetti(o.big ? 96 : 38);
      self.later(done, 1100);
    }, COUNT * 36 + 420);
  };

  Board.prototype.confetti = function (n) {
    var self = this, box = this.pz.getBoundingClientRect(), W = box.width, H = box.height;
    for (var j = 0; j < n; j++) {
      (function () {
        var sz = 6 + Math.random() * 7, ang = Math.random() * Math.PI * 2, spd = W * (0.32 + Math.random() * 0.62);
        var dx = Math.cos(ang) * spd, dy = Math.sin(ang) * spd * 0.8 - W * 0.12, fall = W * (0.3 + Math.random() * 0.4);
        var rot = (Math.random() * 540 - 270) | 0;
        var p = mk('i', 'cf', 'left:' + (W / 2 - sz / 2) + 'px;top:' + (H / 2 - sz / 2) + 'px;width:' + sz + 'px;height:' +
          (Math.random() < 0.5 ? sz : sz * 0.55) + 'px;background:' + COLORS[(Math.random() * COLORS.length) | 0] +
          ';border-radius:' + (Math.random() < 0.5 ? '50%' : '2px'));
        self.fx.appendChild(p);
        var a = p.animate([
          { transform: 'translate(0,0) scale(.3) rotate(0)', opacity: 1 },
          { transform: 'translate(' + dx * 0.7 + 'px,' + dy * 0.7 + 'px) scale(1) rotate(' + rot * 0.6 + 'deg)', opacity: 1, offset: 0.45 },
          { transform: 'translate(' + dx + 'px,' + (dy + fall) + 'px) scale(.9) rotate(' + rot + 'deg)', opacity: 0 }
        ], { duration: 1500 + Math.random() * 900, delay: Math.random() * 140, easing: 'cubic-bezier(.2,.7,.4,1)', fill: 'forwards' });
        a.onfinish = function () { p.parentNode && p.parentNode.removeChild(p); };
      })();
    }
  };

  /* شعار جديد: يختفي الرمز الكامل وترجع القطع أشباحاً بتتابع خفيف */
  Board.prototype.clear = function (animate, done) {
    var self = this;
    this.pz.classList.remove('done');
    this.pcs.forEach(function (p, i) {
      self.on[i] = false;
      p.style.transitionDelay = animate && !reduced() ? (i * 22) + 'ms' : '0ms';
      p.classList.remove('on');
    });
    this.later(function () { self.pcs.forEach(function (p) { p.style.transitionDelay = ''; }); if (done) done(); }, animate && !reduced() ? COUNT * 22 + 520 : 0);
  };


  /* حالة مكتملة فوراً (بلا حركة): الرمز الكامل ظاهر */
  Board.prototype.complete = function () {
    this.fill(COUNT, false);
    this.pz.classList.add('done');
  };

  /* موجة ضوء هادئة تبقي الشاشة حيّة وقت الانتظار */
  Board.prototype.wave = function () {
    if (!CAN || reduced()) return;
    if (this.pz.classList.contains('done')) {
      this.sheen.animate([{ backgroundPosition: '100% 0' }, { backgroundPosition: '0% 0' }],
        { duration: 1400, easing: 'cubic-bezier(.4,0,.2,1)' });
      return;
    }
    var self = this;
    this.pcs.forEach(function (p, i) {
      if (!self.on[i]) return;
      p.animate([{ filter: 'brightness(1)' }, { filter: 'brightness(1.32)' }, { filter: 'brightness(1)' }],
        { duration: 700, delay: i * 45, easing: 'ease-in-out' });
    });
  };

  Board.prototype.destroy = function () {
    this.timers.forEach(clearTimeout);
    this.timers = [];
    if (this.pz.parentNode) this.pz.parentNode.removeChild(this.pz);
  };

  root.WesalPuzzle = {
    COUNT: COUNT,
    lapOf: lapOf,
    pieceOf: pieceOf,
    config: function (o) { for (var k in o) if (Object.prototype.hasOwnProperty.call(o, k)) cfg[k] = o[k]; },
    preload: function (full) {
      var a = new Image(); a.src = cfg.atlas;
      if (full) { var b = new Image(); b.src = cfg.full; }
    },
    mount: function (host) { return new Board(host); }
  };
})(window);
