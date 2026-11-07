// Photo framing. Each frame (app/content.php content_frames) has one or more
// views; for each, the owner drags the photo and sets a zoom. The result is a
// focus point (x, y in %) and zoom per view, which the site applies with
// object-position + scale around the same point (img.fx in site.css).
// The geometry below reproduces that CSS exactly, so the preview is the site.

import { h, icon, modal, clone } from "./lib.js";

const SHAPES = { rect: "10px", arch: "999px 999px 0 0", tile: "6px", circle: "50%" };

function focusFor(v, key) {
  const f = (v && v.focus) || {};
  return f[key] || f.d || { x: 50, y: 50, z: 1 };
}

// Style for an <img> filling a frame of the given view, as the site draws it.
export function frameStyle(v, view) {
  const f = focusFor(v, view && view.key ? view.key : "d");
  const st = {
    objectFit: "cover",
    objectPosition: f.x + "% " + f.y + "%",
    transformOrigin: f.x + "% " + f.y + "%",
    scale: String(f.z || 1),
  };
  if (view && view.tall) { st.height = view.tall * 100 + "%"; st.position = "relative"; st.top = -((view.tall - 1) / 2) * 100 + "%"; }
  return st;
}

// Where the photo sits inside a W x H frame box: { left, top, width, height } in px.
function geometry(W, H, iw, ih, f) {
  const s0 = Math.max(W / iw, H / ih);
  const rw = iw * s0, rh = ih * s0;
  const z = f.z || 1;
  const px = (W * f.x) / 100, py = (H * f.y) / 100;
  const left1 = ((W - rw) * f.x) / 100, top1 = ((H - rh) * f.y) / 100;
  return { left: px + (left1 - px) * z, top: py + (top1 - py) * z, width: rw * z, height: rh * z, rw };
}

