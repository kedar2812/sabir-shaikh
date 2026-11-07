// Entry point: sign-in, the shell (rail on computers, tab bar on phones), the
// hash router with its unsaved-changes guard, and the command menu (Ctrl/⌘ K).

import { h, clear, api, state, icon, toast, modal, confirmDialog, siteUrl, setVisitIgnore } from "./lib.js";
import { overviewView, analyticsView } from "./stats.js";
import { postsView, postEditor, episodesView, episodeEditor, listCache } from "./posts.js";
import { pagesIndex, pagesView, GROUPS } from "./pages.js";
import { mediaView } from "./media.js";
import { settingsView, applyTheme } from "./settings.js";

applyTheme();

const app = document.getElementById("app");

// ------------------------------------------------------------------ Crest
// The site's crest, drawn from the shared SVG so it follows currentColor.
export function crest(cls) {
  const wrap = h("span", { class: cls, "aria-hidden": "true" });
  wrap.innerHTML = '<svg viewBox="0 0 1825 1590"><use href="/assets/img/crest.svg#c"></use></svg>';
  return wrap;
}

// ------------------------------------------------------------------ Routes
const ROUTES = [
  { re: /^\/$/, view: overviewView, nav: "overview", title: "Overview" },
  { re: /^\/analytics$/, view: analyticsView, nav: "analytics", title: "Analytics" },
  { re: /^\/posts$/, view: postsView, nav: "posts", title: "Essays" },
  { re: /^\/posts\/(new|\d+)$/, view: postEditor, nav: "posts", title: "Essay", bare: true },
  { re: /^\/podcast$/, view: episodesView, nav: "podcast", title: "Podcast" },
  { re: /^\/podcast\/(new|\d+)$/, view: episodeEditor, nav: "podcast", title: "Episode", bare: true },
  { re: /^\/pages$/, view: pagesIndex, nav: "pages", title: "Pages" },
  { re: /^\/pages\/([a-z]+)$/, view: pagesView, nav: "pages", title: "Pages" },
  { re: /^\/photos$/, view: mediaView, nav: "photos", title: "Photos" },
  { re: /^\/settings$/, view: settingsView, nav: "settings", title: "Settings" },
];

const NAV = [
  { group: null, items: [
    { key: "overview", label: "Overview", icon: "overview", href: "#/" },
    { key: "analytics", label: "Analytics", icon: "analytics", href: "#/analytics" },
  ] },
  { group: "Writing", items: [
    { key: "posts", label: "Essays", icon: "posts", href: "#/posts", badge: "drafts" },
    { key: "podcast", label: "Podcast", icon: "podcast", href: "#/podcast" },
  ] },
  { group: "Site", items: [
    { key: "pages", label: "Pages", icon: "page", href: "#/pages" },
    { key: "photos", label: "Photos", icon: "photo", href: "#/photos" },
  ] },
];

let shell = null;
let current = { path: null, cleanup: null };

