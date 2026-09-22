/* SQL catalog report: search over the statements, theme toggle, mobile nav. */
(function () {
  'use strict';

  var root = document.body.getAttribute('data-root') || '';
  var input = document.getElementById('search');
  var results = document.getElementById('search-results');
  var selected = -1;

  function items() {
    return window.__CATALOG_INDEX__ || [];
  }

  function score(item, query) {
    var sql = item.q.toLowerCase();
    if (sql.indexOf(query) === 0) { return 0; }
    if (sql.indexOf(query) !== -1) { return 1; }
    if (item.w.toLowerCase().indexOf(query) !== -1) { return 2; }
    if (item.t.toLowerCase().indexOf(query) !== -1) { return 3; }
    return -1;
  }

  function search(query) {
    query = query.trim().toLowerCase();
    if (query === '') { return []; }
    var hits = [];
    var list = items();
    for (var i = 0; i < list.length; i++) {
      var s = score(list[i], query);
      if (s !== -1) { hits.push({ s: s, len: list[i].q.length, item: list[i] }); }
    }
    hits.sort(function (a, b) { return a.s - b.s || a.len - b.len; });
    return hits.slice(0, 40).map(function (h) { return h.item; });
  }

  function esc(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  function renderResults(list, query) {
    if (!results) { return; }
    selected = -1;
    if (query === '') {
      results.hidden = true;
      results.innerHTML = '';
      return;
    }
    if (list.length === 0) {
      results.innerHTML = '<p class="search-empty">No statement matches ' + esc(query) + '.</p>';
      results.hidden = false;
      return;
    }
    var html = '';
    for (var i = 0; i < list.length; i++) {
      var item = list[i];
      html += '<a href="' + root + item.u + '">'
        + '<span class="chip chip-sm k-' + item.g + '">' + esc(item.k) + '</span> '
        + '<span class="chip chip-sm s-' + item.c + '">' + esc(item.r) + '</span>'
        + '<span class="search-hit-sql">' + esc(item.q) + '</span>'
        + '<span class="search-hit-where">' + esc(item.w) + '</span>'
        + '</a>';
    }
    results.innerHTML = html;
    results.hidden = false;
  }

  function moveSelection(delta) {
    if (!results || results.hidden) { return; }
    var links = results.querySelectorAll('a');
    if (links.length === 0) { return; }
    if (selected >= 0) { links[selected].classList.remove('selected'); }
    selected = (selected + delta + links.length) % links.length;
    links[selected].classList.add('selected');
    links[selected].scrollIntoView({ block: 'nearest' });
  }

  if (input) {
    input.addEventListener('input', function () { renderResults(search(input.value), input.value.trim()); });
    input.addEventListener('keydown', function (event) {
      if (event.key === 'ArrowDown') { event.preventDefault(); moveSelection(1); }
      if (event.key === 'ArrowUp') { event.preventDefault(); moveSelection(-1); }
      if (event.key === 'Enter' && results && !results.hidden) {
        var links = results.querySelectorAll('a');
        var target = selected >= 0 ? links[selected] : links[0];
        if (target) { window.location.href = target.getAttribute('href'); }
      }
      if (event.key === 'Escape') { renderResults([], ''); input.blur(); }
    });
  }

  document.addEventListener('keydown', function (event) {
    if (event.key === '/' && input && document.activeElement !== input
      && !/^(input|textarea|select)$/i.test(document.activeElement.tagName)) {
      event.preventDefault();
      input.focus();
      input.select();
    }
  });

  document.addEventListener('click', function (event) {
    if (results && !results.hidden && !results.contains(event.target) && event.target !== input) {
      renderResults([], '');
    }
  });

  var themeToggle = document.getElementById('theme-toggle');
  if (themeToggle) {
    themeToggle.addEventListener('click', function () {
      var current = document.documentElement.dataset.theme
        || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
      var next = current === 'dark' ? 'light' : 'dark';
      document.documentElement.dataset.theme = next;
      try { localStorage.setItem('sql-catalog-theme', next); } catch (error) { /* private mode */ }
    });
  }

  var navToggle = document.getElementById('nav-toggle');
  if (navToggle) {
    navToggle.addEventListener('click', function () {
      document.body.classList.toggle('nav-open');
    });
  }
})();
