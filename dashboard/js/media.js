// The photo library: the Photos screen, and the picker every photo field uses.
// Uploads go up one by one with a progress bar; the server re-encodes each one
// as WebP with smaller copies for phones (app/media.php).

import { h, clear, api, state, icon, toast, modal, confirmDialog, fmt, pageHead, button, empty, debounce } from "./lib.js";

let cache = null;
export async function library(force) {
  if (!cache || force) cache = await api("GET", "media");
  return cache;
}

const MAX_BYTES = 10 * 1024 * 1024;

export function thumb(m) {
  const small = (m.sizes || []).find((s) => s.w === 640);
  return small ? small.src : m.path;
}

// Uploads one file. onProgress(0..1).
export function upload(file, onProgress) {
  return new Promise((resolve, reject) => {
    if (!/^image\/(jpeg|png|webp|gif)$/.test(file.type)) { reject(new Error(file.name + " isn't a photo the site can use. Upload a JPG, PNG or WebP.")); return; }
    if (file.size > MAX_BYTES) { reject(new Error(file.name + " is over 10 MB. Export a smaller copy and try again.")); return; }
    const fd = new FormData();
    fd.append("file", file);
    const xhr = new XMLHttpRequest();
    xhr.open("POST", "/dashboard/api/media");
    xhr.setRequestHeader("X-CSRF-Token", state.csrf);
    xhr.setRequestHeader("Accept", "application/json");
    xhr.upload.onprogress = (e) => { if (e.lengthComputable && onProgress) onProgress(e.loaded / e.total); };
    xhr.onload = () => {
      let json = null;
      try { json = JSON.parse(xhr.responseText); } catch (e) { /* not JSON */ }
      if (xhr.status === 401) window.dispatchEvent(new CustomEvent("signedout"));
      if (xhr.status >= 200 && xhr.status < 300 && json && json.ok) { if (cache) cache.unshift(json.data); resolve(json.data); }
      else reject(new Error(json && json.error ? json.error.message : "The upload didn't finish (" + xhr.status + "). Try again."));
    };
    xhr.onerror = () => reject(new Error("Couldn't reach the server. Check the internet connection and try again."));
    xhr.send(fd);
  });
}

function tile(m, opts = {}) {
  const isNew = m.created_at && Date.now() / 1000 - m.created_at < 120;
  return h("button.photo", { type: "button", "aria-pressed": opts.selected ? "true" : "false", "aria-label": (m.name || "Photo") + (m.alt ? ": " + m.alt : ""), onclick: opts.onClick, ondblclick: opts.onDouble, "data-id": m.id },
    h("div.photo__img",
      h("img", { src: thumb(m), alt: "", loading: "lazy", decoding: "async" }),
      !m.alt && opts.flagAlt ? h("span.photo__flag", "Needs a description") : isNew ? h("span.photo__flag", "Just added") : null,
      h("span.photo__check", icon("check", 14))),
    h("span.photo__name", m.name || "Untitled photo"),
    h("span.photo__meta", m.width ? fmt.num(m.width) + " × " + fmt.num(m.height) : ""));
}

function uploadingTile(file) {
  const bar = h("span.photo__bar", { style: { width: "0%" } });
  const url = URL.createObjectURL(file);
  const el = h("div.photo.photo--uploading", { "aria-label": "Uploading " + file.name },
    h("div.photo__img", h("img", { src: url, alt: "", style: { opacity: "0.55" } }), bar),
    h("span.photo__name", file.name),
    h("span.photo__meta", "Uploading…"));
  return {
    el,
    progress: (p) => { bar.style.width = Math.round(p * 100) + "%"; if (p >= 1) el.querySelector(".photo__meta").textContent = "Processing…"; },
    done: () => URL.revokeObjectURL(url),
  };
}

function filePicker(onFiles) {
  const input = h("input", { type: "file", accept: "image/jpeg,image/png,image/webp,image/gif", multiple: true, hidden: true, onchange: () => { onFiles([...input.files]); input.value = ""; } });
  return { input, open: () => input.click() };
}

function dropZone(el, onFiles) {
  let depth = 0;
  el.classList.add("drop");
  el.addEventListener("dragenter", (e) => { if (!hasFiles(e)) return; e.preventDefault(); depth++; el.classList.add("is-over"); });
  el.addEventListener("dragover", (e) => { if (hasFiles(e)) e.preventDefault(); });
  el.addEventListener("dragleave", () => { depth = Math.max(0, depth - 1); if (!depth) el.classList.remove("is-over"); });
  el.addEventListener("drop", (e) => {
    if (!hasFiles(e)) return;
    e.preventDefault(); depth = 0; el.classList.remove("is-over");
    onFiles([...e.dataTransfer.files]);
  });
}
const hasFiles = (e) => e.dataTransfer && [...(e.dataTransfer.types || [])].includes("Files");

