// The writing editor. Plain contenteditable with a small toolbar; whatever is
// pasted (Word, Google Docs, a web page) is cut down to the same tags the server
// keeps (app/sanitize.php), so what you see here is what the site shows.

import { h, icon, modal, fmt } from "./lib.js";
import { pickPhoto } from "./media.js";

const KEEP = {
  P: [], H2: ["id"], H3: ["id"], H4: ["id"], STRONG: [], EM: [], U: [], A: ["href", "title", "id"],
  UL: [], OL: [], LI: ["id"], BLOCKQUOTE: [], FIGURE: [], FIGCAPTION: [], IMG: ["src", "alt", "width", "height"],
  BR: [], HR: [], SUP: ["id"], SUB: [], CITE: [], CODE: [],
};
const RENAME = { B: "STRONG", I: "EM", H1: "H2", H5: "H4", H6: "H4", DIV: "P", SECTION: "P", ARTICLE: "P" };
const DROP = new Set(["SCRIPT", "STYLE", "IFRAME", "OBJECT", "EMBED", "FORM", "INPUT", "BUTTON", "SELECT", "TEXTAREA", "SVG", "MATH", "TEMPLATE", "NOSCRIPT", "LINK", "META", "TITLE", "HEAD", "CANVAS", "VIDEO", "AUDIO"]);
const BLOCKS = new Set(["P", "H2", "H3", "H4", "UL", "OL", "BLOCKQUOTE", "FIGURE", "HR"]);