function path() {
  const p = location.hash.replace(/^#/, "") || "/";
  return p.split("?")[0];
}

state.go = (hash, opts = {}) => {
  if (opts.force) state.leaveGuard = null;
  if (opts.replace) {
    history.replaceState(null, "", hash);
    route();
  } else if (location.hash === hash) {
    route();
  } else {
    location.hash = hash;
  }
};

window.addEventListener("hashchange", async () => {
  if (!state.user) return;
  const target = location.hash || "#/";
  if (state.leaveGuard && state.leaveGuard() && current.path !== null) {
    history.replaceState(null, "", "#" + current.path);
    const ok = await confirmDialog({ title: "Leave without saving?", text: "The changes on this screen haven't been saved. Leaving now throws them away.", confirm: "Leave", danger: true });
    if (!ok) return;
    state.leaveGuard = null;
    history.replaceState(null, "", target);
  }
  route();
});

window.addEventListener("beforeunload", (e) => {
  if (state.leaveGuard && state.leaveGuard()) { e.preventDefault(); e.returnValue = ""; }
});

function route() {
  if (!shell) return;
  const p = path();
  const r = ROUTES.find((x) => x.re.test(p));
  if (!r) { history.replaceState(null, "", "#/"); route(); return; }
  if (typeof current.cleanup === "function") { try { current.cleanup(); } catch (e) { /* view already gone */ } }
  state.leaveGuard = null;
  current.path = p;
  const params = p.match(r.re).slice(1);
  const view = h("div.view.is-entering", { "data-view": r.nav });
  clear(shell.outlet).append(view);
  document.title = r.title + " | Dashboard";
  shell.setActive(r.nav);
  current.cleanup = r.view(view, ...params) || null;
  window.scrollTo(0, 0);
  shell.outlet.focus({ preventScroll: true });
  refreshBadges();
}

// ------------------------------------------------------------------ Badges
let badgeAt = 0;
async function refreshBadges(force) {
  if (!shell || (!force && Date.now() - badgeAt < 30000)) return;
  badgeAt = Date.now();
  try {
    const posts = await listCache("posts", true);
    shell.setBadge("drafts", posts.filter((p) => p.status === "draft").length);
  } catch (e) { /* not important */ }
}
window.addEventListener("contentchange", () => refreshBadges(true));

// ------------------------------------------------------------------ Shell
function buildShell() {
  const links = [];
  const badges = {};
  const outlet = h("div.outlet", { id: "main", tabindex: "-1", style: { outline: "none" } });
  const initial = (state.user.name || state.user.username || "S").trim().charAt(0).toUpperCase();
  const who = h("div.rail__who", h("strong", state.user.name || "Signed in"), h("span", state.user.username));
  const avatar = h("span.avatar", initial);

  const rail = h("nav.rail", { "aria-label": "Dashboard" },
    h("a.rail__brand", { href: "#/" }, crest("rail__crest"), h("span.rail__name", "Sabir Shaikh", h("small", "Dashboard"))),
    h("button.rail__search", { type: "button", onclick: () => commandMenu() }, icon("search", 18), h("span", "Search and go"), h("kbd", isMac() ? "⌘K" : "Ctrl K")),
    NAV.map((g) => h("div.rail__group",
      g.group ? h("p.rail__heading", g.group) : null,
      h("ul", g.items.map((it) => {
        const badge = it.badge ? h("span.rail__badge", { hidden: true }) : null;
        if (badge) badges[it.badge] = badge;
        const a = h("a.rail__link", { href: it.href, "data-nav": it.key }, icon(it.icon), h("span", it.label), badge);
        links.push(a);
        return h("li", a);
      })))),
    h("div.rail__foot",
      h("a.rail__link", { href: siteUrl("/"), target: "_blank", rel: "noopener" }, icon("external"), h("span", "View the site")),
      (() => { const a = h("a.rail__link", { href: "#/settings", "data-nav": "settings" }, icon("settings"), h("span", "Settings")); links.push(a); return a; })(),
      h("div.rail__user", avatar, who, h("button.icon-btn", { type: "button", "aria-label": "Sign out", title: "Sign out", onclick: signOut }, icon("out", 18)))));

  const tabs = [
    { key: "overview", label: "Overview", icon: "overview", href: "#/" },
    { key: "posts", label: "Essays", icon: "posts", href: "#/posts" },
    { key: "podcast", label: "Podcast", icon: "podcast", href: "#/podcast" },
    { key: "pages", label: "Pages", icon: "page", href: "#/pages" },
  ];
  const tabLinks = tabs.map((t) => h("a.tabbar__link", { href: t.href, "data-nav": t.key }, icon(t.icon, 22), t.label));
  const more = h("button.tabbar__link", { type: "button", "data-nav": "more", onclick: moreSheet }, icon("ul", 22), "More");
  const tabbar = h("nav.tabbar", { "aria-label": "Dashboard" }, tabLinks, more);

  const phoneTop = h("div.topbar-phone", crest("topbar-phone__crest"),
    h("button.icon-btn", { type: "button", "aria-label": "Search and go", onclick: () => commandMenu() }, icon("search")));

  const main = h("main.main", phoneTop, outlet);
  const root = h("div.shell", h("a.vh", { href: "#main", onclick: (e) => { e.preventDefault(); outlet.focus(); } }, "Skip to content"), rail, main, tabbar);
  clear(app).append(root);

  return {
    outlet,
    setActive(key) {
      [...links, ...tabLinks].forEach((a) => {
        if (a.dataset.nav === key) a.setAttribute("aria-current", "page");
        else a.removeAttribute("aria-current");
      });
      const inMore = ["analytics", "photos", "settings"].includes(key);
      if (inMore) more.setAttribute("aria-current", "page"); else more.removeAttribute("aria-current");
    },
    setBadge(name, n) {
      const b = badges[name];
      if (!b) return;
      b.hidden = !n;
      b.textContent = n;
      b.title = n === 1 ? "1 draft" : n + " drafts";
    },
    setUser(u) {
      who.firstChild.textContent = u.name || "Signed in";
      who.lastChild.textContent = u.username;
      avatar.textContent = (u.name || u.username || "S").trim().charAt(0).toUpperCase();
    },
  };
}

window.addEventListener("userchange", () => { if (shell) shell.setUser(state.user); });

function moreSheet() {
  const go = (href) => (close) => { close(); location.hash = href; };
  const item = (ic, label, onClick) => h("button.cmdk__item", { type: "button", onclick: onClick }, icon(ic), h("span", label));
  const m = modal({
    title: "More",
    body: h("div", { style: { display: "flex", flexDirection: "column", gap: "2px", margin: "0 -12px" } },
      item("analytics", "Analytics", () => go("#/analytics")(m.close)),
      item("photo", "Photos", () => go("#/photos")(m.close)),
      item("settings", "Settings", () => go("#/settings")(m.close)),
      item("external", "View the site", () => { m.close(); window.open(siteUrl("/"), "_blank", "noopener"); }),
      item("out", "Sign out", () => { m.close(); signOut(); })),
  });
}

// ------------------------------------------------------------------ Command menu
const isMac = () => /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent);

