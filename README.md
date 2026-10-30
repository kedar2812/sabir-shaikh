# sabirshaikh.in

Personal website for Sabir Salim Shaikh: entrepreneur, writer and podcast host.
Static HTML, CSS and a little JavaScript. No framework, no build step beyond stamping shared partials.

## Preview locally

```sh
python -m http.server 5180
# open http://localhost:5180
```

Open it through a server, not by double-clicking the HTML file. The crest is an SVG referenced with `<use>`, which browsers block on `file://`.

## Structure

| Path | What it is |
| --- | --- |
| `index.html` | Home: hero, manifesto, crest tour, about, business, blogs, podcast, featured, Instagram, contact |
| `about/` | Biography, experience, education, recognition, FAQ (ProfilePage + FAQPage schema) |
| `blogs/` | Essay listing with topic filter and search (`?topic=deals`, `?q=word`) |
| `blogs/why-i-write-every-decision-down/` | Article template: breadcrumbs, H1–H3, summary, dates, author bio, tags, references, share buttons, related posts (BlogPosting schema) |
| `contact/` | Contact form with honeypot spam trap, email and social links |
| `privacy-policy/`, `404.html` | Required pages |
| `_partials/` | Shared `<head>`, header and footer |
| `build.py` | Run after editing anything in `_partials/` to update every page |
| `assets/img/crest.svg` | The crest, vectorised from the brand kit. Colour follows CSS `color` |

## Design system

- **Colours:** haze `#ecede8`, lacquer `#10151f`, oxblood `#6e1517`, saffron `#e6a03a`, slate `#5a5f68`. Taken from the photographs and the crest.
- **Type:** Melodrama for display, Switzer for text. Both from Indian Type Foundry's Fontshare (free for commercial use), self-hosted in `assets/fonts/`.
- **Motion:** Lenis smooth scrolling plus one requestAnimationFrame loop in `assets/js/site.js` that drives every scroll scene. With `prefers-reduced-motion` on, nothing is pinned and every scene shows its final state.

## Before launch

See `CONTENT-TODO.md`.

## Moving to WordPress

The requirements recommend WordPress so articles can be added without code. This build is the finished front end: each page maps to a template (`front-page.php`, `page-about.php`, `archive.php` / `home.php`, `single.php`, `page-contact.php`, `404.php`). Topics become WordPress categories, the `tbd` fields become ACF fields, and the Instagram row can stay as is with a Behold or Smash Balloon feed URL.
