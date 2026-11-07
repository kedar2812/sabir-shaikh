// Pages: every editable section of the site, grouped by page and built from the
// schema in app/content.php. Edits stay on screen until "Save changes"; Preview
// shows the real page with the unsaved edits applied (only to you).

import { h, clear, api, state, icon, toast, confirmDialog, fmt, pageHead, button, linkButton, empty, clone, same, siteUrl } from "./lib.js";
import { renderFields } from "./fields.js";

let contentPromise = null;
export function getContent(force) {
  if (!contentPromise || force) contentPromise = api("GET", "content").catch((e) => { contentPromise = null; throw e; });
  return contentPromise;
}

export const GROUPS = [
  { key: "home", label: "Home", icon: "home", path: "/", about: "The opening photo and tagline, the big statement, the crest, business, featured press and Instagram." },
  { key: "about", label: "About", icon: "person", path: "/about/", about: "Introduction, biography, career and recognition, and the questions Google can quote." },
  { key: "blog", label: "Blog", icon: "posts", path: "/blogs/", about: "The blog page heading and the topics essays are filed under." },
  { key: "podcast", label: "Podcast", icon: "podcast", path: "/podcast/", about: "The podcast page heading and introduction." },
  { key: "contact", label: "Contact", icon: "mail", path: "/contact/", about: "The opening paragraph and the choices in the contact form." },
  { key: "privacy", label: "Privacy policy", icon: "shield", path: "/privacy-policy/", about: "The policy text and the date it was last updated." },
  { key: "site", label: "Site details", icon: "site", path: null, about: "Name, email, WhatsApp, social profiles, the author box and how links to the site look when shared." },
];

function stampText(ts) {
  return ts ? "Edited " + fmt.ago(ts) : "Launch copy";
}

// ------------------------------------------------------------------ Index
export function pagesIndex(root) {
  const grid = h("div.pages-grid");
  root.append(pageHead("Pages", "Change the words and photos on every page. Nothing goes live until you save, and you can preview first."), grid);
  GROUPS.forEach(() => grid.append(h("div.skel", { style: { height: "168px", borderRadius: "16px" } })));
  getContent(true).then((d) => {
    clear(grid);
    const saved = {};
    d.saved.forEach((s) => { saved[s.key] = s.updated_at; });
    GROUPS.forEach((g) => {
      const sections = d.schema.filter((s) => s.group === g.key);
      const last = Math.max(0, ...sections.map((s) => saved[s.key] || 0));
      grid.append(h("a.page-tile", { href: "#/pages/" + g.key },
        h("span.page-tile__icon", icon(g.icon, 22)),
        h("div", h("h2", g.label), h("p", g.about)),
        h("div.page-tile__foot", h("span", sections.length === 1 ? "1 section" : sections.length + " sections"), h("span", last ? "Edited " + fmt.ago(last) : "Launch copy"))));
    });
  }).catch((e) => clear(grid).append(h("div.card", empty("page", "Couldn't load the pages", e.message))));
}