async function commandMenu() {
  if (document.querySelector(".cmdk")) return;
  const base = [
    { group: "Go to", label: "Overview", icon: "overview", run: "#/" },
    { group: "Go to", label: "Analytics", icon: "analytics", run: "#/analytics" },
    { group: "Go to", label: "Essays", icon: "posts", run: "#/posts" },
    { group: "Go to", label: "Podcast", icon: "podcast", run: "#/podcast" },
    { group: "Go to", label: "Pages", icon: "page", run: "#/pages" },
    { group: "Go to", label: "Photos", icon: "photo", run: "#/photos" },
    { group: "Go to", label: "Settings", icon: "settings", run: "#/settings" },
    { group: "Create", label: "Write an essay", icon: "plus", run: "#/posts/new" },
    { group: "Create", label: "Add a podcast episode", icon: "plus", run: "#/podcast/new" },
    ...GROUPS.map((g) => ({ group: "Edit a page", label: g.label, icon: g.icon, run: "#/pages/" + g.key, hint: g.path || "" })),
    { group: "Site", label: "Open the site in a new tab", icon: "external", run: () => window.open(siteUrl("/"), "_blank", "noopener") },
  ];
  let items = base;
  let active = 0;
  let q = "";
  const input = h("input", { type: "text", placeholder: "Search essays, episodes and pages", "aria-label": "Search", autocomplete: "off", spellcheck: "false" });
  const list = h("div.cmdk__list", { role: "listbox" });
  const m = modal({
    title: "Search and go",
    className: "cmdk",
    body: [h("label.cmdk__field", icon("search"), input), list],
  });
  m.sheet.parentElement.classList.add("cmdk-root");

  // Essays and episodes join the list once they've loaded.
  Promise.all([listCache("posts").catch(() => []), listCache("episodes").catch(() => [])]).then(([posts, eps]) => {
    items = base.concat(
      posts.map((p) => ({ group: "Essays", label: p.title, icon: "posts", run: "#/posts/" + p.id, hint: p.status === "draft" ? "Draft" : "" })),
      eps.map((e) => ({ group: "Episodes", label: e.title, icon: "podcast", run: "#/podcast/" + e.id, hint: e.status === "draft" ? "Draft" : "" })));
    render();
  });

  const matches = () => {
    if (!q) return items.filter((i) => i.group !== "Essays" && i.group !== "Episodes").concat(items.filter((i) => i.group === "Essays").slice(0, 4));
    const words = q.toLowerCase().split(/\s+/).filter(Boolean);
    return items.filter((i) => { const t = (i.label + " " + i.group + " " + (i.hint || "")).toLowerCase(); return words.every((w) => t.includes(w)); }).slice(0, 40);
  };
  const run = (it) => {
    m.close();
    if (typeof it.run === "function") it.run();
    else setTimeout(() => { location.hash = it.run; }, 10);
  };
  function render() {
    const found = matches();
    active = Math.min(active, Math.max(0, found.length - 1));
    clear(list);
    if (!found.length) { list.append(h("p.cmdk__none", "Nothing matches “" + q + "”.")); return; }
    let group = null;
    found.forEach((it, i) => {
      if (it.group !== group) { group = it.group; list.append(h("p.cmdk__group", group)); }
      list.append(h("button.cmdk__item", {
        type: "button", role: "option", class: i === active ? "is-active" : "", "aria-selected": i === active ? "true" : "false",
        onclick: () => run(it), onpointermove: () => { if (active !== i) { active = i; render(); } },
      }, icon(it.icon, 18), h("span", it.label), it.hint ? h("span", it.hint) : null));
    });
    const on = list.querySelector(".is-active");
    if (on) on.scrollIntoView({ block: "nearest" });
  }
  input.addEventListener("input", () => { q = input.value.trim(); active = 0; render(); });
  input.addEventListener("keydown", (e) => {
    const found = matches();
    if (e.key === "ArrowDown") { e.preventDefault(); active = (active + 1) % Math.max(1, found.length); render(); }
    else if (e.key === "ArrowUp") { e.preventDefault(); active = (active - 1 + found.length) % Math.max(1, found.length); render(); }
    else if (e.key === "Enter" && found[active]) { e.preventDefault(); run(found[active]); }
  });
  render();
  setTimeout(() => input.focus(), 30);
}

