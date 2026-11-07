<?php
// Every editable piece of the site, described once. The dashboard builds its forms
// from this schema, the API validates against it, and the page templates read the
// saved values (falling back to the defaults below, which are the launch copy).

// Photo frames. Each view is one shape the photo appears in on the site; the
// dashboard's photo positioner previews every view and saves a focus point and
// zoom for each. `tall` mirrors photos that drift with the scroll (CSS height 118%).
function content_frames()
{
    return [
        'hero' => ['label' => 'Opening photo', 'views' => [
            ['key' => 'd', 'label' => 'Computer', 'ratio' => 2 / 3, 'shape' => 'rect'],
            ['key' => 'm', 'label' => 'Phone', 'ratio' => 390 / 844, 'shape' => 'rect'],
        ]],
        'arch' => ['label' => 'Arched photo', 'views' => [
            ['key' => 'd', 'label' => 'Every screen', 'ratio' => 4 / 5, 'shape' => 'arch', 'tall' => 1.18],
        ]],
        'episode' => ['label' => 'Episode photo', 'views' => [
            ['key' => 'd', 'label' => 'Every screen', 'ratio' => 4 / 5, 'shape' => 'arch'],
        ]],
        'profile' => ['label' => 'Profile photo', 'views' => [
            ['key' => 'd', 'label' => 'Every screen', 'ratio' => 3 / 4, 'shape' => 'arch'],
        ]],
        'tile' => ['label' => 'Instagram tile', 'views' => [
            ['key' => 'd', 'label' => 'Every screen', 'ratio' => 4 / 5, 'shape' => 'tile'],
        ]],
        'cover' => ['label' => 'Blog photo', 'views' => [
            ['key' => 'd', 'label' => 'Blog card', 'ratio' => 16 / 10, 'shape' => 'rect'],
            ['key' => 'm', 'label' => 'Top of the essay', 'ratio' => 21 / 9, 'shape' => 'rect'],
        ]],
        'face' => ['label' => 'Round photo', 'views' => [
            ['key' => 'd', 'label' => 'Every screen', 'ratio' => 1, 'shape' => 'circle'],
        ]],
    ];
}

function img_seed($path, $alt, $x = 50, $y = 50, $mx = null, $my = null)
{
    $focus = ['d' => ['x' => $x, 'y' => $y, 'z' => 1]];
    if ($mx !== null) $focus['m'] = ['x' => $mx, 'y' => $my, 'z' => 1];
    $size = @getimagesize(ROOT . $path);
    return ['src' => $path, 'alt' => $alt, 'w' => $size ? $size[0] : 0, 'h' => $size ? $size[1] : 0, 'sizes' => [], 'focus' => $focus];
}

function content_schema()
{
    static $schema = null;
    if ($schema === null) $schema = content_schema_build();
    return $schema;
}

