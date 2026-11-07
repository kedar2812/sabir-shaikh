// Shared toolkit for the dashboard: element building, the API client, toasts,
// dialogs and formatting. No framework; every view is plain DOM.

export const state = { user: null, csrf: "", leaveGuard: null };

// The public site's address ("" on a local test server, where one host serves both).
export const SITE = (document.querySelector('meta[name="site-url"]') || {}).content || "";
export const siteUrl = (path) => SITE + path;

// "Leave my visits out": a cookie the public site reads. On sabirshaikh.in it is
// shared by every subdomain, so signing in on the dashboard covers www too.
const COOKIE_DOMAIN = /(^|\.)sabirshaikh\.in$/.test(location.hostname) ? "; domain=.sabirshaikh.in" : "";
export function setVisitIgnore(on) {
  const secure = location.protocol === "https:" ? "; secure" : "";
  document.cookie = "ss_ignore=" + (on ? "1; max-age=31536000" : "; max-age=0") + "; path=/; samesite=lax" + COOKIE_DOMAIN + secure;
}
export function visitIgnored() { return /(?:^|;\s*)ss_ignore=1(?:;|$)/.test(document.cookie); }

// h("div.card", { onclick }, child, "text") -> element. Children may be arrays,
// strings (as text, never HTML) or null.
export function h(tag, attrs, ...children) {
  const [name, ...classes] = tag.split(".");
  const el = document.createElement(name || "div");
  if (classes.length) el.className = classes.join(" ");
  if (attrs && (typeof attrs !== "object" || attrs instanceof Node || Array.isArray(attrs))) {
    children.unshift(attrs);
    attrs = null;
  }
  for (const [k, v] of Object.entries(attrs || {})) {
    if (v === null || v === undefined || v === false) continue;
    if (k.startsWith("on") && typeof v === "function") el.addEventListener(k.slice(2).toLowerCase(), v);
    else if (k === "class") el.className = [el.className, v].filter(Boolean).join(" ");
    else if (k === "style" && typeof v === "object") { for (const [sk, sv] of Object.entries(v)) { if (sk.startsWith("--")) el.style.setProperty(sk, sv); else el.style[sk] = sv; } }
    else if (k === "html") el.innerHTML = v; // only ever used with constants (icons)
    else if (k === "value") el.value = v;
    else if (k in el && k !== "list" && k !== "form" && typeof v !== "string") el[k] = v;
    else el.setAttribute(k, v === true ? "" : v);
  }
  append(el, children);
  return el;
}

function append(el, children) {
  for (const c of children) {
    if (c === null || c === undefined || c === false) continue;
    if (Array.isArray(c)) append(el, c);
    else el.append(c instanceof Node ? c : document.createTextNode(String(c)));
  }
}

export function clear(el) { el.replaceChildren(); return el; }

