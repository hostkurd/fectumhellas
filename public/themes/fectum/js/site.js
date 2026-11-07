/*
 * FECTUM Hellas website (Stage 3a).
 * - Navigation without page reloads: a clicked link's page is fetched and
 *   swapped in (with a progress bar at the top and a cross-fade); the address,
 *   Back/Forward, title, meta tags, language and direction follow. Pages are
 *   fetched on hover, so they usually appear at once. Anything unusual (an
 *   error page, the admin, a file) opens normally.
 * - Header over the slider, phone menu, Saved / Compare kept in the browser,
 *   office maps (Leaflet + OpenStreetMap).
 * Without JavaScript every page still works as an ordinary page.
 */
(function () {
  'use strict';
  var doc = document;
  var root = doc.documentElement;
  root.classList.add('fh-js');
  var KEYS = { saved: 'fh-saved', compare: 'fh-compare' };
  var MAX_COMPARE = 3;
  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- Saved and Compare (localStorage) ---------- */
  function read(key) {
    try { var v = JSON.parse(localStorage.getItem(key) || '[]'); return Array.isArray(v) ? v.filter(function (n) { return Number.isInteger(n) && n > 0; }) : []; }
    catch (e) { return []; }
  }
  function write(key, list) {
    try { localStorage.setItem(key, JSON.stringify(list)); } catch (e) { /* private mode: keep going */ }
  }
  function refresh() {
    var saved = read(KEYS.saved);
    var compare = read(KEYS.compare);
    doc.querySelectorAll('[data-fh-saved-count]').forEach(function (el) {
      el.textContent = String(saved.length);
      el.hidden = saved.length === 0;
    });
    doc.querySelectorAll('[data-fh-saved-link]').forEach(function (el) {
      var url = el.getAttribute('href').split('?')[0];
      el.setAttribute('href', saved.length ? url + '?ids=' + saved.join(',') : url);
    });
    doc.querySelectorAll('[data-fh-save]').forEach(function (el) {
      el.setAttribute('aria-pressed', saved.indexOf(Number(el.getAttribute('data-fh-save'))) > -1 ? 'true' : 'false');
    });
    doc.querySelectorAll('[data-fh-compare]').forEach(function (el) {
      var on = compare.indexOf(Number(el.getAttribute('data-fh-compare'))) > -1;
      el.setAttribute('aria-pressed', on ? 'true' : 'false');
      el.setAttribute('aria-disabled', !on && compare.length >= MAX_COMPARE ? 'true' : 'false');
    });
  }
  function flip(key, id, max) {
    var list = read(key);
    var at = list.indexOf(id);
    if (at > -1) { list.splice(at, 1); } else if (!max || list.length < max) { list.push(id); }
    write(key, list);
    refresh();
  }

  /* ---------- Short messages (screen readers + a brief note on screen) ---------- */
  var live = doc.createElement('p');
  live.className = 'fh-toast';
  live.setAttribute('role', 'status');
  live.setAttribute('aria-live', 'polite');
  var hide;
  function announce(text, quiet) {
    if (!text) { return; }
    if (!live.isConnected) { doc.body.appendChild(live); }
    live.textContent = text;
    live.classList.toggle('is-shown', !quiet);
    clearTimeout(hide);
    hide = setTimeout(function () { live.classList.remove('is-shown'); }, 4000);
  }

  /* ---------- Header ---------- */
  function header() { return doc.querySelector('[data-fh-header]'); }
  function onScroll() {
    var h = header();
    if (!h) { return; }
    var scrolled = window.scrollY > 24;
    h.classList.toggle('is-scrolled', scrolled);
    if (h.classList.contains('is-overlay')) { h.classList.toggle('is-solid', scrolled); }
  }
  function closeMenu(focus) {
    var h = header();
    if (!h || !h.classList.contains('is-open')) { return; }
    h.classList.remove('is-open');
    var t = h.querySelector('[data-fh-menu-toggle]');
    if (t) { t.setAttribute('aria-expanded', 'false'); if (focus) { t.focus(); } }
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  doc.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeMenu(true); } });

  /* ---------- Office maps ---------- */
  var leaflet = null; // a promise once the Leaflet files are requested
  function loadLeaflet(el) {
    if (window.L) { return Promise.resolve(); }
    if (leaflet) { return leaflet; }
    leaflet = new Promise(function (resolve, reject) {
      var css = doc.createElement('link');
      css.rel = 'stylesheet';
      css.href = el.getAttribute('data-css');
      doc.head.appendChild(css);
      var js = doc.createElement('script');
      js.src = el.getAttribute('data-js');
      js.onload = function () { resolve(); };
      js.onerror = reject;
      doc.head.appendChild(js);
    });
    return leaflet;
  }
  function initMaps() {
    var maps = doc.querySelectorAll('[data-fh-map]:not(.is-live)');
    if (!maps.length) { return; }
    loadLeaflet(maps[0]).then(function () {
      if (!window.L) { return; }
      maps.forEach(function (el) {
        var lat = parseFloat(el.getAttribute('data-lat'));
        var lng = parseFloat(el.getAttribute('data-lng'));
        if (isNaN(lat) || isNaN(lng) || !el.isConnected || el.classList.contains('is-live')) { return; }
        el.classList.add('is-live');
        var map = window.L.map(el, { scrollWheelZoom: false, attributionControl: true }).setView([lat, lng], 16);
        window.L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
          maxZoom: 19,
          attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" rel="noopener">OpenStreetMap</a>'
        }).addTo(map);
        var icon = window.L.divIcon({
          className: 'fh-pin',
          html: '<span class="fh-pin-label"></span><svg width="36" height="44" viewBox="0 0 24 28" aria-hidden="true"><path d="M12 27s-9-8-9-15a9 9 0 0 1 18 0c0 7-9 15-9 15z" fill="#2B4285"/><circle cx="12" cy="12" r="3.5" fill="#fff"/></svg>',
          iconSize: [36, 44], iconAnchor: [18, 44]
        });
        var marker = window.L.marker([lat, lng], { icon: icon, title: el.getAttribute('data-label') || '', keyboard: false }).addTo(map);
        var label = marker.getElement() && marker.getElement().querySelector('.fh-pin-label');
        if (label) { label.textContent = el.getAttribute('data-label') || ''; }
      });
    }).catch(function () { /* the fallback link stays */ });
  }

  /* ---------- Everything a page needs once its HTML is in place ---------- */
  function initPage() {
    onScroll();
    doc.querySelectorAll('[data-fh-card-actions]').forEach(function (el) { el.hidden = false; });
    refresh();
    initMaps();
  }

  /* ---------- Clicks (delegated, so they work on swapped pages too) ---------- */
  doc.addEventListener('click', function (e) {
    var toggle = e.target.closest('[data-fh-menu-toggle]');
    if (toggle) {
      var h = header();
      var open = !h.classList.contains('is-open');
      h.classList.toggle('is-open', open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      return;
    }
    var save = e.target.closest('[data-fh-save]');
    if (save) { e.preventDefault(); flip(KEYS.saved, Number(save.getAttribute('data-fh-save'))); return; }
    var cmp = e.target.closest('[data-fh-compare]');
    if (cmp) {
      e.preventDefault();
      if (cmp.getAttribute('aria-disabled') === 'true') { announce(cmp.getAttribute('data-fh-full') || ''); return; }
      flip(KEYS.compare, Number(cmp.getAttribute('data-fh-compare')), MAX_COMPARE);
    }
  });
  window.addEventListener('storage', refresh);

  /* =====================================================================
   * Navigation without page reloads
   * ===================================================================== */
  var canSwap = !!(window.fetch && window.DOMParser && history.pushState && window.Promise);

  // Progress bar at the top
  var bar = doc.createElement('div');
  bar.className = 'fh-progress';
  bar.setAttribute('aria-hidden', 'true');
  var barTimer = null;
  var barValue = 0;
  function barStart() {
    if (!bar.isConnected) { doc.body.appendChild(bar); }
    clearInterval(barTimer);
    barValue = 0.08;
    bar.classList.remove('is-done');
    bar.classList.add('is-active');
    bar.style.transform = 'scaleX(' + barValue + ')';
    // Creeps towards 90% while waiting
    barTimer = setInterval(function () {
      barValue += (0.9 - barValue) * 0.08;
      bar.style.transform = 'scaleX(' + barValue.toFixed(3) + ')';
    }, 120);
  }
  function barDone() {
    clearInterval(barTimer);
    bar.style.transform = 'scaleX(1)';
    bar.classList.add('is-done');
    setTimeout(function () {
      bar.classList.remove('is-active', 'is-done');
      bar.style.transform = 'scaleX(0)';
    }, 350);
  }

  // Which addresses the site swaps itself
  function swappable(url) {
    if (url.origin !== location.origin) { return false; }
    if (/\/(admin|forms|flo-reactive|assets|uploads|themes)(\/|$)/.test(url.pathname)) { return false; }
    if (/\.(pdf|jpe?g|png|gif|webp|svg|zip|docx?|xlsx?|mp4|xml|txt)$/i.test(url.pathname)) { return false; }
    return true;
  }
  function plain(url) { return url.origin + url.pathname + url.search; }

  // Pages fetched on hover (kept for 60 seconds)
  var cache = {};
  function fetchPage(href) {
    var hit = cache[href];
    if (hit && Date.now() - hit.at < 60000) { return hit.promise; }
    var promise = fetch(href, { credentials: 'same-origin', headers: { Accept: 'text/html', 'X-FH-Navigate': '1' } }).then(function (res) {
      var type = res.headers.get('Content-Type') || '';
      if (!res.ok || type.indexOf('text/html') === -1) { throw new Error('not swappable'); }
      return res.text().then(function (html) { return { html: html, url: res.url || href }; });
    });
    cache[href] = { at: Date.now(), promise: promise };
    promise.catch(function () { delete cache[href]; });
    return promise;
  }
  var hoverTimer;
  function preload(a) {
    if (!canSwap || !a || a.target || a.hasAttribute('download') || a.hasAttribute('data-fh-no-swap')) { return; }
    var url;
    try { url = new URL(a.href, location.href); } catch (e) { return; }
    if (!swappable(url) || plain(url) === plain(new URL(location.href))) { return; }
    fetchPage(plain(url)).catch(function () {});
  }
  doc.addEventListener('pointerover', function (e) {
    var a = e.target.closest && e.target.closest('a[href]');
    clearTimeout(hoverTimer);
    if (a) { hoverTimer = setTimeout(function () { preload(a); }, 80); }
  }, { passive: true });
  doc.addEventListener('touchstart', function (e) { preload(e.target.closest && e.target.closest('a[href]')); }, { passive: true });

  // Head elements that belong to one page (replaced on every swap)
  var PAGE_HEAD = 'meta[name="description"],meta[name="robots"],meta[property^="og:"],meta[name^="twitter:"],link[rel="canonical"],link[rel="alternate"],script[type="application/ld+json"]';

  // flocms-admin block scripts that look for their blocks once when they run:
  // run again after a swap. Others listen on the document and need one run only.
  var RERUN = /\/(slider|faq|stats|motion)\.js/;

  function scriptKey(src) { return src.split('?')[0]; }
  function loadedScripts() {
    var keys = {};
    doc.querySelectorAll('script[src]').forEach(function (s) { keys[scriptKey(s.src)] = true; });
    return keys;
  }

  // New stylesheets first (no flash of unstyled content), resolves when loaded
  function addStyles(next) {
    var have = {};
    doc.querySelectorAll('link[rel="stylesheet"]').forEach(function (l) { have[l.href.split('?')[0]] = true; });
    var waits = [];
    next.querySelectorAll('link[rel="stylesheet"]').forEach(function (l) {
      var href = new URL(l.getAttribute('href'), location.href).href;
      if (have[href.split('?')[0]]) { return; }
      var link = doc.createElement('link');
      link.rel = 'stylesheet';
      link.href = href;
      waits.push(new Promise(function (resolve) { link.onload = link.onerror = resolve; setTimeout(resolve, 3000); }));
      doc.head.appendChild(link);
    });
    return Promise.all(waits);
  }

  function runScripts(sources, before) {
    sources.forEach(function (src) {
      var key = scriptKey(src);
      if (key === scriptKey(currentScript)) { return; } // this file
      if (before[key] && !RERUN.test(key)) { return; }
      var el = doc.createElement('script');
      el.src = src;
      el.async = false;
      doc.body.appendChild(el);
    });
  }

  function swap(next, url) {
    // Language and direction can change (EN → AR)
    root.lang = next.documentElement.lang || root.lang;
    root.dir = next.documentElement.dir || root.dir;
    doc.title = next.title;
    doc.querySelectorAll(PAGE_HEAD).forEach(function (el) { el.remove(); });
    next.head.querySelectorAll(PAGE_HEAD).forEach(function (el) { doc.head.appendChild(doc.importNode(el, true)); });
    var before = loadedScripts();
    // The page's scripts (head and body), run by runScripts: inserted HTML scripts never run
    var sources = Array.prototype.map.call(next.querySelectorAll('script[src]'), function (s) { return new URL(s.getAttribute('src'), location.href).href; });
    doc.body.className = next.body.className;
    next.body.querySelectorAll('script[src]').forEach(function (s) { s.remove(); });
    doc.body.replaceChildren.apply(doc.body, Array.prototype.map.call(next.body.childNodes, function (n) { return doc.importNode(n, true); }));
    runScripts(sources, before);
    initPage();
  }

  function focusPage(hash, scrollY) {
    if (hash) {
      var target = doc.getElementById(decodeURIComponent(hash.slice(1)));
      if (target) { target.scrollIntoView(); return; }
    }
    window.scrollTo(0, typeof scrollY === 'number' ? scrollY : 0);
    // For keyboard and screen reader users: start at the new page's content
    var main = doc.getElementById('main') || doc.querySelector('main, h1');
    if (main) {
      if (!main.hasAttribute('tabindex')) { main.setAttribute('tabindex', '-1'); }
      main.focus({ preventScroll: true });
    }
    announce(doc.title, true);
  }

  var navigating = 0;
  function navigate(href, push, scrollY) {
    var url = new URL(href, location.href);
    var id = ++navigating;
    // Remember where we were, for Back (not when Back/Forward brought us here)
    if (push) { try { history.replaceState({ fh: 1, y: window.scrollY }, '', location.href); } catch (e) { /* ignore */ } }
    closeMenu(false);
    barStart();
    return fetchPage(plain(url)).then(function (page) {
      if (id !== navigating) { return; } // a newer click won
      var next = new DOMParser().parseFromString(page.html, 'text/html');
      // Not one of our pages (offline page, admin, error page): open it normally
      if (!next.body || !next.body.classList.contains('fh') || !next.querySelector('[data-fh-header]')) { throw new Error('not swappable'); }
      return addStyles(next).then(function () {
        if (id !== navigating) { return; }
        var finalUrl = new URL(page.url);
        finalUrl.hash = url.hash;
        var apply = function () {
          swap(next, finalUrl);
          if (push) { history.pushState({ fh: 1, y: 0 }, '', finalUrl.href); }
          focusPage(url.hash, scrollY);
        };
        if (doc.startViewTransition && !reduced) {
          doc.startViewTransition(apply).finished.then(barDone, barDone);
        } else {
          apply();
          barDone();
        }
      });
    }).catch(function () {
      if (id === navigating) { location.href = url.href; }
    });
  }

  var currentScript = (doc.currentScript && doc.currentScript.src) || '';

  if (canSwap) {
    try { history.replaceState({ fh: 1, y: window.scrollY }, '', location.href); } catch (e) { /* ignore */ }

    doc.addEventListener('click', function (e) {
      if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }
      var a = e.target.closest('a[href]');
      if (!a || a.target || a.hasAttribute('download') || a.hasAttribute('data-fh-no-swap')) { return; }
      var url;
      try { url = new URL(a.href, location.href); } catch (err) { return; }
      if (!swappable(url)) { return; }
      // A link to a place on this same page: let the browser scroll
      if (plain(url) === plain(new URL(location.href)) && url.hash) { return; }
      e.preventDefault();
      navigate(url.href, true);
    });

    // Plain GET forms on the site (e.g. quick search): same, with the form's values
    doc.addEventListener('submit', function (e) {
      var form = e.target;
      if (e.defaultPrevented || form.hasAttribute('data-rx-submit') || (form.method || 'get').toLowerCase() !== 'get' || form.target) { return; }
      var url;
      try { url = new URL(form.getAttribute('action') || location.href, location.href); } catch (err) { return; }
      if (!swappable(url)) { return; }
      var params = new URLSearchParams();
      new FormData(form).forEach(function (value, key) { if (typeof value === 'string' && value !== '') { params.append(key, value); } });
      url.search = params.toString();
      e.preventDefault();
      navigate(url.href, true);
    });

    // Back and Forward. Registered before flocms-reactive's own handler (which
    // reloads the page) and stops it: the page for that address is fetched instead.
    var lastPlain = plain(new URL(location.href));
    window.addEventListener('popstate', function (e) {
      var now = plain(new URL(location.href));
      if (now === lastPlain) { return; } // only the #part changed
      e.stopImmediatePropagation();
      lastPlain = now;
      navigate(location.href, false, e.state && typeof e.state.y === 'number' ? e.state.y : 0);
    });
    var push = history.pushState;
    history.pushState = function () { var r = push.apply(history, arguments); lastPlain = plain(new URL(location.href)); return r; };
    var replace = history.replaceState;
    history.replaceState = function () { var r = replace.apply(history, arguments); lastPlain = plain(new URL(location.href)); return r; };
  }

  initPage();
})();
