<?php
// What the site launched with. Used once, when the database is first created.

function seed_data()
{
    $essay = <<<'HTML'
<p>Most of the bad decisions I've made felt obvious at the time. That's what makes them dangerous. In the room, with a good salesperson across the table and a number that looks right, everything feels obvious.</p>
<p>So I made a rule. Anything that commits serious money, or changes who I work with, gets one page before I say yes. Typed or handwritten, it doesn't matter. It just has to exist.</p>
<h2>What goes on the page</h2>
<p>The same four headings, every time.</p>
<ol>
<li><strong>What I'm deciding.</strong> One sentence. If it won't fit in one sentence, I'm deciding two things and I need two pages.</li>
<li><strong>Why now.</strong> What happens if I wait a month? Surprisingly often the answer is nothing, and the urgency belonged to someone else.</li>
<li><strong>What would have to be true.</strong> The assumptions, written as plain sentences I could be wrong about. Revenue holds at this level. The partner does what they say. The market doesn't turn.</li>
<li><strong>How I'll know I was wrong.</strong> A date and a number. If it hasn't happened by then, I stop or change course. The psychologist Gary Klein built a whole method around this idea, the premortem, where a team imagines the project has already failed and works out why.<sup><a href="#ref-1" id="cite-1">1</a></sup></li>
</ol>
<h2>Why a page beats a conversation</h2>
<p>Talking a decision through with people I trust is useful, but conversation flatters. People nod. I fill the gaps with confidence. A weak assumption slides past because nobody wants to be the one who slows things down.</p>
<p>Paper doesn't nod. A sentence like "the client will renew" looks thin when it sits alone on a line with nothing underneath it.</p>
<p>The page also has to survive a night. I write it in the evening and read it again in the morning. More often than I'd like to admit, the morning version of me crosses something out.</p>
<h2>What it costs</h2>
<p>A day, usually. Once in a while, a deal, when the other side wanted an answer in the room. I've made peace with that.</p>
<blockquote><p>The deals that can't wait a day are rarely the ones I regret missing.</p></blockquote>
<h2>What it gives back</h2>
<p>The obvious benefit is fewer mistakes. The less obvious one is that the pages pile up. I keep them in one folder and read back through them every few months. Patterns show up that I would never see from memory: the same optimism about payment terms, the same habit of underestimating how long it takes to hire well.</p>
<p>Daniel Kahneman spent a career showing how confidently people misjudge their own plans.<sup><a href="#ref-2" id="cite-2">2</a></sup> A folder of my own old pages makes the same point, with my handwriting on it.</p>
<p>A few of those pages became essays on this site. Most stay private, which is the point. They were never written to convince anyone except me.</p>
<h2>If you want to try it</h2>
<h3>Start with one decision</h3>
<p>Pick the next decision that makes you slightly nervous and give it the four headings. Don't polish it. Nobody else is going to read it.</p>
<h3>Sleep on it</h3>
<p>Read it once more in the morning, before you act. You'll either make the same decision with more confidence, or you'll find the thing you were hoping nobody would ask about. Both are worth a day.</p>
<h2>Sources</h2>
<ol>
<li id="ref-1">Gary Klein, "Performing a Project Premortem," <cite>Harvard Business Review</cite>, September 2007. <a href="#cite-1">Back</a></li>
<li id="ref-2">Daniel Kahneman, <cite>Thinking, Fast and Slow</cite> (Farrar, Straus and Giroux, 2011). <a href="#cite-2">Back</a></li>
</ol>
HTML;

    $post = function ($slug, $title, $summary, $topic, $text, $tone, $date, $status, $extra = []) {
        return array_replace([
            'slug' => $slug, 'title' => $title, 'summary' => $summary, 'topic' => $topic,
            'card' => 'words', 'card_text' => $text, 'card_tone' => $tone, 'cover' => null, 'cover_caption' => '',
            'body' => '', 'tags' => [], 'seo_title' => '', 'seo_description' => '',
            'status' => $status, 'featured' => 0, 'published_at' => $date,
        ], $extra);
    };

    return [
        'posts' => [
            $post('why-i-write-every-decision-down', 'Why I write every decision down before I make it',
                "Before any decision that costs real money or real trust, I write one page. It slows me down by a day. It has also kept me out of more bad deals than any adviser I've paid.",
                'long-game', 'Write it down first.', 'oxblood', '2026-10-26', 'published', [
                    'featured' => 1,
                    'cover' => img_seed('/assets/img/sabir-stance.webp', 'Sabir Salim Shaikh standing beside his car outside an office building on an overcast morning', 50, 22, 50, 22),
                    'cover_caption' => 'Sabir Salim Shaikh',
                    'body' => $essay,
                    'tags' => ['Decision making', 'Writing'],
                    'seo_description' => 'Before any decision that costs real money or real trust, Sabir Shaikh writes one page under four headings. Here is the method and why it works.',
                ]),
            $post('the-first-hire-is-the-business-plan', 'The first hire is the business plan', 'Whoever you hire first decides what the company is good at for the next five years. Choose like it.', 'building', 'The first hire.', 'lacquer', '2026-10-12', 'draft'),
            $post('what-a-bad-deal-taught-me-about-reading-the-room', 'What a bad deal taught me about reading the room', "The numbers were fine. The people across the table were telling me something else, and I didn't listen.", 'deals', 'Read the room.', 'saffron', '2026-09-28', 'draft'),
            $post('keep-six-months-of-cash', 'Keep six months of cash. Then argue about growth.', 'A business with a cushion can say no. Saying no is where most of the good decisions start.', 'money', 'Six months of cash.', 'bone', '2026-09-14', 'draft'),
            $post('hire-the-person-who-asks-the-awkward-question', 'Hire the person who asks the awkward question', "The candidate who pushes back in the interview will push back when it matters. That's the one you want.", 'people', 'The awkward question.', 'oxblood', '2026-08-31', 'draft'),
            $post('the-meeting-before-the-meeting', 'The meeting before the meeting', 'Most negotiations are settled in a phone call nobody minutes. How to make that call count.', 'deals', 'Before the meeting.', 'lacquer', '2026-08-17', 'draft'),
        ],
        'episodes' => [[
            'slug' => 'candid-conversation-with-girija-oak',
            'title' => 'Candid conversation with Sabir Shaikh: the visionary behind SAVIANS',
            'guest' => 'With actor Girija Oak',
            'summary' => 'Building SAVIANS, the lessons along the way, education, and staying grounded while you build for the future.',
            'body' => '',
            'video_id' => '1UZmebflYBA',
            'duration' => 64,
            'credit' => 'Filmed by Smashbox Productions.',
            'image' => img_seed('/assets/img/insta/5-girija-oak.webp', 'Sabir Shaikh and Girija Oak in hard hats on a SAVIANS construction site', 50, 40),
            'links' => [
                ['label' => 'Watch on YouTube', 'url' => 'https://youtu.be/1UZmebflYBA'],
                ['label' => 'Subscribe to the channel', 'url' => 'https://www.youtube.com/@sabirshaikhlive'],
            ],
            'seo_description' => 'Actor Girija Oak talks with Sabir Shaikh about building SAVIANS Group, the lessons along the way, education, and staying grounded.',
            'status' => 'published',
            'featured' => 1,
            'published_at' => '2026-10-20',
        ]],
        'media' => [
            ['path' => '/assets/img/sabir-dubai.webp', 'name' => 'Dubai skyline', 'alt' => 'Sabir Salim Shaikh in a cream linen blazer, leaning on a black sedan with the Dubai skyline at sunset behind him'],
            ['path' => '/assets/img/sabir-portrait.webp', 'name' => 'Portrait', 'alt' => 'Portrait of Sabir Salim Shaikh smiling, hands in pockets, wearing a cream blazer with an orange pocket square'],
            ['path' => '/assets/img/sabir-stance.webp', 'name' => 'Beside the car', 'alt' => 'Sabir Salim Shaikh standing beside a black sedan, one hand resting on the roof'],
            ['path' => '/assets/img/sabir-car.webp', 'name' => 'In the car', 'alt' => 'Sabir Salim Shaikh with his car'],
            ['path' => '/assets/img/sabir-head.webp', 'name' => 'Head and shoulders', 'alt' => 'Sabir Salim Shaikh'],
            ['path' => '/assets/img/insta/0-midday.webp', 'name' => 'mid-day feature', 'alt' => 'The mid-day feature: Sabir Shaikh builds Savians Group around speed, systems and people'],
            ['path' => '/assets/img/insta/1-helicopter.webp', 'name' => 'Helicopter', 'alt' => 'Sabir sitting in the open door of a helicopter on a helipad'],
            ['path' => '/assets/img/insta/2-tedx-stage.webp', 'name' => 'TEDx stage', 'alt' => 'Sabir holding his TEDx Imperial SBMS speaker memento'],
            ['path' => '/assets/img/insta/3-solapur-award.webp', 'name' => "Solapur's Pride award", 'alt' => "Sabir receiving the Solapur's Pride award from Vijay Foundation on stage"],
            ['path' => '/assets/img/insta/4-tedx-speaker.webp', 'name' => 'TEDx speaker card', 'alt' => 'TEDx Imperial SBMS speaker announcement for Sabir Shaikh, 24 October 2026'],
            ['path' => '/assets/img/insta/5-girija-oak.webp', 'name' => 'With Girija Oak', 'alt' => 'Sabir and Girija Oak in hard hats on a SAVIANS site'],
            ['path' => '/assets/img/insta/6-clay-shooting.webp', 'name' => 'Clay shooting', 'alt' => 'Sabir taking aim at a clay shooting range'],
            ['path' => '/assets/img/insta/7-horse.webp', 'name' => 'On horseback', 'alt' => 'Sabir riding a white horse'],
            ['path' => '/assets/img/og-default.jpg', 'name' => 'Share picture', 'alt' => 'Sabir Salim Shaikh'],
        ],
    ];
}