export function positionPhoto(v, frame) {
  return new Promise((resolve) => {
    const focus = clone(v.focus || {});
    frame.views.forEach((vw) => { if (!focus[vw.key]) focus[vw.key] = clone(focusFor(v, "d")); });
    const views = [];
    let result = null;

    const body = h("div",
      h("div.pos", { style: { "--views": String(frame.views.length) } }, frame.views.map((vw) => {
        const view = buildView(vw);
        views.push(view);
        return view.el;
      })),
      h("p.pos__hint", "Drag the photo to choose what shows. Use the slider, or scroll, to zoom. Arrow keys nudge it."));

    function buildView(vw) {
      const f = focus[vw.key];
      const stage = h("div.pos__stage", { tabindex: "0", role: "application", "aria-label": vw.label + ": drag or use arrow keys to move the photo" });
      const ghost = h("img.pos__ghost", { src: v.src, alt: "", draggable: "false" });
      const inner = h("img", { src: v.src, alt: "", draggable: "false" });
      const frameEl = h("div.pos__frame", { style: { borderRadius: SHAPES[vw.shape] || "10px" } }, inner, h("div.pos__grid"));
      stage.append(ghost, frameEl);
      const zoom = h("input", { type: "range", min: "1", max: "3", step: "0.01", value: String(f.z || 1), "aria-label": "Zoom for " + vw.label, oninput: () => { f.z = parseFloat(zoom.value); draw(); } });
      const reset = h("button.btn.btn--quiet.btn--sm", { type: "button", onclick: () => { f.x = 50; f.y = 50; f.z = 1; zoom.value = "1"; draw(); } }, "Centre");

      let box = null;
      const nat = { w: v.w || 0, h: v.h || 0 };
      const measure = () => {
        const sw = stage.clientWidth, sh = stage.clientHeight;
        if (!sw || !nat.w) return;
        // Fit the frame inside the stage with room for the faint surroundings.
        const ratio = vw.ratio;
        let fh = sh * 0.84, fw = fh * ratio;
        if (fw > sw * 0.84) { fw = sw * 0.84; fh = fw / ratio; }
        const tall = vw.tall || 1;
        box = { W: fw, H: fh * tall, frameH: fh, offTop: -((tall - 1) / 2) * fh, fx: (sw - fw) / 2, fy: (sh - fh) / 2 };
        frameEl.style.width = fw + "px";
        frameEl.style.height = fh + "px";
        draw();
      };
      const draw = () => {
        if (!box) return;
        const g = geometry(box.W, box.H, nat.w, nat.h, f);
        Object.assign(inner.style, { left: g.left + "px", top: g.top + box.offTop + "px", width: g.width + "px", height: g.height + "px" });
        Object.assign(ghost.style, { left: box.fx + g.left + "px", top: box.fy + g.top + box.offTop + "px", width: g.width + "px", height: g.height + "px" });
        zoom.value = String(f.z || 1);
      };
      const move = (dx, dy) => {
        if (!box) return;
        const g = geometry(box.W, box.H, nat.w, nat.h, f);
        const spanX = box.W - g.width, spanY = box.H - g.height;
        if (spanX < -0.5) f.x = clamp(f.x + (dx * 100) / spanX);
        if (spanY < -0.5) f.y = clamp(f.y + (dy * 100) / spanY);
        f.x = round(f.x); f.y = round(f.y);
        draw();
      };

      let drag = null;
      stage.addEventListener("pointerdown", (e) => {
        drag = { x: e.clientX, y: e.clientY };
        stage.setPointerCapture(e.pointerId);
        stage.classList.add("is-dragging");
      });
      stage.addEventListener("pointermove", (e) => {
        if (!drag) return;
        move(e.clientX - drag.x, e.clientY - drag.y);
        drag = { x: e.clientX, y: e.clientY };
      });
      const end = () => { drag = null; stage.classList.remove("is-dragging"); };
      stage.addEventListener("pointerup", end);
      stage.addEventListener("pointercancel", end);
      stage.addEventListener("wheel", (e) => {
        e.preventDefault();
        f.z = Math.max(1, Math.min(3, round((f.z || 1) * (e.deltaY < 0 ? 1.06 : 1 / 1.06), 3)));
        draw();
      }, { passive: false });
      stage.addEventListener("keydown", (e) => {
        const step = e.shiftKey ? 10 : 2;
        const map = { ArrowLeft: [step, 0], ArrowRight: [-step, 0], ArrowUp: [0, step], ArrowDown: [0, -step] };
        if (map[e.key]) { e.preventDefault(); move(...map[e.key]); }
        if (e.key === "+" || e.key === "=") { f.z = Math.min(3, (f.z || 1) + 0.05); draw(); }
        if (e.key === "-") { f.z = Math.max(1, (f.z || 1) - 0.05); draw(); }
      });

      const ro = new ResizeObserver(measure);
      const start = () => { if (!nat.w) { nat.w = inner.naturalWidth; nat.h = inner.naturalHeight; } ro.observe(stage); measure(); };
      if (inner.complete && inner.naturalWidth) requestAnimationFrame(start);
      else inner.addEventListener("load", start, { once: true });

      return {
        el: h("div.pos__view",
          h("p.pos__label", vw.label),
          stage,
          h("div.pos__zoom", icon("minus", 16), zoom, icon("plus", 16), reset)),
        stop: () => ro.disconnect(),
      };
    }

    modal({
      title: frame.label ? "Frame the " + frame.label.toLowerCase() : "Frame the photo",
      body,
      wide: true,
      className: frame.views.length > 1 ? "modal--photo" : "",
      actions: [
        { label: "Cancel", value: null },
        { label: "Use this framing", kind: "primary", onClick: (close) => { result = focus; close(focus); } },
      ],
      onClose: () => { views.forEach((x) => x.stop()); resolve(result); },
    });
  });
}

function clamp(n) { return Math.max(0, Math.min(100, n)); }
function round(n, d = 2) { const p = Math.pow(10, d); return Math.round(n * p) / p; }
