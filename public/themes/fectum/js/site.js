/*
 * FECTUM Hellas website (Stage 3a): header over the slider, phone menu,
 * Saved / Compare kept in the visitor's browser (no account, no cookies).
 * Everything works without this file except Save and Compare: without
 * JavaScript the phone menu is a plain list under the logo (no fh-js class).
 */
(function () {
  'use strict';
  var doc = document;
  doc.documentElement.classList.add('fh-js');
  var KEYS = { saved: 'fh-saved', compare: 'fh-compare' };
  var MAX_COMPARE = 3;

  function read(key) {
    try { var v = JSON.parse(localStorage.getItem(key) || '[]'); return Array.isArray(v) ? v.filter(function (n) { return Number.isInteger(n) && n > 0; }) : []; }
    catch (e) { return []; }
  }
  function write(key, list) {
    try { localStorage.setItem(key, JSON.stringify(list)); } catch (e) { /* private mode: keep going */ }
  }

  // Header: solid once the page scrolls (over the home slider it starts transparent)
  var header = doc.querySelector('[data-fh-header]');
  if (header) {
    var overlay = header.classList.contains('is-overlay');
    var onScroll = function () {
      var scrolled = window.scrollY > 24;
      header.classList.toggle('is-scrolled', scrolled);
      if (overlay) { header.classList.toggle('is-solid', scrolled); }
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    var toggle = header.querySelector('[data-fh-menu-toggle]');
    if (toggle) {
      toggle.addEventListener('click', function () {
        var open = !header.classList.contains('is-open');
        header.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
      doc.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && header.classList.contains('is-open')) {
          header.classList.remove('is-open');
          toggle.setAttribute('aria-expanded', 'false');
          toggle.focus();
        }
      });
    }
  }

  // Saved and Compare
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

  // Short messages for screen readers (and a brief note on screen).
  var live = doc.createElement('p');
  live.className = 'fh-toast';
  live.setAttribute('role', 'status');
  live.setAttribute('aria-live', 'polite');
  doc.body.appendChild(live);
  var hide;
  function announce(text) {
    if (!text) { return; }
    live.textContent = text;
    live.classList.add('is-shown');
    clearTimeout(hide);
    hide = setTimeout(function () { live.classList.remove('is-shown'); }, 4000);
  }

  function flip(key, id, max) {
    var list = read(key);
    var at = list.indexOf(id);
    if (at > -1) { list.splice(at, 1); } else if (!max || list.length < max) { list.push(id); }
    write(key, list);
    refresh();
  }

  doc.addEventListener('click', function (e) {
    var save = e.target.closest('[data-fh-save]');
    if (save) { e.preventDefault(); flip(KEYS.saved, Number(save.getAttribute('data-fh-save'))); return; }
    var cmp = e.target.closest('[data-fh-compare]');
    if (cmp) {
      e.preventDefault();
      if (cmp.getAttribute('aria-disabled') === 'true') { announce(cmp.getAttribute('data-fh-full') || ''); return; }
      flip(KEYS.compare, Number(cmp.getAttribute('data-fh-compare')), MAX_COMPARE);
    }
  });

  // Office maps: Leaflet (served by the site) with OpenStreetMap tiles, loaded once.
  var maps = doc.querySelectorAll('[data-fh-map]');
  if (maps.length) {
    var first = maps[0];
    var css = doc.createElement('link');
    css.rel = 'stylesheet';
    css.href = first.getAttribute('data-css');
    doc.head.appendChild(css);
    var js = doc.createElement('script');
    js.src = first.getAttribute('data-js');
    js.onload = function () {
      if (!window.L) { return; }
      maps.forEach(function (el) {
        var lat = parseFloat(el.getAttribute('data-lat'));
        var lng = parseFloat(el.getAttribute('data-lng'));
        if (isNaN(lat) || isNaN(lng)) { return; }
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
    };
    doc.head.appendChild(js);
  }

  window.addEventListener('storage', refresh);
  doc.querySelectorAll('[data-fh-card-actions]').forEach(function (el) { el.hidden = false; });
  refresh();
})();
