// Charts drawn as SVG/HTML, following one set of rules: thin marks, hairline
// grid, the data is the only loud thing, every chart has a hover/focus readout
// and a table twin. Colours come from CSS custom properties in app.css
// (validated for colour-blind separation in both light and dark mode).

import { h, clear, fmt } from "./lib.js";

const SVGNS = "http://www.w3.org/2000/svg";
function s(tag, attrs) {
  const el = document.createElementNS(SVGNS, tag);
  for (const [k, v] of Object.entries(attrs || {})) el.setAttribute(k, v);
  return el;
}

export function bucketLabel(key, unit, long) {
  if (unit === "hour") return key.slice(11) + ":00";
  if (unit === "month") return new Date(key + "-01T00:00:00").toLocaleDateString("en-GB", long ? { month: "long", year: "numeric" } : { month: "short" });
  return new Date(key + "T00:00:00").toLocaleDateString("en-GB", long ? { weekday: "short", day: "numeric", month: "short" } : { day: "numeric", month: "short" });
}

// Round numbers for the y axis: 0, then 3-4 clean steps.
function niceTicks(max) {
  if (max <= 0) return [0, 1];
  const raw = max / 4;
  const pow = Math.pow(10, Math.floor(Math.log10(raw)));
  const step = [1, 2, 2.5, 5, 10].map((m) => m * pow).find((st) => st >= raw) || raw;
  const ticks = [];
  for (let v = 0; v <= max + step * 0.001; v += step) ticks.push(v);
  if (ticks[ticks.length - 1] < max) ticks.push(ticks[ticks.length - 1] + step);
  return ticks;
}

// ------------------------------------------------------------------ Tooltip
function tooltip(host) {
  const tip = h("div.tip", { role: "presentation" });
  host.append(tip);
  return {
    show(x, y, title, rows) {
      clear(tip);
      tip.append(h("div.tip__title", title));
      rows.forEach((r) => tip.append(h("div.tip__row", r.key ? h("i.tip__key", { style: { background: r.key } }) : null, h("strong", r.value), h("span", r.label))));
      tip.classList.add("is-on");
      const hb = host.getBoundingClientRect();
      const w = tip.offsetWidth, ht = tip.offsetHeight;
      let left = x + 14;
      if (left + w > hb.width - 4) left = x - w - 14;
      tip.style.transform = `translate(${Math.max(4, left)}px, ${Math.max(4, Math.min(y - ht / 2, hb.height - ht - 4))}px)`;
    },
    hide() { tip.classList.remove("is-on"); },
  };
}

