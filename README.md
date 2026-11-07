# sabirshaikh.in

Personal website for Sabir Salim Shaikh, founder, chairman and managing director of SAVIANS Group,
with a dashboard for writing essays, adding podcast episodes, editing every page and reading visit numbers.

Plain PHP (the server runs **PHP 7.3**, so no newer syntax), SQLite, and vanilla JavaScript. No framework, no build step.

## Structure

| Path | What it is |
| --- | --- |
| `index.php` | Front door for every public address (see `.htaccess`). Also receives visit counts at `/api/v`, `/api/t`, `/api/e` |
| `app/` | Shared code: database (`db.php`), the editable-content schema (`content.php`), page templates (`views/`), sign-in, photo processing, analytics. Never served |
| `app/config.local.php` | Server-only secrets and the first login. Not in git; copy `config.local.example.php` |
| `data/` | Created on the server: `site.sqlite` and sessions. Never served, never in git |
| `uploads/` | Photos uploaded from the dashboard, re-encoded as WebP |
| `dashboard/` | The dashboard: `index.php` (the page), `api.php` (JSON API), `app.css`, `js/` |
| `assets/` | Fonts, photos, crest, site CSS and JS |

## Running it locally

```sh
php -d extension=pdo_sqlite -S 127.0.0.1:5191 -t . router.php
```

`router.php` has to mirror `.htaccess`: block `app/` and `data/`, send `dashboard/api/*` to `dashboard/api.php?r=*`,
serve real files, and send everything else to `index.php`. Create `app/config.local.php` with a test login first.

## Dashboard on its own subdomain

`dashboard.sabirshaikh.in` is a subdomain in the Cloud4Host panel whose document root is this same folder
(`/www/sabirshaikh.in`), with its own free Let's Encrypt certificate. The first block in `.htaccess` makes that
host serve only the dashboard (its home page), the API, `assets/` and `uploads/`; anything else goes back to the
dashboard. One copy of the code, one database.

On the server, `app/config.local.php` sets `cookie_domain` to `.sabirshaikh.in` (one sign-in for the dashboard and
www, so previews of unsaved edits open on www) and `dashboard_url` to `https://dashboard.sabirshaikh.in`
(so `www.sabirshaikh.in/dashboard/` redirects there).

## Design system

- **Colours:** haze `#ecede8`, lacquer `#10151f`, oxblood `#6e1517`, saffron `#e6a03a`, slate `#5a5f68`.
- **Type:** Melodrama for display, Switzer for text (Indian Type Foundry, Fontshare), self-hosted in `assets/fonts/`.
- **Dashboard charts:** red, blue, saffron in that fixed order, checked for colour-blind separation in light and dark.

## Before launch

See `CONTENT-TODO.md`.
