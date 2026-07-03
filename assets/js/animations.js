/* ============================================================
   BALANS — Animation engine (progressive enhancement)
   - Scroll-reveal with stagger (IntersectionObserver)
   - Hero entrance on load
   - Header condense on scroll
   - Animated count-up stats
   - Self-typing phone chat with "typing…" indicator
   Fully guarded by prefers-reduced-motion.
   ============================================================ */
(function () {
  'use strict';

  var root = document.documentElement;
  root.classList.add('anim-ready');

  var REDUCED = window.matchMedia &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  var supportsIO = 'IntersectionObserver' in window;

  /* ---------- 1. Header condense on scroll ---------- */
  var hdr = document.querySelector('.hdr');
  if (hdr) {
    var onScroll = function () {
      hdr.classList.toggle('scrolled', window.scrollY > 24);
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  /* ---------- 2. Tag elements for scroll-reveal ---------- */
  // [selector, variant, staggerStep(ms)]
  var REVEAL = [
    ['.hero-text > .hero-sub', 'up', 0],
    ['.hero-text > .hero-waitlist', 'up', 0],
    ['.compare-head', 'up', 0],
    ['.compare-col', 'up', 120],
    ['.feats-head', 'up', 0],
    ['.reviews-head', 'up', 0],
    ['.video-head', 'up', 0],
    ['.video-frame', 'scale', 0],
    ['.feat-eyebrow', 'up', 0],
    ['.feat-text h2', 'up', 0],
    ['.feat-text > p', 'up', 0],
    ['.feat-li', 'up', 90],
    ['.feat-phone-wrap', 'right', 0],
    ['.steps-3 > div', 'up', 110],
    ['.steps-4 > div', 'up', 110],
    ['.purpose h2', 'up', 0],
    ['.purpose p', 'up', 0],
    ['.feat-card', 'up', 90],
    ['.review', 'up', 110],
    ['.faq-head', 'up', 0],
    ['.faq-item', 'up', 70],
    ['#business h2', 'up', 0],
    ['#business > .container > div:first-child > p', 'up', 0],
    ['.dash-cards > div', 'up', 110],
    ['.cta h2', 'up', 0],
    ['.cta-actions', 'up', 0]
  ];

  REVEAL.forEach(function (rule) {
    var sel = rule[0], variant = rule[1], step = rule[2];
    var nodes = document.querySelectorAll(sel);
    // group by parent so stagger restarts per row/section
    var counters = new Map();
    nodes.forEach(function (el) {
      if (el.hasAttribute('data-reveal')) return;
      el.setAttribute('data-reveal', variant === 'up' ? '' : variant);
      var key = el.parentNode;
      var i = counters.get(key) || 0;
      counters.set(key, i + 1);
      el.dataset.revealDelay = String(step * i);
    });
  });

  /* ---------- 3. Reveal observer ---------- */
  var revealTargets = document.querySelectorAll('[data-reveal]');

  function show(el) {
    var d = parseInt(el.dataset.revealDelay || '0', 10);
    if (REDUCED || !d) {
      el.classList.add('in');
    } else {
      setTimeout(function () { el.classList.add('in'); }, d);
    }
  }

  if (REDUCED || !supportsIO) {
    revealTargets.forEach(function (el) { el.classList.add('in'); });
  } else {
    var revealIO = new IntersectionObserver(function (entries, obs) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          show(entry.target);
          obs.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });
    revealTargets.forEach(function (el) { revealIO.observe(el); });
  }

  /* ---------- 4a. Hero headline: split into animatable words ---------- */
  var heroWords = [];
  var h1 = document.querySelector('.hero-text .hero-h1');
  if (h1) {
    var frag = document.createDocumentFragment();
    var lastUnit = null;
    Array.prototype.slice.call(h1.childNodes).forEach(function (node) {
      if (node.nodeType === 3) { // text node → split into words, keep spaces
        node.textContent.split(/(\s+)/).forEach(function (part) {
          if (part === '') return;
          if (/^\s+$/.test(part)) { frag.appendChild(document.createTextNode(part)); return; }
          // pure punctuation (e.g. the comma) → glue to the previous word
          if (lastUnit && !/[\p{L}\p{N}]/u.test(part)) {
            lastUnit.appendChild(document.createTextNode(part));
            return;
          }
          var w = document.createElement('span');
          w.className = 'hword';
          w.textContent = part;
          frag.appendChild(w);
          heroWords.push(w);
          lastUnit = w;
        });
      } else { // styled element (fatture / più tempo / business) → one unit
        var w2 = document.createElement('span');
        w2.className = 'hword';
        w2.appendChild(node.cloneNode(true));
        frag.appendChild(w2);
        heroWords.push(w2);
        lastUnit = w2;
      }
    });
    h1.innerHTML = '';
    h1.appendChild(frag);
  }
  function revealWords() {
    heroWords.forEach(function (w, i) {
      if (REDUCED) { w.classList.add('in'); return; }
      setTimeout(function () { w.classList.add('in'); }, 120 + i * 65);
    });
  }

  /* ---------- 4. Hero entrance (fires on load) ---------- */
  var heroPhone = document.querySelector('.hero-phone-wrap');
  var floatCards = document.querySelectorAll('.hero .float-card');
  function heroEnter() {
    if (heroPhone) heroPhone.classList.add('in');
    floatCards.forEach(function (c, i) {
      if (REDUCED) { c.classList.add('in'); return; }
      setTimeout(function () { c.classList.add('in'); }, 700 + i * 180);
    });
  }
  if (REDUCED) {
    revealWords();
    heroEnter();
  } else {
    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        revealWords();
        heroEnter();
      });
    });
  }

  /* ---------- 5. Animated count-up ---------- */
  function animateCount(el) {
    var target = parseFloat(el.dataset.count);
    if (isNaN(target)) return;
    var decimals = parseInt(el.dataset.decimals || '0', 10);
    var prefix = el.dataset.prefix || '';
    var suffix = el.dataset.suffix || '';
    el.classList.add('counting');

    var format = function (v) {
      var s = v.toFixed(decimals).replace('.', ','); // Italian decimal
      return prefix + s + suffix;
    };

    if (REDUCED) { el.textContent = format(target); return; }

    var dur = 1400, start = null;
    var ease = function (t) { return 1 - Math.pow(1 - t, 3); }; // easeOutCubic
    function tick(ts) {
      if (start === null) start = ts;
      var p = Math.min((ts - start) / dur, 1);
      el.textContent = format(target * ease(p));
      if (p < 1) requestAnimationFrame(tick);
      else el.textContent = format(target);
    }
    requestAnimationFrame(tick);
  }

  var counters = document.querySelectorAll('[data-count]');
  if (counters.length) {
    if (REDUCED || !supportsIO) {
      counters.forEach(animateCount);
    } else {
      var countIO = new IntersectionObserver(function (entries, obs) {
        entries.forEach(function (e) {
          if (e.isIntersecting) { animateCount(e.target); obs.unobserve(e.target); }
        });
      }, { threshold: 0.6 });
      counters.forEach(function (el) { countIO.observe(el); });
    }
  }

  /* ---------- 5b. Slot-machine number ---------- */
  function buildReel(digit) {
    var reel = document.createElement('span');
    reel.className = 'reel';
    var chars = [];
    for (var c = 0; c < 2; c++) { for (var n = 0; n <= 9; n++) chars.push(n); }
    for (var m = 0; m <= digit; m++) chars.push(m);
    chars.forEach(function (n) {
      var s = document.createElement('span');
      s.textContent = n;
      reel.appendChild(s);
    });
    reel._land = chars.length - 1;
    return reel;
  }

  function slotMachine(el) {
    var target = (el.dataset.slot || '').trim();
    if (!/^\d+$/.test(target)) { el.textContent = target; return; }
    el.textContent = '';
    el.classList.add('slot');
    var reels = [];
    target.split('').forEach(function (ch) {
      var reel = buildReel(parseInt(ch, 10));
      el.appendChild(reel);
      reels.push(reel);
    });
    reels.forEach(function (r, i) {
      if (REDUCED) { r.style.transform = 'translateY(-' + r._land + 'em)'; return; }
      r.style.transform = 'translateY(0)';
      void r.offsetHeight; // reflow so the transition runs
      r.style.transition = 'transform ' + (1.2 + i * 0.25) +
        's cubic-bezier(0.16, 1, 0.3, 1) ' + (i * 0.12) + 's';
      r.style.transform = 'translateY(-' + r._land + 'em)';
    });
  }

  var slots = document.querySelectorAll('[data-slot]');
  if (slots.length) {
    if (REDUCED || !supportsIO) {
      slots.forEach(slotMachine);
    } else {
      var slotIO = new IntersectionObserver(function (entries, obs) {
        entries.forEach(function (e) {
          if (e.isIntersecting) { slotMachine(e.target); obs.unobserve(e.target); }
        });
      }, { threshold: 0.6 });
      slots.forEach(function (el) { slotIO.observe(el); });
    }
  }

  /* ---------- 5c. Magnetic buttons ---------- */
  var FINE = window.matchMedia &&
    window.matchMedia('(hover: hover) and (pointer: fine)').matches;
  if (!REDUCED && FINE) {
    var mags = document.querySelectorAll(
      '.hero-btn, .hdr-cta, .btn, .cta-btn-white, .cta-btn-outline');
    mags.forEach(function (btn) {
      btn.classList.add('magnetic');
      btn.style.transition = 'transform 0.18s ease-out';
      btn.addEventListener('mousemove', function (e) {
        var b = btn.getBoundingClientRect();
        var mx = e.clientX - (b.left + b.width / 2);
        var my = e.clientY - (b.top + b.height / 2);
        btn.style.transform = 'translate(' + (mx * 0.3).toFixed(1) + 'px,' +
          (my * 0.3 - 2).toFixed(1) + 'px)';
      });
      btn.addEventListener('mouseleave', function () {
        btn.style.transform = '';
      });
    });
  }

  /* ---------- 6. Self-typing phone chat ---------- */
  function makeTyping() {
    var t = document.createElement('div');
    t.className = 'typing-bub';
    t.innerHTML = '<span></span><span></span><span></span>';
    return t;
  }

  function playChat(body) {
    var bubbles = Array.prototype.slice.call(body.querySelectorAll('.bub'));
    if (!bubbles.length) return;

    if (REDUCED) {
      bubbles.forEach(function (b) { b.classList.add('pop'); });
      body.scrollTop = body.scrollHeight;
      return;
    }

    var i = 0;
    var toBottom = function (el) {
      // offsetTop non è affidabile: .ps-chat-body non è positioned, quindi
      // offsetTop risalirebbe a .phone-content includendo l'header del telefono
      var elBottom = el.getBoundingClientRect().bottom - body.getBoundingClientRect().top + body.scrollTop;
      var target = elBottom - body.clientHeight + 80; // 80 = margine sopra il composer
      body.scrollTop = Math.max(0, target);
    };

    function next() {
      if (i >= bubbles.length) return;
      var bub = bubbles[i];
      var isAI = /\bai\b|ai-card/.test(bub.className);

      if (isAI) {
        var typing = makeTyping();
        body.insertBefore(typing, bub);
        toBottom(typing);
        setTimeout(function () {
          body.removeChild(typing);
          bub.classList.add('pop');
          toBottom(bub);
          i++;
          setTimeout(next, 520);
        }, 950);
      } else {
        bub.classList.add('pop');
        toBottom(bub);
        i++;
        setTimeout(next, 620);
      }
    }
    setTimeout(next, 360);
  }

  var chatBodies = document.querySelectorAll('.ps-chat-body');
  if (chatBodies.length) {
    if (REDUCED || !supportsIO) {
      chatBodies.forEach(playChat);
    } else {
      var chatIO = new IntersectionObserver(function (entries, obs) {
        entries.forEach(function (e) {
          if (e.isIntersecting) { playChat(e.target); obs.unobserve(e.target); }
        });
      }, { threshold: 0.35 });
      chatBodies.forEach(function (el) { chatIO.observe(el); });
    }
  }

  /* ---------- 7. Ambient scroll backdrop + progress bar ---------- */
  var parallaxNodes = []; // { node, speed, section }

  function buildFx(sel, opts) {
    var sec = document.querySelector(sel);
    if (!sec) return;
    sec.classList.add('fx-host');
    var host = document.createElement('div');
    host.className = 'section-fx' + (opts.dark ? ' on-dark' : '');

    opts.blobs.forEach(function (b) {
      var pfx = document.createElement('div');
      pfx.className = 'pfx';
      var el = document.createElement('div');
      el.className = 'blob ' + b.color + ' ' + b.drift;
      el.style.cssText = 'top:' + b.top + ';left:' + b.left +
        ';width:' + b.w + ';height:' + b.h + ';';
      pfx.appendChild(el);
      host.appendChild(pfx);
      parallaxNodes.push({ node: pfx, speed: b.speed, section: sec });
    });

    if (opts.grid) {
      var g = document.createElement('div');
      g.className = 'grid-fx';
      host.appendChild(g);
    }
    sec.insertBefore(host, sec.firstChild);
  }

  var T = 'b-teal', K = 'b-ink';
  // section, options: dark, grid, blobs[{color,drift,top,left,w,h,speed}]
  buildFx('.compare', { grid: true, blobs: [
    { color: T, drift: 'drift-a', top: '-10%', left: '58%', w: '40vw', h: '40vw', speed: 60 },
    { color: K, drift: 'drift-b', top: '40%', left: '-8%', w: '32vw', h: '32vw', speed: -46 }
  ]});
  buildFx('.how', { blobs: [
    { color: T, drift: 'drift-b', top: '10%', left: '-6%', w: '34vw', h: '34vw', speed: 54 },
    { color: K, drift: 'drift-a', top: '30%', left: '70%', w: '30vw', h: '30vw', speed: -40 }
  ]});
  buildFx('.video-sec', { blobs: [
    { color: T, drift: 'drift-a', top: '-5%', left: '65%', w: '38vw', h: '38vw', speed: 66 }
  ]});
  buildFx('.feats', { grid: true, blobs: [
    { color: T, drift: 'drift-b', top: '0%', left: '-10%', w: '36vw', h: '36vw', speed: 58 },
    { color: K, drift: 'drift-a', top: '45%', left: '68%', w: '30vw', h: '30vw', speed: -44 }
  ]});
  buildFx('.reviews', { blobs: [
    { color: T, drift: 'drift-a', top: '5%', left: '55%', w: '38vw', h: '38vw', speed: 62 },
    { color: K, drift: 'drift-b', top: '50%', left: '-8%', w: '30vw', h: '30vw', speed: -42 }
  ]});
  buildFx('.faq', { blobs: [
    { color: T, drift: 'drift-b', top: '10%', left: '-8%', w: '32vw', h: '32vw', speed: 50 }
  ]});
  buildFx('#business', { dark: true, grid: true, blobs: [
    { color: T, drift: 'drift-a', top: '-8%', left: '60%', w: '42vw', h: '42vw', speed: 72 },
    { color: K, drift: 'drift-b', top: '55%', left: '-10%', w: '34vw', h: '34vw', speed: -50 }
  ]});
  buildFx('.cta', { dark: true, blobs: [
    { color: K, drift: 'drift-a', top: '-10%', left: '58%', w: '38vw', h: '38vw', speed: 60 }
  ]});

  // Scroll progress bar
  var progress = document.createElement('div');
  progress.className = 'scroll-progress';
  document.body.appendChild(progress);

  var ticking = false;
  function fxFrame() {
    ticking = false;
    var vh = window.innerHeight;

    if (!REDUCED) {
      for (var i = 0; i < parallaxNodes.length; i++) {
        var p = parallaxNodes[i];
        var r = p.section.getBoundingClientRect();
        if (r.bottom < -vh || r.top > vh * 2) continue; // offscreen, skip
        var center = r.top + r.height / 2;
        var rel = (center - vh / 2) / vh; // 0 when centered in viewport
        p.node.style.transform = 'translate3d(0,' + (rel * p.speed).toFixed(1) + 'px,0)';
      }
    }

    var doc = document.documentElement;
    var max = doc.scrollHeight - vh;
    var ratio = max > 0 ? Math.min(window.scrollY / max, 1) : 0;
    progress.style.transform = 'scaleX(' + ratio.toFixed(4) + ')';
  }
  function onFxScroll() {
    if (!ticking) { ticking = true; requestAnimationFrame(fxFrame); }
  }
  window.addEventListener('scroll', onFxScroll, { passive: true });
  window.addEventListener('resize', onFxScroll, { passive: true });
  fxFrame();
})();