function content_schema_build()
{
    $arrowLink = 'Shown as a link with an arrow.';
    return [
        // ---------------------------------------------------------------- Site
        ['key' => 'site', 'group' => 'site', 'label' => 'Name and contact', 'intro' => 'Used in the header, footer, contact page and every page title.', 'fields' => [
            ['name' => 'name', 'label' => 'Full name', 'type' => 'text', 'default' => 'Sabir Salim Shaikh', 'help' => 'The first and last words become the large name on the home page.'],
            ['name' => 'role', 'label' => 'Role, in one line', 'type' => 'text', 'default' => 'Founder, chairman and managing director of SAVIANS Group'],
            ['name' => 'location', 'label' => 'Based in', 'type' => 'text', 'default' => 'Pune, India'],
            ['name' => 'email', 'label' => 'Email address', 'type' => 'email', 'default' => 'hello.sabirshaikh@gmail.com'],
            ['name' => 'whatsapp', 'label' => 'WhatsApp number', 'type' => 'text', 'default' => '', 'placeholder' => '+91 98765 43210', 'help' => 'Include the country code. Leave empty to hide WhatsApp from the contact page.'],
            ['name' => 'footer_note', 'label' => 'Footer note', 'type' => 'textarea', 'default' => 'Founder, chairman and managing director of SAVIANS Group. Rooted in India, inspired by the world. New essays go up on the blog; the day-to-day goes on Instagram.'],
        ]],
        ['key' => 'social', 'group' => 'site', 'label' => 'Social profiles', 'intro' => 'Links in the header menu, the footer, the home page and the contact page. Leave one empty to hide it everywhere.', 'fields' => [
            ['name' => 'instagram', 'label' => 'Instagram', 'type' => 'url', 'default' => 'https://www.instagram.com/sabirshaikh.global/'],
            ['name' => 'linkedin', 'label' => 'LinkedIn', 'type' => 'url', 'default' => 'https://www.linkedin.com/in/sabirshaikhlive/'],
            ['name' => 'x', 'label' => 'X', 'type' => 'url', 'default' => 'https://x.com/sabirshaikhlive'],
            ['name' => 'youtube', 'label' => 'YouTube', 'type' => 'url', 'default' => 'https://www.youtube.com/@sabirshaikhlive'],
            ['name' => 'facebook', 'label' => 'Facebook', 'type' => 'url', 'default' => 'https://www.facebook.com/profile.php?id=61595695503875'],
        ]],
        ['key' => 'author', 'group' => 'site', 'label' => 'Author box', 'intro' => 'Shown at the end of every essay.', 'fields' => [
            ['name' => 'photo', 'label' => 'Photo', 'type' => 'image', 'frame' => 'face', 'default' => img_seed('/assets/img/sabir-head.webp', 'Sabir Salim Shaikh', 50, 15)],
            ['name' => 'bio', 'label' => 'Short bio', 'type' => 'textarea', 'default' => "Founder, chairman and managing director of SAVIANS Group in Pune. He writes about building and running businesses, from the decisions that worked and the ones that didn't."],
        ]],
        ['key' => 'seo', 'group' => 'site', 'label' => 'Search and sharing', 'intro' => 'How Google and link previews describe Sabir. Most visitors never see this, but search results do.', 'fields' => [
            ['name' => 'job_title', 'label' => 'Job title', 'type' => 'text', 'default' => 'Founder, Chairman and Managing Director'],
            ['name' => 'company', 'label' => 'Company', 'type' => 'text', 'default' => 'SAVIANS Group'],
            ['name' => 'company_url', 'label' => 'Company website', 'type' => 'url', 'default' => 'https://saviansindia.com/'],
            ['name' => 'description', 'label' => 'Who Sabir is, in a sentence or two', 'type' => 'textarea', 'default' => 'Sabir Salim Shaikh is the founder, chairman and managing director of SAVIANS Group, a Pune-based group of companies in architecture, interiors, branding, construction and project execution.'],
            ['name' => 'award', 'label' => 'Best-known award', 'type' => 'text', 'default' => "Solapur's Pride and a Tribute to Solapur's Dignity, Vijay Foundation"],
            ['name' => 'share_image', 'label' => 'Picture shown when the site is shared', 'type' => 'image', 'frame' => null, 'help' => 'WhatsApp, LinkedIn and X show this beside links to the site. A wide picture works best.', 'default' => img_seed('/assets/img/og-default.jpg', 'Sabir Salim Shaikh')],
        ]],

        // ---------------------------------------------------------------- Home
        ['key' => 'home.hero', 'group' => 'home', 'label' => 'Opening', 'intro' => 'The first screen: the photo in the arch, the tagline, and what appears once the arch opens.', 'fields' => [
            ['name' => 'photo', 'label' => 'Photo', 'type' => 'image', 'frame' => 'hero', 'default' => img_seed('/assets/img/sabir-dubai.webp', 'Sabir Salim Shaikh in a cream linen blazer, leaning on a black sedan with the Dubai skyline at sunset behind him', 50, 30, 50, 30)],
            ['name' => 'tagline', 'label' => 'Tagline', 'type' => 'textarea', 'rows' => 2, 'default' => "A curious mind.\nA purposeful life.", 'help' => 'Each line shows on its own line. Hidden on phones.'],
            ['name' => 'heading', 'label' => 'Heading after the arch opens', 'type' => 'text', 'default' => 'I build businesses and write down what they teach me.'],
            ['name' => 'text', 'label' => 'Text under it', 'type' => 'textarea', 'default' => 'Founder of SAVIANS Group. Essays on the blog, long conversations on the podcast, and the day-to-day on Instagram.'],
            ['name' => 'links', 'label' => 'Links', 'type' => 'list', 'help' => $arrowLink, 'max' => 3, 'item' => 'label', 'fields' => [
                ['name' => 'label', 'label' => 'Label', 'type' => 'text'],
                ['name' => 'url', 'label' => 'Goes to', 'type' => 'url'],
            ], 'default' => [['label' => 'About Sabir', 'url' => '/about/'], ['label' => 'Read the essays', 'url' => '/blogs/']]],
        ]],
        ['key' => 'home.manifesto', 'group' => 'home', 'label' => 'Big statement', 'intro' => 'The large sentence that fills in word by word as visitors scroll.', 'fields' => [
            ['name' => 'text', 'label' => 'Statement', 'type' => 'textarea', 'default' => "Success isn't only about building businesses. It's about building people, and creating opportunities for them."],
            ['name' => 'signature', 'label' => 'Signed', 'type' => 'text', 'default' => 'Sabir'],
        ]],
        ['key' => 'home.crest', 'group' => 'home', 'label' => 'The crest', 'intro' => 'The dark red section where the camera moves across the crest. The four parts follow the artwork, so there are always four.', 'fields' => [
            ['name' => 'intro_title', 'label' => 'Opening heading', 'type' => 'text', 'default' => "What's on the crest"],
            ['name' => 'intro_text', 'label' => 'Opening text', 'type' => 'textarea', 'default' => "It was drawn as a logo. It turned into a set of rules. Keep scrolling and I'll walk you through them."],
            ['name' => 'items', 'label' => 'The four parts', 'type' => 'list', 'fixed' => true, 'item' => 'title', 'fields' => [
                ['name' => 'title', 'label' => 'Heading', 'type' => 'text'],
                ['name' => 'text', 'label' => 'Text', 'type' => 'textarea'],
            ], 'default' => [
                ['title' => 'The tiger', 'text' => 'Nerve. The tiger is for the conversations most people put off: the hard negotiation, the honest review, the call to the client who is three months late. Whoever walks in first usually sets the terms.'],
                ['title' => 'The horse', 'text' => "Stamina. Horses have been a passion of mine for years, and they teach what business does: nobody wins on the first furlong. Most of what I've built came from turning up on ordinary days."],
                ['title' => 'The pen and the book', 'text' => "If I can't explain a decision on one page, I'm not ready to make it. The pen is for writing it down. The book is for reading what everyone before me already worked out."],
                ['title' => 'The flags', 'text' => 'Ground taken, and held. Two flags, because nothing worth holding is held alone. Some of the people at SAVIANS have been with me for more than twenty years.'],
            ]],
            ['name' => 'outro_title', 'label' => 'Closing heading', 'type' => 'text', 'default' => 'All four at once'],
            ['name' => 'outro_text', 'label' => 'Closing text', 'type' => 'textarea', 'default' => 'Nerve without stamina burns out. Stamina without thinking goes in circles. The crest is there to remind me to carry all of it.'],
            ['name' => 'outro_link', 'label' => 'Closing link label', 'type' => 'text', 'default' => "Read Sabir's story", 'help' => 'Goes to the About page.'],
        ]],
        ['key' => 'home.about', 'group' => 'home', 'label' => 'About', 'intro' => 'The short introduction with the arched portrait.', 'fields' => [
            ['name' => 'photo', 'label' => 'Photo', 'type' => 'image', 'frame' => 'arch', 'default' => img_seed('/assets/img/sabir-portrait.webp', 'Portrait of Sabir Salim Shaikh smiling, hands in pockets, wearing a cream blazer with an orange pocket square', 50, 20)],
            ['name' => 'prefix', 'label' => 'Heading', 'type' => 'text', 'default' => 'Curious about'],
            ['name' => 'words', 'label' => 'Words that turn over after the heading', 'type' => 'words', 'default' => ['ideas.', 'people.', 'places.', 'conversations.']],
            ['name' => 'lead', 'label' => 'Opening paragraph', 'type' => 'textarea', 'default' => 'Sabir Salim Shaikh is the founder, chairman and managing director of SAVIANS Group, based in Pune. The group works across architecture, interiors, branding, construction and project execution, and employs more than 2,000 people.'],
            ['name' => 'text', 'label' => 'Second paragraph', 'type' => 'textarea', 'default' => "He learns the way most good operators do: from the people he meets, the places work takes him and the ideas he tries out. Away from the site, you'll find him on horseback or at the shooting range."],
            ['name' => 'facts', 'label' => 'Quick facts', 'type' => 'list', 'max' => 6, 'item' => 'value', 'fields' => [
                ['name' => 'label', 'label' => 'Label', 'type' => 'text'],
                ['name' => 'value', 'label' => 'Value', 'type' => 'text'],
            ], 'default' => [
                ['label' => 'Based in', 'value' => 'Pune, India'],
                ['label' => 'Leads', 'value' => 'SAVIANS Group'],
                ['label' => 'Team', 'value' => '2,000+ people'],
                ['label' => 'Spoke at', 'value' => 'TEDx Imperial SBMS, 2026'],
            ]],
            ['name' => 'link', 'label' => 'Link label', 'type' => 'text', 'default' => 'Read the full story', 'help' => 'Goes to the About page.'],
        ]],
        ['key' => 'home.business', 'group' => 'home', 'label' => 'Business', 'intro' => 'The companies and the headline numbers.', 'fields' => [
            ['name' => 'title', 'label' => 'Heading', 'type' => 'text', 'default' => 'Business'],
            ['name' => 'intro', 'label' => 'Introduction', 'type' => 'textarea', 'default' => "SAVIANS Group puts architecture, interiors, branding and construction at the same table from the first day of a project. Each link opens the company's own site."],
            ['name' => 'ventures', 'label' => 'Companies', 'type' => 'list', 'item' => 'name', 'fields' => [
                ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
                ['name' => 'what', 'label' => 'What it does', 'type' => 'textarea'],
                ['name' => 'meta', 'label' => 'Small print on the right', 'type' => 'text'],
                ['name' => 'url', 'label' => 'Website', 'type' => 'url'],
            ], 'default' => [
                ['name' => 'SAVIANS Group', 'what' => 'Architecture, interiors, branding, EPC contracting, construction and project execution, with every discipline at the table from day one.', 'meta' => 'Founder, chairman and managing director', 'url' => 'https://saviansindia.com/'],
                ['name' => 'LUXSTERLING', 'what' => "Architecture and interiors. The group's first company, and still its foundation.", 'meta' => 'SAVIANS Group', 'url' => 'https://saviansindia.com/companies/luxsterling'],
                ['name' => 'ANTAARA', 'what' => 'Architecture and planning, shaped by the site and the people who will use it.', 'meta' => 'SAVIANS Group', 'url' => 'https://saviansindia.com/companies/antaara'],
                ['name' => 'GRACEWORKS', 'what' => 'Branding, strategy and communication, so every project has a clear identity and one voice.', 'meta' => 'SAVIANS Group', 'url' => 'https://saviansindia.com/companies/graceworks'],
            ]],
            ['name' => 'figures', 'label' => 'Headline numbers', 'type' => 'list', 'max' => 3, 'item' => 'value', 'fields' => [
                ['name' => 'value', 'label' => 'Number', 'type' => 'text'],
                ['name' => 'label', 'label' => 'What it counts', 'type' => 'text'],
            ], 'default' => [
                ['value' => '2,000+', 'label' => 'people across the group'],
                ['value' => '15 lakh', 'label' => 'sq ft of work delivered in about 60 days'],
                ['value' => '22,000', 'label' => 'sq ft delivered in about 42 days'],
            ]],
        ]],
        ['key' => 'home.blogs', 'group' => 'home', 'label' => 'Blogs', 'intro' => 'The newest essays appear here by themselves. Choose the lead essay by marking it as featured in Blog posts.', 'fields' => [
            ['name' => 'title', 'label' => 'Heading', 'type' => 'text', 'default' => 'Blogs'],
            ['name' => 'intro', 'label' => 'Introduction', 'type' => 'textarea', 'default' => "What I've learned about business, people and the world around me. One idea per piece, and nothing I haven't tried myself."],
            ['name' => 'button', 'label' => 'Button label', 'type' => 'text', 'default' => 'See every essay'],
        ]],
        ['key' => 'home.podcast', 'group' => 'home', 'label' => 'Podcast', 'intro' => 'Shows the featured episode, or the newest one if none is featured.', 'fields' => [
            ['name' => 'title', 'label' => 'Heading', 'type' => 'text', 'default' => 'Podcast'],
            ['name' => 'intro', 'label' => 'Introduction', 'type' => 'textarea', 'default' => 'Long conversations about building SAVIANS, the people behind it, and what the work has taught me along the way.'],
            ['name' => 'label', 'label' => 'Small label above the episode', 'type' => 'text', 'default' => 'Latest conversation'],
        ]],
        ['key' => 'home.featured', 'group' => 'home', 'label' => 'Featured', 'intro' => 'One highlighted moment, then the list of press, talks and awards.', 'fields' => [
            ['name' => 'title', 'label' => 'Heading', 'type' => 'text', 'default' => 'Featured'],
            ['name' => 'intro', 'label' => 'Introduction', 'type' => 'textarea', 'default' => 'Interviews, talks and recognition, and the occasional story from along the way.'],
            ['name' => 'spot_photo', 'label' => 'Highlight photo', 'type' => 'image', 'frame' => 'arch', 'default' => img_seed('/assets/img/insta/2-tedx-stage.webp', 'Sabir Shaikh holding his TEDx Imperial SBMS speaker memento in front of the event backdrop', 50, 30)],
            ['name' => 'spot_label', 'label' => 'Highlight: small label', 'type' => 'text', 'default' => 'TEDx Imperial SBMS, 24 October 2026'],
            ['name' => 'spot_title', 'label' => 'Highlight: heading', 'type' => 'text', 'default' => 'Dreams to Reality'],
            ['name' => 'spot_text', 'label' => 'Highlight: text', 'type' => 'textarea', 'default' => "At Aurum, The Auditorium, Sabir talked about the belief that has shaped how he leads: success isn't only building businesses, it's building people and creating opportunities for them."],
            ['name' => 'spot_link_label', 'label' => 'Highlight: link label', 'type' => 'text', 'default' => 'See the talk on Instagram'],
            ['name' => 'spot_link_url', 'label' => 'Highlight: link', 'type' => 'url', 'default' => 'https://www.instagram.com/p/De9ARLPiJAA/'],
            ['name' => 'press', 'label' => 'Press, talks and awards', 'type' => 'list', 'item' => 'headline', 'fields' => [
                ['name' => 'outlet', 'label' => 'Where', 'type' => 'text'],
                ['name' => 'headline', 'label' => 'Headline', 'type' => 'text'],
                ['name' => 'url', 'label' => 'Link', 'type' => 'url'],
                ['name' => 'kind', 'label' => 'Kind', 'type' => 'text', 'placeholder' => 'Feature, Speaker, Award, Interview'],
                ['name' => 'year', 'label' => 'Year', 'type' => 'text'],
            ], 'default' => [
                ['outlet' => 'mid-day', 'headline' => 'Sabir Shaikh builds Savians Group around speed, systems and people', 'url' => 'https://www.mid-day.com/buzz/article/sabir-shaikh-builds-savians-group-around-speed-systems-and-people-11325', 'kind' => 'Feature', 'year' => '2026'],
                ['outlet' => 'TEDx Imperial SBMS', 'headline' => 'Dreams to Reality', 'url' => 'https://www.instagram.com/p/De9ARLPiJAA/', 'kind' => 'Speaker', 'year' => '2026'],
                ['outlet' => 'Vijay Foundation', 'headline' => "Solapur's Pride and a Tribute to Solapur's Dignity award", 'url' => 'https://www.instagram.com/p/DezQXhTCFCf/', 'kind' => 'Award', 'year' => '2026'],
                ['outlet' => 'Smashbox Productions', 'headline' => 'Candid conversation with Sabir Shaikh, with Girija Oak', 'url' => 'https://youtu.be/1UZmebflYBA', 'kind' => 'Interview', 'year' => '2026'],
            ]],
        ]],
        ['key' => 'home.instagram', 'group' => 'home', 'label' => 'Instagram', 'intro' => 'The row of posts near the bottom of the home page.', 'fields' => [
            ['name' => 'title', 'label' => 'Heading', 'type' => 'text', 'default' => 'Instagram'],
            ['name' => 'intro', 'label' => 'Introduction', 'type' => 'textarea', 'default' => "Site visits, talks, travel and the odd day off. Follow along for the parts that don't make it into an essay."],
            ['name' => 'feed_url', 'label' => 'Live feed link (Behold)', 'type' => 'url', 'default' => '', 'help' => 'Paste the JSON feed link from behold.so and new posts will appear by themselves. Until then, the posts below are shown.'],
            ['name' => 'tiles', 'label' => 'Posts shown', 'type' => 'list', 'max' => 12, 'item' => 'url', 'fields' => [
                ['name' => 'image', 'label' => 'Picture', 'type' => 'image', 'frame' => 'tile'],
                ['name' => 'url', 'label' => 'Instagram post link', 'type' => 'url'],
            ], 'default' => [
                ['image' => img_seed('/assets/img/insta/0-midday.webp', 'The mid-day feature: Sabir Shaikh builds Savians Group around speed, systems and people'), 'url' => 'https://www.instagram.com/p/DfCWlHshLBg/'],
                ['image' => img_seed('/assets/img/insta/1-helicopter.webp', 'Sabir sitting in the open door of a helicopter on a helipad'), 'url' => 'https://www.instagram.com/p/DeQ1EtoiHP-/'],
                ['image' => img_seed('/assets/img/insta/2-tedx-stage.webp', 'Sabir holding his TEDx Imperial SBMS speaker memento'), 'url' => 'https://www.instagram.com/p/De9ARLPiJAA/'],
                ['image' => img_seed('/assets/img/insta/3-solapur-award.webp', "Sabir receiving the Solapur's Pride award from Vijay Foundation on stage"), 'url' => 'https://www.instagram.com/p/DezQXhTCFCf/'],
                ['image' => img_seed('/assets/img/insta/4-tedx-speaker.webp', 'TEDx Imperial SBMS speaker announcement for Sabir Shaikh, 24 October 2026'), 'url' => 'https://www.instagram.com/p/De0_Wp_IMtA/'],
                ['image' => img_seed('/assets/img/insta/5-girija-oak.webp', 'Sabir and Girija Oak in hard hats on a SAVIANS site'), 'url' => 'https://www.instagram.com/p/DdvPqArIkCq/'],
                ['image' => img_seed('/assets/img/insta/6-clay-shooting.webp', 'Sabir taking aim at a clay shooting range'), 'url' => 'https://www.instagram.com/p/DegnkCpiFEb/'],
                ['image' => img_seed('/assets/img/insta/7-horse.webp', 'Sabir riding a white horse'), 'url' => 'https://www.instagram.com/p/DeMVb08IQuV/'],
            ]],
        ]],
        ['key' => 'home.reach', 'group' => 'home', 'label' => 'Write to Sabir', 'intro' => 'The dark band at the bottom of the home page.', 'fields' => [
            ['name' => 'title', 'label' => 'Heading', 'type' => 'text', 'default' => 'Write to Sabir.'],
            ['name' => 'text', 'label' => 'Text', 'type' => 'textarea', 'default' => 'Always open to a good conversation. Business proposals, speaking invitations, podcast guests and press: every message is read.'],
            ['name' => 'button', 'label' => 'Button label', 'type' => 'text', 'default' => 'Open the contact form'],
        ]],
        ['key' => 'home.seo', 'group' => 'home', 'label' => 'Search result', 'intro' => 'The title and description Google shows for the home page.', 'fields' => [
            ['name' => 'title', 'label' => 'Page title', 'type' => 'text', 'max' => 70, 'default' => 'Sabir Salim Shaikh: founder of SAVIANS Group'],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'max' => 170, 'default' => 'Sabir Salim Shaikh is the founder, chairman and managing director of SAVIANS Group in Pune. Essays on building businesses, conversations, and his latest on Instagram.'],
        ]],

        // --------------------------------------------------------------- About
        ['key' => 'about.intro', 'group' => 'about', 'label' => 'Introduction', 'fields' => [
            ['name' => 'title', 'label' => 'Page heading', 'type' => 'text', 'default' => 'About Sabir'],
            ['name' => 'lead', 'label' => 'Opening paragraph', 'type' => 'textarea', 'default' => 'Sabir Salim Shaikh is the founder, chairman and managing director of SAVIANS Group, based in Pune, India. The group works across architecture, interiors, branding, construction and project execution, and employs more than 2,000 people.'],
            ['name' => 'photo', 'label' => 'Photo', 'type' => 'image', 'frame' => 'profile', 'default' => img_seed('/assets/img/sabir-stance.webp', 'Sabir Salim Shaikh standing beside a black sedan, one hand resting on the roof, in a cream blazer and brown trousers', 50, 18)],
        ]],
        ['key' => 'about.story', 'group' => 'about', 'label' => 'Biography', 'fields' => [
            ['name' => 'bio', 'label' => 'Biography', 'type' => 'richtext', 'default' => '<p>SAVIANS began with LUXSTERLING, an architecture and interiors company. ANTAARA followed in architecture and planning, then GRACEWORKS in branding and communication. The idea that holds them together is simple: every discipline sits at the table from the first day of a project, not one after another.</p><p>Today the group also works in EPC contracting, engineering, construction, project execution and art sculpture manufacturing, with more than 2,000 people. Some of them joined in junior roles and have been with him for over twenty years. His belief is short: when your team grows, your business grows.</p><p>Much of his work is about the small gaps that turn into big delays: materials that arrive late, machines standing idle, two departments that aren’t talking. His teams have delivered about 15 lakh sq ft of work in around 60 days, and a 22,000 sq ft project in about 42.</p>'],
        ]],
        ['key' => 'about.timeline', 'group' => 'about', 'label' => 'Career and recognition', 'intro' => 'Each list appears on the page once it has at least one entry.', 'fields' => [
            ['name' => 'now', 'label' => 'Now', 'type' => 'list', 'item' => 'title', 'fields' => timeline_fields(), 'default' => [
                ['when' => 'Today', 'title' => 'Founder, chairman and managing director, SAVIANS Group', 'text' => 'Architecture, interiors, branding, construction and project execution, with more than 2,000 people.', 'url' => 'https://saviansindia.com/'],
                ['when' => 'Today', 'title' => 'LUXSTERLING, ANTAARA and GRACEWORKS', 'text' => 'The group’s three companies: architecture and interiors; architecture and planning; branding and communication.', 'url' => ''],
            ]],
            ['name' => 'experience', 'label' => 'Experience', 'type' => 'list', 'item' => 'title', 'fields' => timeline_fields(), 'default' => []],
            ['name' => 'education', 'label' => 'Education', 'type' => 'list', 'item' => 'title', 'fields' => timeline_fields(), 'default' => []],
            ['name' => 'recognition', 'label' => 'Recognition', 'type' => 'list', 'item' => 'title', 'fields' => timeline_fields(), 'default' => [
                ['when' => '2026', 'title' => 'Featured in mid-day', 'text' => '“Sabir Shaikh builds Savians Group around speed, systems and people,” 28 October 2026.', 'url' => 'https://www.mid-day.com/buzz/article/sabir-shaikh-builds-savians-group-around-speed-systems-and-people-11325'],
                ['when' => '2026', 'title' => 'Speaker, TEDx Imperial SBMS', 'text' => '“Dreams to Reality,” 24 October 2026, at Aurum, The Auditorium.', 'url' => ''],
                ['when' => '2026', 'title' => 'Solapur’s Pride and a Tribute to Solapur’s Dignity', 'text' => 'Award presented by Vijay Foundation.', 'url' => ''],
            ]],
        ]],
        ['key' => 'about.faq', 'group' => 'about', 'label' => 'Questions', 'intro' => 'Also shown to Google as answers it can quote in search results.', 'fields' => [
            ['name' => 'items', 'label' => 'Questions and answers', 'type' => 'list', 'item' => 'q', 'fields' => [
                ['name' => 'q', 'label' => 'Question', 'type' => 'text'],
                ['name' => 'a', 'label' => 'Answer', 'type' => 'textarea'],
            ], 'default' => [
                ['q' => 'Who is Sabir Salim Shaikh?', 'a' => 'Sabir Salim Shaikh is the founder, chairman and managing director of SAVIANS Group, a Pune-based group of companies in architecture, interiors, branding, construction and project execution.'],
                ['q' => 'What is SAVIANS Group?', 'a' => 'A group of three companies: LUXSTERLING (architecture and interiors), ANTAARA (architecture and planning) and GRACEWORKS (branding and communication). The group also works in EPC contracting, engineering, construction, project execution and art sculpture manufacturing, and has more than 2,000 people.'],
                ['q' => 'What does Sabir speak about?', 'a' => 'Entrepreneurship, leadership, managing people and machines on large projects, and how technology can make people more capable rather than replace them. He spoke at TEDx Imperial SBMS in October 2026.'],
                ['q' => 'How can I invite Sabir to speak?', 'a' => 'Use the contact form on this site and choose Speaking invitation. Include the date, the audience and the format.'],
            ]],
        ]],
        ['key' => 'about.reach', 'group' => 'about', 'label' => 'Closing band', 'fields' => [
            ['name' => 'title', 'label' => 'Heading', 'type' => 'text', 'default' => 'Write to Sabir.'],
            ['name' => 'text', 'label' => 'Text', 'type' => 'textarea', 'default' => 'Business proposals, speaking invitations, podcast guests and press. Every message is read.'],
        ]],
        ['key' => 'about.seo', 'group' => 'about', 'label' => 'Search result', 'fields' => [
            ['name' => 'title', 'label' => 'Page title', 'type' => 'text', 'max' => 70, 'default' => 'About Sabir Salim Shaikh'],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'max' => 170, 'default' => 'Biography of Sabir Salim Shaikh, founder, chairman and managing director of SAVIANS Group in Pune: his companies, recognition and the work he is known for.'],
        ]],

        // --------------------------------------------------------------- Blog
        ['key' => 'blog.page', 'group' => 'blog', 'label' => 'Blog page', 'fields' => [
            ['name' => 'title', 'label' => 'Page heading', 'type' => 'text', 'default' => 'Blogs'],
            ['name' => 'empty', 'label' => 'Shown when a search finds nothing', 'type' => 'text', 'default' => 'Nothing matches that search yet. Try a shorter word, or pick a topic above.'],
            ['name' => 'seo_title', 'label' => 'Search result title', 'type' => 'text', 'max' => 70, 'default' => 'Blogs by Sabir Salim Shaikh'],
            ['name' => 'seo_description', 'label' => 'Search result description', 'type' => 'textarea', 'max' => 170, 'default' => 'Essays by Sabir Salim Shaikh on building companies, deals and negotiation, leading people, money and the long game.'],
        ]],
        ['key' => 'blog.topics', 'group' => 'blog', 'label' => 'Topics', 'intro' => 'The filter buttons on the blog page. Each essay belongs to one topic.', 'fields' => [
            ['name' => 'items', 'label' => 'Topics', 'type' => 'list', 'item' => 'name', 'fields' => [
                ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
                ['name' => 'slug', 'label' => 'Web address word', 'type' => 'slug', 'help' => 'Used in links like /blogs/?topic=money. Lowercase, no spaces.'],
                ['name' => 'about', 'label' => 'Shown when the topic is picked', 'type' => 'textarea'],
            ], 'default' => [
                ['name' => 'Building companies', 'slug' => 'building', 'about' => 'Building companies: starting, hiring, and the first few years when everything is held together by the founder.'],
                ['name' => 'Deals and negotiation', 'slug' => 'deals', 'about' => 'Deals and negotiation: buying, selling, partnering, and knowing when to leave the table.'],
                ['name' => 'Leading people', 'slug' => 'people', 'about' => 'Leading people: hiring, trust, hard conversations, and building a team that can run without you in the room.'],
                ['name' => 'Money', 'slug' => 'money', 'about' => 'Money: cash, margins, debt, and the boring habits that keep a business alive in a bad year.'],
                ['name' => 'The long game', 'slug' => 'long-game', 'about' => 'The long game: decisions, discipline, health and the habits that compound over decades.'],
            ]],
        ]],

        // ------------------------------------------------------------ Podcast
        ['key' => 'podcast.page', 'group' => 'podcast', 'label' => 'Podcast page', 'fields' => [
            ['name' => 'title', 'label' => 'Page heading', 'type' => 'text', 'default' => 'Podcast'],
            ['name' => 'intro', 'label' => 'Introduction', 'type' => 'textarea', 'default' => 'Long conversations about building SAVIANS, the people behind it, and what the work has taught me along the way.'],
            ['name' => 'seo_title', 'label' => 'Search result title', 'type' => 'text', 'max' => 70, 'default' => 'Podcast with Sabir Salim Shaikh'],
            ['name' => 'seo_description', 'label' => 'Search result description', 'type' => 'textarea', 'max' => 170, 'default' => 'Long conversations with Sabir Salim Shaikh about building SAVIANS Group, the people behind it and what the work has taught him.'],
        ]],

        // ------------------------------------------------------------ Contact
        ['key' => 'contact.page', 'group' => 'contact', 'label' => 'Contact page', 'intro' => 'The email address, WhatsApp number and social links come from Site details.', 'fields' => [
            ['name' => 'title', 'label' => 'Page heading', 'type' => 'text', 'default' => 'Contact'],
            ['name' => 'lead', 'label' => 'Opening paragraph', 'type' => 'textarea', 'default' => 'Business proposals, speaking invitations, podcast guests and press. The form goes to the same inbox as email, so use whichever you prefer.'],
            ['name' => 'reasons', 'label' => 'Choices in "What it\'s about"', 'type' => 'words', 'default' => ['Business enquiry', 'Speaking invitation', 'Podcast guest', 'Press', 'Something else']],
            ['name' => 'seo_title', 'label' => 'Search result title', 'type' => 'text', 'max' => 70, 'default' => 'Contact Sabir Salim Shaikh'],
            ['name' => 'seo_description', 'label' => 'Search result description', 'type' => 'textarea', 'max' => 170, 'default' => 'Contact Sabir Salim Shaikh about business, speaking, the podcast or press. Use the form, email, or reach him on LinkedIn and Instagram.'],
        ]],

        // ------------------------------------------------------------ Privacy
        ['key' => 'privacy.page', 'group' => 'privacy', 'label' => 'Privacy policy', 'fields' => [
            ['name' => 'updated', 'label' => 'Last updated', 'type' => 'date', 'default' => '2026-11-02'],
            ['name' => 'body', 'label' => 'Policy', 'type' => 'richtext', 'default' => privacy_default()],
        ]],
    ];
}

