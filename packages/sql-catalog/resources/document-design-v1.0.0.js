/*
 * document-design — the optional behaviour layer.
 *
 * Everything the stylesheet describes works without this file. What is here is
 * the handful of behaviours that three separate generators had each written
 * for themselves: a theme toggle, a client-side search, sortable tables, copy
 * buttons, facets, a sidebar that opens on a phone, and a table of contents
 * that tracks the heading you are reading.
 *
 * It is driven entirely by data attributes, so there is no API to learn and
 * nothing to call. Add the attribute, get the behaviour.
 *
 * A classic script on purpose, not a module. These documents are opened from
 * disk as often as they are served, and a module script fails outright on
 * file:// because the origin is opaque. `defer` gives the same ordering
 * guarantee without that cost.
 *
 *   <script src="…/document-design.js" defer></script>
 */
(function () {
  "use strict";

  var root = document.documentElement;
  var THEME_KEY = root.getAttribute("data-dd-theme-key") || "dd-theme";

  // Follow the nearest declared language, including embedded examples. The
  // document language is the same contract used by the typography and captions.
  function localizedLabel(el, english, japanese) {
    var owner = el.closest("[lang]") || root;
    return /^ja(?:-|$)/i.test(owner.getAttribute("lang") || "") ? japanese : english;
  }

  /* localStorage throws outright in a sandboxed frame and in some private
     modes — not merely returning null — so every access is guarded and the
     page is expected to work when it is unavailable. */
  function readStore(key) {
    try { return window.localStorage.getItem(key); } catch (e) { return null; }
  }
  function writeStore(key, value) {
    try {
      if (value === null) window.localStorage.removeItem(key);
      else window.localStorage.setItem(key, value);
    } catch (e) { /* not available; the setting simply does not persist */ }
  }

  /*
   * Everything below is called again on refresh(), which a host like Storybook
   * runs on every render. Document-level listeners must therefore be installed
   * exactly once, and per-element work must not be repeated: bound twice, a
   * nav toggle opened and immediately closed the sidebar, and a theme click
   * advanced two states at a time.
   */
  var installed = false;

  function once(el, flag) {
    if (el.hasAttribute(flag)) return false;
    el.setAttribute(flag, "");
    return true;
  }

  function each(selector, fn, context) {
    var list = (context || document).querySelectorAll(selector);
    for (var i = 0; i < list.length; i++) fn(list[i], i);
  }

  function debounce(fn, ms) {
    var t;
    return function () {
      var args = arguments, self = this;
      clearTimeout(t);
      t = setTimeout(function () { fn.apply(self, args); }, ms);
    };
  }

  /* ---------------------------------------------------------------- theme */

  /*
   * Three states, not two. "auto" is a real answer — it means the document
   * follows the reader's system — and a toggle that only flips light/dark
   * takes that away from them the first time they touch it, with no way back.
   */
  var THEMES = ["auto", "light", "dark"];

  function applyTheme(theme) {
    if (theme === "auto") root.removeAttribute("data-dd-theme");
    else root.setAttribute("data-dd-theme", theme);
    each("[data-dd-theme-toggle]", function (btn) {
      btn.setAttribute("data-dd-theme-state", theme);
      var name = localizedLabel(btn, "Theme: " + theme, "テーマ：" + { auto: "自動", light: "ライト", dark: "ダーク" }[theme]);
      btn.setAttribute("title", name);
      btn.setAttribute("aria-label", name);
    });
  }

  /*
   * The current theme lives in memory; storage is where it is *remembered*,
   * not where it is kept. Reading it back on every click meant that wherever
   * storage throws — a sandboxed frame, a locked-down private mode — every
   * click restarted from "auto" and landed on "light" again, so the toggle
   * appeared dead after the first press.
   */
  var theme = null;

  function currentTheme() {
    if (theme === null) {
      var stored = readStore(THEME_KEY);
      theme = THEMES.indexOf(stored) === -1 ? "auto" : stored;
    }
    return theme;
  }

  function initTheme() {
    applyTheme(currentTheme());
    if (installed) return;
    document.addEventListener("click", function (ev) {
      var btn = ev.target.closest && ev.target.closest("[data-dd-theme-toggle]");
      if (!btn) return;
      var next = THEMES[(THEMES.indexOf(currentTheme()) + 1) % THEMES.length];
      theme = next;
      writeStore(THEME_KEY, next === "auto" ? null : next);
      applyTheme(next);
    });
  }

  /* ------------------------------------------------------------ sidebar */

  /*
   * The collapsed sidebar and the tab strip are both CSS that only makes sense
   * once this file is running: without it, a hidden sidebar is unreachable
   * navigation and a hidden tab panel is unreachable content. Both are gated
   * on a flag set here, so the no-JavaScript rendering stays complete.
   */
  function initNav() {
    function setOpen(open) {
      document.body.classList.toggle("nav-open", open);
      each("[data-dd-nav-toggle]", function (button) {
        button.setAttribute("aria-expanded", String(open));
      });
    }
    /* Only a frame that actually has a working toggle may collapse its
       sidebar. A .doc with navigation and no toggle — a tree in a sidebar with
       no topbar, which the Tree story is — would otherwise hide its navigation
       below 900px with nothing to bring it back. */
    each(".doc", function (doc) {
      if (doc.querySelector("[data-dd-nav-toggle]")) doc.setAttribute("data-dd-nav-ready", "");
      else doc.removeAttribute("data-dd-nav-ready");
    });

    if (installed) return;
    document.addEventListener("click", function (ev) {
      var btn = ev.target.closest && ev.target.closest("[data-dd-nav-toggle]");
      if (btn) {
        setOpen(!document.body.classList.contains("nav-open"));
        if (document.body.classList.contains("nav-open")) {
          var sidebar = document.getElementById(btn.getAttribute("aria-controls"));
          var first = sidebar && sidebar.querySelector("a, button, input");
          if (first) first.focus();
        }
        return;
      }
      /* Tapping the page behind an open sidebar closes it, which is what a
         reader expects from an overlay and saves them aiming at the toggle. */
      if (document.body.classList.contains("nav-open") &&
          !(ev.target.closest && ev.target.closest(".sidebar"))) {
        setOpen(false);
      }
    });
    document.addEventListener("keydown", function (ev) {
      if (ev.key !== "Escape" || !document.body.classList.contains("nav-open")) return;
      setOpen(false);
      var toggle = document.querySelector("[data-dd-nav-toggle]");
      if (toggle) toggle.focus();
    });
  }

  /* --------------------------------------------------------------- copy */

  function initCopy() {
    if (installed) return;
    document.addEventListener("click", function (ev) {
      var btn = ev.target.closest && ev.target.closest("[data-dd-copy]");
      if (!btn) return;
      var sel = btn.getAttribute("data-dd-copy");
      var source = sel
        ? document.querySelector(sel)
        : (btn.closest(".code-block") || btn.parentNode).querySelector("pre, code");
      if (!source) return;

      var text = source.innerText;
      var done = function () {
        var label = btn.textContent;
        btn.classList.add("is-done");
        btn.textContent = localizedLabel(btn, "Copied", "コピーしました");
        setTimeout(function () {
          btn.classList.remove("is-done");
          btn.textContent = label;
        }, 1200);
      };

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(done, function () { fallbackCopy(text, done); });
      } else {
        fallbackCopy(text, done);
      }
    });
  }

  /* navigator.clipboard is unavailable on file:// and on plain http, which is
     exactly where these documents get opened. */
  function fallbackCopy(text, done) {
    var ta = document.createElement("textarea");
    ta.value = text;
    ta.setAttribute("readonly", "");
    ta.style.cssText = "position:fixed;top:-1000px;opacity:0";
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand("copy"); done(); } catch (e) { /* nothing to offer */ }
    document.body.removeChild(ta);
  }

  /* -------------------------------------------------------------- sort */

  function cellValue(row, index) {
    var cell = row.cells[index];
    if (!cell) return "";
    var explicit = cell.getAttribute("data-dd-value");
    return explicit !== null ? explicit : cell.innerText.trim();
  }

  function compare(a, b) {
    /* Numeric when both sides are numeric, so "9" sorts under "10" rather
       than after it; text otherwise, compared in the reader's locale. */
    var na = parseFloat(a.replace(/[,\s]/g, ""));
    var nb = parseFloat(b.replace(/[,\s]/g, ""));
    var aNum = !isNaN(na) && /^[\d.,\s+-]+$/.test(a);
    var bNum = !isNaN(nb) && /^[\d.,\s+-]+$/.test(b);
    if (aNum && bNum) return na - nb;
    return a.localeCompare(b, undefined, { numeric: true, sensitivity: "base" });
  }

  function initSort() {
    each("table[data-dd-sortable]", function (table) {
      if (!once(table, "data-dd-sort-bound")) return;
      each("th[data-dd-sort]", function (th, index) {
        th.setAttribute("tabindex", "0");
        th.setAttribute("role", "button");
        var run = function () {
          var body = table.tBodies[0];
          if (!body) return;
          var headIndex = Array.prototype.indexOf.call(th.parentNode.cells, th);
          var desc = th.classList.contains("is-asc");
          each("th[data-dd-sort]", function (other) {
            other.classList.remove("is-asc", "is-desc");
            other.removeAttribute("aria-sort");
          }, table);
          th.classList.add(desc ? "is-desc" : "is-asc");
          th.setAttribute("aria-sort", desc ? "descending" : "ascending");
          var rows = Array.prototype.slice.call(body.rows);
          rows.sort(function (x, y) {
            var r = compare(cellValue(x, headIndex), cellValue(y, headIndex));
            return desc ? -r : r;
          });
          rows.forEach(function (r) { body.appendChild(r); });
        };
        th.addEventListener("click", run);
        th.addEventListener("keydown", function (ev) {
          if (ev.key === "Enter" || ev.key === " ") { ev.preventDefault(); run(); }
        });
      }, table);
    });
  }

  /* ------------------------------------------------------------- filter */

  function textOf(el) {
    var cached = el.getAttribute("data-dd-text");
    if (cached !== null) return cached;
    var t = el.innerText.toLowerCase();
    el.setAttribute("data-dd-text", t);
    return t;
  }

  function initFilter() {
    each("[data-dd-filter]", function (input) {
      if (!once(input, "data-dd-filter-bound")) return;
      var target = document.querySelector(input.getAttribute("data-dd-filter"));
      if (!target) return;
      var rows = target.tagName === "TABLE"
        ? (target.tBodies[0] ? target.tBodies[0].rows : [])
        : target.children;
      input.addEventListener("input", debounce(function () {
        var q = input.value.trim().toLowerCase();
        var shown = 0;
        for (var i = 0; i < rows.length; i++) {
          var hit = !q || textOf(rows[i]).indexOf(q) !== -1;
          rows[i].classList.toggle("is-hidden", !hit);
          if (hit) shown++;
        }
        report(input, shown, rows.length);
        toggleEmpty(optional(input, "data-dd-empty"), shown === 0);
      }, 80));
    });
  }

  /* An optional selector is optional. `querySelector("")` is a SyntaxError,
     not a null — so a filter without a data-dd-empty target threw on every
     keystroke, after updating the rows but before reporting the count. */
  function optional(el, attr) {
    var sel = el.getAttribute(attr);
    if (!sel) return null;
    try { return document.querySelector(sel); } catch (e) { return null; }
  }

  function toggleEmpty(target, isEmpty) {
    if (target) target.hidden = !isEmpty;
  }

  function report(scopeEl, shown, total) {
    var box = scopeEl.closest("[data-dd-facets], .facets, .content, body");
    var out = box && box.querySelector(".facet-shown");
    if (out) out.textContent = shown === total ? total + "" : shown + " / " + total;
  }

  /* ------------------------------------------------------------- facets */

  /*
   * Facets within one group are an OR — picking SELECT and INSERT means
   * either. Across groups they are an AND — a SELECT that is also flagged.
   * That is what a reader means by ticking two boxes, and the opposite
   * convention makes every second click empty the listing.
   */
  function initFacets() {
    each("[data-dd-facets]", function (bar) {
      if (!once(bar, "data-dd-facets-bound")) return;
      var target = document.querySelector(bar.getAttribute("data-dd-facets"));
      if (!target) return;
      var rows = target.children;
      var search = bar.querySelector("[data-dd-facet-search]");

      function apply() {
        var groups = {};
        each("[data-dd-facet].is-on", function (btn) {
          var parts = btn.getAttribute("data-dd-facet").split(":");
          var key = parts.shift();
          (groups[key] = groups[key] || []).push(parts.join(":"));
        }, bar);

        var q = search ? search.value.trim().toLowerCase() : "";
        var shown = 0;
        for (var i = 0; i < rows.length; i++) {
          var row = rows[i];
          var hit = true;
          for (var key in groups) {
            if (!Object.prototype.hasOwnProperty.call(groups, key)) continue;
            var value = row.getAttribute("data-dd-" + key) || "";
            var values = value.split(/\s+/);
            var any = groups[key].some(function (want) { return values.indexOf(want) !== -1; });
            if (!any) { hit = false; break; }
          }
          if (hit && q) hit = textOf(row).indexOf(q) !== -1;
          row.classList.toggle("is-hidden", !hit);
          if (hit) shown++;
        }

        /* A group heading with nothing left under it is noise. */
        each(".group", function (group) {
          var live = group.querySelectorAll(".row:not(.is-hidden), tr:not(.is-hidden)");
          group.classList.toggle("is-empty", live.length === 0);
        }, target.parentNode || document);

        var out = bar.querySelector(".facet-shown");
        if (out) out.textContent = shown === rows.length ? rows.length + "" : shown + " / " + rows.length;

        /*
         * Zero matches is an answer, and it needs saying. Hiding every row and
         * leaving a blank column makes the reader wonder whether the page
         * failed; the empty state says which filters did it and offers the way
         * back. Markup: an element with [data-dd-empty] next to the listing.
         */
        toggleEmpty(
          optional(bar, "data-dd-empty") || document.querySelector("[data-dd-empty]"),
          shown === 0
        );

        syncUrl(bar, groups);
      }

      /* The URL is read first: initialising aria-pressed before restoring the
         selection announced "not pressed" for facets that were visibly on. */
      readUrl(bar);

      each("[data-dd-facet]", function (btn) {
        btn.setAttribute("aria-pressed", btn.classList.contains("is-on") ? "true" : "false");
        btn.addEventListener("click", function () {
          btn.classList.toggle("is-on");
          btn.setAttribute("aria-pressed", btn.classList.contains("is-on") ? "true" : "false");
          apply();
        });
      }, bar);

      function clearAll() {
        each("[data-dd-facet]", function (f) {
          f.classList.remove("is-on");
          f.setAttribute("aria-pressed", "false");
        }, bar);
        if (search) search.value = "";
        apply();
      }

      each("[data-dd-facet-clear]", function (btn) {
        btn.addEventListener("click", clearAll);
      }, bar);

      /*
       * The empty state's own recovery button is outside the bar — it sits
       * with the listing it is explaining — so binding only within the bar
       * left the one control a stranded reader would actually reach doing
       * nothing at all.
       */
      var emptyBox = optional(bar, "data-dd-empty") || document.querySelector("[data-dd-empty]");
      if (emptyBox) {
        each("[data-dd-facet-clear]", function (btn) {
          btn.addEventListener("click", clearAll);
        }, emptyBox);
      }

      if (search) search.addEventListener("input", debounce(apply, 80));

      apply();
    });
  }

  /* The current narrowing lives in the query string, so a filtered listing is
     a link someone can send. */
  function syncUrl(bar, groups) {
    if (bar.getAttribute("data-dd-facet-url") === "off") return;
    var params = new URLSearchParams();
    for (var key in groups) {
      if (Object.prototype.hasOwnProperty.call(groups, key)) params.set(key, groups[key].join(","));
    }
    var qs = params.toString();
    try {
      history.replaceState(null, "", qs ? "?" + qs + location.hash : location.pathname + location.hash);
    } catch (e) { /* file:// refuses replaceState; the filter still works */ }
  }

  function readUrl(bar) {
    var params;
    try { params = new URLSearchParams(location.search); } catch (e) { return; }
    params.forEach(function (value, key) {
      value.split(",").forEach(function (one) {
        var btn = bar.querySelector('[data-dd-facet="' + key + ":" + one + '"]');
        if (btn) btn.classList.add("is-on");
      });
    });
  }

  /* ------------------------------------------------------------- search */

  /*
   * The index is the page's, not ours: only the generator knows what is worth
   * finding. Provide window.ddSearchIndex as an array of
   *   { name, where, body, href }
   * or set window.ddSearch to a function (query) -> those objects.
   */
  function initSearch() {
    var input = document.querySelector("[data-dd-search]");
    if (!input || !once(input, "data-dd-search-bound")) return;
    var panel = document.querySelector("[data-dd-search-results], #search-results");
    if (!panel) return;

    var selected = -1;

    function provider(q) {
      if (typeof window.ddSearch === "function") return window.ddSearch(q);
      var index = window.ddSearchIndex || [];
      var needle = q.toLowerCase();

      /*
       * Rank first, truncate second.
       *
       * This used to stop collecting at fifty and sort afterwards, so fifty
       * incidental mentions in statement bodies could push the exact symbol
       * the reader typed out of the results entirely — on a 542-symbol
       * reference, searching for a real name and being told it does not exist.
       * The cap is on what is shown, not on what is considered.
       */
      var named = [];
      var other = [];
      for (var i = 0; i < index.length; i++) {
        var item = index[i];
        var name = (item.name || "").toLowerCase();
        if (name.indexOf(needle) !== -1) {
          /* An exact name beats a name that merely contains it. */
          named.push([name === needle ? 0 : 1, name.indexOf(needle), item]);
        } else {
          var rest = ((item.where || "") + " " + (item.body || "")).toLowerCase();
          if (rest.indexOf(needle) !== -1) other.push(item);
        }
      }
      named.sort(function (a, b) { return a[0] - b[0] || a[1] - b[1]; });
      return named.map(function (n) { return n[2]; }).concat(other).slice(0, 50);
    }

    function close() { panel.hidden = true; selected = -1; }

    function render(results) {
      panel.textContent = "";
      if (!results.length) {
        var empty = document.createElement("div");
        empty.className = "search-empty";
        empty.textContent = localizedLabel(input, "Nothing matched.", "該当する項目はありません。");
        panel.appendChild(empty);
      } else {
        results.forEach(function (item) {
          var a = document.createElement("a");
          a.href = item.href || "#";
          var name = document.createElement("span");
          name.className = "hit-name";
          name.textContent = item.name || "";
          a.appendChild(name);
          if (item.where) {
            a.appendChild(document.createTextNode(" "));
            var where = document.createElement("span");
            where.className = "hit-where";
            where.textContent = item.where;
            a.appendChild(where);
          }
          if (item.body) {
            var body = document.createElement("span");
            body.className = "hit-body";
            body.textContent = item.body;
            a.appendChild(body);
          }
          panel.appendChild(a);
        });
      }
      panel.hidden = false;
      selected = -1;
    }

    input.addEventListener("input", debounce(function () {
      var q = input.value.trim();
      if (q.length < 2) { close(); return; }
      render(provider(q));
    }, 100));

    input.addEventListener("keydown", function (ev) {
      var hits = panel.querySelectorAll("a");
      if (ev.key === "Escape") { close(); input.blur(); return; }
      if (!hits.length) return;
      if (ev.key === "ArrowDown" || ev.key === "ArrowUp") {
        ev.preventDefault();
        selected += ev.key === "ArrowDown" ? 1 : -1;
        if (selected < 0) selected = hits.length - 1;
        if (selected >= hits.length) selected = 0;
        for (var i = 0; i < hits.length; i++) hits[i].classList.toggle("is-selected", i === selected);
        hits[selected].scrollIntoView({ block: "nearest" });
      } else if (ev.key === "Enter" && selected >= 0) {
        ev.preventDefault();
        hits[selected].click();
      }
    });

    document.addEventListener("click", function (ev) {
      if (ev.target !== input && !(ev.target.closest && ev.target.closest("[data-dd-search-results], #search-results"))) close();
    });

    /* "/" focuses search, the convention every documentation site shares —
       but not while the reader is typing into something else. */
    document.addEventListener("keydown", function (ev) {
      if (ev.key !== "/" || ev.metaKey || ev.ctrlKey || ev.altKey) return;
      var el = document.activeElement;
      if (el && (el.tagName === "INPUT" || el.tagName === "TEXTAREA" || el.isContentEditable)) return;
      ev.preventDefault();
      input.focus();
      input.select();
    });
  }

  /* --------------------------------------------------------------- tabs */

  /*
   * Tabs. Arrow keys move between them, which is what the tab pattern says
   * and what a reader who navigates by keyboard expects; only the selected
   * tab is in the tab order, so Tab leaves the strip rather than walking
   * every view of the same thing.
   */
  function initTabs() {
    each("[data-dd-tabs]", function (root) {
      if (!once(root, "data-dd-tabs-bound")) return;
      var all = [].slice.call(root.querySelectorAll('[role="tab"]'));
      /* A disabled tab is neither reachable by arrow key nor selectable, but
         it stays in the strip so the reader can see the view exists. */
      var tabs = all.filter(function (t) { return !t.disabled; });
      if (!tabs.length) return;
      root.setAttribute("data-dd-tabs-ready", "");

      function select(tab) {
        if (!tab || tab.disabled) return;
        all.forEach(function (t) {
          var on = t === tab;
          t.setAttribute("aria-selected", on ? "true" : "false");
          t.tabIndex = on ? 0 : -1;
          var panel = document.getElementById(t.getAttribute("aria-controls"));
          if (panel) panel.hidden = !on;
        });
      }

      /* Panels ship visible so that they are readable without this script;
         collapsing them is the first thing the script does. */
      var first = tabs.filter(function (t) { return t.getAttribute("aria-selected") === "true"; })[0] || tabs[0];
      select(first);

      tabs.forEach(function (tab, i) {
        tab.tabIndex = tab.getAttribute("aria-selected") === "true" ? 0 : -1;
        tab.addEventListener("click", function () { select(tab); });
        tab.addEventListener("keydown", function (ev) {
          var next = null;
          if (ev.key === "ArrowRight") next = tabs[(i + 1) % tabs.length];
          else if (ev.key === "ArrowLeft") next = tabs[(i - 1 + tabs.length) % tabs.length];
          else if (ev.key === "Home") next = tabs[0];
          else if (ev.key === "End") next = tabs[tabs.length - 1];
          if (!next) return;
          ev.preventDefault();
          select(next);
          next.focus();
        });
      });
    });
  }

  /* ------------------------------------------------------------- to top */

  function initToTop() {
    var btn = document.querySelector("[data-dd-to-top]");
    if (!btn || !once(btn, "data-dd-to-top-bound")) return;
    btn.addEventListener("click", function () {
      window.scrollTo({ top: 0, behavior: matchMedia("(prefers-reduced-motion: reduce)").matches ? "auto" : "smooth" });
    });
    var onScroll = function () {
      btn.classList.toggle("is-shown", window.scrollY > window.innerHeight);
    };
    /* Passive: this runs on every scroll frame and never calls preventDefault,
       and saying so is what keeps it off the main thread's critical path. */
    window.addEventListener("scroll", onScroll, { passive: true });
    onScroll();
  }

  /* ---------------------------------------------------------------- toc */

  /*
   * Which heading you are reading, marked in the sidebar. Uses an observer
   * with a band near the top rather than a scroll handler: a scroll handler
   * runs on every frame of a flick through a long catalog page, and this runs
   * only when a heading crosses the band.
   */
  var tocObserver = null;

  function initToc() {
    var toc = document.querySelector("[data-dd-toc], .sidebar-context");
    if (!toc || !("IntersectionObserver" in window)) return;
    /* A stale observer keeps reporting on a document the story has replaced. */
    if (tocObserver) { tocObserver.disconnect(); tocObserver = null; }

    var links = {};
    var targets = [];
    each("a[href^='#']", function (a) {
      var id = decodeURIComponent(a.getAttribute("href").slice(1));
      var target = id && document.getElementById(id);
      if (!target) return;
      links[id] = a;
      targets.push(target);
    }, toc);
    if (!targets.length) return;

    var visible = new Set();
    var observer = tocObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) visible.add(entry.target.id);
        else visible.delete(entry.target.id);
      });
      var first = targets.filter(function (t) { return visible.has(t.id); })[0];
      if (!first) return;
      for (var id in links) {
        if (!Object.prototype.hasOwnProperty.call(links, id)) continue;
        var li = links[id].closest("li") || links[id];
        li.classList.toggle("is-active", id === first.id);
      }
    }, { rootMargin: "-" + (56) + "px 0px -70% 0px", threshold: 0 });

    targets.forEach(function (t) { observer.observe(t); });
  }

  /* -------------------------------------------------------------- print */

  /*
   * A print is a record of the whole document, and a closed <details> is not
   * part of one by default: the UA hides its content with content-visibility,
   * which no amount of print CSS on the children can undo. So every closed one
   * is opened for the duration of the print and put back afterwards — the
   * reader's page is unchanged when the dialog closes.
   */
  function initPrint() {
    if (installed) return;
    var opened = [];

    var expanded = false;

    function expand() {
      /* Both `beforeprint` and the print media query fire in some engines. The
         second call used to find everything already open and overwrite the
         restore list with an empty one, so disclosures the reader had closed
         stayed open after the dialog closed. */
      if (expanded) return;
      expanded = true;
      opened = [];
      each("details:not([open])", function (d) {
        opened.push(d);
        d.open = true;
      });
      /* An inactive tab panel is content too; the tab strip that labelled it
         is not printed, so each panel's own heading carries the label. */
      each(".tabpanel[hidden]", function (p) {
        p.hidden = false;
        p.setAttribute("data-dd-print-shown", "");
      });
    }

    function restore() {
      expanded = false;
      opened.forEach(function (d) { d.open = false; });
      opened = [];
      each("[data-dd-print-shown]", function (p) {
        p.hidden = true;
        p.removeAttribute("data-dd-print-shown");
      });
    }

    if (window.matchMedia) {
      var mq = window.matchMedia("print");
      var onChange = function (e) { (e.matches ? expand : restore)(); };
      if (mq.addEventListener) mq.addEventListener("change", onChange);
    }
    window.addEventListener("beforeprint", expand);
    window.addEventListener("afterprint", restore);
  }

  /* -------------------------------------------------------------- start */

  function start() {
    initTheme();
    initNav();
    initCopy();
    initSort();
    initFilter();
    initFacets();
    initSearch();
    initTabs();
    initToTop();
    initToc();
    initPrint();
    each("[data-dd-enhance]", function (el) { el.hidden = false; });
    installed = true;
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", start);
  } else {
    start();
  }

  /* One escape hatch, for a page that builds part of itself. */
  window.documentDesign = {
    refresh: start,
    applyTheme: function (t) { theme = t; applyTheme(t); },
  };
})();
