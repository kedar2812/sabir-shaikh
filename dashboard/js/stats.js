// Overview (the landing screen) and the full Analytics report.

import { h, clear, api, fmt, pageHead, segmented, icon, linkButton, state, empty } from "./lib.js";
import { timeChart, sparkline, barList, splitBar, heatmap, table, chartCard } from "./charts.js";

const RANGES = [
  { value: "today", label: "Today", prev: "yesterday" },
  { value: "7d", label: "7 days", prev: "the 7 days before" },
  { value: "30d", label: "30 days", prev: "the 30 days before" },
  { value: "90d", label: "90 days", prev: "the 90 days before" },
  { value: "12m", label: "12 months", prev: "the 12 months before" },
];

const METRICS = [
  { key: "visitors", label: "Visitors", help: "People who visited, counted once a day.", format: fmt.num, up: true },
  { key: "views", label: "Page views", help: "Pages opened, including repeat visits.", format: fmt.num, up: true },
  { key: "duration", label: "Visit length", help: "Average time a visit lasts.", format: fmt.duration, up: true },
  { key: "reached", label: "Reached out", help: "Email taps, WhatsApp taps and contact form messages.", format: fmt.num, up: true },
];

const SERIES = ["var(--s1)", "var(--s2)", "var(--s3)"];

function savedRange() {
  try { return localStorage.getItem("dash_range") || "30d"; } catch (e) { return "30d"; }
}
function saveRange(r) { try { localStorage.setItem("dash_range", r); } catch (e) { /* storage blocked */ } }

const EVENT_NAMES = {
  "click:email": "Tapped the email address", "click:whatsapp": "Tapped WhatsApp", "contact_form:": "Used the contact form",
  "click:instagram": "Opened Instagram", "click:linkedin": "Opened LinkedIn", "click:x": "Opened X", "click:youtube": "Opened YouTube", "click:facebook": "Opened Facebook",
  "click:instagram-post": "Opened an Instagram post", "click:press": "Opened a press link", "click:venture": "Opened a company website",
  "click:podcast-link": "Opened a podcast link", "click:podcast-youtube": "Opened a podcast link", "podcast_play:": "Played a podcast episode",
  "click:share-linkedin": "Shared an essay on LinkedIn", "click:share-x": "Shared an essay on X", "click:share-whatsapp": "Shared an essay on WhatsApp",
};

function eventRows(events) {
  const merged = {};
  events.forEach((e) => {
    const key = e.name + ":" + (e.name === "click" ? e.label : "");
    const name = EVENT_NAMES[key] || (e.name === "click" ? "Clicked " + e.label : e.name.replace(/_/g, " "));
    merged[name] = (merged[name] || 0) + e.value;
  });
  return Object.entries(merged).map(([label, value]) => ({ label, value })).sort((a, b) => b.value - a.value);
}

const regionNames = (() => { try { return new Intl.DisplayNames(["en"], { type: "region" }); } catch (e) { return null; } })();
function countryName(code) {
  if (!code) return "Unknown";
  try { return (regionNames && regionNames.of(code)) || code; } catch (e) { return code; }
}

function delta(cur, prev, up) {
  if (!prev && !cur) return h("span.delta.delta--flat", "No change");
  if (!prev) return h("span.delta.delta--up", icon("up", 14), "New");
  const pct = Math.round(((cur - prev) / prev) * 100);
  if (pct === 0) return h("span.delta.delta--flat", "No change");
  const good = pct > 0 === up;
  return h("span.delta", { class: good ? "delta--up" : "delta--down" }, icon(pct > 0 ? "up" : "down", 14), Math.abs(pct) + "%");
}

function greeting() {
  const hr = new Date().getHours();
  const part = hr < 5 ? "Good evening" : hr < 12 ? "Good morning" : hr < 17 ? "Good afternoon" : "Good evening";
  const first = ((state.user && state.user.name) || "").split(" ")[0];
  return first ? `${part}, ${first}` : part;
}

