// Form fields generated from the content schema (app/content.php). Each field
// gets its current value and a setter; nothing here talks to the server except
// through the photo picker.

import { h, clear, icon, button, clone } from "./lib.js";
import { richEditor } from "./editor.js";
import { pickPhoto } from "./media.js";
import { positionPhoto, frameStyle } from "./positioner.js";

// ctx: { frames, errors: Map(path -> element) }
export function renderFields(fields, values, onChange, ctx, prefix = "") {
  const box = h("div.fields");
  fields.forEach((f) => {
    box.append(field(f, values[f.name], (v) => { values[f.name] = v; onChange(values); }, ctx, prefix + f.name));
  });
  return box;
}

function field(f, value, set, ctx, path) {
  const id = "f-" + path.replace(/[^a-z0-9]+/gi, "-") + "-" + Math.random().toString(36).slice(2, 6);
  const err = h("p.field__err", { role: "alert" });
  ctx.errors.set(path, err);
  let control;
  let counter = null;

  const count = (el) => {
    if (!f.max) return;
    counter = counter || h("span.field__count");
    const n = el.value.length;
    counter.textContent = n + " / " + f.max;
    counter.classList.toggle("is-over", n > f.max);
  };

  switch (f.type) {
    case "text":
    case "email":
    case "url":
    case "date":
    case "slug": {
      control = h("input.input", {
        id, type: f.type === "slug" || f.type === "text" ? "text" : f.type, value: value || "", placeholder: f.placeholder || (f.type === "url" ? "https://" : ""),
        autocomplete: "off", spellcheck: f.type === "text" ? "true" : "false",
        oninput: () => { set(control.value); count(control); },
      });
      if (f.type === "slug") control.addEventListener("blur", () => { control.value = slugifyLocal(control.value); set(control.value); });
      count(control);
      break;
    }
    case "textarea": {
      control = h("textarea.input.input--area", { id, rows: f.rows || 3, value: value || "", placeholder: f.placeholder || "", oninput: () => { set(control.value); count(control); grow(control); } });
      count(control);
      requestAnimationFrame(() => grow(control));
      break;
    }
    case "richtext": {
      const ed = richEditor(value || "", (html) => set(html), { label: f.label });
      control = ed.el;
      break;
    }
    case "words":
      control = wordsField(value || [], set, id);
      break;
    case "image":
      control = imageField(value, set, f.frame ? ctx.frames[f.frame] : null, ctx);
      break;
    case "list":
      control = listField(f, value || [], set, ctx, path);
      break;
    default:
      control = h("p", "Unsupported field: " + f.type);
  }

  const isGroup = f.type === "list" || f.type === "image" || f.type === "words" || f.type === "richtext";
  const label = isGroup ? h("p.field__label", { id: id + "-l" }, f.label) : h("label.field__label", { for: id }, f.label);
  return h("div.field", { class: "field--" + f.type, "data-path": path, role: isGroup ? "group" : null, "aria-labelledby": isGroup ? id + "-l" : null },
    h("div.field__top", label, counter),
    f.help ? h("p.field__help", f.help) : null,
    control,
    err);
}

function grow(el) {
  el.style.height = "auto";
  el.style.height = Math.min(el.scrollHeight + 2, 640) + "px";
}

