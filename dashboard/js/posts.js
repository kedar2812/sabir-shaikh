// Essays and podcast episodes: the two lists, and one document editor shared by
// both (title and body on the left, publishing details on the right).

import { h, clear, api, state, icon, toast, confirmDialog, fmt, pageHead, button, linkButton, segmented, toggle, empty, clone, same, today, slugify, debounce, siteUrl } from "./lib.js";
import { renderFields, wordsField, imageField } from "./fields.js";
import { richEditor } from "./editor.js";
import { getContent } from "./pages.js";
import { frameStyle } from "./positioner.js";

// ------------------------------------------------------------------ Shared
const lists = {};
export async function listCache(kind, force) {
  if (!lists[kind] || force) lists[kind] = api("GET", kind).catch((e) => { lists[kind] = null; throw e; });
  return lists[kind];
}

export function statusOf(item) {
  if (item.status !== "published") return "draft";
  if (item.published_at && item.published_at > today()) return "scheduled";
  return "published";
}
const STATUS_LABEL = { draft: "Draft", published: "Published", scheduled: "Scheduled" };
const pill = (st) => h("span.pill", { class: "pill--" + st }, STATUS_LABEL[st]);

function whenText(item) {
  const st = statusOf(item);
  if (st === "scheduled") return "Goes live " + fmt.date(item.published_at);
  if (st === "published") return fmt.date(item.published_at);
  return "Edited " + fmt.ago(item.updated_at);
}

function autoGrow(el) {
  const fit = () => { el.style.height = "auto"; el.style.height = el.scrollHeight + "px"; };
  el.addEventListener("input", fit);
  requestAnimationFrame(fit);
  new ResizeObserver(() => fit()).observe(el);
  return el;
}