// ------------------------------------------------------------------ Line / area over time
// series: [{ name, values, color, role: "current" | "previous" }]
export function timeChart({ keys, unit, series, format = fmt.num, height = 260, label = "Chart" }) {
  const wrap = h("div.tchart", { tabindex: "0", role: "img", "aria-label": label });
  const svg = s("svg", { class: "tchart__svg", role: "presentation" });
  wrap.append(svg);
  const tip = tooltip(wrap);
  let focusIndex = -1;
  let geom = null;

  const draw = () => {
    const W = wrap.clientWidth || 600;
    const H = height;
    const pad = { t: 12, r: 12, b: 28, l: 44 };
    const max = Math.max(1, ...series.flatMap((x) => x.values));
    const ticks = niceTicks(max);
    const top = ticks[ticks.length - 1];
    const iw = W - pad.l - pad.r, ih = H - pad.t - pad.b;
    const n = keys.length;
    const x = (i) => pad.l + (n === 1 ? iw / 2 : (i * iw) / (n - 1));
    const y = (v) => pad.t + ih - (v / top) * ih;
    geom = { x, y, n, pad, W, H, iw };
    svg.setAttribute("viewBox", `0 0 ${W} ${H}`);
    svg.setAttribute("width", W);
    svg.setAttribute("height", H);
    while (svg.firstChild) svg.firstChild.remove();

    const grid = s("g", { class: "tchart__grid" });
    ticks.forEach((t) => {
      grid.append(s("line", { x1: pad.l, x2: W - pad.r, y1: y(t), y2: y(t), class: t === 0 ? "base" : "" }));
      const tx = s("text", { x: pad.l - 10, y: y(t) + 4, "text-anchor": "end" });
      tx.textContent = fmt.compact(t) === "0" ? "0" : format === fmt.num ? fmt.compact(t) : format(t);
      grid.append(tx);
    });
    // x labels: as many as fit without crowding, always the first and the last.
    const every = Math.max(1, Math.ceil(n / Math.max(2, Math.floor(iw / 72))));
    for (let i = 0; i < n; i++) {
      if (i % every !== 0 && i !== n - 1) continue;
      if (i !== n - 1 && n - 1 - i < every * 0.6) continue;
      const tx = s("text", { x: x(i), y: H - 8, "text-anchor": i === 0 ? "start" : i === n - 1 ? "end" : "middle" });
      tx.textContent = bucketLabel(keys[i], unit);
      grid.append(tx);
    }
    svg.append(grid);

    // Previous period underneath, current on top with a soft wash.
    [...series].sort((a, b) => (a.role === "previous" ? -1 : 1) - (b.role === "previous" ? -1 : 1)).forEach((ser) => {
      const pts = ser.values.map((v, i) => [x(i), y(v)]);
      const d = pts.map((p, i) => (i ? "L" : "M") + p[0].toFixed(1) + " " + p[1].toFixed(1)).join(" ");
      if (ser.role !== "previous") {
        svg.append(s("path", { d: d + ` L${x(n - 1)} ${y(0)} L${x(0)} ${y(0)} Z`, fill: ser.color, "fill-opacity": "0.1", stroke: "none" }));
      }
      svg.append(s("path", { d, fill: "none", stroke: ser.color, "stroke-width": ser.role === "previous" ? 1.5 : 2, "stroke-linejoin": "round", "stroke-linecap": "round", class: ser.role === "previous" ? "prev" : "" }));
      if (ser.role !== "previous" && n > 0) {
        const last = pts[pts.length - 1];
        svg.append(s("circle", { cx: last[0], cy: last[1], r: 4, fill: ser.color, stroke: "var(--card)", "stroke-width": 2 }));
      }
    });

    const cross = s("line", { class: "tchart__cross", y1: pad.t, y2: pad.t + ih, x1: -10, x2: -10 });
    svg.append(cross);
    const dots = series.map((ser) => { const c = s("circle", { r: 4, fill: ser.color, stroke: "var(--card)", "stroke-width": 2, class: "tchart__dot", cx: -10, cy: -10 }); svg.append(c); return c; });
    geom.cross = cross;
    geom.dots = dots;
    if (focusIndex >= 0) point(focusIndex);
  };

  const point = (i) => {
    if (!geom) return;
    focusIndex = i;
    const { x, y, cross, dots } = geom;
    cross.setAttribute("x1", x(i));
    cross.setAttribute("x2", x(i));
    cross.classList.add("is-on");
    series.forEach((ser, k) => { dots[k].setAttribute("cx", x(i)); dots[k].setAttribute("cy", y(ser.values[i] || 0)); dots[k].classList.add("is-on"); });
    tip.show(x(i), y(Math.max(...series.map((ser) => ser.values[i] || 0))), bucketLabel(keys[i], unit, true),
      series.map((ser) => ({ key: ser.color, value: format(ser.values[i] || 0), label: ser.name })));
  };
  const leave = () => {
    focusIndex = -1;
    tip.hide();
    if (geom) { geom.cross.classList.remove("is-on"); geom.dots.forEach((d) => d.classList.remove("is-on")); }
  };

  wrap.addEventListener("pointermove", (e) => {
    if (!geom) return;
    const r = svg.getBoundingClientRect();
    const px = e.clientX - r.left;
    const i = Math.round(((px - geom.pad.l) / geom.iw) * (geom.n - 1));
    point(Math.max(0, Math.min(geom.n - 1, i)));
  });
  wrap.addEventListener("pointerleave", leave);
  wrap.addEventListener("blur", leave);
  wrap.addEventListener("keydown", (e) => {
    if (e.key !== "ArrowLeft" && e.key !== "ArrowRight") return;
    e.preventDefault();
    const i = focusIndex < 0 ? keys.length - 1 : focusIndex + (e.key === "ArrowRight" ? 1 : -1);
    point(Math.max(0, Math.min(keys.length - 1, i)));
  });

  new ResizeObserver(() => draw()).observe(wrap);
  return wrap;
}