function timeline_fields()
{
    return [
        ['name' => 'when', 'label' => 'When', 'type' => 'text', 'placeholder' => '2019, or Today'],
        ['name' => 'title', 'label' => 'Heading', 'type' => 'text'],
        ['name' => 'text', 'label' => 'Details', 'type' => 'textarea'],
        ['name' => 'url', 'label' => 'Link (optional)', 'type' => 'url'],
    ];
}

function privacy_default()
{
    return '<p><strong>This policy explains what information sabirshaikh.in collects, why, and what you can ask us to do with it.</strong> "We" means Sabir Salim Shaikh and the team that runs this website.</p>'
        . '<h2>What we collect</h2>'
        . '<h3>When you use the contact form</h3><p>Your name, email address, the topic you choose and your message. We use these only to read and reply to your message.</p>'
        . '<h3>When you browse the site</h3><p>The site counts visits itself, without cookies and without any outside analytics company. For each page view it records the page, the site that sent you, your device type, browser and operating system, and your country, worked out from your time zone setting. To tell visits apart it keeps a code that changes every day and cannot be traced back to you. It does not store your IP address or tell us who you are.</p>'
        . '<h3>Instagram posts</h3><p>The home page shows recent Instagram posts. Clicking one takes you to Instagram, where Instagram\'s own privacy policy applies.</p>'
        . '<h3>The podcast video</h3><p>Podcast episodes are hosted on YouTube. Nothing loads from YouTube until you press play; after that, the video plays through YouTube\'s privacy-enhanced mode, and YouTube\'s privacy policy applies.</p>'
        . '<h2>Cookies</h2><p>Visitors get no cookies. The only cookie the site sets is a sign-in cookie for the people who run it.</p>'
        . '<h2>How long we keep it</h2><p>Contact messages are kept for as long as the conversation is useful, and no longer than two years. Visit counts are kept for about 13 months.</p>'
        . '<h2>Who we share it with</h2><p>We don\'t sell your information. It stays with our web host, which stores the site and its data for us.</p>'
        . '<h2>Your rights</h2><p>You can ask to see the information we hold about you, correct it, or delete it. Email the address on the contact page and we will respond within 30 days.</p>'
        . '<h2>Changes</h2><p>If this policy changes, the new version will be posted on this page with a new date at the top.</p>';
}

