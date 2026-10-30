# Content still needed from the client

Anything underlined with a dotted line on the site (class `tbd`) is a placeholder. Search the code for `tbd` to find every one.

## Sourced so far
- SAVIANS Group, its three companies (LUXSTERLING, ANTAARA, GRACEWORKS) and the four disciplines: saviansindia.com
- Chairman and managing director, 2,000+ people, team members of 20+ years, 15 lakh sq ft in about 60 days, 22,000 sq ft in about 42 days, the sectors (EPC contracting, engineering, construction, project execution, art sculpture manufacturing), "When your team grows, your business grows": mid-day, 28 October 2026
- TEDx Imperial SBMS ("Dreams to Reality", 24 October 2026, Aurum, The Auditorium), the Solapur's Pride award (Vijay Foundation), the Girija Oak conversation, horses, clay shooting, the helicopter ride: his Instagram posts
- Social handles and www.sabirshaikh.in: supplied by the client

## Facts
- [ ] Year LUXSTERLING was founded (About: Biography)
- [ ] Home town and family background (About: Biography). The Solapur's Pride award suggests Solapur; confirm before saying so
- [ ] Earlier roles and first business (About: Experience)
- [ ] Degree and institution (About: Education)
- [ ] The YouTube video calls him "Dr. Sabir Shaikh". If he holds a doctorate (or an honorary one), add it to the About page and the schema
- [ ] Award year for Solapur's Pride (listed as 2026 from the post date)

## Accounts and links
- [ ] Facebook: the handle supplied, facebook.com/sabirshaikh, opens a different person's profile ("Sabir S"). The site links to the page his Instagram links to (profile.php?id=61595695503875). Once a username is set on that page, swap the URL in `_partials/header.html`, `_partials/footer.html`, `index.html`, `about/index.html` and `contact/index.html`
- [ ] Contact email. `hello@sabirshaikh.in` matches the reference mockup; confirm the inbox exists
- [ ] WhatsApp business number, if he wants it shown
- [ ] Instagram live feed. The home page shows his 8 latest posts, saved into `assets/img/insta/`. To have new posts appear automatically: create a free feed at behold.so, connect @sabirshaikh.global, and paste the JSON feed URL into `data-feed=""` on the Instagram section in `index.html`. The site swaps the saved tiles for the live feed on its own
- [ ] Contact form endpoint: paste a Formspree/Basin URL into `data-endpoint=""` in `contact/index.html` (until then the form opens the visitor's email app)
- [ ] Google Analytics 4 measurement ID (uncomment the block in `_partials/head.html`, then run `python build.py`)
- [ ] Google Search Console verification tag (same file)
- [ ] Hosting: point www.sabirshaikh.in at the host and redirect the bare domain to www (every canonical URL uses www)

## Copy to approve
These were written in Sabir's voice and need his sign-off:
- [ ] Hero line, the tagline "A curious mind. A purposeful life." (from the reference mockup), the "Curious about ideas, people, places, conversations" line, and the four crest meanings (tiger, horse, pen and book, flags). The horse and flags lines now draw on his own posts and the mid-day piece
- [ ] Manifesto, paraphrased from his TEDx post: "Success isn't only about building businesses. It's about building people, and creating opportunities for them."
- [ ] The sample essay "Why I write every decision down before I make it", and the five other essay titles and summaries on `blogs/`. Only the first essay has a page; the other cards link to it for now
- [ ] Privacy policy: plain-language draft, should be checked against the law where he operates