// ------------------------------------------------------------------ Icons
// SF-Symbols-like line icons, 20px grid, drawn with currentColor.
const ICONS = {
  overview: '<path d="M3.5 10.5 10 4l6.5 6.5M5.5 9v7h3.5v-4h2v4h3.5V9"/>',
  analytics: '<path d="M4 16V9M8 16V5M12 16v-6M16 16V7"/>',
  posts: '<path d="M5 3.5h7l3 3v10H5z"/><path d="M12 3.5v3h3M7.5 10h5M7.5 13h5"/>',
  podcast: '<rect x="7.5" y="3" width="5" height="9" rx="2.5"/><path d="M5 10a5 5 0 0 0 10 0M10 15v2.5"/>',
  page: '<rect x="4" y="3.5" width="12" height="13" rx="2"/><path d="M7 7.5h6M7 10.5h6M7 13.5h3.5"/>',
  home: '<path d="M3.5 9.5 10 4l6.5 5.5M5.5 8v8.5h9V8"/>',
  person: '<circle cx="10" cy="7" r="3"/><path d="M4.5 16.5c.8-3 3-4.5 5.5-4.5s4.7 1.5 5.5 4.5"/>',
  mail: '<rect x="3" y="5" width="14" height="10.5" rx="2"/><path d="m3.5 6 6.5 5 6.5-5"/>',
  shield: '<path d="M10 3 4.5 5v4.5c0 3.5 2.4 6 5.5 7.5 3.1-1.5 5.5-4 5.5-7.5V5z"/>',
  site: '<circle cx="10" cy="10" r="6.5"/><path d="M3.5 10h13M10 3.5c2 2 2.8 4.2 2.8 6.5s-.8 4.5-2.8 6.5c-2-2-2.8-4.2-2.8-6.5s.8-4.5 2.8-6.5z"/>',
  photo: '<rect x="3" y="4.5" width="14" height="11" rx="2"/><circle cx="7.5" cy="8.5" r="1.5"/><path d="m3.5 14 4-4 3 3 2-2 4 4"/>',
  settings: '<circle cx="10" cy="10" r="2.5"/><path d="M10 2.8v2M10 15.2v2M17.2 10h-2M4.8 10h-2M15.1 4.9l-1.4 1.4M6.3 13.7l-1.4 1.4M15.1 15.1l-1.4-1.4M6.3 6.3 4.9 4.9"/>',
  out: '<path d="M8 4.5H5a1.5 1.5 0 0 0-1.5 1.5v8A1.5 1.5 0 0 0 5 15.5h3M12 6.5 15.5 10 12 13.5M15.5 10H8"/>',
  external: '<path d="M8.5 5H5.5A1.5 1.5 0 0 0 4 6.5v8A1.5 1.5 0 0 0 5.5 16h8a1.5 1.5 0 0 0 1.5-1.5v-3M11 4h5v5M16 4l-7 7"/>',
  plus: '<path d="M10 4.5v11M4.5 10h11"/>',
  back: '<path d="M12 4.5 6.5 10l5.5 5.5"/>',
  search: '<circle cx="9" cy="9" r="5"/><path d="m13 13 3.5 3.5"/>',
  check: '<path d="m4.5 10.5 3.5 3.5 7.5-8"/>',
  close: '<path d="m5.5 5.5 9 9M14.5 5.5l-9 9"/>',
  trash: '<path d="M4.5 6h11M8 6V4.5h4V6M6 6l.7 10h6.6L14 6"/>',
  up: '<path d="m5.5 12 4.5-4.5 4.5 4.5"/>',
  down: '<path d="m5.5 8 4.5 4.5L14.5 8"/>',
  move: '<path d="M10 3v14M3 10h14M7.5 5.5 10 3l2.5 2.5M7.5 14.5 10 17l2.5-2.5M5.5 7.5 3 10l2.5 2.5M14.5 7.5 17 10l-2.5 2.5"/>',
  eye: '<path d="M2.5 10s2.8-5 7.5-5 7.5 5 7.5 5-2.8 5-7.5 5-7.5-5-7.5-5z"/><circle cx="10" cy="10" r="2.2"/>',
  upload: '<path d="M10 13V4M6.5 7.5 10 4l3.5 3.5M4 13.5V16h12v-2.5"/>',
  bold: '<path d="M6 4h4.5a3 3 0 0 1 0 6H6zM6 10h5.2a3 3 0 0 1 0 6H6z"/>',
  italic: '<path d="M12.5 4h-4M11.5 16h-4M10.8 4 9.2 16"/>',
  link: '<path d="M8.5 11.5a3 3 0 0 0 4.2 0l2.3-2.3a3 3 0 0 0-4.2-4.2l-1 1M11.5 8.5a3 3 0 0 0-4.2 0L5 10.8A3 3 0 0 0 9.2 15l1-1"/>',
  ul: '<path d="M8 6h8M8 10h8M8 14h8"/><circle cx="4.5" cy="6" r=".8"/><circle cx="4.5" cy="10" r=".8"/><circle cx="4.5" cy="14" r=".8"/>',
  ol: '<path d="M8.5 6h7.5M8.5 10h7.5M8.5 14h7.5M4 5l1-.5V8M3.8 11.5c.5-.7 2-.6 1.8.4-.1.6-1.8 1.4-1.8 2.1h2"/>',
  quote: '<path d="M5 9.5c0-2 1-3.5 3-4M5 9.5h2.5V13H4.5V9.5M11 9.5c0-2 1-3.5 3-4M11 9.5h2.5V13h-3V9.5"/>',
  undo: '<path d="M7 5 4 8l3 3M4 8h7a4.5 4.5 0 0 1 0 9H8"/>',
  redo: '<path d="m13 5 3 3-3 3M16 8H9a4.5 4.5 0 0 0 0 9h3"/>',
  minus: '<path d="M4.5 10h11"/>',
  star: '<path d="m10 3.5 2 4.3 4.6.5-3.4 3.1 1 4.6L10 13.7 5.8 16l1-4.6-3.4-3.1 4.6-.5z"/>',
  youtube: '<rect x="2.5" y="5" width="15" height="10" rx="3"/><path d="m8.5 8 3.5 2-3.5 2z"/>',
  device: '<rect x="3" y="4" width="14" height="9.5" rx="1.5"/><path d="M7.5 16.5h5"/>',
  phone: '<rect x="6.5" y="2.5" width="7" height="15" rx="2"/><path d="M9 15h2"/>',
  live: '<circle cx="10" cy="10" r="3"/>',
  table: '<rect x="3" y="4" width="14" height="12" rx="1.5"/><path d="M3 8h14M3 12h14M8 4v12"/>',
  chart: '<path d="M3.5 15.5 7.5 10l3 2.5 5.5-7"/>',
  lock: '<rect x="4.5" y="9" width="11" height="8" rx="2"/><path d="M7 9V6.5a3 3 0 0 1 6 0V9"/>',
  sparkle: '<path d="M10 3v4M10 13v4M3 10h4M13 10h4M5.5 5.5l2 2M12.5 12.5l2 2M14.5 5.5l-2 2M7.5 12.5l-2 2"/>',
};