document.addEventListener("keydown", (e) => {
  if (!state.user) return;
  if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === "k" && !e.target.closest("[contenteditable]")) {
    e.preventDefault();
    commandMenu();
  }
});

// ------------------------------------------------------------------ Sign in
function passwordInput(id, autocomplete) {
  const input = h("input.input", { id, type: "password", autocomplete, required: true });
  const show = h("button.btn.btn--quiet.btn--sm", { type: "button", style: { margin: "3px" }, onclick: () => {
    const on = input.type === "password";
    input.type = on ? "text" : "password";
    show.textContent = on ? "Hide" : "Show";
  } }, "Show");
  return { input, el: h("div.input-group", input, show) };
}

function signInView() {
  shell = null;
  document.title = "Sign in | Dashboard";
  const user = h("input.input", { id: "si-user", type: "text", autocomplete: "username", autocapitalize: "none", spellcheck: "false", required: true });
  const pw = passwordInput("si-pass", "current-password");
  const err = h("p.signin__err", { role: "alert" });
  const submit = h("button.btn.btn--primary", { type: "submit" }, "Sign in");
  const form = h("form.signin__form", { novalidate: true, onsubmit: async (e) => {
    e.preventDefault();
    err.textContent = "";
    if (!user.value.trim() || !pw.input.value) { err.textContent = "Enter your username and password."; return; }
    submit.classList.add("is-busy");
    try {
      const d = await api("POST", "auth/login", { username: user.value, password: pw.input.value });
      started(d);
    } catch (ex) {
      err.textContent = ex.message;
      pw.input.select();
    } finally { submit.classList.remove("is-busy"); }
  } },
    h("h1", "Sign in"),
    h("p", "Write essays, add episodes, edit pages and see who's visiting sabirshaikh.in."),
    h("div.field", h("label.field__label", { for: "si-user" }, "Username"), user),
    h("div.field", h("label.field__label", { for: "si-pass" }, "Password"), pw.el),
    err, submit,
    h("p.signin__foot", h("a", { href: siteUrl("/") }, "Back to sabirshaikh.in")));

  clear(app).append(h("div.signin",
    h("div.signin__art",
      crest("signin__crest"),
      h("p.signin__line", "A curious mind. A purposeful life."),
      h("div.signin__arch", h("img", { src: "/assets/img/sabir-portrait.webp", alt: "" }))),
    h("div.signin__panel", form)));
  setTimeout(() => user.focus(), 50);
}