// Uploads several files, showing each as a tile at the front of `grid`.
async function uploadAll(files, grid) {
  const done = [];
  for (const file of files) {
    const t = uploadingTile(file);
    grid.prepend(t.el);
    try {
      const m = await upload(file, t.progress);
      done.push(m);
    } catch (e) {
      toast(e.message, "error");
    } finally {
      t.done();
      t.el.remove();
    }
  }
  if (done.length) toast(done.length === 1 ? "Photo uploaded" : done.length + " photos uploaded");
  return done;
}

const matches = (m, q) => !q || ((m.name || "") + " " + (m.alt || "")).toLowerCase().includes(q.toLowerCase());

// ------------------------------------------------------------------ Picker
// Resolves with the chosen media row, or null.
export function pickPhoto() {
  return new Promise((resolve) => {
    let rows = [];
    let chosen = null;
    let q = "";
    const grid = h("div.photos");
    const status = h("p.quiet", { style: { padding: "40px 0", textAlign: "center" } }, "Loading photos…");
    const fp = filePicker(async (files) => {
      const added = await uploadAll(files, grid);
      if (added.length) { rows = await library(); chosen = added[0]; render(); }
    });
    const search = h("input.input", { type: "search", placeholder: "Search by name or description", "aria-label": "Search photos", oninput: debounce(() => { q = search.value.trim(); render(); }, 120) });
    const body = h("div.picker",
      h("div.picker-bar", h("div.search", icon("search", 18), search), button("Upload", { icon: "upload", onClick: fp.open }), fp.input),
      grid, status);
    dropZone(body, async (files) => {
      const added = await uploadAll(files, grid);
      if (added.length) { rows = await library(); chosen = added[0]; render(); }
    });

    const m = modal({
      title: "Choose a photo",
      body,
      wide: true,
      actions: [
        { label: "Cancel", value: null },
        { label: "Use photo", kind: "primary", onClick: (close) => { if (chosen) close(chosen); } },
      ],
      onClose: (v) => resolve(v || null),
    });
    const useBtn = m.sheet.querySelector(".modal__foot .btn--primary");

    function render() {
      clear(grid);
      useBtn.disabled = !chosen;
      const shown = rows.filter((r) => matches(r, q));
      status.hidden = shown.length > 0;
      if (!rows.length) status.textContent = "No photos yet. Upload one, or drop photos here.";
      else if (!shown.length) status.textContent = "No photo matches “" + q + "”.";
      shown.forEach((r) => grid.append(tile(r, {
        selected: chosen && chosen.id === r.id,
        onClick: () => { chosen = r; render(); },
        onDouble: () => { chosen = r; m.close(r); },
      })));
    }
    library().then((r) => { rows = r; render(); }).catch((e) => { status.textContent = e.message; });
    useBtn.disabled = true;
  });
}