export function icon(name, size = 20) {
  const span = document.createElement("span");
  span.className = "ic";
  span.setAttribute("aria-hidden", "true");
  span.innerHTML = `<svg viewBox="0 0 20 20" width="${size}" height="${size}" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">${ICONS[name] || ""}</svg>`;
  return span;
}

// ------------------------------------------------------------------ API
export class ApiError extends Error {
  constructor(message, status, fields) { super(message); this.status = status; this.fields = fields || {}; }
}

export async function api(method, path, body) {
  const opts = { method, headers: { Accept: "application/json" }, credentials: "same-origin" };
  if (method !== "GET") opts.headers["X-CSRF-Token"] = state.csrf;
  if (body instanceof FormData) opts.body = body;
  else if (body !== undefined) { opts.headers["Content-Type"] = "application/json"; opts.body = JSON.stringify(body); }
  let res;
  try { res = await fetch("/dashboard/api/" + path, opts); }
  catch (e) { throw new ApiError("Couldn't reach the server. Check the internet connection and try again.", 0); }
  let json = null;
  try { json = await res.json(); } catch (e) { /* not JSON */ }
  if (!res.ok || !json || !json.ok) {
    const err = json && json.error ? json.error : { message: "The server sent back an error (" + res.status + "). Try again in a moment." };
    if (res.status === 401 && path.indexOf("auth/") !== 0) window.dispatchEvent(new CustomEvent("signedout"));
    throw new ApiError(err.message, res.status, err.fields);
  }
  return json.data;
}