// A session that ends mid-edit asks for the password in a sheet, so the
// unsaved work on screen survives.
let reauthOpen = false;
window.addEventListener("signedout", () => {
  if (!state.user || reauthOpen) return;
  reauthOpen = true;
  const user = h("input.input", { id: "ra-user", type: "text", value: state.user.username, autocomplete: "username" });
  const pw = passwordInput("ra-pass", "current-password");
  const err = h("p.signin__err", { role: "alert" });
  const m = modal({
    title: "Sign in again",
    body: h("form.fields", { onsubmit: (e) => { e.preventDefault(); go(); } },
      h("p.modal__text", "For safety, the session ended. Sign in to carry on: nothing on screen has been lost."),
      h("div.field", h("label.field__label", { for: "ra-user" }, "Username"), user),
      h("div.field", h("label.field__label", { for: "ra-pass" }, "Password"), pw.el),
      err, h("button", { type: "submit", hidden: true })),
    actions: [
      { label: "Sign out", onClick: (close) => { close(); state.leaveGuard = null; signOut(); } },
      { label: "Sign in", kind: "primary", onClick: () => go() },
    ],
    onClose: () => { reauthOpen = false; },
  });
  async function go() {
    err.textContent = "";
    try {
      const d = await api("POST", "auth/login", { username: user.value, password: pw.input.value });
      state.csrf = d.csrf;
      state.user = d.user;
      m.close();
      // Nothing unsaved on screen: reload the view so anything that failed loads now.
      if (!(state.leaveGuard && state.leaveGuard())) { route(); toast("Signed in"); }
      else toast("Signed in. Your changes are still here; save them now.");
    } catch (ex) { err.textContent = ex.message; }
  }
  setTimeout(() => pw.input.focus(), 80);
});

async function signOut() {
  if (state.leaveGuard && state.leaveGuard()) {
    const ok = await confirmDialog({ title: "Sign out without saving?", text: "The changes on this screen haven't been saved.", confirm: "Sign out", danger: true });
    if (!ok) return;
  }
  state.leaveGuard = null;
  try { await api("POST", "auth/logout"); } catch (e) { /* signed out either way */ }
  state.user = null;
  state.csrf = "";
  signInView();
}

function started(d) {
  state.user = d.user;
  state.csrf = d.csrf;
  // The owner's own visits stay out of the numbers unless Settings says otherwise.
  let countMe = false;
  try { countMe = localStorage.getItem("ss_count_me") === "1"; } catch (e) { /* storage blocked */ }
  if (!countMe) setVisitIgnore(true);
  shell = buildShell();
  current = { path: null, cleanup: null };
  route();
}

// ------------------------------------------------------------------ Boot
(async function boot() {
  app.append(h("div.boot", crest("boot__mark")));
  try {
    started(await api("GET", "auth/session"));
  } catch (e) {
    if (e.status === 401) signInView();
    else clear(app).append(h("div.boot", h("div.empty", icon("site", 28), h("h3", "The dashboard couldn't start"), h("p", e.message),
      h("button.btn", { type: "button", onclick: () => location.reload() }, "Try again"))));
  }
})();