// ------------------------------------------------------------------ Lists
function listView(root, kind) {
  const isPost = kind === "posts";
  const noun = isPost ? "essay" : "episode";
  let rows = [];
  let topics = {};
  let filter = "all";
  let q = "";
  const holder = h("div");
  const counts = { all: 0, published: 0, draft: 0 };
  let seg = null;
  const search = h("input.input", { type: "search", placeholder: isPost ? "Search essays" : "Search episodes", "aria-label": "Search", oninput: debounce(() => { q = search.value.trim().toLowerCase(); render(); }, 100) });
  const segHost = h("div");

  root.append(
    pageHead(isPost ? "Essays" : "Podcast",
      isPost ? "Everything on the blog. Drafts stay private until you publish them." : "Episodes on the podcast page. Paste a YouTube link and most of the details fill in.",
      [linkButton(isPost ? "Write an essay" : "Add an episode", isPost ? "#/posts/new" : "#/podcast/new", { kind: "primary", icon: "plus" })]),
    h("div.toolbar", segHost, h("div.search", icon("search", 18), search)),
    holder);
  holder.append(h("div.rows", Array.from({ length: 4 }, () => h("div.row", h("div.skel", { style: { width: "64px", height: "44px" } }), h("div", h("div.skel", { style: { height: "14px", width: "50%" } }), h("div.skel", { style: { height: "11px", width: "30%", marginTop: "8px" } }))))));

  function render() {
    counts.all = rows.length;
    counts.published = rows.filter((r) => statusOf(r) !== "draft").length;
    counts.draft = rows.filter((r) => r.status === "draft").length;
    const opts = [
      { value: "all", label: "All " + (counts.all ? counts.all : "") },
      { value: "published", label: "Live " + (counts.published ? counts.published : "") },
      { value: "draft", label: "Drafts " + (counts.draft ? counts.draft : "") },
    ];
    clear(segHost).append(seg = segmented(opts, filter, (v) => { filter = v; render(); }, "Show"));

    clear(holder);
    if (!rows.length) {
      holder.append(h("div.card", empty(isPost ? "posts" : "podcast", isPost ? "No essays yet" : "No episodes yet",
        isPost ? "Write the first one. It stays a draft until you choose to publish." : "Add the first episode from its YouTube link.",
        linkButton(isPost ? "Write an essay" : "Add an episode", isPost ? "#/posts/new" : "#/podcast/new", { kind: "primary", icon: "plus" }))));
      return;
    }
    const shown = rows.filter((r) => {
      if (filter === "published" && statusOf(r) === "draft") return false;
      if (filter === "draft" && r.status !== "draft") return false;
      if (q && !(r.title + " " + (r.guest || "") + " " + (topics[r.topic] || "")).toLowerCase().includes(q)) return false;
      return true;
    });
    if (!shown.length) {
      holder.append(h("div.card", empty("search", "Nothing matches", q ? "No " + noun + " matches “" + q + "”." : "No " + noun + "s here yet.")));
      return;
    }
    holder.append(h("div.rows", shown.map((r) => row(r))));
  }

  function row(r) {
    const st = statusOf(r);
    let thumb;
    if (isPost) {
      if (r.card === "photo" && r.cover && r.cover.src) thumb = h("div.row__thumb", h("img", { src: r.cover.src, alt: "", loading: "lazy", style: frameStyle(r.cover, { key: "d" }) }));
      else thumb = h("div.row__thumb", { class: "tone--" + (r.card_tone || "oxblood") }, h("span.row__thumb-words", r.card_text || ""));
    } else {
      thumb = h("div.row__thumb.row__thumb--arch", r.image && r.image.src ? h("img", { src: r.image.src, alt: "", loading: "lazy", style: frameStyle(r.image, { key: "d" }) }) : icon("podcast"));
    }
    const meta = isPost
      ? [topics[r.topic] || "No topic", whenText(r)].filter(Boolean).join(", ")
      : [r.guest ? "With " + r.guest : "", r.duration ? r.duration + " min" : "", whenText(r)].filter(Boolean).join(", ");
    return h("a.row", { href: (isPost ? "#/posts/" : "#/podcast/") + r.id },
      thumb,
      h("div.row__main",
        h("div.row__title", h("span", r.title || "Untitled"), r.featured ? h("span.row__star", { title: isPost ? "Lead essay on the home page" : "Featured on the home page" }, icon("star", 16)) : null, pill(st)),
        h("p.row__meta", meta)),
      h("div.row__stat", fmt.num(r.views), h("small", "views, 30 days")),
      h("div.row__stat.row__stat--b", isPost ? fmt.num(r.words) : fmt.num(r.plays), h("small", isPost ? "words" : "plays, 30 days")),
      h("span.row__go", icon("back", 18)));
  }

  Promise.all([listCache(kind, true), isPost ? getContent().catch(() => null) : Promise.resolve(null)]).then(([r, content]) => {
    rows = r;
    if (content) (content.values["blog.topics"].items || []).forEach((t) => { topics[t.slug] = t.name; });
    render();
  }).catch((e) => clear(holder).append(h("div.card", empty(isPost ? "posts" : "podcast", "Couldn't load the list", e.message))));
}

export function postsView(root) { return listView(root, "posts"); }
export function episodesView(root) { return listView(root, "episodes"); }

// ------------------------------------------------------------------ Field helpers
// A small labelled field that registers its error slot under `name`.
function fieldBox(label, control, errs, name, opts = {}) {
  const err = h("p.field__err", { role: "alert" });
  if (name) errs.set(name, err);
  const id = control.id || (control.querySelector && control.querySelector("input, select, textarea") ? control.querySelector("input, select, textarea").id : "");
  return h("div.field", { "data-name": name || null },
    h("div.field__top", id ? h("label.field__label", { for: id }, label) : h("p.field__label", label), opts.counter || null),
    opts.help ? h("p.field__help", opts.help) : null,
    control, err);
}

function counter(input, max) {
  const c = h("span.field__count");
  const upd = () => { c.textContent = input.value.length + " / " + max; c.classList.toggle("is-over", input.value.length > max); };
  input.addEventListener("input", upd);
  upd();
  return c;
}

let uid = 0;
const nid = (p) => p + "-" + (++uid);

// ------------------------------------------------------------------ Document editor
const KINDS = {
  post: { api: "posts", list: "#/posts", listLabel: "Essays", noun: "essay", base: "/blogs/", slugType: "post" },
  episode: { api: "episodes", list: "#/podcast", listLabel: "Podcast", noun: "episode", base: "/podcast/", slugType: "episode" },
};