// ------------------------------------------------------------------ Sparkline
export function sparkline(values, color) {
  const W = 120, H = 32;
  const max = Math.max(1, ...values);
  const n = values.length;
  const svg = s("svg", { viewBox: `0 0 ${W} ${H}`, class: "spark", "aria-hidden": "true", preserveAspectRatio: "none" });
  if (n < 2) return svg;
  const pts = values.map((v, i) => [(i * (W - 4)) / (n - 1) + 2, H - 3 - (v / max) * (H - 6)]);
  const d = pts.map((p, i) => (i ? "L" : "M") + p[0].toFixed(1) + " " + p[1].toFixed(1)).join(" ");
  svg.append(s("path", { d: d + ` L${pts[n - 1][0]} ${H} L${pts[0][0]} ${H} Z`, fill: color, "fill-opacity": "0.1" }));
  svg.append(s("path", { d, fill: "none", stroke: color, "stroke-width": 1.75, "stroke-linejoin": "round", "stroke-linecap": "round", "vector-effect": "non-scaling-stroke" }));
  return svg;
}

// ------------------------------------------------------------------ Ranked bars
// items: [{ label, value, sub, lead (element shown before the label) }]
export function barList(items, { format = fmt.num, valueLabel = "Visitors", limit = 8 } = {}) {
  const list = h("ol.bars");
  const max = Math.max(1, ...items.map((i) => i.value));
  const total = items.reduce((a, i) => a + i.value, 0) || 1;
  items.slice(0, limit).forEach((it) => {
    const pct = (it.value / max) * 100;
    list.append(h("li.bars__row", { tabindex: "0", title: `${it.label}: ${format(it.value)} ${valueLabel.toLowerCase()} (${Math.round((it.value / total) * 100)}%)` },
      h("div.bars__fill", { style: { width: Math.max(pct, 1.5) + "%" } }),
      h("div.bars__label", it.lead || null, h("span.bars__name", it.label), it.sub ? h("span.bars__sub", it.sub) : null),
      h("div.bars__value", format(it.value))));
  });
  return list;
}

// ------------------------------------------------------------------ Tall segmented bar
// One whole split into a few parts, each part labelled inside where it fits.
export function splitBar(parts, colors, { format = fmt.num } = {}) {
  const total = parts.reduce((a, p) => a + p.value, 0) || 1;
  const bar = h("div.split", { role: "img", "aria-label": parts.map((p) => `${p.name} ${Math.round((p.value / total) * 100)}%`).join(", ") });
  const legend = h("ul.legend");
  parts.forEach((p, i) => {
    if (!p.value) return;
    const share = (p.value / total) * 100;
    const seg = h("div.split__part", { style: { flexGrow: String(p.value), background: colors[i % colors.length] }, tabindex: "0", title: `${p.name}: ${format(p.value)} (${Math.round(share)}%)` });
    if (share >= 12) seg.append(h("span", Math.round(share) + "%"));
    bar.append(seg);
    legend.append(h("li", h("i", { style: { background: colors[i % colors.length] } }), h("span", p.name)));
  });
  return h("div.split-wrap", bar, legend);
}