function slugifyLocal(t) {
  return String(t || "").toLowerCase().replace(/[’']/g, "").replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "").slice(0, 80);
}

// ------------------------------------------------------------------ Words (chips)
export function wordsField(words, set, id) {
  words = [...words];
  const list = h("ul.chips");
  const input = h("input.chips__input", {
    id, type: "text", placeholder: "Type and press Enter",
    onkeydown: (e) => {
      if ((e.key === "Enter" || e.key === ",") && input.value.trim()) { e.preventDefault(); add(input.value); }
      else if (e.key === "Backspace" && !input.value && words.length) { words.pop(); commit(); }
    },
    onblur: () => { if (input.value.trim()) add(input.value); },
  });
  const add = (w) => { words.push(w.trim().replace(/,$/, "")); input.value = ""; commit(); };
  const commit = () => { set([...words]); render(); };
  const render = () => {
    clear(list);
    words.forEach((w, i) => list.append(h("li.chip", h("span", w),
      h("button.chip__x", { type: "button", "aria-label": "Remove " + w, onclick: () => { words.splice(i, 1); commit(); } }, icon("close", 14)))));
    list.append(h("li.chips__add", input));
  };
  render();
  return h("div.chips-box", { onclick: (e) => { if (e.target === e.currentTarget || e.target === list) input.focus(); } }, list);
}

// ------------------------------------------------------------------ Photo
export function imageField(value, set, frame, ctx, opts = {}) {
  let v = value ? clone(value) : null;
  const box = h("div.photo-field");
  const render = () => {
    clear(box);
    const view = frame ? frame.views[0] : { ratio: v && v.w && v.h ? v.w / v.h : 16 / 9, shape: "rect", key: "d" };
    const thumb = h("div.photo-field__frame", { class: "shape--" + view.shape, style: { aspectRatio: String(view.ratio) } });
    if (v && v.src) thumb.append(h("img", { src: v.src, alt: "", style: frameStyle(v, view) }));
    else thumb.append(h("span.photo-field__none", icon("photo", 28), "No photo"));

    const actions = h("div.photo-field__actions",
      button(v && v.src ? "Replace" : "Choose photo", { icon: "photo", onClick: async () => {
        const m = await pickPhoto();
        if (!m) return;
        const keepFocus = v && v.focus ? v.focus : null;
        v = { src: m.path, alt: m.alt || (v ? v.alt : ""), w: m.width, h: m.height, sizes: (m.sizes || []).map((s) => ({ w: s.w, src: s.src })), focus: keepFocus || { d: { x: 50, y: 50, z: 1 } } };
        if (frame && frame.views.length > 1) v.focus.m = v.focus.m || { x: 50, y: 50, z: 1 };
        set(clone(v)); render();
      } }),
      frame && v && v.src ? button("Adjust framing", { icon: "move", onClick: async () => {
        const focus = await positionPhoto(v, frame);
        if (!focus) return;
        v.focus = focus; set(clone(v)); render();
      } }) : null,
      v && v.src && !opts.required ? button("", { icon: "trash", kind: "quiet", title: "Remove photo", onClick: () => { v = null; set(null); render(); } }) : null);

    const alt = v && v.src ? h("div.photo-field__alt",
      h("label", { for: "alt-" + box.dataset.k }, "Describe the photo"),
      h("input.input", { id: "alt-" + box.dataset.k, type: "text", value: v.alt || "", placeholder: "What's in the picture, for screen readers and Google", oninput: (e) => { v.alt = e.target.value; set(clone(v)); } })) : null;

    box.append(h("div.photo-field__row", thumb, h("div.photo-field__side",
      actions,
      frame && v && v.src ? h("p.field__help", frame.views.length > 1 ? "Framing is set separately for " + frame.views.map((x) => x.label.toLowerCase()).join(" and ") + "." : "Drag the photo to choose which part shows.") : null,
      alt)));
  };
  box.dataset.k = Math.random().toString(36).slice(2, 7);
  render();
  return box;
}

// ------------------------------------------------------------------ Repeating list
function listField(f, items, set, ctx, path) {
  items = clone(items) || [];
  const box = h("div.list");
  const open = new Set();
  const titleOf = (it, i) => {
    const t = f.item && it[f.item];
    if (t && typeof t === "string") return t;
    if (t && t.alt) return t.alt;
    return "Item " + (i + 1);
  };
  const commit = () => set(clone(items));
  const render = () => {
    clear(box);
    items.forEach((it, i) => {
      const isOpen = open.has(i);
      const body = isOpen ? renderFields(f.fields, it, () => { commit(); head.querySelector(".list__title").textContent = titleOf(it, i); }, ctx, path + "." + i + ".") : null;
      const head = h("div.list__head",
        h("button.list__toggle", { type: "button", "aria-expanded": isOpen ? "true" : "false", onclick: () => { isOpen ? open.delete(i) : open.add(i); render(); } },
          icon(isOpen ? "down" : "move", 16), h("span.list__title", titleOf(it, i))),
        h("div.list__tools",
          f.fixed ? null : h("button.icon-btn", { type: "button", "aria-label": "Move up", disabled: i === 0, onclick: () => { swap(i, i - 1); } }, icon("up", 16)),
          f.fixed ? null : h("button.icon-btn", { type: "button", "aria-label": "Move down", disabled: i === items.length - 1, onclick: () => { swap(i, i + 1); } }, icon("down", 16)),
          f.fixed ? null : h("button.icon-btn", { type: "button", "aria-label": "Remove " + titleOf(it, i), onclick: () => { items.splice(i, 1); remap(i); commit(); render(); } }, icon("trash", 16))));
      head.querySelector(".list__toggle .ic").replaceWith(icon(isOpen ? "down" : "up", 16));
      head.querySelector(".list__toggle .ic").style.transform = isOpen ? "" : "rotate(90deg)";
      box.append(h("div.list__item", { class: isOpen ? "is-open" : "" }, head, body ? h("div.list__body", body) : null));
    });
    if (!f.fixed && (!f.max || items.length < f.max)) {
      box.append(button("Add " + (f.addLabel || singular(f.label)), { icon: "plus", kind: "quiet", onClick: () => {
        const blank = {};
        f.fields.forEach((sf) => { blank[sf.name] = sf.type === "list" || sf.type === "words" ? [] : sf.type === "image" ? null : ""; });
        items.push(blank); open.add(items.length - 1); commit(); render();
      } }));
    }
    if (!items.length) box.prepend(h("p.list__empty", "Nothing here yet. This part is hidden on the site until it has an entry."));
  };
  const swap = (a, b) => {
    [items[a], items[b]] = [items[b], items[a]];
    const oa = open.has(a), ob = open.has(b);
    open.delete(a); open.delete(b);
    if (oa) open.add(b);
    if (ob) open.add(a);
    commit(); render();
  };
  const remap = (removed) => {
    const next = [...open].filter((i) => i !== removed).map((i) => (i > removed ? i - 1 : i));
    open.clear(); next.forEach((i) => open.add(i));
  };
  render();
  return box;
}

function singular(label) {
  const l = label.toLowerCase();
  const map = { "companies": "a company", "headline numbers": "a number", "quick facts": "a fact", "questions and answers": "a question", "press, talks and awards": "an entry", "topics": "a topic", "links": "a link", "posts shown": "a post", now: "an entry", experience: "a role", education: "a qualification", recognition: "an entry" };
  return map[l] || "an entry";
}