// ------------------------------------------------------------------ One page
export function pagesView(root, groupKey) {
  const group = GROUPS.find((g) => g.key === groupKey);
  if (!group) { state.go("#/pages", { replace: true }); return; }
  root.classList.add("view--wide");

  const switcher = h("div.seg.pages__switch", { role: "tablist", "aria-label": "Pages" }, GROUPS.map((g) =>
    h("a.seg__opt", { href: "#/pages/" + g.key, role: "tab", "aria-checked": g.key === groupKey ? "true" : "false", style: { textDecoration: "none" } }, g.label)));
  const previewBtn = button("Preview", { icon: "eye", onClick: () => preview() });
  root.append(
    h("a.crumb", { href: "#/pages" }, icon("back", 18), "Pages"),
    pageHead(group.label, group.about, [previewBtn, group.path ? linkButton("Open page", siteUrl(group.path), { icon: "external", blank: true }) : null]),
    switcher);
  const layout = h("div.pages");
  root.append(layout);
  layout.append(h("div.skel", { style: { height: "300px" } }), h("div.skel", { style: { height: "600px" } }));

  let sections = [];
  const sv = {}; // key -> { values, saved, ctx, card, stamp, tocLink, errs, updated_at }
  let observer = null;

  const savebarText = h("span.savebar__text");
  const saveBtn = h("button.btn.btn--primary", { type: "button", onclick: () => saveAll() }, "Save changes");
  const savebar = h("div.savebar", { role: "region", "aria-label": "Unsaved changes" },
    savebarText,
    h("button.btn.btn--quiet", { type: "button", onclick: () => discard() }, "Discard"),
    h("button.btn", { type: "button", onclick: () => preview() }, icon("eye", 18), h("span", "Preview")),
    saveBtn);
  document.body.append(savebar);

  const dirtyKeys = () => sections.filter((s) => !same(sv[s.key].values, sv[s.key].saved)).map((s) => s.key);
  state.leaveGuard = () => dirtyKeys().length > 0;

  function refresh() {
    const dirty = dirtyKeys();
    sections.forEach((s) => {
      const x = sv[s.key];
      const on = dirty.includes(s.key);
      x.card.classList.toggle("is-dirty", on);
      x.stamp.textContent = on ? "Unsaved changes" : stampText(x.updated_at);
      x.tocDot.hidden = !on;
    });
    savebarText.textContent = dirty.length === 1 ? "1 section has unsaved changes" : dirty.length + " sections have unsaved changes";
    savebar.classList.toggle("is-on", dirty.length > 0);
  }

  function clearErrors(key) {
    const x = sv[key];
    x.ctx.errors.forEach((el) => { el.textContent = ""; const f = el.closest(".field"); if (f) f.classList.remove("has-error"); });
    clear(x.errs);
  }
  function showErrors(key, fields) {
    const x = sv[key];
    let first = null;
    Object.entries(fields || {}).forEach(([path, msg]) => {
      const el = x.ctx.errors.get(path);
      if (el && document.body.contains(el)) {
        el.textContent = msg;
        const f = el.closest(".field");
        if (f) f.classList.add("has-error");
        first = first || el;
      } else {
        x.errs.append(h("p.field__err", msg));
        first = first || x.errs;
      }
    });
    return first;
  }

  async function saveAll() {
    const keys = dirtyKeys();
    if (!keys.length) return;
    saveBtn.classList.add("is-busy");
    let failed = 0, firstErr = null;
    for (const key of keys) {
      clearErrors(key);
      try {
        const sent = clone(sv[key].values);
        const r = await api("PUT", "content/" + key, sent);
        // The form stays bound to sv[key].values, so that object is only
        // replaced when the card is rebuilt around the new one.
        sv[key].saved = clone(r.values);
        sv[key].updated_at = r.updated_at;
        const d = await getContent();
        d.values[key] = clone(r.values);
        // The server tidied something (a link, a web address word): show its version.
        if (!same(sent, r.values)) {
          sv[key].values = clone(r.values);
          const s = sections.find((x) => x.key === key);
          const old = sv[key].card;
          makeCard(s, d.frames);
          old.replaceWith(sv[key].card);
          if (observer) { observer.unobserve(old); observer.observe(sv[key].card); }
        }
      } catch (e) {
        failed++;
        if (e.status === 422) firstErr = firstErr || showErrors(key, e.fields);
        else toast(e.message, "error");
      }
    }
    saveBtn.classList.remove("is-busy");
    contentPromise = null;
    refresh();
    if (failed) {
      toast(failed === 1 ? "One section needs a look before it can be saved" : failed + " sections need a look before they can be saved", "error");
      if (firstErr) firstErr.closest(".section-card, .field").scrollIntoView({ behavior: "smooth", block: "center" });
    } else {
      toast(keys.length === 1 ? "Saved. The page is updated." : "Saved " + keys.length + " sections. The page is updated.");
    }
  }

  async function discard() {
    const ok = await confirmDialog({ title: "Discard changes?", text: "Every unsaved edit on this page goes back to what's live on the site.", confirm: "Discard", danger: true });
    if (!ok) return;
    sections.forEach((s) => { sv[s.key].values = clone(sv[s.key].saved); });
    build();
    toast("Changes discarded");
  }

  async function preview() {
    const win = window.open("about:blank", "_blank");
    const draft = {};
    dirtyKeys().forEach((k) => { draft[k] = sv[k].values; });
    try {
      const r = await api("POST", "preview", { kind: "content", group: groupKey, sections: draft });
      if (win) win.location = r.url; else location.href = r.url;
    } catch (e) {
      if (win) win.close();
      toast(e.message, "error");
    }
  }

  function makeCard(s, frames) {
    const x = sv[s.key];
    x.ctx = { frames, errors: new Map() };
    x.stamp = h("span.section-card__stamp");
    x.errs = h("div");
    x.card = h("section.card.section-card", { id: "sec-" + s.key, "aria-labelledby": "sh-" + s.key },
      h("header.card__head", h("div", h("h2.card__title", { id: "sh-" + s.key }, s.label), s.intro ? h("p.card__sub", s.intro) : null), x.stamp),
      h("div.section-card__body", renderFields(s.fields, x.values, () => refresh(), x.ctx), x.errs));
  }

  function build() {
    if (observer) observer.disconnect();
    clear(layout);
    const toc = h("nav.pages__toc", { "aria-label": "Sections" }, h("p.pages__toc-title", "On this page"));
    const list = h("div.stack");
    getContent().then((d) => {
      sections.forEach((s) => {
        const x = sv[s.key];
        x.tocDot = h("i", { hidden: true, "aria-label": "Unsaved" });
        const link = h("a", { href: "#sec-" + s.key, onclick: (e) => { e.preventDefault(); x.card.scrollIntoView({ behavior: "smooth", block: "start" }); } }, h("span", s.label), x.tocDot);
        x.tocLink = link;
        toc.append(link);
        makeCard(s, d.frames);
        list.append(x.card);
      });
      layout.append(toc, list);
      refresh();
      observer = new IntersectionObserver((entries) => {
        entries.forEach((en) => {
          if (!en.isIntersecting) return;
          const key = en.target.id.slice(4);
          sections.forEach((s) => sv[s.key].tocLink.classList.toggle("is-on", s.key === key));
        });
      }, { rootMargin: "-20% 0px -70% 0px" });
      sections.forEach((s) => observer.observe(sv[s.key].card));
    });
  }

  getContent(true).then((d) => {
    sections = d.schema.filter((s) => s.group === groupKey);
    const saved = {};
    d.saved.forEach((s) => { saved[s.key] = s.updated_at; });
    sections.forEach((s) => { sv[s.key] = { values: clone(d.values[s.key]), saved: clone(d.values[s.key]), updated_at: saved[s.key] || 0 }; });
    build();
  }).catch((e) => clear(layout).append(h("div.card", empty("page", "Couldn't load this page", e.message))));

  const onKey = (e) => {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === "s") { e.preventDefault(); saveAll(); }
  };
  document.addEventListener("keydown", onKey);
  return () => {
    document.removeEventListener("keydown", onKey);
    savebar.remove();
    if (observer) observer.disconnect();
  };
}