// ------------------------------------------------------------------ Heatmap (day x hour)
export function heatmap(cells) {
  const days = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];
  const grid = {};
  let max = 0;
  cells.forEach(([dow, hour, n]) => { grid[dow + "-" + hour] = n; max = Math.max(max, n); });
  const wrap = h("div.heat");
  const tipHost = h("div.heat__grid", { role: "img", "aria-label": "Page views by day of the week and hour of the day" });
  const tip = tooltip(wrap);
  for (let d = 1; d <= 7; d++) {
    tipHost.append(h("div.heat__day", days[d - 1]));
    for (let hr = 0; hr < 24; hr++) {
      const n = grid[d + "-" + hr] || 0;
      const level = n === 0 ? 0 : Math.min(5, Math.ceil((n / Math.max(1, max)) * 5));
      const cell = h("div.heat__cell", { class: "l" + level, tabindex: n ? "0" : "-1", "data-tip": `${days[d - 1]} ${String(hr).padStart(2, "0")}:00|${fmt.num(n)}` });
      tipHost.append(cell);
    }
  }
  const showTip = (el) => {
    if (!el || !el.dataset.tip) return;
    const [title, value] = el.dataset.tip.split("|");
    const r = el.getBoundingClientRect(), w = wrap.getBoundingClientRect();
    tip.show(r.left - w.left + r.width / 2, r.top - w.top, title, [{ value, label: "page views" }]);
  };
  tipHost.addEventListener("pointerover", (e) => showTip(e.target.closest(".heat__cell")));
  tipHost.addEventListener("focusin", (e) => showTip(e.target.closest(".heat__cell")));
  tipHost.addEventListener("pointerleave", () => tip.hide());
  tipHost.addEventListener("focusout", () => tip.hide());
  const hours = h("div.heat__hours", h("span"), ...[0, 6, 12, 18].map((x) => h("span", { style: { gridColumn: x + 2 + " / span 6" } }, String(x).padStart(2, "0") + ":00")));
  const scale = h("div.heat__scale", h("span", "Fewer"), ...[1, 2, 3, 4, 5].map((l) => h("i", { class: "l" + l })), h("span", "More"));
  wrap.append(tipHost, hours, scale);
  return wrap;
}

// ------------------------------------------------------------------ Table twin
export function table(columns, rows) {
  return h("div.table-wrap", h("table.data",
    h("thead", h("tr", columns.map((c) => h("th", { class: c.num ? "num" : "" }, c.label)))),
    h("tbody", rows.length ? rows.map((r) => h("tr", columns.map((c) => h("td", { class: c.num ? "num" : "" }, c.format ? c.format(r[c.key]) : r[c.key]))))
      : h("tr", h("td", { colspan: columns.length }, "Nothing yet.")))));
}

// A card holding a chart, with a switch to its table twin.
export function chartCard({ title, sub, chart, tableEl, className = "", action }) {
  const body = h("div.card__body");
  let showing = "chart";
  const swap = tableEl ? h("button.icon-btn.card__swap", {
    type: "button", "aria-label": "Show as a table", title: "Show as a table",
    onclick: () => {
      showing = showing === "chart" ? "table" : "chart";
      body.replaceChildren(showing === "chart" ? chart : tableEl);
      swap.setAttribute("aria-label", showing === "chart" ? "Show as a table" : "Show as a chart");
      swap.title = swap.getAttribute("aria-label");
      swap.classList.toggle("is-on", showing === "table");
    },
  }) : null;
  if (swap) swap.append(Object.assign(document.createElement("span"), { className: "ic", innerHTML: '<svg viewBox="0 0 20 20" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="3" y="4" width="14" height="12" rx="1.5"/><path d="M3 8h14M3 12h14M8 4v12"/></svg>' }));
  body.append(chart);
  return h("section.card", { class: className },
    h("header.card__head", h("div", h("h2.card__title", title), sub ? h("p.card__sub", sub) : null), h("div.card__tools", action || null, swap)),
    body);
}
