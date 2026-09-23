/* SQL catalog report: search, narrowing a listing, sortable tables, theme and mobile navigation. */
(function () {
  'use strict';

  var root = document.body.getAttribute('data-root') || '';

  function esc(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  /* ---- Search over every statement ---- */

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
    if (item.t.toLowerCase().indexOf(query) !== -1) { return 2; }
    if (item.f.toLowerCase().indexOf(query) !== -1) { return 3; }
    if (item.w.toLowerCase().indexOf(query) !== -1) { return 4; }
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
        + (item.r === 'resolved' ? '' : '<span class="chip chip-sm s-' + item.c + '">' + esc(item.r) + '</span> ')
        + '<span class="search-hit-where">' + esc(item.w) + (item.f ? ' · ' + esc(item.f) : '') + '</span>'
        + '<span class="search-hit-sql">' + esc(item.q) + '</span>'
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

  /* ---- Narrowing a listing of statements ---- */

  var FACETS = ['kind', 'resolution', 'severity'];
  var PRESETS = ['namespace', 'class', 'function', 'file', 'table', 'rule', 'sink', 'open'];

  function readQuery() {
    var query = {};
    var pairs = window.location.search.replace(/^\?/, '').split('&');
    for (var i = 0; i < pairs.length; i++) {
      if (pairs[i] === '') { continue; }
      var at = pairs[i].indexOf('=');
      var key = decodeURIComponent(at === -1 ? pairs[i] : pairs[i].slice(0, at));
      var value = at === -1 ? '' : decodeURIComponent(pairs[i].slice(at + 1).replace(/\+/g, ' '));
      query[key] = value;
    }
    return query;
  }

  function writeQuery(state) {
    var pairs = [];
    for (var i = 0; i < FACETS.length; i++) {
      if (state[FACETS[i]].length > 0) { pairs.push(FACETS[i] + '=' + encodeURIComponent(state[FACETS[i]].join(','))); }
    }
    for (var j = 0; j < PRESETS.length; j++) {
      if (state[PRESETS[j]]) { pairs.push(PRESETS[j] + '=' + encodeURIComponent(state[PRESETS[j]])); }
    }
    if (state.q) { pairs.push('q=' + encodeURIComponent(state.q)); }
    var url = window.location.pathname + (pairs.length ? '?' + pairs.join('&') : '') + window.location.hash;
    try { window.history.replaceState(null, '', url); } catch (error) { /* file:// in some browsers */ }
  }

  function matches(row, state) {
    var data = row.dataset;
    for (var i = 0; i < FACETS.length; i++) {
      var wanted = state[FACETS[i]];
      if (wanted.length > 0 && wanted.indexOf(data[FACETS[i]] || '') === -1) { return false; }
    }
    if (state.namespace !== null && state.namespace !== undefined && data.namespace !== state.namespace) { return false; }
    if (state['class'] && data['class'] !== state['class']) { return false; }
    if (state['function'] && data['function'] !== state['function']) { return false; }
    if (state.file && data.file !== state.file) { return false; }
    if (state.sink && data.sink !== state.sink) { return false; }
    if (state.table && (' ' + data.table + ' ').indexOf(' ' + state.table + ' ') === -1) { return false; }
    if (state.rule && (' ' + data.rule + ' ').indexOf(' ' + state.rule + ' ') === -1) { return false; }
    if (state.open && data.open !== 'open') { return false; }
    if (state.q && row.textContent.toLowerCase().indexOf(state.q) === -1) { return false; }
    return true;
  }

  function narrowable(container, fromQuery) {
    var rows = container.querySelectorAll('li.row');
    var chips = container.querySelectorAll('button.facet');
    var text = container.querySelector('.facet-search');
    var shown = container.querySelector('.facet-shown');
    var clear = container.querySelector('.facet-clear');
    var active = container.querySelector('.active-filters');
    var state = { kind: [], resolution: [], severity: [], q: '' };
    var i;

    if (fromQuery) {
      var query = readQuery();
      for (i = 0; i < FACETS.length; i++) {
        if (query[FACETS[i]]) { state[FACETS[i]] = query[FACETS[i]].split(','); }
      }
      for (i = 0; i < PRESETS.length; i++) {
        if (query[PRESETS[i]] !== undefined) { state[PRESETS[i]] = query[PRESETS[i]]; }
      }
      if (query.q) { state.q = query.q.toLowerCase(); if (text) { text.value = query.q; } }
    }

    function presetLabel(key) {
      if (key === 'open') { return 'search left open'; }
      if (key === 'namespace' && state[key] === '') { return 'namespace: (global)'; }
      return key + ': ' + state[key];
    }

    function apply() {
      var kept = 0;
      for (var r = 0; r < rows.length; r++) {
        var keep = matches(rows[r], state);
        rows[r].classList.toggle('is-hidden', !keep);
        if (keep) { kept++; }
      }
      var groups = container.querySelectorAll('.group');
      for (var g = 0; g < groups.length; g++) {
        groups[g].classList.toggle('is-empty', groups[g].querySelectorAll('li.row:not(.is-hidden)').length === 0);
      }
      for (var c = 0; c < chips.length; c++) {
        chips[c].classList.toggle('is-on', state[chips[c].dataset.facet].indexOf(chips[c].dataset.value) !== -1);
      }
      var narrowed = kept !== rows.length;
      if (shown) { shown.textContent = narrowed ? kept + ' of ' + rows.length + ' shown' : ''; }
      if (clear) { clear.hidden = !narrowed; }
      if (active) {
        var html = '';
        for (var p = 0; p < PRESETS.length; p++) {
          if (state[PRESETS[p]] !== undefined && state[PRESETS[p]] !== null) {
            html += '<button type="button" class="chip chip-ghost" data-preset="' + PRESETS[p] + '" title="Remove this narrowing">'
              + esc(presetLabel(PRESETS[p])) + ' ×</button>';
          }
        }
        active.innerHTML = html === '' ? '' : '<span>Narrowed to</span>' + html;
        active.hidden = html === '';
      }
      if (fromQuery) { writeQuery(state); }
    }

    for (i = 0; i < chips.length; i++) {
      chips[i].addEventListener('click', function () {
        var list = state[this.dataset.facet];
        var at = list.indexOf(this.dataset.value);
        if (at === -1) { list.push(this.dataset.value); } else { list.splice(at, 1); }
        apply();
      });
    }
    if (text) {
      text.addEventListener('input', function () { state.q = text.value.trim().toLowerCase(); apply(); });
    }
    if (clear) {
      clear.addEventListener('click', function () {
        state = { kind: [], resolution: [], severity: [], q: '' };
        if (text) { text.value = ''; }
        apply();
      });
    }
    if (active) {
      active.addEventListener('click', function (event) {
        var button = event.target.closest('[data-preset]');
        if (button) { delete state[button.dataset.preset]; apply(); }
      });
    }
    apply();
  }

  var containers = document.querySelectorAll('[data-narrowable]');
  for (var n = 0; n < containers.length; n++) {
    narrowable(containers[n], n === 0);
  }

  /* ---- Narrowing a table of tables, classes or files by name ---- */

  var rowFilter = document.querySelector('.row-filter');
  if (rowFilter) {
    rowFilter.addEventListener('input', function () {
      var query = rowFilter.value.trim().toLowerCase();
      var tables = document.querySelectorAll('.filter-target');
      for (var t = 0; t < tables.length; t++) {
        var trs = tables[t].querySelectorAll('tbody tr');
        var kept = 0;
        for (var r = 0; r < trs.length; r++) {
          var keep = query === '' || trs[r].textContent.toLowerCase().indexOf(query) !== -1;
          trs[r].classList.toggle('is-hidden', !keep);
          if (keep) { kept++; }
        }
        var group = tables[t].closest('.group');
        if (group) { group.classList.toggle('is-empty', kept === 0); }
      }
    });
  }

  /* ---- Sorting a table by a column ---- */

  function cellValue(row, index, numeric) {
    var cell = row.children[index];
    var text = cell ? cell.textContent.trim() : '';
    return numeric ? parseFloat(text.replace(/,/g, '')) || 0 : text.toLowerCase();
  }

  document.addEventListener('click', function (event) {
    var th = event.target.closest('.sortable th[data-sort]');
    if (!th) { return; }
    var table = th.closest('table');
    var index = Array.prototype.indexOf.call(th.parentNode.children, th);
    var numeric = th.dataset.sort === 'num';
    var ascending = !th.classList.contains('is-asc') && (th.classList.contains('is-desc') || !numeric);
    var heads = th.parentNode.querySelectorAll('th');
    for (var h = 0; h < heads.length; h++) { heads[h].classList.remove('is-asc', 'is-desc'); }
    th.classList.add(ascending ? 'is-asc' : 'is-desc');
    var body = table.tBodies[0];
    var rows = Array.prototype.slice.call(body.rows);
    rows.sort(function (a, b) {
      var left = cellValue(a, index, numeric);
      var right = cellValue(b, index, numeric);
      if (left < right) { return ascending ? -1 : 1; }
      if (left > right) { return ascending ? 1 : -1; }
      return 0;
    });
    for (var r = 0; r < rows.length; r++) { body.appendChild(rows[r]); }
  });

  /* ---- Copying the statement ---- */

  var copy = document.querySelector('[data-copy]');
  if (copy) {
    copy.addEventListener('click', function () {
      var source = document.getElementById(copy.dataset.copy);
      if (!source) { return; }
      var done = function () { copy.classList.add('is-done'); copy.textContent = 'Copied'; setTimeout(function () { copy.classList.remove('is-done'); copy.textContent = 'Copy'; }, 1500); };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(source.value).then(done, function () { /* denied */ });
      } else {
        source.hidden = false;
        source.select();
        try { document.execCommand('copy'); done(); } catch (error) { /* unsupported */ }
        source.hidden = true;
      }
    });
  }

  /* ---- Theme and navigation ---- */

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