// ----------------------------------------------------------------------------- Reading values

function content_section($key)
{
    foreach (content_schema() as $s) if ($s['key'] === $key) return $s;
    return null;
}

function content_defaults($section)
{
    $out = [];
    foreach ($section['fields'] as $f) $out[$f['name']] = isset($f['default']) ? $f['default'] : ($f['type'] === 'list' || $f['type'] === 'words' ? [] : '');
    return $out;
}

// All saved values, loaded once per request. Draft values from the dashboard's
// preview are layered on top when the signed-in owner is previewing.
function content_all()
{
    static $all = null;
    if ($all !== null) return $all;
    $saved = [];
    foreach (q_all('SELECT key, value FROM content') as $row) $saved[$row['key']] = json_decode($row['value'], true);
    $all = [];
    foreach (content_schema() as $s) {
        $all[$s['key']] = array_replace(content_defaults($s), isset($saved[$s['key']]) && is_array($saved[$s['key']]) ? $saved[$s['key']] : []);
    }
    $preview = preview_overrides('content');
    foreach ($preview as $key => $values) if (isset($all[$key]) && is_array($values)) $all[$key] = array_replace($all[$key], $values);
    return $all;
}

// c('home.hero') returns the section; c('home.hero', 'tagline') returns one field.
function c($key, $field = null)
{
    $all = content_all();
    $section = isset($all[$key]) ? $all[$key] : [];
    if ($field === null) return $section;
    return isset($section[$field]) ? $section[$field] : '';
}

function preview_overrides($kind)
{
    if (empty($_GET['preview']) || !isset($_COOKIE[session_cookie_name()])) return [];
    if (session_status() !== PHP_SESSION_ACTIVE) start_session();
    if (empty($_SESSION['user_id']) || empty($_SESSION['preview'][$kind])) return [];
    return $_SESSION['preview'][$kind];
}
