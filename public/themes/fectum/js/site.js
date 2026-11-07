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
  var KEYS = { saved: 'fh-saved', compare: 'fh-compare', items: 'fh-compare-items' };
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
      var card = el.closest('.fh-card');
      if (card) { card.classList.toggle('is-compared', on); }
    });
    renderCompareBar(compare);
  }

  // What the compare bar shows of each property (title, price, photo, address)
  function items() {
    try { var v = JSON.parse(localStorage.getItem(KEYS.items) || '{}'); return v && typeof v === 'object' ? v : {}; } catch (e) { return {}; }
  }
  function remember(el) {
    var holder = el.closest('[data-fh-card]');
    if (!holder) { return; }
    try {
      var data = JSON.parse(holder.getAttribute('data-fh-card'));
      var all = items();
      all[String(data.id)] = { title: String(data.title || ''), price: String(data.price || ''), url: String(data.url || ''), img: String(data.img || '') };
      try { localStorage.setItem(KEYS.items, JSON.stringify(all)); } catch (e) { /* ignore */ }
    } catch (e) { /* ignore */ }
  }
  function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  // Only addresses on this site (stored values could have been edited by hand)
  function safeUrl(u) {
    try { var url = new URL(u, location.href); return url.origin === location.origin ? url.href : '#'; } catch (e) { return '#'; }
  }
  function words() {
    try { return JSON.parse((doc.querySelector('[data-fh-t]') || {}).textContent || '{}'); } catch (e) { return {}; }
  }
  function renderCompareBar(compare) {
    doc.querySelectorAll('[data-fh-compare-bar]').forEach(function (bar) {
      if (!compare.length) { bar.hidden = true; bar.innerHTML = ''; return; }
      var t = words();
      var all = items();
      var slots = '';
      for (var i = 0; i < MAX_COMPARE; i++) {
        var id = compare[i];
        var it = id ? all[String(id)] : null;
        if (id) {
          slots += '<div class="fh-cbar-slot">' + (it && it.img ? '<img src="' + esc(safeUrl(it.img)) + '" alt="">' : '<span class="fh-cbar-noimg"></span>')
            + '<a href="' + esc(it ? safeUrl(it.url) : '#') + '"><b>' + esc(it ? it.title : '#' + id) + '</b><span>' + esc(it ? it.price : '') + '</span></a>'
            + '<button type="button" data-fh-compare="' + id + '" aria-pressed="true" aria-label="' + esc((t.remove || 'Remove') + ' ' + (it ? it.title : '')) + '"><svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>';
        } else {
          slots += '<div class="fh-cbar-empty"><svg aria-hidden="true" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>' + esc(t.add || '') + '</div>';
        }
      }
      var go = compare.length >= 2
        ? '<a class="fh-btn fh-btn-solid" href="' + esc(safeUrl((t.compareUrl || '') + '?ids=' + compare.join(','))) + '">' + esc((t.compareN || 'Compare (:n)').replace(':n', compare.length)) + '</a>'
        : '<span class="fh-cbar-wait">' + esc(t.oneMore || '') + '</span>';
      bar.innerHTML = '<div class="fh-cbar" role="region" aria-label="' + esc(t.region || 'Compare') + '"><div class="fh-cbar-head"><small>' + esc(t.title || 'Compare') + '</small><span role="status">'
        + esc((t.selected || ':n / 3').replace(':n', compare.length)) + '</span></div><div class="fh-cbar-slots">' + slots + '</div><div class="fh-cbar-go"><button type="button" class="fh-text-btn" data-fh-compare-clear>'
        + esc(t.clear || 'Clear') + '</button>' + go + '</div></div>';
      bar.hidden = false;
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
        var map = window.L.map(el, { scrollWheelZoom: false, attributionControl: true }).setView([lat, lng], parseInt(el.getAttribute('data-zoom'), 10) || 16);
        leafletMaps.push(map);
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
    leafletMaps = leafletMaps.filter(function (m) { if (!m.getContainer().isConnected) { m.remove(); return false; } return true; });
    onScroll();
    doc.querySelectorAll('[data-fh-card-actions]').forEach(function (el) { el.hidden = false; });
    refresh();
    initMaps();
    initResultsMaps();
    initDevelopment();
  }

  var leafletMaps = [];

  /* ---------- Search results map (price pins) ---------- */
  function initResultsMaps() {
    var boxes = doc.querySelectorAll('[data-fh-results-map]:not(.is-live)');
    if (!boxes.length) { return; }
    loadLeaflet(boxes[0]).then(function () {
      if (!window.L) { return; }
      boxes.forEach(function (el) {
        var points;
        try { points = JSON.parse(el.getAttribute('data-points') || '[]'); } catch (e) { points = []; }
        if (!points.length || !el.isConnected || el.classList.contains('is-live')) { return; }
        el.classList.add('is-live');
        var map = window.L.map(el, { scrollWheelZoom: false });
        leafletMaps.push(map);
        window.L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" rel="noopener">OpenStreetMap</a>' }).addTo(map);
        var bounds = [];
        points.forEach(function (pt) {
          var icon = window.L.divIcon({ className: 'fh-price-pin', html: '<a href="' + esc(safeUrl(pt.url)) + '">' + esc(pt.label) + '</a>', iconSize: [0, 0] });
          window.L.marker([pt.lat, pt.lng], { icon: icon, keyboard: false }).addTo(map);
          bounds.push([pt.lat, pt.lng]);
        });
        if (bounds.length === 1) { map.setView(bounds[0], 15); } else { map.fitBounds(bounds, { padding: [48, 48], maxZoom: 15 }); }
      });
    }).catch(function () {});
  }

  /* ---------- Gallery lightbox ---------- */
  var lb = { i: 0, list: [], opener: null };
  function openLightbox(index) {
    var tpl = doc.querySelector('template[data-fh-photos]');
    var dialog = doc.querySelector('[data-fh-lightbox]');
    if (!tpl || !dialog || !dialog.showModal) { return; }
    lb.list = Array.prototype.map.call(tpl.content.querySelectorAll('a'), function (a) { return { src: a.getAttribute('href'), alt: a.getAttribute('data-alt') || '' }; });
    lb.opener = doc.activeElement;
    showPhoto(index);
    dialog.showModal();
  }
  function showPhoto(index) {
    var dialog = doc.querySelector('[data-fh-lightbox]');
    if (!dialog || !lb.list.length) { return; }
    lb.i = (index + lb.list.length) % lb.list.length;
    var img = dialog.querySelector('[data-fh-lb-img]');
    img.src = lb.list[lb.i].src;
    img.alt = lb.list[lb.i].alt;
    dialog.querySelector('[data-fh-lb-count]').textContent = (lb.i + 1) + ' / ' + lb.list.length;
  }
  doc.addEventListener('click', function (e) {
    var dialog = e.target.closest && e.target.closest('[data-fh-lightbox]');
    if (!dialog) { return; }
    if (e.target.closest('[data-fh-lb-next]')) { showPhoto(lb.i + 1); }
    else if (e.target.closest('[data-fh-lb-prev]')) { showPhoto(lb.i - 1); }
    else if (e.target.closest('[data-fh-lb-close]') || e.target === dialog) { dialog.close(); }
  });
  doc.addEventListener('keydown', function (e) {
    if (!doc.querySelector('[data-fh-lightbox][open]')) { return; }
    var rtl = root.dir === 'rtl';
    if (e.key === 'ArrowRight') { showPhoto(lb.i + (rtl ? -1 : 1)); }
    if (e.key === 'ArrowLeft') { showPhoto(lb.i + (rtl ? 1 : -1)); }
  });
  doc.addEventListener('close', function (e) {
    if (e.target.matches && e.target.matches('[data-fh-lightbox]') && lb.opener && lb.opener.focus) { lb.opener.focus(); }
  }, true);
  var touchX = null;
  doc.addEventListener('touchstart', function (e) { if (e.target.closest && e.target.closest('[data-fh-lightbox]')) { touchX = e.touches[0].clientX; } }, { passive: true });
  doc.addEventListener('touchend', function (e) {
    if (touchX === null) { return; }
    var dx = e.changedTouches[0].clientX - touchX;
    touchX = null;
    if (Math.abs(dx) > 50) { showPhoto(lb.i + ((dx < 0) !== (root.dir === 'rtl') ? 1 : -1)); }
  }, { passive: true });

  /* ---------- Video: YouTube/Vimeo loads only when the visitor presses play ---------- */
  function playVideo(btn) {
    var src = btn.getAttribute('data-fh-video');
    if (!/^https:\/\/(www\.youtube-nocookie\.com|player\.vimeo\.com)\//.test(src)) { return; }
    var frame = doc.createElement('iframe');
    frame.src = src;
    frame.title = btn.getAttribute('aria-label') || '';
    frame.allow = 'autoplay; fullscreen; picture-in-picture';
    frame.allowFullscreen = true;
    var box = doc.createElement('div');
    box.className = 'fh-video';
    box.appendChild(frame);
    btn.replaceWith(box);
    frame.focus();
  }

  /* ---------- Share ---------- */
  function sharePage(btn) {
    var data = { title: btn.getAttribute('data-title') || doc.title, url: location.href.split('#')[0] };
    if (navigator.share) { navigator.share(data).catch(function () {}); return; }
    if (navigator.clipboard) {
      navigator.clipboard.writeText(data.url).then(function () { announce(btn.getAttribute('data-fh-copied') || ''); }, function () {});
    }
  }

  /* ---------- Cost of buying calculator (the server prints the asking price's figures) ---------- */
  function format(n, lang, plain) {
    var s = String(Math.abs(Math.round(n))).replace(/\B(?=(\d{3})+(?!\d))/g, lang === 'el' && !plain ? '.' : ',');
    if (plain) { return s; }
    return lang === 'en' ? '€' + s : s + ' €';
  }
  function recalc(calc) {
    var lang = calc.getAttribute('data-lang') || 'en';
    var input = calc.querySelector('[data-fh-calc-input]');
    var price = Number(String(input.value).replace(/[^0-9]/g, '')) || 0;
    var sum = 0;
    calc.querySelectorAll('[data-rate]').forEach(function (row) {
      var amount = price * Number(row.getAttribute('data-rate')) / 100;
      sum += amount;
      row.querySelector('[data-fh-calc-amount]').textContent = format(amount, lang);
    });
    calc.querySelector('[data-fh-calc-sum]').textContent = format(sum, lang);
    calc.querySelector('[data-fh-calc-grand]').textContent = format(price + sum, lang);
  }
  doc.addEventListener('input', function (e) {
    if (!e.target.matches || !e.target.matches('[data-fh-calc-input]')) { return; }
    var input = e.target;
    // Keep the caret after the same digit when the separators are re-added
    var before = String(input.value).slice(0, input.selectionStart || 0).replace(/[^0-9]/g, '').length;
    var digits = String(input.value).replace(/[^0-9]/g, '').slice(0, 12);
    input.value = digits ? format(Number(digits), 'en', true) : '';
    var pos = 0;
    for (var seen = 0; pos < input.value.length && seen < before; pos++) { if (/[0-9]/.test(input.value[pos])) { seen++; } }
    try { input.setSelectionRange(pos, pos); } catch (err) { /* not focused */ }
    recalc(input.closest('[data-fh-calc]'));
  });

  /* ---------- After flocms-reactive updates a component (search results) ---------- */
  var scrollResults = 0;
  var lastAction = null; // the control a visitor used, to give focus back after the update
  doc.addEventListener('click', function (e) {
    var el = e.target.closest && e.target.closest('[data-rx-component] [data-rx-action]');
    lastAction = el ? { action: el.getAttribute('data-rx-action'), value: el.getAttribute('data-rx-value') } : lastAction;
  }, true);
  doc.addEventListener('flo-reactive:updated', function (e) {
    doc.querySelectorAll('[data-fh-card-actions]').forEach(function (el) { el.hidden = false; });
    // Checkboxes that run an action (features): show what the server says
    (e.target.querySelectorAll ? e.target : doc).querySelectorAll('input[type=checkbox][data-rx-action]').forEach(function (box) { box.checked = box.hasAttribute('checked'); });
    if (lastAction && (doc.activeElement === doc.body || !doc.activeElement)) {
      var sel = '[data-rx-action="' + lastAction.action + '"]' + (lastAction.value !== null ? '[data-rx-value="' + String(lastAction.value).replace(/"/g, '') + '"]' : '');
      var again = doc.querySelector(sel);
      if (again && again.focus) { again.focus({ preventScroll: true }); }
    }
    lastAction = null;
    // Maps whose container was replaced: free them
    leafletMaps = leafletMaps.filter(function (m) { if (!m.getContainer().isConnected) { m.remove(); return false; } return true; });
    refresh();
    initResultsMaps();
    if (scrollResults && Date.now() - scrollResults < 5000) {
      scrollResults = 0;
      var target = doc.getElementById('fh-results');
      if (target) { window.scrollTo({ top: target.getBoundingClientRect().top + window.scrollY - 160, behavior: reduced ? 'auto' : 'smooth' }); }
    }
  });


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
      var inBar = !!cmp.closest('[data-fh-compare-bar]');
      remember(cmp);
      flip(KEYS.compare, Number(cmp.getAttribute('data-fh-compare')), MAX_COMPARE);
      if (inBar) {
        var nextFocus = doc.querySelector('[data-fh-compare-bar] .fh-cbar-slot button, [data-fh-compare-bar] [data-fh-compare-clear]') || doc.getElementById('fh-results') || doc.getElementById('main');
        if (nextFocus) { if (!nextFocus.matches('button, a')) { nextFocus.setAttribute('tabindex', '-1'); } nextFocus.focus({ preventScroll: true }); }
      }
      return;
    }
    if (e.target.closest('[data-fh-compare-clear]')) { write(KEYS.compare, []); refresh(); return; }
    var share = e.target.closest('[data-fh-share]');
    if (share) { sharePage(share); return; }
    var video = e.target.closest('[data-fh-video]');
    if (video) { playVideo(video); return; }
    var open = e.target.closest('[data-fh-open]');
    if (open) { openLightbox(Number(open.getAttribute('data-fh-open'))); return; }
    var reset = e.target.closest('[data-fh-calc-reset]');
    if (reset) { var calc = reset.closest('[data-fh-calc]'); var input = calc.querySelector('[data-fh-calc-input]'); input.value = format(Number(calc.getAttribute('data-price')), 'en', true); recalc(calc); return; }
    if (e.target.closest('[data-fh-to-results]')) { scrollResults = Date.now(); }
  });
  window.addEventListener('storage', refresh);

  /* ---------- Development page: residence filter, floor plans, "Enquire about", sub navigation ---------- */
  doc.addEventListener('click', function (e) {
    if (!e.target.closest) { return; }
    var pick = e.target.closest('[data-fh-units-filter] button');
    if (pick) {
      var state = pick.getAttribute('data-filter');
      pick.parentNode.querySelectorAll('button').forEach(function (b) { b.setAttribute('aria-pressed', b === pick ? 'true' : 'false'); });
      doc.querySelectorAll('[data-fh-units] tbody tr').forEach(function (tr) { tr.hidden = state !== 'all' && tr.getAttribute('data-state') !== state; });
      doc.querySelectorAll('[data-fh-planbox]').forEach(function (box) {
        var row = doc.querySelector('[data-fh-plan="' + box.id.replace('fh-plan-', '') + '"]');
        if (row && row.closest('tr').hidden) { box.hidden = true; row.setAttribute('aria-expanded', 'false'); row.closest('tr').classList.remove('is-open'); }
      });
      return;
    }
    var plan = e.target.closest('[data-fh-plan]');
    if (plan) {
      var box = doc.getElementById('fh-plan-' + plan.getAttribute('data-fh-plan'));
      var opening = plan.getAttribute('aria-expanded') !== 'true';
      doc.querySelectorAll('[data-fh-plan]').forEach(function (b) { b.setAttribute('aria-expanded', 'false'); b.closest('tr').classList.remove('is-open'); });
      doc.querySelectorAll('[data-fh-planbox]').forEach(function (b) { b.hidden = true; });
      if (box && opening) {
        plan.setAttribute('aria-expanded', 'true');
        plan.closest('tr').classList.add('is-open');
        box.hidden = false;
        if (box.getBoundingClientRect().top > window.innerHeight - 120) { box.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }
      }
      return;
    }
    var enquire = e.target.closest('[data-fh-enquire]');
    if (enquire) {
      var select = doc.querySelector('[data-fh-interest-unit]');
      if (select) {
        select.value = enquire.getAttribute('data-fh-enquire');
        select.dispatchEvent(new Event('change', { bubbles: true }));
      }
    }
  });
  var subnavObserver = null;
  function initDevelopment() {
    if (subnavObserver) { subnavObserver.disconnect(); subnavObserver = null; }
    doc.querySelectorAll('[data-fh-units-filter]').forEach(function (el) { el.hidden = false; });
    var subnav = doc.querySelector('[data-fh-subnav]');
    if (!subnav || !window.IntersectionObserver) { return; }
    var links = Array.prototype.slice.call(subnav.querySelectorAll('a'));
    var seen = {};
    var io = subnavObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) { seen[en.target.id] = en.isIntersecting; });
      var current = null;
      links.forEach(function (a) { var id = a.getAttribute('href').slice(1); if (!current && seen[id]) { current = a; } });
      if (current) { links.forEach(function (a) { if (a === current) { a.setAttribute('aria-current', 'true'); } else { a.removeAttribute('aria-current'); } }); }
    }, { rootMargin: '-160px 0px -55% 0px' });
    links.forEach(function (a) { var t = doc.getElementById(a.getAttribute('href').slice(1)); if (t) { io.observe(t); } });
  }

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
      // Links that drive a live component (search tabs, pages): flocms-reactive handles them
      if (a.hasAttribute('data-rx-action') && a.closest('[data-rx-component]')) { return; }
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
      // Never let flocms-reactive's handler reload the page (it would for a video frame's step too)
      e.stopImmediatePropagation();
      var now = plain(new URL(location.href));
      if (now === lastPlain) { return; } // only the #part changed, or a frame inside the page went back
      lastPlain = now;
      navigate(location.href, false, e.state && typeof e.state.y === 'number' ? e.state.y : 0);
    });
    var push = history.pushState;
    var replace = history.replaceState;
    // flocms-reactive pushes a history entry on every action, even when the address doesn't
    // change (opening More filters, sending a form): those would make Back look dead.
    history.pushState = function (state, title, url) {
      var target = url ? plain(new URL(url, location.href)) : lastPlain;
      var r = target === plain(new URL(location.href)) && url !== undefined && !(state && state.fh) ? replace.apply(history, arguments) : push.apply(history, arguments);
      lastPlain = plain(new URL(location.href));
      return r;
    };
    history.replaceState = function () { var r = replace.apply(history, arguments); lastPlain = plain(new URL(location.href)); return r; };
  }

  initPage();
})();