// ------------------------------------------------------------------ Toasts
let toastHost = null;
export function toast(message, kind = "ok") {
  if (!toastHost) { toastHost = h("div.toasts", { role: "status", "aria-live": "polite" }); document.body.append(toastHost); }
  const t = h("div.toast", { class: "toast--" + kind }, icon(kind === "error" ? "close" : "check", 16), h("span", message));
  toastHost.append(t);
  requestAnimationFrame(() => t.classList.add("is-in"));
  setTimeout(() => { t.classList.remove("is-in"); setTimeout(() => t.remove(), 300); }, kind === "error" ? 6000 : 3200);
}

// ------------------------------------------------------------------ Dialogs
// A sheet that rises from the bottom on phones and scales in on larger screens.
export function modal({ title, body, actions = [], wide = false, onClose, className = "" }) {
  const prevFocus = document.activeElement;
  const close = (value) => {
    sheet.classList.remove("is-in");
    scrim.classList.remove("is-in");
    document.removeEventListener("keydown", onKey);
    setTimeout(() => { root.remove(); if (prevFocus && prevFocus.focus) prevFocus.focus(); }, 220);
    if (onClose) onClose(value);
  };
  const onKey = (e) => { if (e.key === "Escape") close(null); };
  const scrim = h("div.modal__scrim", { onclick: () => close(null) });
  const head = h("header.modal__head", h("h2", title), h("button.icon-btn", { type: "button", "aria-label": "Close", onclick: () => close(null) }, icon("close")));
  const foot = actions.length ? h("footer.modal__foot", actions.map((a) => h("button.btn", {
    type: "button", class: a.kind ? "btn--" + a.kind : "", onclick: () => (a.onClick ? a.onClick(close) : close(a.value)),
  }, a.label))) : null;
  const sheet = h("div.modal", { class: (wide ? "modal--wide " : "") + className, role: "dialog", "aria-modal": "true", "aria-label": title }, head, h("div.modal__body", body), foot);
  const root = h("div.modal-root", scrim, sheet);
  document.body.append(root);
  document.addEventListener("keydown", onKey);
  requestAnimationFrame(() => { scrim.classList.add("is-in"); sheet.classList.add("is-in"); });
  setTimeout(() => { const f = sheet.querySelector("input, textarea, .modal__foot .btn--primary, button"); if (f) f.focus(); }, 60);
  return { close, sheet };
}

export function confirmDialog({ title, text, confirm = "Continue", danger = false }) {
  return new Promise((resolve) => {
    modal({
      title,
      body: h("p.modal__text", text),
      actions: [
        { label: "Cancel", value: false },
        { label: confirm, kind: danger ? "danger" : "primary", value: true },
      ],
      onClose: (v) => resolve(!!v),
    });
  });
}

// ------------------------------------------------------------------ Formatting
const nf = new Intl.NumberFormat("en-IN");
export const fmt = {
  num: (n) => nf.format(Math.round(n || 0)),
  compact: (n) => {
    n = Math.round(n || 0);
    if (n < 10000) return nf.format(n);
    if (n < 1e6) return (n / 1000).toFixed(n < 100000 ? 1 : 0).replace(/\.0$/, "") + "K";
    return (n / 1e6).toFixed(1).replace(/\.0$/, "") + "M";
  },
  duration: (s) => {
    s = Math.round(s || 0);
    if (s < 60) return s + "s";
    const m = Math.floor(s / 60), r = s % 60;
    if (m < 60) return m + "m " + String(r).padStart(2, "0") + "s";
    return Math.floor(m / 60) + "h " + (m % 60) + "m";
  },
  pct: (n) => Math.round(n || 0) + "%",
  date: (ymd, opts = { day: "numeric", month: "short", year: "numeric" }) => {
    if (!ymd) return "";
    const d = new Date(String(ymd).length <= 10 ? ymd + "T00:00:00" : ymd);
    return d.toLocaleDateString("en-GB", opts);
  },
  ago: (unix) => {
    const s = Math.round(Date.now() / 1000 - unix);
    if (s < 60) return "just now";
    if (s < 3600) return Math.floor(s / 60) + (Math.floor(s / 60) === 1 ? " minute ago" : " minutes ago");
    if (s < 86400) return Math.floor(s / 3600) + (Math.floor(s / 3600) === 1 ? " hour ago" : " hours ago");
    const d = Math.floor(s / 86400);
    if (d < 30) return d === 1 ? "yesterday" : d + " days ago";
    return fmt.date(new Date(unix * 1000).toISOString().slice(0, 10));
  },
  bytes: (b) => (b < 1024 * 1024 ? Math.round(b / 1024) + " KB" : (b / 1024 / 1024).toFixed(1) + " MB"),
};