// ------------------------------------------------------------------ Overview
export function overviewView(root) {
  let range = savedRange();
  let metric = "visitors";
  let data = null;

  const live = h("span.live", icon("live", 12), h("span", "Checking who's on the site"));
  const filter = segmented(RANGES, range, (v) => { range = v; saveRange(v); load(); }, "Time range");
  const body = h("div.stack");
  root.append(
    pageHead(greeting(), "Here's how sabirshaikh.in is doing.", [live]),
    h("div.filters", filter),
    body,
  );

  async function load() {
    body.classList.add("is-loading");
    try {
      data = await api("GET", "stats?range=" + range);
      render();
    } catch (e) {
      clear(body).append(empty("analytics", "Couldn't load the numbers", e.message));
    } finally {
      body.classList.remove("is-loading");
    }
  }

  function render() {
    const r = RANGES.find((x) => x.value === range);
    live.lastChild.textContent = data.live === 1 ? "1 person on the site now" : data.live + " people on the site now";
    live.classList.toggle("is-on", data.live > 0);
    clear(body);

    if (!data.first_visit) {
      body.append(h("div.notice", icon("sparkle", 20), h("div",
        h("strong", "Counting has started."),
        h("p", "Visits are counted from the moment this dashboard went live. The charts fill in as people arrive, so check back tomorrow."))));
    }

    // Headline numbers. Picking one redraws the chart below it.
    const tiles = h("div.kpis", { role: "tablist", "aria-label": "Headline numbers" });
    METRICS.forEach((m) => {
      const cur = data.current.totals[m.key], prev = data.previous.totals[m.key];
      const on = m.key === metric;
      tiles.append(h("button.kpi", {
        type: "button", role: "tab", "aria-selected": on ? "true" : "false", title: m.help,
        onclick: () => { metric = m.key; render(); },
      },
        h("span.kpi__label", m.label),
        h("span.kpi__value", m.format(cur)),
        h("span.kpi__foot", delta(cur, prev, m.up), h("span.kpi__vs", "vs " + r.prev)),
        sparkline(data.current.series[m.key], on ? "var(--s1)" : "var(--ink-3)")));
    });
    body.append(tiles);

    const m = METRICS.find((x) => x.key === metric);
    const chart = timeChart({
      keys: data.current.keys, unit: data.unit, format: m.format, label: m.label + " over time",
      series: [
        { name: "This period", values: data.current.series[metric], color: "var(--s1)", role: "current" },
        { name: "Previous period", values: data.previous.series[metric], color: "var(--prev)", role: "previous" },
      ],
    });
    const legend = h("ul.legend.legend--lines", h("li", h("i", { style: { background: "var(--s1)" } }), "This period"), h("li", h("i", { style: { background: "var(--prev)" } }), "Previous period"));
    body.append(chartCard({
      title: m.label, sub: m.help, className: "card--main", action: legend,
      chart,
      tableEl: table([{ key: "label", label: "Period" }, { key: "cur", label: "This period", num: true, format: m.format }, { key: "prev", label: "Previous period", num: true, format: m.format }],
        data.current.keys.map((k, i) => ({ label: k, cur: data.current.series[metric][i], prev: data.previous.series[metric][i] || 0 }))),
    }));

    const pages = data.pages.map((p) => ({ label: p.title, sub: p.path, value: p.views }));
    const sources = data.sources.map((s) => ({ label: s.name, value: s.value }));
    body.append(h("div.grid-2",
      chartCard({ title: "Most read", sub: "Page views", chart: pages.length ? barList(pages, { valueLabel: "Page views" }) : quiet("No page views in this period."),
        tableEl: table([{ key: "title", label: "Page" }, { key: "path", label: "Address" }, { key: "views", label: "Views", num: true, format: fmt.num }, { key: "visitors", label: "Visitors", num: true, format: fmt.num }, { key: "avg", label: "Avg. time", num: true, format: fmt.duration }], data.pages) }),
      chartCard({ title: "Where visitors come from", sub: "Visitors, by the site that sent them", chart: sources.length ? barList(sources) : quiet("No visits in this period."),
        tableEl: table([{ key: "name", label: "Source" }, { key: "value", label: "Visitors", num: true, format: fmt.num }], data.sources) }),
    ));

    const devices = ["Phone", "Computer", "Tablet"].map((n) => ({ name: n, value: (data.devices.find((d) => d.name === n) || { value: 0 }).value }));
    const countries = data.countries.map((c) => ({ label: countryName(c.name), lead: h("span.cc", c.name), value: c.value }));
    body.append(h("div.grid-2",
      h("div.stack",
        chartCard({ title: "Devices", sub: "Visitors", chart: devices.some((d) => d.value) ? splitBar(devices, SERIES) : quiet("No visits in this period."),
          tableEl: table([{ key: "name", label: "Device" }, { key: "value", label: "Visitors", num: true, format: fmt.num }], devices) }),
        chartCard({ title: "Countries", sub: "Visitors, from their time zone", chart: countries.length ? barList(countries, { limit: 6 }) : quiet("No visits in this period."),
          tableEl: table([{ key: "label", label: "Country" }, { key: "value", label: "Visitors", num: true, format: fmt.num }], countries) }),
      ),
      chartCard({ title: "When people visit", sub: "Page views by day and hour, Indian time", chart: heatmap(data.heat),
        tableEl: table([{ key: "d", label: "Day" }, { key: "h", label: "Hour" }, { key: "n", label: "Page views", num: true, format: fmt.num }],
          data.heat.map(([d, hr, n]) => ({ d: ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"][d - 1], h: String(hr).padStart(2, "0") + ":00", n })).sort((a, b) => b.n - a.n)) }),
    ));

    const events = eventRows(data.events);
    body.append(h("div.grid-2",
      chartCard({ title: "What people did", sub: "Taps and clicks on the site", chart: events.length ? barList(events, { valueLabel: "Times" }) : quiet("Nobody has tapped a link in this period yet."),
        tableEl: table([{ key: "label", label: "Action" }, { key: "value", label: "Times", num: true, format: fmt.num }], events) }),
      contentCard(data.content),
    ));
  }

  load();
  const timer = setInterval(async () => {
    if (document.hidden || !document.body.contains(root)) return;
    try {
      const d = await api("GET", "stats?range=today");
      live.lastChild.textContent = d.live === 1 ? "1 person on the site now" : d.live + " people on the site now";
      live.classList.toggle("is-on", d.live > 0);
    } catch (e) { /* keep the last value */ }
  }, 60000);
  return () => clearInterval(timer);
}

function quiet(text) { return h("p.quiet", text); }

function contentCard(c) {
  const days = c.last_post ? Math.round((Date.now() - new Date(c.last_post.published_at + "T00:00:00").getTime()) / 86400000) : null;
  const nudge = days === null ? "No essays are published yet."
    : days <= 0 ? "An essay went up today."
      : days === 1 ? "The latest essay went up yesterday."
        : `The latest essay went up ${days} days ago.`;
  return h("section.card.content-card",
    h("header.card__head", h("div", h("h2.card__title", "Keep the site fresh"), h("p.card__sub", nudge))),
    h("dl.mini-stats",
      h("div", h("dt", "Published essays"), h("dd", fmt.num(c.posts))),
      h("div", h("dt", "Drafts"), h("dd", fmt.num(c.drafts))),
      h("div", h("dt", "Episodes"), h("dd", fmt.num(c.episodes)))),
    h("div.quick",
      linkButton("Write an essay", "#/posts/new", { kind: "primary", icon: "plus" }),
      linkButton("Add an episode", "#/podcast/new", { icon: "podcast" }),
      linkButton("Edit the home page", "#/pages/home", { icon: "home" })));
}

// ------------------------------------------------------------------ Full report
export function analyticsView(root) {
  let range = savedRange();
  const filter = segmented(RANGES, range, (v) => { range = v; saveRange(v); load(); }, "Time range");
  const body = h("div.stack");
  root.append(pageHead("Analytics", "Every number behind the overview. Each card can switch to a table."), h("div.filters", filter), body);

  async function load() {
    body.classList.add("is-loading");
    try {
      const d = await api("GET", "stats?full=1&range=" + range);
      clear(body);
      const t = d.current.totals, p = d.previous.totals;
      body.append(h("div.kpis.kpis--static",
        [["Visitors", t.visitors, p.visitors, fmt.num, true], ["Visits", t.sessions, p.sessions, fmt.num, true], ["Page views", t.views, p.views, fmt.num, true],
          ["Visit length", t.duration, p.duration, fmt.duration, true], ["Left after one page", t.bounce, p.bounce, fmt.pct, false], ["Reached out", t.reached, p.reached, fmt.num, true]]
          .map(([label, cur, prev, f, up]) => h("div.kpi", h("span.kpi__label", label), h("span.kpi__value", f(cur)), h("span.kpi__foot", delta(cur, prev, up))))));

      const list = (title, sub, rows, cols, opts) => chartCard({ title, sub, chart: rows.length ? barList(rows.map(opts.map), { limit: 12, valueLabel: opts.valueLabel || "Visitors" }) : quiet("Nothing in this period."), tableEl: table(cols, rows) });
      body.append(h("div.grid-2",
        list("Pages", "Page views", d.pages, [{ key: "title", label: "Page" }, { key: "path", label: "Address" }, { key: "views", label: "Views", num: true, format: fmt.num }, { key: "visitors", label: "Visitors", num: true, format: fmt.num }, { key: "avg", label: "Avg. time", num: true, format: fmt.duration }], { map: (x) => ({ label: x.title, sub: x.path, value: x.views }), valueLabel: "Page views" }),
        list("First page seen", "Where visits start", d.entries, [{ key: "name", label: "Address" }, { key: "value", label: "Visitors", num: true, format: fmt.num }], { map: (x) => ({ label: x.name, value: x.value }) }),
        list("Sources", "The site that sent each visitor", d.sources, [{ key: "name", label: "Source" }, { key: "value", label: "Visitors", num: true, format: fmt.num }], { map: (x) => ({ label: x.name, value: x.value }) }),
        list("Campaigns", "Links tagged with utm_campaign", d.campaigns, [{ key: "name", label: "Campaign" }, { key: "value", label: "Visitors", num: true, format: fmt.num }], { map: (x) => ({ label: x.name, value: x.value }) }),
        list("Countries", "From each visitor's time zone", d.countries, [{ key: "name", label: "Code" }, { key: "value", label: "Visitors", num: true, format: fmt.num }], { map: (x) => ({ label: countryName(x.name), lead: h("span.cc", x.name), value: x.value }) }),
        list("Devices", "Visitors", d.devices, [{ key: "name", label: "Device" }, { key: "value", label: "Visitors", num: true, format: fmt.num }], { map: (x) => ({ label: x.name, value: x.value }) }),
        list("Browsers", "Visitors", d.browsers, [{ key: "name", label: "Browser" }, { key: "value", label: "Visitors", num: true, format: fmt.num }], { map: (x) => ({ label: x.name, value: x.value }) }),
        list("Operating systems", "Visitors", d.os, [{ key: "name", label: "System" }, { key: "value", label: "Visitors", num: true, format: fmt.num }], { map: (x) => ({ label: x.name, value: x.value }) }),
      ));
      const events = eventRows(d.events);
      body.append(h("div.grid-2",
        chartCard({ title: "What people did", sub: "Taps and clicks", chart: events.length ? barList(events, { limit: 14, valueLabel: "Times" }) : quiet("Nothing in this period."), tableEl: table([{ key: "label", label: "Action" }, { key: "value", label: "Times", num: true, format: fmt.num }], events) }),
        chartCard({ title: "When people visit", sub: "Page views by day and hour, Indian time", chart: heatmap(d.heat) })));
      body.append(h("p.footnote", "Visits are counted by the site itself, without cookies. Each visitor is counted once a day; nobody can be followed from one day to the next. Your own visits from this browser aren't counted (change that in Settings)."));
    } catch (e) {
      clear(body).append(empty("analytics", "Couldn't load the numbers", e.message));
    } finally {
      body.classList.remove("is-loading");
    }
  }
  load();
}
