/* ==========================================================================
   وصال الابتكار: سلوك صفحة الشركة
   لا اعتماد على أي مكتبة. كل شيء يعمل بتحسين تدريجي: بدون هذا الملف تبقى
   الصفحة كاملة الظهور والقراءة.
   ========================================================================== */
(function () {
  'use strict';
  window.HP_READY = true;

  var d = document, root = d.documentElement, win = window;
  var $ = function (s, c) { return (c || d).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || d).querySelectorAll(s)); };
  var hasIO = 'IntersectionObserver' in win;
  /* الصنف js يسحبه السكربت المضمّن إن تأخر هذا الملف، فلا نضيف حالات مخفية */
  var enhance = root.classList.contains('js');

  var rmq = win.matchMedia('(prefers-reduced-motion: reduce)');
  var reduced = rmq.matches;
  var onRm = function (e) { reduced = e.matches; req(); };
  if (rmq.addEventListener) rmq.addEventListener('change', onRm); else if (rmq.addListener) rmq.addListener(onRm);

  function store(k, v) {
    try { if (v === undefined) return localStorage.getItem(k); localStorage.setItem(k, v); } catch (e) { return null; }
    return null;
  }

  /* ---------- زر إيقاف الحركة المستمرة ---------- */
  var moBtn = $('#moBtn');
  function setPaused(p, persist) {
    root.classList.toggle('mo-paused', p);
    if (moBtn) {
      moBtn.setAttribute('aria-pressed', p ? 'true' : 'false');
      var u = $('use', moBtn);
      if (u) u.setAttribute('href', p ? '#i-play' : '#i-pause');
    }
    if (persist) store('wesal-motion', p ? 'off' : 'on');
  }
  setPaused(root.classList.contains('mo-paused'), false);
  if (moBtn) moBtn.addEventListener('click', function () { setPaused(!root.classList.contains('mo-paused'), true); });
  /* تتوقف الحركة المستمرة تلقائياً عندما تكون التبويبة مخفية */
  d.addEventListener('visibilitychange', function () { root.classList.toggle('mo-hidden', d.hidden); });
  root.classList.toggle('mo-hidden', d.hidden);

  /* ---------- الرأس والقائمة ---------- */
  var hdr = $('#hdr'), menuBtn = $('#menuBtn'), panel = $('#navPanel');
  var menuMQ = win.matchMedia('(max-width:1039px)');
  var isOpen = false;
  var lockEls = [$('.skip'), $('#main'), $('.foot'), $('#toTop')];

  function focusables() {
    return $$('a[href],button:not([disabled])', hdr).filter(function (el) { return el.getClientRects().length > 0; });
  }
  function setHidden(h) {
    if (hdr.classList.contains('is-hidden') !== h) hdr.classList.toggle('is-hidden', h);
  }
  function setOpen(o, restore) {
    if (o === isOpen) return;
    isOpen = o;
    if (o) {
      /* عرض شريط التمرير وجهته الفعلية (يسار الصفحة في العربية عادة) لتعويضه عند إخفائه */
      var sbw = win.innerWidth - root.clientWidth, onLeft = root.getBoundingClientRect().left > 0;
      root.style.setProperty('--sb-l', onLeft ? sbw + 'px' : '0px');
      root.style.setProperty('--sb-r', onLeft ? '0px' : sbw + 'px');
    }
    hdr.classList.toggle('is-open', o);
    root.classList.toggle('menu-open', o);
    menuBtn.setAttribute('aria-expanded', o ? 'true' : 'false');
    lockEls.forEach(function (el) {
      if (!el) return;
      if (o) el.setAttribute('inert', ''); else el.removeAttribute('inert');
    });
    if (o) {
      setHidden(false);
      var first = $('.nav-links a', panel);
      if (first) first.focus({ preventScroll: true });
    } else if (restore !== false) {
      menuBtn.focus({ preventScroll: true });
    }
  }
  if (menuBtn && panel) {
    menuBtn.addEventListener('click', function () { setOpen(!isOpen); });
    /* الضغط على أي رابط داخل القائمة يغلقها ويترك المتصفح ينتقل إلى الوجهة */
    panel.addEventListener('click', function (e) {
      if (isOpen && e.target.closest && e.target.closest('a')) setOpen(false, false);
    });
    var onMq = function (e) { if (!e.matches && isOpen) setOpen(false, false); };
    if (menuMQ.addEventListener) menuMQ.addEventListener('change', onMq); else if (menuMQ.addListener) menuMQ.addListener(onMq);
  }
  d.addEventListener('keydown', function (e) {
    if (!isOpen) return;
    if (e.key === 'Escape') { e.preventDefault(); setOpen(false); return; }
    if (e.key !== 'Tab') return;
    var f = focusables();
    if (!f.length) return;
    var first = f[0], last = f[f.length - 1], a = d.activeElement;
    if (e.shiftKey && (a === first || !hdr.contains(a))) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && (a === last || !hdr.contains(a))) { e.preventDefault(); first.focus(); }
  });
  /* الرأس لا يختفي وفي داخله عنصر عليه التركيز، ويعود فوراً عند التركيز عليه */
  hdr.addEventListener('focusin', function () { setHidden(false); });

  /* التركيز بلوحة المفاتيح على عنصر تحت الرأس الظاهر: نزيحه إلى ما بعده (WCAG 2.4.11) */
  d.addEventListener('focusin', function (e) {
    var t = e.target;
    if (!t || hdr.contains(t) || !t.getBoundingClientRect) return;
    requestAnimationFrame(function () {
      if (isOpen || hdr.classList.contains('is-hidden')) return;
      var r = t.getBoundingClientRect(), hb = hdr.getBoundingClientRect().bottom;
      if (r.top < hb + 4 && r.bottom > 0) {
        try { win.scrollBy({ top: r.top - hb - 16, behavior: 'instant' }); } catch (err) { win.scrollBy(0, r.top - hb - 16); }
      }
    });
  });

  /* ---------- التمرير: شريط التقدم، الرأس، الزر العلوي، العمق ---------- */
  var progress = $('#progress'), toTop = $('#toTop'), hero = $('.hero');
  var px = $$('[data-px]').map(function (el) { return { el: el, k: parseFloat(el.getAttribute('data-px')) || 0 }; });
  var ticking = false, lastY = win.pageYOffset, acc = 0, solid = false, shown = false, lastP = -1, heroH = 0;

  function measure() { heroH = hero ? hero.offsetHeight : 0; }
  function update() {
    ticking = false;
    var y = win.pageYOffset, vh = win.innerHeight;

    var s = y > 24;
    if (s !== solid) { solid = s; hdr.classList.toggle('is-solid', s); }

    var dy = y - lastY; lastY = y;
    if (dy) { acc = (dy > 0) === (acc > 0) ? acc + dy : dy; }
    if (reduced || isOpen || y < 140 || hdr.matches(':focus-within')) setHidden(false);
    else if (acc > 24) setHidden(true);
    else if (acc < -12) setHidden(false);

    var max = root.scrollHeight - vh;
    var p = max > 0 ? Math.min(1, Math.max(0, y / max)) : 0;
    if (progress && Math.abs(p - lastP) > 0.0005) { lastP = p; progress.style.transform = 'scaleX(' + p.toFixed(4) + ')'; }

    var sh = y > vh * 1.5;
    if (toTop && sh !== shown) { shown = sh; toTop.classList.toggle('show', sh); }

    if (!reduced && y < heroH + 120) {
      for (var i = 0; i < px.length; i++) px[i].el.style.transform = 'translate3d(0,' + (y * px[i].k).toFixed(1) + 'px,0)';
    } else if (px.length && px[0].el.style.transform) {
      for (var j = 0; j < px.length; j++) px[j].el.style.transform = '';
    }
  }
  function req() { if (!ticking) { ticking = true; requestAnimationFrame(update); } }
  win.addEventListener('scroll', req, { passive: true });
  win.addEventListener('resize', function () { measure(); req(); }, { passive: true });
  measure(); update();

  if (toTop) toTop.addEventListener('click', function () {
    win.scrollTo({ top: 0, behavior: reduced ? 'auto' : 'smooth' });
    var b = $('.brand', hdr);
    if (b) b.focus({ preventScroll: true });
  });

  /* ---------- تمييز القسم الحالي في القائمة ---------- */
  var links = $$('.nav-links a'), byId = {};
  links.forEach(function (a) { byId[(a.getAttribute('href') || '').slice(1)] = a; });
  function setCurrent(id) {
    links.forEach(function (a) {
      if (a === byId[id]) a.setAttribute('aria-current', 'location'); else a.removeAttribute('aria-current');
    });
  }
  if (hasIO) {
    var sio = new IntersectionObserver(function (es) {
      es.forEach(function (en) { if (en.isIntersecting) setCurrent(en.target.id); });
    }, { rootMargin: '-45% 0px -50% 0px' });
    $$('main>section[id]').forEach(function (s) { if (!s.hidden) sio.observe(s); });
  }

  /* ---------- الظهور عند التمرير (مرة واحدة، بتأخير متدرج عبر --i) ---------- */
  var rev = $$('[data-r]');
  function doneReveal(el) { el.classList.add('is-in'); el.removeAttribute('data-r'); }
  if (!enhance || reduced || !hasIO) {
    rev.forEach(doneReveal);
  } else {
    var rio = new IntersectionObserver(function (es) {
      es.forEach(function (en) {
        if (!en.isIntersecting) return;
        var el = en.target;
        rio.unobserve(el);
        el.classList.add('is-in');
        var i = parseFloat(el.style.getPropertyValue('--i')) || 0;
        /* بعد انتهاء الانتقال نُسقط السمة كي لا يتأخر تأثير الرفع عند تمرير المؤشر */
        setTimeout(function () { el.removeAttribute('data-r'); }, 1300 + i * 90);
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -6% 0px' });
    rev.forEach(function (el) { rio.observe(el); });
  }

  /* ---------- الخط الزمني: تُضاء الخطوات واحدة بعد أخرى ---------- */
  var tl = $$('.tl li');
  function activate(k) {
    for (var j = 0; j <= k; j++) {
      tl[j].classList.add('is-on');
      if (j > 0) tl[j - 1].classList.add('link-on');
    }
  }
  if (tl.length) {
    if (!enhance || reduced || !hasIO) activate(tl.length - 1);
    else {
      var tio = new IntersectionObserver(function (es) {
        es.forEach(function (en) {
          if (!en.isIntersecting) return;
          tio.unobserve(en.target);
          /* في العرض الأفقي كل الخطوات على صف واحد فتُضاء كلها بالتتابع، وفي العمودي تُضاء كل خطوة عند وصولها */
          var row = Math.abs(tl[0].getBoundingClientRect().top - tl[tl.length - 1].getBoundingClientRect().top) < 8;
          activate(row ? tl.length - 1 : tl.indexOf(en.target));
        });
      }, { rootMargin: '0px 0px -22% 0px', threshold: 0.4 });
      tl.forEach(function (li) { tio.observe(li); });
    }
  }

  /* ---------- بقعة الضوء التي تتبع المؤشر (الأجهزة ذات المؤشر الدقيق فقط) ---------- */
  if (!reduced && win.matchMedia('(hover:hover) and (pointer:fine)').matches) {
    $$('[data-spot]').forEach(function (card) {
      var s = d.createElement('span'), raf = 0, x = 0, y = 0;
      s.className = 'spot';
      s.setAttribute('aria-hidden', 'true');
      s.appendChild(d.createElement('i'));
      card.insertBefore(s, card.firstChild);
      card.addEventListener('pointermove', function (e) {
        var r = card.getBoundingClientRect();
        x = e.clientX - r.left; y = e.clientY - r.top;
        if (!raf) raf = requestAnimationFrame(function () {
          raf = 0;
          s.style.setProperty('--mx', x.toFixed(0) + 'px');
          s.style.setProperty('--my', y.toFixed(0) + 'px');
        });
      }, { passive: true });
    });
  }

  /* ================= الدعم الفني: تذكرة ضيف بلا حساب =================
     نفس نمط النداء في corporate.html: fetch وPOST JSON على نفس الأصل. */
  var API = '/api/';
  async function api(path, body) {
    try {
      var r = await fetch(API + path, { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify(body || {}) });
      var j = await r.json().catch(function () { return null; });
      if (j && typeof j === 'object') return j;
      throw new Error('http ' + r.status);
    } catch (e) { return { ok: false, offline: true }; }
  }
  function showMsg(id, txt) { var e = $('#' + id); if (e) e.textContent = txt || ''; }
  function clearInvalid(form) {
    $$('[aria-invalid]', form).forEach(function (el) { el.removeAttribute('aria-invalid'); el.removeAttribute('aria-describedby'); });
  }
  /* يعرض الرسالة، ويعلّم الحقل، وينقل التركيز إليه */
  function invalid(boxId, fieldId, msg) {
    showMsg(boxId, msg);
    var f = $('#' + fieldId);
    if (f) { f.setAttribute('aria-invalid', 'true'); f.setAttribute('aria-describedby', boxId); f.focus(); }
  }
  function btnLoad(id, on, label) {
    var b = $('#' + id); if (!b) return;
    b.disabled = on;
    b.setAttribute('aria-busy', on ? 'true' : 'false');
    if (on) { b.dataset.html = b.innerHTML; b.innerHTML = '<span class="spin" aria-hidden="true"></span> ' + (label || 'لحظة...'); }
    else if (b.dataset.html) { b.innerHTML = b.dataset.html; }
  }
  function validEmail(v) { return /^[^\s@]+@[^\s@]+\.[a-z]{2,}$/i.test(v); }

  var ticketForm = $('#ticketForm');
  if (ticketForm) {
    ticketForm.addEventListener('input', function (e) {
      if (e.target.removeAttribute) { e.target.removeAttribute('aria-invalid'); e.target.removeAttribute('aria-describedby'); }
    });
    ticketForm.addEventListener('submit', async function (e) {
      e.preventDefault();
      showMsg('tkErr', ''); clearInvalid(ticketForm);
      var type = $('#tkType').value;
      var name = $('#tkName').value.trim();
      var email = $('#tkEmail').value.trim();
      var urgency = $('#tkUrgency').value;
      var details = $('#tkDetails').value.trim();
      if (!type) return invalid('tkErr', 'tkType', 'اختر نوع الطلب.');
      if (name.length < 3) return invalid('tkErr', 'tkName', 'اكتب اسمك كاملاً حتى نعرف من نخاطب.');
      if (!validEmail(email)) return invalid('tkErr', 'tkEmail', 'اكتب بريداً إلكترونياً صحيحاً، فعليه نرسل لك رابط المتابعة.');
      if (details.length < 10) return invalid('tkErr', 'tkDetails', 'اكتب تفاصيل الطلب (10 أحرف على الأقل).');
      btnLoad('tkBtn', true, 'جارٍ الإرسال');
      var res = await api('tickets.php', { action: 'create', type: type, name: name, email: email, urgency: urgency, details: details, source: 'corporate' });
      btnLoad('tkBtn', false);
      if (!res.ok) return showMsg('tkErr', res.offline
        ? 'لم يصلنا طلبك، ويبدو أن الاتصال منقطع. نصّك ما زال في الصفحة، فأعد الإرسال بعد عودة الاتصال.'
        : (res.error || 'تعذّر إرسال الطلب. حاول مرة أخرى.'));
      showTicketSuccess(res.ref, res.token, res.message);
    });
  }

  function showTicketSuccess(ref, token, message) {
    $('#tkSuccessMsg').textContent = message || ('استلمنا طلبك. رقمه ' + ref + '.');
    $('#tkRefValue').textContent = ref;
    $('#tkTrackLink').href = '/ticket.html?token=' + encodeURIComponent(token);
    ticketForm.hidden = true;
    var box = $('#tkSuccess');
    box.hidden = false;
    box.focus();
    box.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'start' });
  }
  var again = $('#tkAgainBtn');
  if (again) again.addEventListener('click', function () {
    $('#tkSuccess').hidden = true;
    ticketForm.hidden = false;
    ticketForm.reset();
    $('#tkType').focus();
  });

  /* ---------- متابعة تذكرة سابقة ---------- */
  var trackForm = $('#trackForm');
  if (trackForm) trackForm.addEventListener('submit', function (e) {
    e.preventDefault();
    showMsg('trErr', ''); clearInvalid(trackForm);
    var raw = $('#trInput').value;
    var m = raw.match(/token=([a-f0-9]+)/i);
    var token = ((m ? m[1] : raw) || '').replace(/[^a-f0-9]/ig, '').toLowerCase();
    if (token.length !== 32) return invalid('trErr', 'trInput', 'أدخل رابط المتابعة كاملاً أو الرمز الذي أرسلناه إليك بالبريد.');
    location.href = '/ticket.html?token=' + token;
  });
})();