// Returns a DocumentFragment holding only allowed markup.
export function cleanFragment(html) {
  const doc = new DOMParser().parseFromString(html, "text/html");
  const out = document.createDocumentFragment();
  const walk = (node, parent) => {
    node.childNodes.forEach((n) => {
      if (n.nodeType === Node.TEXT_NODE) { parent.append(document.createTextNode(n.textContent.replace(/ /g, " "))); return; }
      if (n.nodeType !== Node.ELEMENT_NODE) return;
      let tag = n.tagName;
      if (DROP.has(tag)) return;
      const style = (n.getAttribute("style") || "").toLowerCase();
      // Google Docs wraps everything in <b style="font-weight:normal">, and marks real bold with spans.
      if (tag === "B" && /font-weight:\s*(normal|400)/.test(style)) { walk(n, parent); return; }
      if (tag === "SPAN") {
        const bold = /font-weight:\s*(bold|[6-9]00)/.test(style);
        const ital = /font-style:\s*italic/.test(style);
        if (bold || ital) {
          let target = parent;
          if (bold) { const s = document.createElement("strong"); target.append(s); target = s; }
          if (ital) { const e = document.createElement("em"); target.append(e); target = e; }
          walk(n, target);
          return;
        }
      }
      tag = RENAME[tag] || tag;
      if (!KEEP[tag]) { walk(n, parent); return; }
      if (tag === "IMG") {
        const src = n.getAttribute("src") || "";
        if (!/^\/(uploads|assets)\//.test(src)) return;
      }
      if (tag === "A") {
        const href = (n.getAttribute("href") || "").trim();
        if (!/^(https?:\/\/|mailto:|tel:|#|\/(?!\/))/i.test(href)) { walk(n, parent); return; }
      }
      const el = document.createElement(tag.toLowerCase());
      KEEP[tag].forEach((a) => { if (n.hasAttribute(a)) el.setAttribute(a, n.getAttribute(a)); });
      parent.append(el);
      walk(n, el);
    });
  };
  walk(doc.body, out);
  // A paragraph inside a list item or quote becomes its text.
  out.querySelectorAll("li > p:only-child").forEach((p) => p.replaceWith(...p.childNodes));
  return out;
}

function fragmentHtml(frag) {
  const d = document.createElement("div");
  d.append(frag);
  return d.innerHTML;
}

const BLOCK_OPTIONS = [
  { tag: "p", label: "Text" },
  { tag: "h2", label: "Heading" },
  { tag: "h3", label: "Subheading" },
  { tag: "blockquote", label: "Quote" },
];

export function richEditor(html, onChange, opts = {}) {
  const area = h("div.rte__area", {
    contenteditable: "true", role: "textbox", "aria-multiline": "true", "aria-label": opts.label || "Text",
    "data-placeholder": opts.placeholder || "Start writing…", spellcheck: "true",
  });
  area.innerHTML = html || "";
  let savedRange = null;

  const exec = (cmd, value = null) => {
    area.focus();
    restore();
    document.execCommand(cmd, false, value);
    changed();
  };

  const select = h("select.input.rte__block", { "aria-label": "Paragraph style", onchange: () => {
    exec("formatBlock", "<" + select.value + ">");
  } }, BLOCK_OPTIONS.map((o) => h("option", { value: o.tag }, o.label)));

  const btn = (name, label, onClick, key) => h("button.icon-btn", {
    type: "button", "aria-label": label, title: key ? label + " (" + key + ")" : label, "data-cmd": name,
    onmousedown: (e) => e.preventDefault(), onclick: onClick,
  }, icon(name, 18));

  const mod = /Mac|iPhone|iPad/.test(navigator.platform || "") ? "⌘" : "Ctrl+";
  const bar = h("div.rte__bar", { role: "toolbar", "aria-label": "Formatting" },
    select,
    h("span.rte__sep"),
    btn("bold", "Bold", () => exec("bold"), mod + "B"),
    btn("italic", "Italic", () => exec("italic"), mod + "I"),
    btn("link", "Link", () => editLink(), mod + "K"),
    h("span.rte__sep"),
    btn("ul", "Bulleted list", () => exec("insertUnorderedList")),
    btn("ol", "Numbered list", () => exec("insertOrderedList")),
    opts.photos === false ? null : btn("photo", "Photo", () => insertPhoto()),
    btn("minus", "Divider", () => exec("insertHorizontalRule")),
    h("span.rte__sep"),
    btn("undo", "Undo", () => exec("undo"), mod + "Z"),
    btn("redo", "Redo", () => exec("redo")));

  const count = h("span");
  const tip = h("span", opts.short ? "" : "Paste from Word or Google Docs; the formatting is tidied for you.");
  const foot = h("div.rte__foot", count, tip);
  const el = h("div.rte", { class: opts.short ? "rte--short" : "" }, bar, area, foot);

  function save() {
    const sel = getSelection();
    if (sel.rangeCount && area.contains(sel.anchorNode)) savedRange = sel.getRangeAt(0).cloneRange();
  }
  function restore() {
    if (!savedRange) return;
    const sel = getSelection();
    sel.removeAllRanges();
    sel.addRange(savedRange);
  }

  function value() {
    let out = area.innerHTML.replace(/<p><br><\/p>/g, "").replace(/&nbsp;/g, " ").trim();
    if (out === "<br>") out = "";
    return out;
  }
  function counts() {
    const text = area.innerText || "";
    const words = (text.match(/[\p{L}\p{N}’'-]+/gu) || []).length;
    count.textContent = words === 0 ? "No words yet" : fmt.num(words) + (words === 1 ? " word" : " words") + (opts.short ? "" : ", about " + Math.max(1, Math.round(words / 220)) + " min to read");
  }
  function changed() { counts(); onChange(value()); syncBar(); }

  function syncBar() {
    const sel = getSelection();
    if (!sel.rangeCount || !area.contains(sel.anchorNode)) return;
    ["bold", "italic"].forEach((c) => bar.querySelector(`[data-cmd="${c}"]`).setAttribute("aria-pressed", document.queryCommandState(c) ? "true" : "false"));
    const node = sel.anchorNode.nodeType === 1 ? sel.anchorNode : sel.anchorNode.parentElement;
    bar.querySelector('[data-cmd="link"]').setAttribute("aria-pressed", node.closest("a") && area.contains(node.closest("a")) ? "true" : "false");
    bar.querySelector('[data-cmd="ul"]').setAttribute("aria-pressed", node.closest("ul") ? "true" : "false");
    bar.querySelector('[data-cmd="ol"]').setAttribute("aria-pressed", node.closest("ol") ? "true" : "false");
    let block = node;
    while (block && block !== area && !BLOCKS.has(block.tagName)) block = block.parentElement;
    const tag = block && block !== area ? block.tagName.toLowerCase() : "p";
    select.value = BLOCK_OPTIONS.some((o) => o.tag === tag) ? tag : "p";
  }

  async function editLink() {
    save();
    const sel = getSelection();
    const node = sel.anchorNode ? (sel.anchorNode.nodeType === 1 ? sel.anchorNode : sel.anchorNode.parentElement) : null;
    const existing = node && node.closest("a") && area.contains(node.closest("a")) ? node.closest("a") : null;
    const needsText = !existing && (!savedRange || savedRange.collapsed);
    const url = h("input.input", { type: "url", id: "lnk-url", value: existing ? existing.getAttribute("href") : "", placeholder: "https://" });
    const text = needsText ? h("input.input", { type: "text", id: "lnk-text", placeholder: "What the link says" }) : null;
    const err = h("p.field__err");
    const apply = (close) => {
      let href = url.value.trim();
      if (href && !/^(https?:\/\/|mailto:|tel:|#|\/)/i.test(href)) href = (/^[^@\s]+@[^@\s]+$/.test(href) ? "mailto:" : "https://") + href;
      if (!href) { err.textContent = "Paste the address the link should open."; return; }
      close();
      area.focus();
      restore();
      if (existing) { existing.setAttribute("href", href); changed(); return; }
      if (needsText) {
        const t = (text.value.trim() || href.replace(/^https?:\/\//, "")).replace(/[&<>"]/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" }[c]));
        exec("insertHTML", `<a href="${href.replace(/"/g, "%22")}">${t}</a>`);
      } else exec("createLink", href);
    };
    const m = modal({
      title: existing ? "Edit link" : "Add a link",
      body: h("form.fields", { onsubmit: (e) => { e.preventDefault(); m.sheet.querySelector(".btn--primary").click(); } },
        text ? h("div.field", h("label.field__label", { for: "lnk-text" }, "Text"), text) : null,
        h("div.field", h("label.field__label", { for: "lnk-url" }, "Address"), url, h("p.field__help", { style: { marginTop: "6px" } }, "Links to other sites open in a new tab."), err),
        h("button", { type: "submit", hidden: true })),
      actions: [
        existing ? { label: "Remove link", onClick: (close) => { close(); area.focus(); restore(); const r = document.createRange(); r.selectNodeContents(existing); const s = getSelection(); s.removeAllRanges(); s.addRange(r); exec("unlink"); } } : null,
        { label: "Cancel", value: null },
        { label: existing ? "Update link" : "Add link", kind: "primary", onClick: apply },
      ].filter(Boolean),
    });
  }

  async function insertPhoto() {
    save();
    const p = await pickPhoto();
    if (!p) return;
    const esc = (s) => String(s || "").replace(/[&<>"]/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" }[c]));
    const src = (p.sizes || []).find((s) => s.w === 1280) ? p.sizes.find((s) => s.w === 1280).src : p.path;
    const ratio = p.width && p.height ? p.height / p.width : 0;
    const w = p.width ? Math.min(p.width, 1280) : 0;
    const dims = w ? ` width="${w}" height="${Math.round(w * ratio)}"` : "";
    area.focus();
    restore();
    exec("insertHTML", `<figure><img src="${esc(src)}" alt="${esc(p.alt)}"${dims}><figcaption>${esc(p.alt) || "Caption"}</figcaption></figure><p><br></p>`);
  }

  area.addEventListener("input", changed);
  area.addEventListener("focus", () => {
    try { document.execCommand("defaultParagraphSeparator", false, "p"); } catch (e) { /* older browser */ }
    // Typing into an empty editor starts inside a paragraph, not loose text.
    if (!area.textContent.trim() && !area.querySelector("img, hr")) {
      area.innerHTML = "<p><br></p>";
      const r = document.createRange();
      r.setStart(area.firstChild, 0);
      r.collapse(true);
      const sel = getSelection();
      sel.removeAllRanges();
      sel.addRange(r);
    }
  });
  area.addEventListener("keyup", () => { save(); syncBar(); });
  area.addEventListener("mouseup", () => { save(); syncBar(); });
  area.addEventListener("blur", save);
  area.addEventListener("keydown", (e) => {
    const k = e.key.toLowerCase();
    if ((e.metaKey || e.ctrlKey) && k === "k") { e.preventDefault(); editLink(); }
  });
  area.addEventListener("paste", (e) => {
    const cd = e.clipboardData;
    if (!cd) return;
    e.preventDefault();
    const htmlData = cd.getData("text/html");
    let out;
    if (htmlData) out = fragmentHtml(cleanFragment(htmlData));
    else {
      const text = cd.getData("text/plain") || "";
      const esc = (s) => s.replace(/[&<>]/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;" }[c]));
      const paras = text.replace(/\r\n/g, "\n").split(/\n{2,}/);
      out = paras.length > 1 ? paras.map((p) => "<p>" + esc(p).replace(/\n/g, "<br>") + "</p>").join("") : esc(text).replace(/\n/g, "<br>");
    }
    document.execCommand("insertHTML", false, out);
    changed();
  });
  // Dropped files would otherwise be shown inline as data URLs.
  area.addEventListener("drop", (e) => { if (e.dataTransfer && e.dataTransfer.files.length) e.preventDefault(); });
  document.addEventListener("selectionchange", () => { if (document.activeElement === area) syncBar(); });

  counts();
  return {
    el,
    get: value,
    set(next) { area.innerHTML = next || ""; counts(); },
    focus() { area.focus(); },
  };
}