export function today() {
  const d = new Date();
  return d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, "0") + "-" + String(d.getDate()).padStart(2, "0");
}

export function slugify(text) {
  return String(text || "").toLowerCase().normalize("NFKD").replace(/[̀-ͯ]/g, "")
    .replace(/[’']/g, "").replace(/&/g, " and ").replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "").slice(0, 80).replace(/-+$/, "");
}

export function debounce(fn, ms) {
  let t;
  return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

export const clone = (v) => JSON.parse(JSON.stringify(v === undefined ? null : v));
export const same = (a, b) => JSON.stringify(a) === JSON.stringify(b);

// Page header used by every view: large title, optional subtitle and actions.
export function pageHead(title, subtitle, actions) {
  return h("header.page-head",
    h("div", h("h1.page-head__title", title), subtitle ? h("p.page-head__sub", subtitle) : null),
    actions ? h("div.page-head__actions", actions) : null);
}

export function button(label, opts = {}) {
  return h("button.btn", { type: opts.type || "button", class: opts.kind ? "btn--" + opts.kind : "", onclick: opts.onClick, disabled: opts.disabled, title: opts.title },
    opts.icon ? icon(opts.icon, 18) : null, label ? h("span", label) : null);
}

export function linkButton(label, href, opts = {}) {
  return h("a.btn", { href, class: opts.kind ? "btn--" + opts.kind : "", target: opts.blank ? "_blank" : null, rel: opts.blank ? "noopener" : null },
    opts.icon ? icon(opts.icon, 18) : null, h("span", label));
}

// Segmented control (Apple's UISegmentedControl): one choice from a few.
export function segmented(options, value, onChange, label) {
  const wrap = h("div.seg", { role: "radiogroup", "aria-label": label || "" });
  const render = () => {
    clear(wrap);
    options.forEach((o) => {
      const on = o.value === value;
      wrap.append(h("button.seg__opt", {
        type: "button", role: "radio", "aria-checked": on ? "true" : "false", tabindex: on ? "0" : "-1",
        onclick: () => { value = o.value; render(); onChange(o.value); },
        onkeydown: (e) => {
          const i = options.findIndex((x) => x.value === value);
          if (e.key === "ArrowRight" || e.key === "ArrowLeft") {
            e.preventDefault();
            const n = options[(i + (e.key === "ArrowRight" ? 1 : -1) + options.length) % options.length];
            value = n.value; render(); onChange(n.value); wrap.querySelector('[aria-checked="true"]').focus();
          }
        },
      }, o.icon ? icon(o.icon, 16) : null, o.label));
    });
  };
  render();
  wrap.set = (v) => { value = v; render(); };
  return wrap;
}

export function toggle(checked, onChange, label) {
  const input = h("input", { type: "checkbox", role: "switch", checked: !!checked, onchange: () => onChange(input.checked) });
  return h("label.switch", input, h("span.switch__track", h("span.switch__thumb")), label ? h("span.switch__label", label) : null);
}

export function empty(iconName, title, text, action) {
  return h("div.empty", icon(iconName, 28), h("h3", title), text ? h("p", text) : null, action || null);
}
