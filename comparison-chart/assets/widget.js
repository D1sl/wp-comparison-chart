/*!
 * Comparison Chart Widget
 * Adapted from skincare-comparison-mobile-perf2.html
 * Class prefix: sdb-sc-  |  Config: <script id="{mountId}-cfg" type="application/json">
 */
(function () {
  "use strict";

  // ── Helpers ───────────────────────────────────────────────────────────────
  function esc(s) {
    return String(s == null ? "" : s)
      .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;").replace(/'/g, "&#39;");
  }
  function el(tag, cls, html) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (html != null) n.innerHTML = html;
    return n;
  }

  // Read a CSS custom property from a root element, falling back to a default.
  function cssVar(root, prop, fallback) {
    var v = getComputedStyle(root).getPropertyValue(prop).trim();
    return v || fallback;
  }

  // ── Colour helpers ────────────────────────────────────────────────────────

  // Parse any CSS colour string the browser has already resolved into an RGB(A)
  // value by painting it onto a temporary canvas pixel. Returns [r,g,b] 0-255
  // or null if the value is empty / invalid.
  function parseColour(str) {
    str = (str || "").trim();
    if (!str) return null;
    var cv = document.createElement("canvas");
    cv.width = cv.height = 1;
    var ctx = cv.getContext("2d");
    ctx.clearRect(0, 0, 1, 1);
    ctx.fillStyle = "#000"; // sentinel
    ctx.fillStyle = str;
    // If the browser rejected the colour, fillStyle stays "#000"
    // Use the painted pixel instead to handle all formats including var()
    ctx.fillRect(0, 0, 1, 1);
    var d = ctx.getImageData(0, 0, 1, 1).data;
    return [d[0], d[1], d[2]];
  }

  // Darken an already-resolved colour by mixing it with black (amount 0-1).
  function darken(str, amount) {
    var rgb = parseColour(str);
    if (!rgb) return str;
    var r = Math.round(rgb[0] * (1 - amount));
    var g = Math.round(rgb[1] * (1 - amount));
    var b = Math.round(rgb[2] * (1 - amount));
    return "rgb(" + r + "," + g + "," + b + ")";
  }

  // ── Cell renderers (extend via window.SDB_SC_RENDERERS) ──────────────────
  var RENDERERS = window.SDB_SC_RENDERERS || {};

  RENDERERS.text = function (v, ctx) {
    var variant = ctx.variant ? " " + ctx.variant : "";
    return '<span class="sdb-sc-text' + variant + '">' + esc(v) + '</span>';
  };
  RENDERERS.pills = function (v, ctx) {
    var items = Array.isArray(v) ? v : [];
    var bg    = ctx.accent ? 'background:' + ctx.accent + '1a;color:' + ctx.accent + ';' : '';
    return '<span class="sdb-sc-pills">' +
      items.map(function (t) { return '<span class="sdb-sc-pill" style="' + bg + '">' + esc(t) + '</span>'; }).join("") +
      '</span>';
  };
  RENDERERS.rating = function (v, ctx) {
    var max = ctx.max || 5, val = Number(v) || 0, html = "";
    for (var i = 0; i < max; i++) {
      var on = i < val;
      if (ctx.icon === "star") {
        html += '<span class="sdb-sc-star" style="color:' +
          (on ? ctx.accent : "rgba(0,0,0,0.15)") + '">✦</span>';
      } else {
        html += '<span class="sdb-sc-dot" style="background:' +
          (on ? ctx.accent : "rgba(0,0,0,0.13)") + '"></span>';
      }
    }
    var dots = '<span class="sdb-sc-rating">' + html + '</span>';
    if (ctx.descriptor) {
      return '<span class="sdb-sc-rating-wrap">' + dots +
        '<span class="sdb-sc-rating-descriptor">' + esc(ctx.descriptor) + '</span></span>';
    }
    return dots;
  };
  RENDERERS.meter = function (v, ctx) {
    var max = ctx.max || 5, val = Number(v) || 0;
    var pct = Math.max(0, Math.min(1, val / max)) * 100;
    return '<span class="sdb-sc-meter">' +
      '<span class="sdb-sc-meter-track"><span class="sdb-sc-meter-fill" style="width:' + pct +
        '%;background:' + ctx.accent + '"></span></span>' +
      '<span class="sdb-sc-meter-label">' + val + '/' + max + '</span></span>';
  };
  RENDERERS.bool = function (v, ctx) {
    var yesText = (ctx.yes_label && ctx.yes_label.trim()) || "Yes";
    var noText  = (ctx.no_label  && ctx.no_label.trim())  || "No";
    if (v) {
      return '<span class="sdb-sc-bool yes" style="color:' + ctx.accent + '">' +
        '<span class="mark" style="background:' + ctx.accent + ';color:#fff">✓</span>' + esc(yesText) + '</span>';
    }
    return '<span class="sdb-sc-bool no">' +
      '<span class="mark mark-no" style="border:1.5px solid rgba(0,0,0,0.15)"></span>' + esc(noText) + '</span>';
  };

  // ── Config resolution ─────────────────────────────────────────────────────
  // Supports multiple independent instances on the same page.
  // Each mount element has a paired <script id="{mountId}-cfg"> tag.
  function loadConfigForNode(node) {
    var id     = node.id;
    var cfgTag = id ? document.getElementById(id + "-cfg") : null;
    if (cfgTag) {
      try { return JSON.parse(cfgTag.textContent); } catch (e) {}
    }
    // Legacy single-instance fallback
    if (window.SDB_SC_Config) return window.SDB_SC_Config;
    return null;
  }

  // ── Mount widget into a root element ─────────────────────────────────────
  function mount(root, cfg) {
    var COL_W       = cfg.columnWidth || 188;
    var LABEL_W     = cfg.labelWidth  || 156;
    var EASE        = 0.24;
    var CURRENT     = cfg.currentServiceId || null;
    var HAS_CURRENT = !!CURRENT;
    var BASE_X      = LABEL_W + (HAS_CURRENT ? COL_W : 0);
    var STORE       = (cfg.storageKey || "sdb-svc") + ":" +
                      (cfg.services || []).map(function (s) { return s.id; }).join(",");

    var SERVICES = cfg.services || [];
    var ROWS     = cfg.rows     || [];
    var allIds   = SERVICES.map(function (s) { return s.id; });
    var svcById  = {};
    SERVICES.forEach(function (s) { svcById[s.id] = s; });

    // ── Colour system ─────────────────────────────────────────────────────
    // Everything derives from two inputs: primary and secondary (optional).
    // parseColour() resolves any CSS value (including var() chains) via canvas.

    var primaryRaw   = cssVar(root, "--sc-primary",   "#2563eb");
    var secondaryRaw = cssVar(root, "--sc-secondary",  "");
    var inkRaw       = cssVar(root, "--sc-ink",        "#1f2933");

    var primary   = parseColour(primaryRaw)   || [37, 99, 235];
    var secondary = parseColour(secondaryRaw) || primary;  // falls back to primary
    var ink       = parseColour(inkRaw)       || [26, 26, 46];

    // Helpers to emit rgba strings from [r,g,b] arrays
    function rgb(c)        { return "rgb("  + c[0] + "," + c[1] + "," + c[2] + ")"; }
    function rgba(c, a)    { return "rgba(" + c[0] + "," + c[1] + "," + c[2] + "," + a + ")"; }
    function mix(c, d, t)  { // linear interpolate two [r,g,b] arrays by t (0=c, 1=d)
      return [
        Math.round(c[0] + (d[0]-c[0])*t),
        Math.round(c[1] + (d[1]-c[1])*t),
        Math.round(c[2] + (d[2]-c[2])*t)
      ];
    }
    var black = [0, 0, 0];
    var white = [255, 255, 255];

    // Derived colours
    var accentCurrent = rgb(primary);                   // pinned column header top
    var accentCompare = rgb(mix(primary, black, 0.45)); // compare column header top (darker)
    var secondary_c   = rgb(secondary);                 // pills, bool, meter, rating

    // Row backgrounds: very light primary tint
    var rowEven     = rgba(mix(primary, white, 0.96), 0.97);
    var rowOdd      = rgba(mix(primary, white, 0.92), 0.90);
    var rowCurrEven = rgba(mix(primary, white, 0.94), 0.95);
    var rowCurrOdd  = rgba(mix(primary, white, 0.90), 0.90);

    // Borders: primary at low opacity
    var borderRow    = rgba(primary, 0.12);
    var borderCol    = rgba(primary, 0.10);
    var borderPinned = rgba(primary, 0.30);

    function clamp(v, a, b) { return Math.max(a, Math.min(b, v)); }
    function normalize(arr) {
      if (!HAS_CURRENT) return arr.slice();
      return [CURRENT].concat(arr.filter(function (x) { return x !== CURRENT; }));
    }

    // ── Persisted state ───────────────────────────────────────────────────
    var order, enabled;
    (function load() {
      try {
        var p = JSON.parse(localStorage.getItem(STORE) || "null");
        if (p) {
          order = (p.order || allIds).filter(function (id) { return allIds.indexOf(id) !== -1; });
          allIds.forEach(function (id) { if (order.indexOf(id) === -1) order.push(id); });
          enabled = {};
          allIds.forEach(function (id) { enabled[id] = true; });
          if (p.enabled) Object.keys(p.enabled).forEach(function (k) { enabled[k] = p.enabled[k]; });
          order = normalize(order);
          return;
        }
      } catch (e) {}
      order   = normalize(allIds.slice());
      enabled = {};
      allIds.forEach(function (id) { enabled[id] = true; });
    }());

    function persist() {
      try { localStorage.setItem(STORE, JSON.stringify({ order: order, enabled: enabled })); } catch (e) {}
    }

    // ── Animation engine ──────────────────────────────────────────────────
    var colEls = {}, pos = {}, targets = {};
    var currCol = null;
    var dragId = null, draggedX = null, dragGrabOffset = 0;
    var lastClientX = 0, blockTouch = null;
    var dragWrapLeft = 0, dragScrollRect = null;
    var raf = 0, running = false, animating = {};

    function visibleDraggable() {
      return order.filter(function (id) { return id !== CURRENT && enabled[id]; });
    }
    function computeTargets() {
      var t   = {};
      var vis = visibleDraggable();
      vis.forEach(function (id, i) { t[id] = BASE_X + i * COL_W; });
      if (dragId != null && draggedX != null) t[dragId] = draggedX;
      order.forEach(function (id) {
        if (id === CURRENT || enabled[id]) return;
        var before = order.slice(0, order.indexOf(id))
          .filter(function (x) { return x !== CURRENT && enabled[x]; }).length;
        t[id] = BASE_X + before * COL_W;
      });
      targets = t;
    }

    function tick() {
      if (dragId != null && dragScrollRect) {
        var sr = dragScrollRect;
        var EDGE = 56, MAX_SPEED = 18, sx = 0;
        if (lastClientX < sr.left + EDGE) {
          sx = -((sr.left + EDGE - lastClientX) / EDGE) * MAX_SPEED;
        } else if (lastClientX > sr.right - EDGE) {
          sx = ((lastClientX - (sr.right - EDGE)) / EDGE) * MAX_SPEED;
        }
        if (sx !== 0) {
          var before = scroll.scrollLeft;
          scroll.scrollLeft += sx;
          var moved = scroll.scrollLeft - before;
          if (moved !== 0) {
            dragWrapLeft -= moved;
            updateDragFromPointer(lastClientX);
          }
        }
      }

      var moving = false;
      for (var i = 0; i < allIds.length; i++) {
        var id  = allIds[i];
        if (id === CURRENT) continue;
        var elc = colEls[id];
        if (!elc) continue;
        var target = targets[id] != null ? targets[id] : (pos[id] != null ? pos[id] : BASE_X);
        var p      = pos[id] == null ? target : pos[id];
        if (id === dragId) {
          p = target;
        } else {
          var d = target - p;
          if (Math.abs(d) > 0.3) { p += d * EASE; moving = true; }
          else p = target;
        }
        pos[id] = p;
        elc.style.transform = "translateX(" + p + "px)";
      }
      if (moving || dragId != null) raf = requestAnimationFrame(tick);
      else running = false;
    }
    function startLoop() { if (running) return; running = true; raf = requestAnimationFrame(tick); }

    // ── DOM build ──────────────────────────────────────────────────────────
    // Preserve any inline style vars set by the Bricks element, then append
    // the layout tokens.
    root.classList.add("sdb-sc-root");
    root.style.setProperty("--sc-col-w",   COL_W   + "px");
    root.style.setProperty("--sc-label-w", LABEL_W + "px");
    // Expose derived colours as CSS vars so CSS-only rules (toggle buttons etc.) can use them
    root.style.setProperty("--sc-primary-rgb",      primary.join(","));
    root.style.setProperty("--sc-primary-dark-rgb",  mix(primary, black, 0.45).join(","));
    root.style.setProperty("--sc-secondary-rgb",     secondary.join(","));
    root.innerHTML = "";

    // Toggle buttons (no label — build your own heading above this element)
    var toggles = el("div", "sdb-sc-toggles");
    var btnEls = {};
    order.forEach(function (id) {
      var svc = svcById[id];
      var b   = el("button", "sdb-sc-btn");
      // Current service button: prefix with indicator label (or star if none set)
      if (id === CURRENT) {
        b.innerHTML = '<span style="margin-right:6px;font-size:11px;opacity:.9">&#10022;</span>' + esc(svc.name);
      } else {
        b.innerHTML = esc(svc.name);
      }
      b.addEventListener("click", function () { toggle(id); });
      // Hover: highlight the matching column and scroll it into view
      b.addEventListener("mouseenter", function () { highlightColumn(id); });
      b.addEventListener("mouseleave", function () { unhighlightColumn(id); });
      btnEls[id] = b;
      toggles.appendChild(b);
    });
    root.appendChild(toggles);

    // Chart scaffold
    var chart  = el("div", "sdb-sc-chart");
    var scroll = el("div", "sdb-sc-scroll");
    var wrap   = el("div", "sdb-sc-wrap");
    scroll.appendChild(wrap); chart.appendChild(scroll); root.appendChild(chart);

    // Frozen block
    var frozen   = el("div", "sdb-sc-frozen");
    var labelCol = el("div", "sdb-sc-labelcol");
    var lblHdr   = el("div", "sdb-sc-lbl-hdr");
    labelCol.appendChild(lblHdr);

    ROWS.forEach(function (row, ri) {
      var c = el("div", "sdb-sc-lbl-cell");
      c.setAttribute("data-r", ri);
      c.style.height       = "var(--rh-" + ri + ")";
      c.style.background   = "transparent";
      c.style.borderBottom = ri < ROWS.length - 1 ? "1px solid " + borderRow : "none";
      c.appendChild(el("span", null, esc(row.label)));
      labelCol.appendChild(c);
    });
    frozen.appendChild(labelCol);

    if (HAS_CURRENT && svcById[CURRENT]) {
      currCol = el("div", "sdb-sc-currcol");
      currCol.style.width = COL_W + "px";
      buildColumnBody(currCol, svcById[CURRENT], true);
      frozen.appendChild(currCol);
    }
    wrap.appendChild(frozen);

    // Draggable columns
    order.forEach(function (id) {
      if (id === CURRENT) return;
      var col = el("div", "sdb-sc-dragcol");
      col.setAttribute("data-col", id);
      col.style.width = COL_W + "px";
      buildColumnBody(col, svcById[id], false);
      colEls[id] = col;
      col.addEventListener("pointerdown", function (e) {
        var hdr = e.target.closest(".sdb-sc-col-hdr");
        if (hdr && col.contains(hdr)) onPointerDown(e, id);
      });
      wrap.appendChild(col);
    });

    // Placeholder
    var placeholder = el("div", "sdb-sc-placeholder");
    placeholder.style.top   = "var(--hdr-h)";
    placeholder.style.left  = BASE_X + "px";
    placeholder.style.right = "0";
    placeholder.innerHTML = "";
    wrap.appendChild(placeholder);



    // ── Column builder ─────────────────────────────────────────────────────
    // Read header background vars — Bricks can set flat colour or leave default gradient
    function buildColumnBody(container, svc, isCurrent) {
      var hdr = el("div", "sdb-sc-col-hdr");

      // Header backgrounds derived from primary colour (set in mount() scope above)
      var gradCurr  = "linear-gradient(180deg," + accentCurrent + "," + darken(accentCurrent, 0.30) + ")";
      var gradComp  = "linear-gradient(180deg," + accentCompare + "," + darken(accentCompare, 0.20) + ")";

      hdr.style.background  = isCurrent ? gradCurr : gradComp;
      hdr.style.borderLeft  = isCurrent ? "none" : "1px solid rgba(255,255,255,.06)";  // subtle separator, not themeable

      var badge  = svc.badge
        ? '<span class="sdb-sc-badge">' + esc(svc.badge) + '</span>'
        : "";
      var currentLabel = cfg.currentLabel || "";
      var corner = isCurrent
        ? (currentLabel ? '<span class="sdb-sc-currtag"><span style="font-size:9px;vertical-align:middle;margin-right:4px">&#10022;</span>' + esc(currentLabel) + '</span>' : "")
        : '<span class="sdb-sc-handle"></span>';
      hdr.innerHTML = badge + corner +
        '<div style="overflow:hidden">' +
          '<div class="sdb-sc-hdr-name">' + esc(svc.name)  + '</div>' +
          '<div class="sdb-sc-hdr-price">' + esc(svc.price) + '</div>' +
        '</div>';
      container.appendChild(hdr);

      ROWS.forEach(function (row, ri) {
        var cell = el("div", "sdb-sc-cell");
        cell.setAttribute("data-r", ri);
        cell.style.height       = "var(--rh-" + ri + ")";
        cell.style.background   = isCurrent
          ? (ri % 2 === 0 ? rowCurrEven : rowCurrOdd)
          : (ri % 2 === 0 ? rowEven     : rowOdd);
        cell.style.borderLeft   = isCurrent ? "2px solid " + borderPinned : "1px solid " + borderCol;
        cell.style.borderBottom = ri < ROWS.length - 1 ? "1px solid " + borderRow : "none";

        var renderer = RENDERERS[row.type] || RENDERERS.text;
        var ctx      = Object.assign({}, row, {
          isCurrent:  isCurrent,
          accent:     secondary_c,   // pills, rating, meter, bool use secondary colour
          descriptor: row.type === 'rating' ? (svc[row.key + '__desc'] || '') : '',
          // Child-level bool label overrides — fall back to schema-level, then defaults
          yes_label:  row.type === 'bool' ? (svc[row.key + '__yes'] || row.yes_label || '') : '',
          no_label:   row.type === 'bool' ? (svc[row.key + '__no']  || row.no_label  || '') : '',
        });
        if (row.type === 'rating' && !ctx.descriptor) {
          cell.style.alignItems = 'center';
        }
        cell.innerHTML = renderer(svc[row.key], ctx);
        container.appendChild(cell);
      });
    }

    // ── Row height measurement ─────────────────────────────────────────────
    function measureRows() {
      wrap.classList.add("measuring");
      void wrap.offsetWidth;
      var max = {};
      var cells = wrap.querySelectorAll("[data-r]");
      for (var i = 0; i < cells.length; i++) {
        var r = cells[i].getAttribute("data-r");
        var h = cells[i].offsetHeight;
        if (max[r] == null || h > max[r]) max[r] = h;
      }
      Object.keys(max).forEach(function (r) {
        var next = max[r] + "px";
        if (wrap.style.getPropertyValue("--rh-" + r) !== next) {
          wrap.style.setProperty("--rh-" + r, next);
        }
      });
      wrap.classList.remove("measuring");
    }

    var measureQueued = false;
    function scheduleMeasure() {
      if (measureQueued) return;
      measureQueued = true;
      requestAnimationFrame(function () { measureQueued = false; measureRows(); });
    }

    // ── View sync ──────────────────────────────────────────────────────────
    function syncView() {
      var vis = visibleDraggable();
      var w   = BASE_X + vis.length * COL_W;
      wrap.style.width    = w + "px";
      wrap.style.minWidth = w + "px";
      placeholder.style.display = vis.length > 0 ? "none" : "flex";
      placeholder.style.left    = BASE_X + "px";
      order.forEach(function (id) {
        if (id === CURRENT) return;
        var col = colEls[id];
        if (col) {
          col.style.opacity       = enabled[id] ? 1 : 0;
          col.style.pointerEvents = enabled[id] ? "auto" : "none";
        }
      });
      Object.keys(btnEls).forEach(function (id) {
        var b = btnEls[id];
        b.className = "sdb-sc-btn " + (id === CURRENT ? "curr" : enabled[id] ? "on" : "off");
      });
    }

    // ── Toggle ─────────────────────────────────────────────────────────────
    function toggle(id) {
      if (id === CURRENT || animating[id]) return;
      animating[id] = true;
      if (enabled[id]) {
        var col = colEls[id];
        if (col) col.style.opacity = 0;
        setTimeout(function () {
          enabled[id] = false;
          persist(); applyFluidColW(); startLoop();
          setTimeout(function () { delete animating[id]; }, 360);
        }, 250);
      } else {
        enabled[id] = true;
        persist(); applyFluidColW(); startLoop();
        setTimeout(function () {
          var col = colEls[id];
          if (col) col.style.opacity = 1;
          setTimeout(function () { delete animating[id]; }, 260);
        }, 300);
      }
    }

    // ── Drag ───────────────────────────────────────────────────────────────
    function updateDragFromPointer(clientX) {
      if (dragId == null) return;
      var vis  = visibleDraggable();
      var maxX = BASE_X + Math.max(0, vis.length - 1) * COL_W;
      var x    = clamp((clientX - dragWrapLeft) - dragGrabOffset, BASE_X, maxX);
      draggedX = x;
      pos[dragId] = x;
      if (colEls[dragId]) colEls[dragId].style.transform = "translateX(" + x + "px)";
      var slot   = clamp(Math.round((x - BASE_X) / COL_W), 0, vis.length - 1);
      var curIdx = vis.indexOf(dragId);
      if (curIdx !== -1 && curIdx !== slot) {
        var newVis = vis.slice();
        newVis.splice(curIdx, 1);
        newVis.splice(slot, 0, dragId);
        var rest = order.filter(function (x2) { return newVis.indexOf(x2) === -1 && x2 !== CURRENT; });
        order = normalize(newVis.concat(rest));
      }
      computeTargets();
    }

    function onPointerDown(e, id) {
      if (id === CURRENT) return;
      if (e.pointerType === "mouse" && e.button !== 0) return;
      var startX = e.clientX, startY = e.clientY;
      var started = false;
      var pid     = e.pointerId;
      var captureEl = colEls[id];
      try { if (captureEl && captureEl.setPointerCapture) captureEl.setPointerCapture(pid); } catch (_) {}

      function beginDrag() {
        started         = true;
        dragId          = id;
        lastClientX     = startX;
        var wrapRect    = wrap.getBoundingClientRect();
        dragWrapLeft    = wrapRect.left;
        dragScrollRect  = scroll.getBoundingClientRect();
        dragGrabOffset  = (startX - wrapRect.left) - (pos[id] != null ? pos[id] : BASE_X);
        if (colEls[id]) colEls[id].classList.add("dragging");
        document.body.style.cursor = "grabbing";
        blockTouch = function (te) { te.preventDefault(); };
        window.addEventListener("touchmove", blockTouch, { passive: false });
      }

      function onMove(ev) {
        var dx = ev.clientX - startX, dy = ev.clientY - startY;
        if (!started) {
          if (Math.max(Math.abs(dx), Math.abs(dy)) > 4) beginDrag(); else return;
        }
        lastClientX = ev.clientX;
        updateDragFromPointer(ev.clientX);
        startLoop();
      }

      function cleanup() {
        window.removeEventListener("pointermove", onMove);
        window.removeEventListener("pointerup", onUp);
        window.removeEventListener("pointercancel", onUp);
        if (blockTouch) { window.removeEventListener("touchmove", blockTouch, { passive: false }); blockTouch = null; }
        try { if (captureEl && captureEl.releasePointerCapture) captureEl.releasePointerCapture(pid); } catch (_) {}
      }

      function onUp() {
        cleanup();
        if (started) {
          if (colEls[id]) colEls[id].classList.remove("dragging");
          dragId = null; draggedX = null; dragScrollRect = null;
          document.body.style.cursor = "";
          persist(); computeTargets(); startLoop();
        }
      }

      window.addEventListener("pointermove", onMove);
      window.addEventListener("pointerup",   onUp);
      window.addEventListener("pointercancel", onUp);
    }

    // ── Fluid column width ────────────────────────────────────────────────
    // Columns expand to fill the scroll container, never shrinking below the
    // configured minimum (cfg.columnWidth). Scroll only activates when the
    // visible columns at minimum width exceed the available space.
    var MIN_COL_W = cfg.columnWidth || 188;

    // instant=true snaps columns to targets immediately (init/resize).
    // instant=false (default) leaves positions in place so the animation
    // loop springs them smoothly to the new targets (toggle add/remove).
    function applyFluidColW(instant) {
      var vis   = visibleDraggable();
      var count = vis.length + (HAS_CURRENT ? 1 : 0);

      // Available width inside the scroll container, minus the sticky label col.
      // Temporarily disable overflow so the measurement isn't polluted by an
      // existing scrollbar from a previous (wider) layout.
      scroll.style.overflowX = "hidden";
      var available = scroll.clientWidth - LABEL_W;

      var w;
      if (count === 0) {
        w = MIN_COL_W;
      } else {
        var fluid = Math.floor(available / count);
        w = Math.max(MIN_COL_W, fluid);
      }

      // Decide whether scrolling is needed: total column span vs available.
      var totalSpan   = count * w;
      var needsScroll = totalSpan > available + 1; // +1 px tolerance for rounding
      scroll.style.overflowX = needsScroll ? "auto" : "hidden";

      // Set widths explicitly on every column element (frozen + draggable).
      if (currCol) currCol.style.width = w + "px";
      allIds.forEach(function (id) {
        if (id === CURRENT) return;
        if (colEls[id]) colEls[id].style.width = w + "px";
      });

      // Update runtime layout values after DOM widths are set.
      COL_W  = w;
      BASE_X = LABEL_W + (HAS_CURRENT ? COL_W : 0);
      root.style.setProperty("--sc-col-w", w + "px");

      // Flush layout so the frozen column is resized before positions are read.
      void frozen.offsetWidth;

      computeTargets();

      if (instant) {
        // Snap immediately — used on initial mount and container resize.
        // Suppress width transition so resize tracks the cursor without lag.
        var toReset = [];
        if (currCol) { currCol.style.transition = "none"; toReset.push(currCol); }
        allIds.forEach(function (id) {
          if (id === CURRENT) return;
          var col = colEls[id];
          if (col) { col.style.transition = "none"; toReset.push(col); }
          pos[id] = targets[id] != null ? targets[id] : BASE_X;
          if (col) col.style.transform = "translateX(" + pos[id] + "px)";
        });
        // Restore transitions on next frame
        requestAnimationFrame(function () {
          toReset.forEach(function (c) { c.style.transition = ""; });
        });
      } else {
        // Leave pos[] as-is so the spring loop animates to new targets,
        // while CSS transitions the column widths smoothly.
        startLoop();
      }

      syncView();
      scheduleMeasure();
    }

    // ── Hover highlight + scroll-into-view ────────────────────────────────
    function columnEl(id) {
      return id === CURRENT ? currCol : colEls[id];
    }

    function highlightColumn(id) {
      if (!enabled[id]) return; // don't highlight hidden columns
      var col = columnEl(id);
      if (!col) return;
      col.classList.add("sdb-sc-col-hover");

      // Scroll the column into view only if horizontal scrolling is active.
      if (scroll.scrollWidth > scroll.clientWidth + 1 && id !== CURRENT) {
        var colLeft  = pos[id] != null ? pos[id] : BASE_X;
        var colRight = colLeft + COL_W;
        var viewLeft  = scroll.scrollLeft + BASE_X; // account for sticky frozen area
        var viewRight = scroll.scrollLeft + scroll.clientWidth;
        var targetScroll = null;
        if (colRight > viewRight) {
          targetScroll = colRight - scroll.clientWidth + 16;
        } else if (colLeft < viewLeft) {
          targetScroll = colLeft - BASE_X - 16;
        }
        if (targetScroll != null) {
          scroll.scrollTo({ left: Math.max(0, targetScroll), behavior: "smooth" });
        }
      }
    }

    function unhighlightColumn(id) {
      var col = columnEl(id);
      if (col) col.classList.remove("sdb-sc-col-hover");
    }

    // ── Initial layout ─────────────────────────────────────────────────────
    syncView();
    measureRows();
    applyFluidColW(true); // instant on mount
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(scheduleMeasure);
    if (window.ResizeObserver) {
      new ResizeObserver(function () {
        applyFluidColW(true); // instant on resize
      }).observe(root);
    }
  }

  // ── Boot: mount all instances on the page ─────────────────────────────────
  // Finds every element that has a paired config script tag ({id}-cfg).
  // Supports three selectors so it works regardless of how the element was added:
  //   1. [data-sdb-comparison]   — explicit attribute (manual / shortcode use)
  //   2. .sdb-sc-root            — class added by the Bricks element render()
  //   3. #sdb-comparison         — legacy single-instance fallback
  function init() {
    var seen = {};
    var candidates = document.querySelectorAll("[data-sdb-comparison], .sdb-sc-root, #sdb-comparison");
    for (var i = 0; i < candidates.length; i++) {
      var node = candidates[i];
      var id   = node.id;
      if (!id || seen[id]) continue; // skip duplicates and nodes without an id
      seen[id] = true;
      var cfg = loadConfigForNode(node);
      if (cfg) mount(node, cfg);
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
}());