// ------------------------------------------------------------------ Photos screen
export function mediaView(root) {
  let rows = [];
  let q = "";
  let filter = "all";
  const grid = h("div.photos");
  const count = h("span.quiet", { style: { padding: 0 } });
  const fp = filePicker(async (files) => { await uploadAll(files, grid); rows = await library(); render(); });
  const search = h("input.input", { type: "search", placeholder: "Search photos", "aria-label": "Search photos", oninput: debounce(() => { q = search.value.trim(); render(); }, 120) });
  const seg = h("div.seg", { role: "radiogroup", "aria-label": "Show" });
  const holder = h("div.stack");

  root.append(
    pageHead("Photos", "Every photo the site can use. Upload once, then choose it anywhere. Drop photos onto this page to upload them.", [button("Upload photos", { kind: "primary", icon: "upload", onClick: fp.open }), fp.input]),
    h("div.toolbar", seg, h("div", { style: { display: "flex", gap: "14px", alignItems: "center", flex: "1 1 auto", justifyContent: "flex-end" } }, count, h("div.search", icon("search", 18), search))),
    holder);
  dropZone(root, async (files) => { await uploadAll(files, grid); rows = await library(); render(); });

  function renderSeg() {
    const missing = rows.filter((r) => !r.alt).length;
    clear(seg);
    [{ v: "all", l: "All photos" }, { v: "alt", l: missing ? "Need a description (" + missing + ")" : "Need a description" }].forEach((o) => {
      seg.append(h("button.seg__opt", { type: "button", role: "radio", "aria-checked": filter === o.v ? "true" : "false", onclick: () => { filter = o.v; render(); } }, o.l));
    });
  }

  function render() {
    renderSeg();
    clear(holder);
    const shown = rows.filter((r) => matches(r, q) && (filter === "all" || !r.alt));
    count.textContent = shown.length === 1 ? "1 photo" : fmt.num(shown.length) + " photos";
    if (!rows.length) {
      holder.append(h("div.card", empty("photo", "No photos yet", "Upload portraits, event photos and essay pictures here. They're resized for phones automatically.", button("Upload photos", { kind: "primary", icon: "upload", onClick: fp.open }))));
      return;
    }
    clear(grid);
    shown.forEach((r) => grid.append(tile(r, { flagAlt: true, onClick: () => detail(r) })));
    holder.append(shown.length ? grid : h("div.card", empty("search", filter === "alt" ? "Every photo has a description" : "No photo matches “" + q + "”", filter === "alt" ? "Descriptions help screen readers and Google. Nice work." : "Try a shorter word.")));
  }

  function detail(m) {
    const name = h("input.input", { id: "md-name", type: "text", value: m.name || "" });
    const alt = h("textarea.input.input--area", { id: "md-alt", rows: 3, value: m.alt || "", placeholder: "For example: Sabir on stage at TEDx Imperial SBMS, holding the speaker memento" });
    const uses = h("ul.uses", h("li.quiet", { style: { padding: 0 } }, "Checking where it's used…"));
    let usage = null;
    api("GET", "media/" + m.id + "/usage").then((u) => {
      usage = u;
      clear(uses);
      if (!u.length) uses.append(h("li", { style: { color: "var(--ink-3)" } }, "Not used anywhere yet."));
      u.forEach((x) => uses.append(h("li", icon("check", 16), x)));
    }).catch(() => { clear(uses).append(h("li", "Couldn't check right now.")); });
    const isLaunch = m.path.indexOf("/assets/") === 0;
    const sheet = modal({
      title: m.name || "Photo",
      wide: true,
      body: h("div.photo-detail",
        h("div.photo-detail__img", h("img", { src: m.path, alt: m.alt || "" })),
        h("div.photo-detail__side",
          h("div.field", h("label.field__label", { for: "md-name" }, "Name"), h("p.field__help", "Only you see this. It helps you find the photo later."), name),
          h("div.field", h("label.field__label", { for: "md-alt" }, "Description"), h("p.field__help", "Read aloud by screen readers and used by Google. Say what's in the picture."), alt),
          h("dl", { style: { display: "grid", gap: "6px", fontSize: "14px" } },
            h("div.kv", h("span", "Size"), h("span.tnum", m.width ? fmt.num(m.width) + " × " + fmt.num(m.height) + " px" : "Unknown")),
            m.bytes ? h("div.kv", h("span", "File"), h("span.tnum", fmt.bytes(m.bytes) + ", WebP")) : null,
            h("div.kv", h("span", "Added"), h("span", isLaunch ? "With the launch design" : fmt.ago(m.created_at)))),
          h("div", h("p.field__label", { style: { marginBottom: "6px" } }, "Used on"), uses))),
      actions: [
        { label: "Delete", kind: "ghost-danger", onClick: async (close) => {
          const where = usage && usage.length ? " It's used on: " + usage.join(", ") + ". Those places will show no photo until you choose another." : "";
          const ok = await confirmDialog({ title: "Delete this photo?", text: (isLaunch ? "It leaves the library; the file stays on the server because it came with the launch design." : "The photo and its smaller copies are deleted from the server.") + where, confirm: "Delete photo", danger: true });
          if (!ok) return;
          try {
            await api("DELETE", "media/" + m.id);
            rows = rows.filter((r) => r.id !== m.id);
            if (cache) cache = cache.filter((r) => r.id !== m.id);
            close();
            render();
            toast("Photo deleted");
          } catch (e) { toast(e.message, "error"); }
        } },
        { label: "Close", value: null },
        { label: "Save", kind: "primary", onClick: async (close) => {
          try {
            const next = await api("PUT", "media/" + m.id, { name: name.value, alt: alt.value });
            Object.assign(m, next);
            close();
            render();
            toast("Photo details saved");
          } catch (e) { toast(e.message, "error"); }
        } },
      ],
    });
    sheet.sheet.querySelector(".modal__foot").firstChild.style.marginRight = "auto";
  }

  holder.append(h("div.photos", Array.from({ length: 8 }, () => h("div", h("div.skel", { style: { aspectRatio: "1" } }), h("div.skel", { style: { height: "12px", width: "60%", marginTop: "10px" } })))));
  library(true).then((r) => { rows = r; render(); }).catch((e) => { clear(holder).append(h("div.card", empty("photo", "Couldn't load the photos", e.message))); });
}