const BLANK = {
  post: () => ({ title: "", slug: "", summary: "", topic: "", card: "words", card_text: "", card_tone: "oxblood", cover: null, cover_caption: "", body: "", tags: [], seo_title: "", seo_description: "", status: "draft", featured: 0, published_at: "" }),
  episode: () => ({ title: "", slug: "", guest: "", summary: "", body: "", video: "", duration: 0, credit: "", image: null, links: [], seo_description: "", status: "draft", featured: 0, published_at: "" }),
};

// Keeps only the fields the API takes, so "unsaved changes" compares like with like.
function pick(kind, d) {
  const out = BLANK[kind]();
  Object.keys(out).forEach((k) => { if (d[k] !== undefined && d[k] !== null) out[k] = d[k]; else if (k === "cover" || k === "image") out[k] = d[k] || null; });
  if (kind === "episode" && d.video_id !== undefined && !d.video) out.video = d.video_id;
  out.featured = d.featured ? 1 : 0;
  if (out.published_at === null) out.published_at = "";
  return out;
}

function docEditor(root, idParam, kind) {
  const K = KINDS[kind];
  const isNew = idParam === "new";
  let id = isNew ? 0 : parseInt(idParam, 10);
  let doc = null;
  let saved = null;
  let liveStatus = "draft";
  let slugTouched = false;
  let saving = false;
  const errs = new Map();
  let content = null;

  root.classList.add("view--wide");
  root.append(h("div.skel", { style: { height: "48px", marginBottom: "28px" } }), h("div.doc", h("div.skel", { style: { height: "520px" } }), h("div.skel", { style: { height: "420px" } })));

  const dirty = () => doc && !same(doc, saved);
  state.leaveGuard = () => dirty();

  // ---------------------------------------------------------------- Bar
  const stateEl = h("span.doc-bar__state");
  const barRight = h("div.doc-bar__right");
  const bar = h("div.doc-bar",
    h("div.doc-bar__left", h("a.btn.btn--quiet", { href: K.list }, icon("back", 18), h("span", K.listLabel)), stateEl),
    barRight);

  function renderBar() {
    const d = dirty();
    stateEl.classList.toggle("is-dirty", d);
    stateEl.textContent = saving ? "Saving…" : d ? "Unsaved changes" : isNew && !id ? "New " + K.noun : liveStatus === "draft" ? "Draft saved" : "Up to date";
    clear(barRight);
    barRight.append(button("Preview", { icon: "eye", kind: "quiet", onClick: preview }));
    if (liveStatus === "draft") {
      barRight.append(button("Save draft", { onClick: () => save("draft") }));
      barRight.append(button(doc.published_at && doc.published_at > today() ? "Schedule" : "Publish", { kind: "primary", onClick: () => save("published") }));
    } else {
      barRight.append(button(statusOf({ status: "published", published_at: doc.published_at }) === "scheduled" ? "Update schedule" : "Update", { kind: "primary", disabled: !d, onClick: () => save("published") }));
    }
  }

  // ---------------------------------------------------------------- Save
  function clearErrors() {
    errs.forEach((el) => { el.textContent = ""; const f = el.closest(".field, .doc__lead"); if (f) f.classList.remove("has-error"); });
  }
  function showErrors(fields) {
    let first = null;
    Object.entries(fields || {}).forEach(([name, msg]) => {
      const el = errs.get(name) || errs.get(name.split(".")[0]);
      if (!el) { toast(msg, "error"); return; }
      el.textContent = msg;
      const f = el.closest(".field, .doc__lead");
      if (f) f.classList.add("has-error");
      const det = el.closest("details");
      if (det) det.open = true;
      first = first || el;
    });
    if (first) first.closest(".field, .doc__lead").scrollIntoView({ behavior: "smooth", block: "center" });
  }

  async function save(status) {
    if (saving) return;
    const goingLive = status === "published" && liveStatus === "draft";
    if (goingLive) {
      const when = doc.published_at && doc.published_at > today() ? "It goes live on " + fmt.date(doc.published_at) + "." : "It appears on the site straight away.";
      const ok = await confirmDialog({ title: (doc.published_at > today() ? "Schedule" : "Publish") + " this " + K.noun + "?", text: "“" + (doc.title || "Untitled") + "”. " + when, confirm: doc.published_at > today() ? "Schedule" : "Publish" });
      if (!ok) return;
    }
    saving = true;
    renderBar();
    clearErrors();
    const body = Object.assign({}, doc, { status });
    try {
      const r = id ? await api("PUT", K.api + "/" + id, body) : await api("POST", K.api, body);
      const wasNew = !id;
      id = r.id;
      liveStatus = r.status;
      // Take the server's tidy versions of the address and date without disturbing the text being edited.
      doc = pick(kind, Object.assign({}, doc, { slug: r.slug, published_at: r.published_at || "", status: r.status, featured: r.featured },
        kind === "episode" ? { video: r.video_id || "" } : {}));
      saved = clone(doc);
      slugTouched = true;
      lists[K.api] = null;
      window.dispatchEvent(new CustomEvent("contentchange"));
      toast(goingLive ? (statusOf(r) === "scheduled" ? "Scheduled for " + fmt.date(r.published_at) : "Published") : status === "draft" ? "Draft saved" : "Changes are live");
      if (wasNew) history.replaceState(null, "", "#/" + (kind === "post" ? "posts" : "podcast") + "/" + id);
      renderSide();
    } catch (e) {
      if (e.status === 422) showErrors(e.fields);
      toast(e.message, "error");
    } finally {
      saving = false;
      renderBar();
    }
  }

  async function preview() {
    const win = window.open("about:blank", "_blank");
    try {
      const r = await api("POST", "preview", { kind, id, value: doc });
      if (win) win.location = r.url; else location.href = r.url;
    } catch (e) { if (win) win.close(); toast(e.message, "error"); }
  }

  async function unpublish() {
    const ok = await confirmDialog({ title: "Move back to drafts?", text: "It disappears from the site. Its address keeps working as a redirect only if you change it later.", confirm: "Move to drafts", danger: true });
    if (!ok) return;
    liveStatus = "draft";
    doc.status = "draft";
    await save("draft");
  }

  async function remove() {
    const ok = await confirmDialog({ title: "Delete this " + K.noun + "?", text: "“" + (doc.title || "Untitled") + "” is deleted for good. Anyone following an old link is sent to the " + (kind === "post" ? "blog" : "podcast") + " page.", confirm: "Delete", danger: true });
    if (!ok) return;
    try {
      await api("DELETE", K.api + "/" + id);
      lists[K.api] = null;
      window.dispatchEvent(new CustomEvent("contentchange"));
      toast(kind === "post" ? "Essay deleted" : "Episode deleted");
      state.go(K.list, { force: true });
    } catch (e) { toast(e.message, "error"); }
  }

  const change = () => renderBar();

  // ---------------------------------------------------------------- Main column
  function title() {
    const t = autoGrow(h("textarea.doc__title", { rows: 1, placeholder: kind === "post" ? "Title" : "Episode title", "aria-label": "Title", value: doc.title,
      oninput: () => {
        doc.title = t.value.replace(/\n/g, " ");
        if (!slugTouched) { doc.slug = slugify(doc.title); if (slugInput) { slugInput.value = doc.slug; checkSlug(); } }
        if (cardPreview) cardPreview();
        if (serp) serp();
        change();
      },
      onkeydown: (e) => { if (e.key === "Enter") { e.preventDefault(); summary.focus(); } } }));
    const err = h("p.field__err", { role: "alert" });
    errs.set("title", err);
    const summary = autoGrow(h("textarea.doc__summary", { rows: 1, placeholder: kind === "post" ? "A summary in two sentences. It shows on the blog card and under the title." : "What the conversation is about, in two or three sentences.", "aria-label": "Summary", value: doc.summary, oninput: () => { doc.summary = summary.value; if (serp) serp(); change(); } }));
    return h("div.doc__lead", t, err, h("div", { style: { height: "10px" } }), summary);
  }

  function bodyEditor(label, placeholder) {
    const ed = richEditor(doc.body, (html) => { doc.body = html; change(); }, { label, placeholder });
    return ed.el;
  }

  function videoCard() {
    const input = h("input.input", { id: nid("yt"), type: "url", value: doc.video ? "https://youtu.be/" + doc.video : "", placeholder: "https://www.youtube.com/watch?v=…" });
    const info = h("div");
    const show = async () => {
      const v = input.value.trim();
      clear(info);
      if (!v) { doc.video = ""; change(); return; }
      info.append(h("p.quiet", { style: { padding: "6px 0" } }, "Looking it up…"));
      try {
        const y = await api("GET", "youtube?url=" + encodeURIComponent(v));
        doc.video = y.id;
        change();
        clear(info).append(h("div.yt", h("img", { src: y.thumbnail, alt: "" }),
          h("div.yt__text", h("strong", y.title || "YouTube video"), h("span", y.channel || "YouTube"),
            h("div.yt__actions",
              y.title && y.title !== doc.title ? button("Use this title", { kind: "quiet", onClick: () => { doc.title = y.title; rebuild(); } }) : null,
              button(doc.image ? "Use the video thumbnail instead" : "Use the video thumbnail as the photo", { kind: "quiet", icon: "photo", onClick: async (e) => {
                const b = e.currentTarget;
                b.classList.add("is-busy");
                try {
                  const m = await api("POST", "media/youtube", { id: y.id });
                  doc.image = { src: m.path, alt: doc.title || y.title || "", w: m.width, h: m.height, sizes: m.sizes || [], focus: { d: { x: 50, y: 50, z: 1 } } };
                  toast("Thumbnail saved to Photos");
                  rebuild();
                } catch (ex) { toast(ex.message, "error"); b.classList.remove("is-busy"); }
              } })))));
        if (!doc.title && y.title) { doc.title = y.title; rebuild(); }
      } catch (e) {
        clear(info).append(h("p.field__err", e.message));
      }
    };
    input.addEventListener("change", show);
    input.addEventListener("paste", () => setTimeout(show, 0));
    if (doc.video) show();
    return h("section.panel", h("div.panel__head", h("h2.panel__title", "YouTube video")), h("div.panel__body",
      fieldBox("Link", input, errs, "video", { help: "Copy it from the Share button under the video. The title and thumbnail can be filled in from it." }), info));
  }

  // ---------------------------------------------------------------- Side column
  let slugInput = null;
  let slugState = null;
  let cardPreview = null;
  let serp = null;
  const side = h("div.doc__side");

  const checkSlug = debounce(async () => {
    if (!slugInput || !slugState) return;
    const v = slugInput.value.trim();
    slugState.className = "slug-state";
    if (!v) { slugState.textContent = "Filled in from the title when you save."; return; }
    if (id && saved && slugify(v) === saved.slug) { slugState.textContent = ""; return; }
    try {
      const r = await api("GET", "slug?type=" + K.slugType + "&id=" + id + "&slug=" + encodeURIComponent(v));
      if (r.available) { slugState.className = "slug-state is-ok"; slugState.replaceChildren(icon("check", 14), document.createTextNode(" " + K.base + r.slug + "/ is free")); }
      else {
        slugState.className = "slug-state is-bad";
        slugState.replaceChildren(document.createTextNode("Already used. "), h("button", { type: "button", onclick: () => { slugInput.value = r.suggestion; doc.slug = r.suggestion; slugTouched = true; change(); checkSlug(); } }, "Use " + r.suggestion));
      }
    } catch (e) { slugState.textContent = ""; }
  }, 300);

  function publishPanel() {
    const st = statusOf({ status: liveStatus, published_at: doc.published_at });
    const date = h("input.input", { id: nid("date"), type: "date", value: doc.published_at || "", oninput: () => { doc.published_at = date.value; change(); } });
    slugInput = h("input.input", { id: nid("slug"), type: "text", value: doc.slug, spellcheck: "false", autocomplete: "off",
      oninput: () => { slugTouched = true; doc.slug = slugInput.value; change(); checkSlug(); },
      onblur: () => { slugInput.value = slugify(slugInput.value); doc.slug = slugInput.value; change(); checkSlug(); } });
    slugState = h("p.slug-state");
    const live = liveStatus !== "draft";
    const url = K.base + (doc.slug || "…") + "/";
    const panel = h("section.panel", h("div.panel__head", h("h2.panel__title", "Publishing"), pill(st)),
      h("div.panel__body",
        live && st === "published" ? h("a.live-link", { href: siteUrl(url), target: "_blank", rel: "noopener", title: "Open " + url }, h("strong", "Live"), h("span", url), icon("external", 16)) : null,
        fieldBox("Date", date, errs, "published_at", { help: liveStatus === "draft" ? "Leave empty to use the day you publish. A future date schedules it." : "A future date hides it until that day." }),
        fieldBox("Web address", h("div", h("div.input-group", h("span.input-group__pre", K.base), slugInput), slugState), errs, "slug",
          { help: live ? "Changing it keeps the old address working as a redirect." : null }),
        h("div.field", toggle(doc.featured, (v) => { doc.featured = v ? 1 : 0; change(); }, kind === "post" ? "Lead essay on the home page" : "Featured on the home page"),
          h("p.field__help", { style: { marginTop: "6px" } }, kind === "post" ? "Only one essay leads at a time; turning this on moves it here." : "Without a featured episode, the newest one is shown.")),
        live ? h("button.btn.btn--quiet.btn--sm", { type: "button", style: { alignSelf: "flex-start", marginLeft: "-8px" }, onclick: unpublish }, "Move back to drafts") : null));
    checkSlug();
    return panel;
  }

  function topicPanel() {
    const topics = (content && content.values["blog.topics"].items) || [];
    const sel = h("select.input", { id: nid("topic"), onchange: () => { doc.topic = sel.value; if (cardPreview) cardPreview(); change(); } },
      h("option", { value: "" }, "No topic"), topics.map((t) => h("option", { value: t.slug, selected: t.slug === doc.topic }, t.name)));
    sel.value = doc.topic || "";
    const tags = wordsField(doc.tags || [], (v) => { doc.tags = v; change(); }, nid("tags"));
    return h("section.panel", h("div.panel__head", h("h2.panel__title", "Topic and tags")), h("div.panel__body",
      fieldBox("Topic", sel, errs, "topic", { help: h("span", "Topics are the filters on the blog page. ", h("a", { href: "#/pages/blog" }, "Edit topics")) }),
      fieldBox("Tags", tags, errs, "tags", { help: "Shown at the end of the essay. Up to 8." })));
  }

  function cardPanel() {
    const prev = h("div.cardprev");
    const tones = ["oxblood", "lacquer", "saffron", "bone"];
    const names = { oxblood: "Oxblood", lacquer: "Lacquer", saffron: "Saffron", bone: "Bone" };
    const toneRow = h("div.tones", { role: "radiogroup", "aria-label": "Colour" });
    const drawTones = () => {
      clear(toneRow);
      tones.forEach((t) => toneRow.append(h("button.tone-dot", { type: "button", role: "radio", class: "tone--" + t, "aria-checked": doc.card_tone === t ? "true" : "false", "aria-label": names[t], title: names[t], onclick: () => { doc.card_tone = t; drawTones(); cardPreview(); change(); } })));
    };
    drawTones();
    const words = h("input.input", { id: nid("cardtext"), type: "text", value: doc.card_text, maxlength: 60, placeholder: "Write it down first.", oninput: () => { doc.card_text = words.value; cardPreview(); change(); } });
    const wordsBox = h("div", { style: { display: "flex", flexDirection: "column", gap: "14px" } },
      fieldBox("Words on the card", words, errs, "card_text", { counter: counter(words, 60), help: "A short line, set large on the colour." }),
      h("div.field", h("p.field__label", { style: { marginBottom: "8px" } }, "Colour"), toneRow));
    const coverField = fieldBox("Photo", imageField(doc.cover, (v) => { doc.cover = v; cardPreview(); change(); }, content ? content.frames.cover : null, { errors: new Map() }), errs, "cover",
      { help: "Shown at the top of the essay, and on the card when the card uses the photo." });
    const caption = h("input.input", { id: nid("cap"), type: "text", value: doc.cover_caption, placeholder: "Who or what is in the photo", oninput: () => { doc.cover_caption = caption.value; change(); } });

    const kindSeg = segmented([{ value: "words", label: "Words on colour" }, { value: "photo", label: "Photo" }], doc.card, (v) => { doc.card = v; wordsBox.hidden = v !== "words"; cardPreview(); change(); }, "Card style");
    wordsBox.hidden = doc.card !== "words";

    cardPreview = () => {
      const topics = (content && content.values["blog.topics"].items) || [];
      const topicName = (topics.find((t) => t.slug === doc.topic) || {}).name || "";
      const cover = h("div.cardprev__cover", { class: doc.card === "photo" ? "" : "tone--" + doc.card_tone });
      if (doc.card === "photo") {
        if (doc.cover && doc.cover.src) cover.append(h("img", { src: doc.cover.src, alt: "", style: frameStyle(doc.cover, { key: "d" }) }));
        else cover.append(h("span.quiet", "Choose a photo below"));
      } else cover.append(h("p.cardprev__words", doc.card_text || doc.title || "Your line here"));
      clear(prev).append(cover, h("div.cardprev__text", h("p.cardprev__topic", topicName || "No topic"), h("p.cardprev__title", doc.title || "Title")));
    };
    cardPreview();
    return h("section.panel", h("div.panel__head", h("h2.panel__title", "Blog card")), h("div.panel__body",
      prev, kindSeg, wordsBox, coverField, fieldBox("Photo caption", caption, errs, "cover_caption")));
  }

  function episodePanel() {
    const dur = h("input.input", { id: nid("dur"), type: "number", min: 0, max: 1000, inputmode: "numeric", value: doc.duration || "", placeholder: "45", oninput: () => { doc.duration = parseInt(dur.value, 10) || 0; change(); } });
    const guest = h("input.input", { id: nid("guest"), type: "text", value: doc.guest, placeholder: "Name, and what they do", oninput: () => { doc.guest = guest.value; change(); } });
    const credit = h("input.input", { id: nid("credit"), type: "text", value: doc.credit, placeholder: "Recorded with…", oninput: () => { doc.credit = credit.value; change(); } });
    const photo = fieldBox("Photo", imageField(doc.image, (v) => { doc.image = v; change(); }, content ? content.frames.episode : null, { errors: new Map() }), errs, "image", { help: "Shown in the arch on the podcast page." });

    const linksBox = h("div.links-edit");
    const drawLinks = () => {
      clear(linksBox);
      doc.links.forEach((l, i) => {
        const err = h("p.field__err");
        errs.set("links." + i + ".url", err);
        const label = h("input.input", { type: "text", value: l.label, placeholder: "Spotify", "aria-label": "Link name", oninput: (e) => { l.label = e.target.value; change(); } });
        const url = h("input.input", { type: "url", value: l.url, placeholder: "https://", "aria-label": "Link address", oninput: (e) => { l.url = e.target.value; change(); } });
        linksBox.append(h("div.field", h("div.links-edit__row", label, url, h("button.icon-btn", { type: "button", "aria-label": "Remove link", onclick: () => { doc.links.splice(i, 1); drawLinks(); change(); } }, icon("trash", 16))), err));
      });
      if (doc.links.length < 6) linksBox.append(h("button.btn.btn--quiet.btn--sm", { type: "button", style: { alignSelf: "flex-start", color: "var(--accent-text)" }, onclick: () => { doc.links.push({ label: "", url: "" }); drawLinks(); change(); } }, icon("plus", 16), h("span", "Add a listening link")));
    };
    drawLinks();
    return [
      h("section.panel", h("div.panel__head", h("h2.panel__title", "Episode")), h("div.panel__body",
        fieldBox("Guest", guest, errs, "guest"),
        fieldBox("Length in minutes", dur, errs, "duration"),
        photo,
        fieldBox("Credit", credit, errs, "credit", { help: "Optional. Who produced or hosted it." }))),
      h("section.panel", h("div.panel__head", h("h2.panel__title", "Listen elsewhere")), h("div.panel__body",
        h("p.field__help", { style: { margin: 0 } }, "Spotify, Apple Podcasts or anywhere else the episode lives."), linksBox)),
    ];
  }

  function searchPanel() {
    const ctx = { frames: {}, errors: errs };
    const fields = kind === "post"
      ? [{ name: "seo_title", label: "Title in search results", type: "text", max: 70, placeholder: "Uses the essay title when empty" }, { name: "seo_description", label: "Description", type: "textarea", max: 160, rows: 3, placeholder: "Uses the summary when empty" }]
      : [{ name: "seo_description", label: "Description", type: "textarea", max: 160, rows: 3, placeholder: "Uses the summary when empty" }];
    const preview = h("div.serp");
    serp = () => {
      const t = (kind === "post" && doc.seo_title) || doc.title || "Title";
      const d = doc.seo_description || doc.summary || "The summary appears here.";
      clear(preview).append(
        h("div.serp__site", h("img", { src: "/assets/img/favicon.png", alt: "" }), h("div", h("div", "Sabir Salim Shaikh"), h("div.serp__url", "https://www.sabirshaikh.in" + K.base + (doc.slug || "") + "/"))),
        h("p.serp__title", t.length > 62 ? t.slice(0, 60) + "…" : t),
        h("p.serp__desc", d));
    };
    serp();
    const values = doc;
    const box = renderFields(fields, values, () => { serp(); change(); }, ctx);
    return h("section.panel", h("details", h("summary", h("span", "Search result"), icon("down", 18)),
      h("div.panel__body", h("p.field__help", { style: { margin: 0 } }, "How Google is likely to show it."), preview, box)));
  }

  function dangerPanel() {
    if (!id) return null;
    return h("section.panel.panel--danger", h("div.panel__body", h("button.btn.btn--ghost-danger.btn--sm", { type: "button", style: { marginLeft: "-8px" }, onclick: remove }, icon("trash", 16), h("span", "Delete this " + K.noun))));
  }

  function renderSide() {
    clear(side);
    side.append(publishPanel());
    if (kind === "post") side.append(topicPanel(), cardPanel());
    else side.append(...episodePanel());
    side.append(searchPanel());
    const d = dangerPanel();
    if (d) side.append(d);
  }

  function rebuild() {
    clear(root);
    errs.clear();
    const main = h("div.doc__main");
    if (kind === "episode") main.append(videoCard());
    main.append(title());
    main.append(bodyEditor(kind === "post" ? "Essay" : "Show notes", kind === "post" ? "Start writing. Use Heading for sections; paste from Word or Google Docs if you like." : "Show notes: what you talked about, people and books mentioned, links."));
    renderSide();
    root.append(bar, h("div.doc", main, side));
    renderBar();
  }

  const onKey = (e) => {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === "s") {
      e.preventDefault();
      if (doc) save(liveStatus === "draft" ? "draft" : "published");
    }
  };
  document.addEventListener("keydown", onKey);

  Promise.all([isNew ? Promise.resolve(null) : api("GET", K.api + "/" + id), getContent().catch(() => null)]).then(([r, c]) => {
    content = c;
    if (r) {
      doc = pick(kind, r);
      liveStatus = r.status;
      slugTouched = true;
    } else {
      doc = BLANK[kind]();
      if (kind === "post" && c) doc.topic = ((c.values["blog.topics"].items || [])[0] || {}).slug || "";
    }
    saved = clone(doc);
    rebuild();
    if (isNew) setTimeout(() => { const t = root.querySelector(kind === "post" ? ".doc__title" : ".panel input"); if (t) t.focus(); }, 50);
  }).catch((e) => {
    clear(root).append(h("a.crumb", { href: K.list }, icon("back", 18), K.listLabel), h("div.card", empty(kind === "post" ? "posts" : "podcast", "Couldn't open this " + K.noun, e.message, linkButton("Back to " + K.listLabel.toLowerCase(), K.list))));
  });

  return () => document.removeEventListener("keydown", onKey);
}

export function postEditor(root, id) { return docEditor(root, id, "post"); }
export function episodeEditor(root, id) { return docEditor(root, id, "episode"); }
