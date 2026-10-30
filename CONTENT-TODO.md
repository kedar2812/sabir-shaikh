# Content still needed from the client

Anything underlined with a dotted line on the site (class `tbd`) is a placeholder. Search the code for `tbd` to find every one.

## Facts
- [ ] Company names, one-line descriptions, roles and start years (home: Business; About: Now and Experience)
- [ ] City, sectors, degree and institution, year in business (home: About; About page)
- [ ] Biography: family background, first business, current companies (About: Biography)
- [ ] Awards and milestones (About: Recognition)
- [ ] Press, panels and podcast appearances with links (home: Featured)
- [ ] Podcast name, real episodes, guest names, audio or Spotify links (home: Podcast)

## Accounts and links
- [ ] Final domain. Every canonical URL, Open Graph tag, schema block, `sitemap.xml` and `robots.txt` uses `https://sabirshaikh.in`
- [ ] Contact email. `hello@sabirshaikh.in` is a placeholder
- [ ] Instagram, LinkedIn, X and YouTube profile URLs (header menu, footer, hero, contact page, `sameAs` in schema)
- [ ] WhatsApp business number, if he wants it shown
- [ ] Instagram feed: create a free feed at behold.so and paste the JSON URL into `data-feed=""` on the Instagram section in `index.html`
- [ ] Contact form endpoint: paste a Formspree/Basin URL into `data-endpoint=""` in `contact/index.html` (until then the form opens the visitor's email app)
- [ ] Google Analytics 4 measurement ID (uncomment the block in `_partials/head.html`, then run `python build.py`)
- [ ] Google Search Console verification tag (same file)

## Copy to approve
These were written in Sabir's voice and need his sign-off:
- [ ] Hero line, manifesto, and the four crest meanings (tiger, horse, pen and book, flags)
- [ ] The sample essay "Why I write every decision down before I make it", and the five other essay titles and summaries on `blogs/`. Only the first essay has a page; the other cards link to it for now
- [ ] Privacy policy: plain-language draft, should be checked against the law where he operates
