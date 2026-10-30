/* Sabir Salim Shaikh — site behaviour
   One rAF loop drives every scroll scene, fed by Lenis for smooth scrolling.
   With reduced motion on, Lenis is skipped and scenes render their end state. */
(function () {
  "use strict";

  var root = document.documentElement;
  var reduced = root.classList.contains("rm");
  var clamp = function (v, a, b) { return Math.min(b === undefined ? 1 : b, Math.max(a === undefined ? 0 : a, v)); };
  var lerp = function (a, b, t) { return a + (b - a) * t; };
  var ease = function (t) { return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2; };
  var mobile = function () { return window.innerWidth <= 860; };

  /* ---------- Smooth scroll ---------- */
  var lenis = null;
  if (!reduced && window.Lenis) {
    lenis = new window.Lenis({ lerp: 0.09, wheelMultiplier: 0.95, smoothWheel: true });
    document.querySelectorAll('a[href^="#"]').forEach(function (a) {
      a.addEventListener("click", function (e) {
        var id = a.getAttribute("href");
        var el = id.length > 1 && document.querySelector(id);
        if (!el) return;
        e.preventDefault();
        lenis.scrollTo(el, { offset: -parseInt(getComputedStyle(root).getPropertyValue("--nav-h"), 10) || 0 });
      });
    });
  }

  /* ---------- Navigation ---------- */
  var nav = document.querySelector("[data-nav]");
  var toggle = document.querySelector(".nav__toggle");
  var sheet = document.querySelector(".menu-sheet");
  if (toggle && sheet) {
    var setMenu = function (open) {
      toggle.setAttribute("aria-expanded", String(open));
      sheet.classList.toggle("is-open", open);
      nav.classList.toggle("menu-open", open);
      if (lenis) open ? lenis.stop() : lenis.start();
    };
    toggle.addEventListener("click", function () { setMenu(toggle.getAttribute("aria-expanded") !== "true"); });
    sheet.addEventListener("click", function (e) { if (e.target.closest("a")) setMenu(false); });
    document.addEventListener("keydown", function (e) { if (e.key === "Escape") setMenu(false); });
  }

  /* ---------- Scenes ---------- */
  var scenes = [];
  var progressOf = function (el, pinned) {
    var r = el.getBoundingClientRect();
    var vh = window.innerHeight;
    if (pinned) return clamp(-r.top / (r.height - vh));
    return clamp((vh - r.top) / (vh + r.height));
  };
  var inView = function (el, pad) {
    var r = el.getBoundingClientRect();
    return r.bottom > -(pad || 0) && r.top < window.innerHeight + (pad || 0);
  };

  // Hero: the arch window opens around the portrait while the name parts.
  var hero = document.querySelector("[data-scene=hero]");
  if (hero) {
    var stage = hero.querySelector(".hero__stage");
    var arch = hero.querySelector(".hero__arch");
    var img = arch.querySelector("img");
    var first = hero.querySelector(".hero__first");
    var last = hero.querySelector(".hero__last");
    var fadeOut = hero.querySelectorAll(".hero__lede, .hero__cue, .hero__middle");
    var after = hero.querySelector(".hero__after");
    var fadeIn = hero.querySelectorAll(".hero__intro, .hero__social");
    var dims = {};
    var measure = function () {
      dims.w = arch.offsetWidth;
      dims.h = arch.offsetHeight;
      dims.m = mobile();
      dims.slit = dims.m ? dims.w * 0.36 : dims.w * 0.3;
      stage.style.setProperty("--slit", dims.slit + "px");
    };
    measure();
    window.addEventListener("resize", measure);

    scenes.push(function () {
      if (!inView(hero)) return;
      var p = reduced ? 1 : progressOf(hero, true);
      var e = ease(clamp(p / 0.72));
      var w = dims.w, h = dims.h;
      var side = lerp((w - dims.slit) / 2, 0, e);
      var top = lerp(h * (dims.m ? 0.3 : 0.2), 0, e);
      var bottom = lerp(h * (dims.m ? 0.26 : 0.1), 0, e);
      var radius = dims.m ? lerp(dims.slit / 2, 0, e) : lerp(dims.slit / 2, w / 2, e);
      arch.style.clipPath = "inset(" + top + "px " + side + "px " + bottom + "px " + side + "px round " + radius + "px " + radius + "px 0 0)";
      img.style.transform = "scale(" + lerp(1.22, 1, e) + ")";

      var shift = e * (dims.m ? window.innerHeight * 0.5 : window.innerWidth * 0.45);
      var nameFade = 1 - clamp(p / 0.5);
      if (dims.m) {
        first.style.transform = "translate3d(0," + -shift + "px,0)";
        last.style.transform = "translate3d(0," + shift + "px,0)";
      } else {
        first.style.transform = "translate3d(" + -shift + "px,0,0)";
        last.style.transform = "translate3d(" + shift + "px,0,0)";
      }
      first.style.opacity = last.style.opacity = nameFade;
      var lede = 1 - clamp(p / 0.18);
      fadeOut.forEach(function (n) { n.style.opacity = lede; });

      var a = clamp((p - 0.62) / 0.3);
      after.style.setProperty("--after", a);
      fadeIn.forEach(function (n, i) {
        var k = ease(clamp(a * 1.2 - i * 0.15));
        n.style.opacity = k;
        n.style.transform = "translate3d(0," + (1 - k) * 24 + "px,0)";
      });
    });
  }

  // Manifesto: words ink in as the paragraph moves up the screen.
  document.querySelectorAll("[data-scene=words]").forEach(function (el) {
    var words = [];
    el.querySelectorAll("[data-split]").forEach(function (node) {
      var parts = node.textContent.trim().split(/\s+/);
      node.textContent = "";
      parts.forEach(function (word, i) {
        var s = document.createElement("span");
        s.className = "w";
        s.textContent = word;
        node.appendChild(s);
        if (i < parts.length - 1) node.appendChild(document.createTextNode(" "));
        words.push(s);
      });
    });
    var lit = -1;
    scenes.push(function () {
      if (!inView(el, 200)) return;
      var r = el.getBoundingClientRect(), vh = window.innerHeight;
      var p = reduced ? 1 : clamp((vh * 0.85 - r.top) / (r.height * 0.9 + vh * 0.3));
      var n = Math.round(p * words.length);
      if (n === lit) return;
      lit = n;
      words.forEach(function (w, i) { w.classList.toggle("on", i < n); });
    });
  });

  // Crest tour: the camera moves across the crest, one figure at a time.
  var tour = document.querySelector("[data-scene=tour]");
  if (tour) {
    var view = tour.querySelector(".tour__view");
    var svg = view.querySelector("svg");
    var caps = tour.querySelectorAll(".tour__cap");
    var ticks = tour.querySelectorAll(".tour__progress i");
    // [centre x, centre y, span] in crest units (artwork is 1825 x 1590).
    // The span is framed inside a focus box on the left (desktop) or top (mobile).
    var views = [
      [912, 795, 1900],  // whole crest
      [300, 720, 900],   // tiger
      [1525, 700, 900],  // horse
      [912, 640, 1200],  // pen nib and book
      [912, 400, 1250],  // flags
      [912, 795, 1900]   // whole crest again
    ];
    var shown = -1;
    scenes.push(function () {
      if (!inView(tour)) return;
      var p = reduced ? 0 : progressOf(tour, true);
      var t = p * (views.length - 1);
      var i = Math.min(Math.floor(t), views.length - 2);
      var f = t - i;
      var k = f < 0.42 ? 0 : ease((f - 0.42) / 0.58);
      var a = views[i], b = views[i + 1];
      var cx = lerp(a[0], b[0], k), cy = lerp(a[1], b[1], k), s = lerp(a[2], b[2], k);
      var W = view.clientWidth, H = view.clientHeight, m = mobile() && !reduced;
      var box = m ? Math.min(W * 0.9, H * 0.42) : Math.min(W * 0.5, H * 0.78);
      var scale = box / s;
      var vw = W / scale, vh = H / scale;
      var fx = m || reduced ? 0.5 : 0.3, fy = m ? 0.3 : 0.53;
      svg.setAttribute("viewBox", (cx - fx * vw).toFixed(1) + " " + (cy - fy * vh).toFixed(1) + " " + vw.toFixed(1) + " " + vh.toFixed(1));
      var idx = f < 0.7 ? i : i + 1;
      if (idx !== shown) {
        shown = idx;
        caps.forEach(function (c, j) { c.classList.toggle("is-on", j === idx); c.setAttribute("aria-hidden", String(j !== idx)); });
      }
      ticks.forEach(function (tick, j) { tick.style.transform = "scaleX(" + clamp(t - j) + ")"; });
    });
  }

  // Gentle parallax inside framed photos.
  document.querySelectorAll("[data-parallax]").forEach(function (el) {
    var img = el.querySelector("img");
    scenes.push(function () {
      if (reduced || !inView(el, 100)) return;
      var p = progressOf(el, false);
      img.style.transform = "translate3d(0," + lerp(-15, 0, p) + "%,0)";
    });
  });

  // Instagram row drifts sideways with the scroll.
  document.querySelectorAll("[data-drift]").forEach(function (el) {
    var row = el.querySelector(".insta__row");
    scenes.push(function () {
      if (reduced || !inView(el, 100)) return;
      var p = progressOf(el, false);
      var max = Math.max(0, row.scrollWidth - window.innerWidth);
      row.style.transform = "translate3d(" + -p * max + "px,0,0)";
    });
  });

  // Nav material appears once the page has moved, and turns dark over dark sections.
  if (nav) {
    var solid = false, dark = false;
    var darkZones = document.querySelectorAll("[data-dark]");
    scenes.push(function () {
      var s = window.scrollY > 24;
      if (s !== solid) { solid = s; nav.classList.toggle("is-solid", s); }
      var mid = nav.offsetHeight / 2, d = false;
      darkZones.forEach(function (z) { var r = z.getBoundingClientRect(); if (r.top <= mid && r.bottom >= mid) d = true; });
      if (d !== dark) { dark = d; nav.classList.toggle("on-dark", d); }
    });
  }

  var frame = function (time) {
    if (lenis) lenis.raf(time);
    for (var i = 0; i < scenes.length; i++) scenes[i]();
    requestAnimationFrame(frame);
  };
  requestAnimationFrame(frame);

  /* ---------- Instagram feed ----------
     Set data-feed on the section to a JSON feed URL (Behold.so, or your own
     Instagram Graph API proxy). Until then the tiles below stay as they are. */
  var insta = document.querySelector("[data-feed]");
  if (insta && insta.dataset.feed) {
    fetch(insta.dataset.feed)
      .then(function (r) { return r.json(); })
      .then(function (json) {
        var posts = (json.posts || json.data || json || []).slice(0, 8);
        if (!posts.length) return;
        var row = insta.querySelector(".insta__row");
        row.innerHTML = "";
        posts.forEach(function (p) {
          var src = (p.sizes && p.sizes.medium && p.sizes.medium.mediaUrl) || p.thumbnailUrl || p.thumbnail_url || p.mediaUrl || p.media_url;
          if (!src) return;
          var a = document.createElement("a");
          a.className = "insta__tile";
          a.href = p.permalink;
          a.target = "_blank";
          a.rel = "noopener";
          a.dataset.track = "instagram-post";
          var caption = (p.caption || "").split("\n")[0].slice(0, 90);
          a.innerHTML = '<img loading="lazy" alt="">' + (caption ? '<span class="insta__cap"></span>' : "");
          a.querySelector("img").src = src;
          a.querySelector("img").alt = caption || "Instagram post by Sabir Salim Shaikh";
          if (caption) a.querySelector(".insta__cap").textContent = caption;
          row.appendChild(a);
        });
      })
      .catch(function () { /* keep the static tiles */ });
  }

  /* ---------- Podcast player (visual until a real episode file is wired in) ---------- */
  document.querySelectorAll(".player").forEach(function (player) {
    var btn = player.querySelector(".player__play");
    var bars = player.querySelectorAll(".wave i");
    var now = player.querySelector("[data-now]");
    var total = parseInt(player.dataset.length || "0", 10);
    var audio = player.querySelector("audio");
    var pos = 0, timer = null;
    var fmt = function (s) { return Math.floor(s / 60) + ":" + String(Math.floor(s % 60)).padStart(2, "0"); };
    var paint = function () {
      var f = total ? pos / total : 0;
      bars.forEach(function (b, i) { b.classList.toggle("done", i / bars.length < f); });
      if (now) now.textContent = fmt(pos);
    };
    btn.addEventListener("click", function () {
      var playing = player.classList.toggle("is-playing");
      btn.setAttribute("aria-label", playing ? "Pause episode" : "Play episode");
      if (audio && audio.src) {
        playing ? audio.play() : audio.pause();
        audio.ontimeupdate = function () { pos = audio.currentTime; total = audio.duration || total; paint(); };
        return;
      }
      clearInterval(timer);
      if (playing) timer = setInterval(function () { pos = Math.min(total, pos + 1); paint(); }, 1000);
    });
  });

  /* ---------- Blog listing: topic filter + search ---------- */
  var finder = document.querySelector("[data-finder]");
  if (finder) {
    var posts = document.querySelectorAll(".grid-posts .post");
    var search = finder.querySelector("input[type=search]");
    var buttons = finder.querySelectorAll("[data-topic]");
    var note = document.querySelector("[data-topic-note]");
    var empty = document.querySelector("[data-empty]");
    var params = new URLSearchParams(location.search);
    var topic = params.get("topic") || "all";
    var apply = function () {
      var q = (search.value || "").trim().toLowerCase();
      var count = 0;
      posts.forEach(function (p) {
        var ok = (topic === "all" || p.dataset.topic === topic) && (!q || p.textContent.toLowerCase().indexOf(q) > -1);
        p.hidden = !ok;
        if (ok) count++;
      });
      buttons.forEach(function (b) { b.setAttribute("aria-pressed", String(b.dataset.topic === topic)); });
      var active = finder.querySelector('[data-topic="' + topic + '"]');
      if (note) note.textContent = (active && active.dataset.about) || "";
      if (empty) empty.hidden = count > 0;
    };
    buttons.forEach(function (b) {
      b.addEventListener("click", function () {
        topic = b.dataset.topic;
        var url = new URL(location.href);
        topic === "all" ? url.searchParams.delete("topic") : url.searchParams.set("topic", topic);
        history.replaceState(null, "", url);
        apply();
      });
    });
    search.addEventListener("input", apply);
    if (params.get("q")) search.value = params.get("q");
    apply();
  }

  /* ---------- Contact form ----------
     Set data-endpoint to a form service (Formspree, Basin, or the WordPress
     handler). Without one, the form opens the visitor's email app instead. */
  var form = document.querySelector("[data-form]");
  if (form) {
    var status = form.querySelector(".form__status");
    var showError = function (field, msg) {
      var holder = field.closest(".field");
      var err = holder && holder.querySelector(".err");
      if (err) err.textContent = msg;
      field.setAttribute("aria-invalid", msg ? "true" : "false");
    };
    form.querySelectorAll("input, textarea, select").forEach(function (f) {
      f.addEventListener("blur", function () { if (f.required || f.value) showError(f, f.checkValidity() ? "" : f.dataset.msg || "Please check this field."); });
      f.addEventListener("input", function () { if (f.getAttribute("aria-invalid") === "true" && f.checkValidity()) showError(f, ""); });
    });
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var bad = null;
      form.querySelectorAll("[required]").forEach(function (f) {
        var ok = f.checkValidity();
        showError(f, ok ? "" : f.dataset.msg || "Please check this field.");
        if (!ok && !bad) bad = f;
      });
      if (bad) { bad.focus(); return; }
      if (form.querySelector(".hp input").value) { status.textContent = "Message sent. Sabir's team replies within two working days."; form.reset(); return; }
      var data = new FormData(form);
      var endpoint = form.dataset.endpoint;
      if (!endpoint) {
        var body = data.get("message") + "\n\n" + data.get("name") + "\n" + data.get("email");
        location.href = "mailto:" + form.dataset.mailto + "?subject=" + encodeURIComponent(data.get("reason") + ": " + data.get("name")) + "&body=" + encodeURIComponent(body);
        status.textContent = "Your email app should open with the message filled in.";
        return;
      }
      status.textContent = "Sending…";
      fetch(endpoint, { method: "POST", body: data, headers: { Accept: "application/json" } })
        .then(function (r) {
          if (!r.ok) throw new Error();
          status.textContent = "Message sent. Sabir's team replies within two working days.";
          form.reset();
          track("contact_form_submit", { reason: data.get("reason") });
        })
        .catch(function () { status.textContent = "That didn't go through. Email " + form.dataset.mailto + " directly and it will reach the same inbox."; });
    });
  }

  /* ---------- Copy link (article share) ---------- */
  document.querySelectorAll("[data-copy]").forEach(function (b) {
    b.addEventListener("click", function () {
      navigator.clipboard && navigator.clipboard.writeText(location.href).then(function () {
        var label = b.querySelector("span");
        var was = label.textContent;
        label.textContent = "Link copied";
        setTimeout(function () { label.textContent = was; }, 1800);
      });
    });
  });

  /* ---------- Click tracking for GA4 (requirement 9) ---------- */
  function track(name, params) { if (typeof window.gtag === "function") window.gtag("event", name, params || {}); }
  document.addEventListener("click", function (e) {
    var a = e.target.closest("[data-track]");
    if (a) track("outbound_click", { link_type: a.dataset.track, link_url: a.href || "" });
  });
})();
