<?php
// Shared head, header and footer for every public page.

function social_links()
{
    $s = c('social');
    $out = [];
    foreach (['instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'x' => 'X', 'youtube' => 'YouTube', 'facebook' => 'Facebook'] as $key => $label) {
        if (!empty($s[$key])) $out[] = ['key' => $key, 'label' => $label, 'url' => $s[$key]];
    }
    return $out;
}

function nav_items()
{
    return [
        ['key' => 'about', 'label' => 'About Sabir', 'url' => '/about/'],
        ['key' => 'business', 'label' => 'Business', 'url' => '/#business'],
        ['key' => 'blogs', 'label' => 'Blogs', 'url' => '/blogs/'],
        ['key' => 'podcast', 'label' => 'Podcast', 'url' => '/podcast/'],
        ['key' => 'featured', 'label' => 'Featured', 'url' => '/#featured'],
        ['key' => 'contact', 'label' => 'Contact', 'url' => '/contact/'],
    ];
}

function person_schema()
{
    $site = c('site');
    $seo = c('seo');
    $same = [];
    foreach (social_links() as $l) $same[] = $l['url'];
    $person = [
        '@type' => 'Person',
        '@id' => site_url('/#person'),
        'name' => $site['name'],
        'alternateName' => 'Sabir Shaikh',
        'url' => site_url('/'),
        'image' => abs_url(c('about.intro', 'photo')['src']),
        'jobTitle' => $seo['job_title'],
        'worksFor' => ['@type' => 'Organization', 'name' => $seo['company'], 'url' => $seo['company_url']],
        'description' => $seo['description'],
        'email' => 'mailto:' . $site['email'],
        'homeLocation' => ['@type' => 'Place', 'name' => $site['location']],
        'sameAs' => $same,
    ];
    if (!empty($seo['award'])) $person['award'] = $seo['award'];
    return $person;
}

// $m: title, description, path, type, image (stored photo or path), schema (array of graph nodes), preload, robots, extra
function page_head($m)
{
    $share = isset($m['image']) && $m['image'] ? $m['image'] : c('seo', 'share_image');
    $shareUrl = abs_url(is_array($share) ? $share['src'] : $share);
    $title = $m['title'];
    $desc = isset($m['description']) ? $m['description'] : '';
    $canonical = site_url($m['path']);
    $type = isset($m['type']) ? $m['type'] : 'website';
    $name = c('site', 'name');
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#ecede8">
<link rel="icon" type="image/png" href="/assets/img/favicon.png">
<link rel="apple-touch-icon" href="/assets/img/favicon.png">
<link rel="preload" href="/assets/fonts/melodrama-500.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/assets/fonts/switzer-400.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="/assets/css/site.css?v=<?= asset_version('/assets/css/site.css') ?>">
<script>document.documentElement.classList.toggle("rm", matchMedia("(prefers-reduced-motion: reduce)").matches);</script>
<title><?= e($title) ?></title>
<?php if ($desc): ?><meta name="description" content="<?= e($desc) ?>">
<?php endif; ?>
<?php if (!empty($m['robots'])): ?><meta name="robots" content="<?= e($m['robots']) ?>">
<?php else: ?><link rel="canonical" href="<?= e($canonical) ?>">
<?php endif; ?>
<meta property="og:type" content="<?= e($type) ?>">
<meta property="og:site_name" content="<?= e($name) ?>">
<meta property="og:title" content="<?= e(isset($m['share_title']) ? $m['share_title'] : $title) ?>">
<?php if ($desc): ?><meta property="og:description" content="<?= e($desc) ?>">
<?php endif; ?>
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:image" content="<?= e($shareUrl) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e(isset($m['share_title']) ? $m['share_title'] : $title) ?>">
<?php if ($desc): ?><meta name="twitter:description" content="<?= e($desc) ?>">
<?php endif; ?>
<meta name="twitter:image" content="<?= e($shareUrl) ?>">
<?php if (!empty($m['preload'])): ?><link rel="preload" as="image" href="<?= e($m['preload']) ?>" fetchpriority="high">
<?php endif; ?>
<?php if (!empty($m['extra'])) echo $m['extra'], "\n"; ?>
<?php if (!empty($m['schema'])) echo json_ld(['@context' => 'https://schema.org', '@graph' => $m['schema']]), "\n"; ?>
</head>
<body>
<?php
}

function asset_version($path)
{
    $file = ROOT . $path;
    return is_file($file) ? substr(md5((string) filemtime($file)), 0, 8) : '1';
}

function site_header($current = '')
{
    $items = nav_items();
    $left = array_slice($items, 0, 3);
    $right = array_slice($items, 3);
    $name = c('site', 'name');
    ?>
<a class="skip" href="#main">Skip to content</a>
<header class="nav" data-nav>
  <nav class="nav__inner wrap" aria-label="Main">
    <ul class="nav__list">
<?php foreach ($left as $i): ?>
      <li><a href="<?= e($i['url']) ?>" data-key="<?= e($i['key']) ?>"<?= $current === $i['key'] ? ' aria-current="page"' : '' ?>><?= e($i['label']) ?></a></li>
<?php endforeach; ?>
    </ul>
    <a class="nav__crest" href="/" aria-label="<?= e($name) ?>, home">
      <svg viewBox="0 0 1825 1590" aria-hidden="true"><use href="/assets/img/crest.svg#c"/></svg>
    </a>
    <ul class="nav__list nav__list--right">
<?php foreach ($right as $i): ?>
      <li><a href="<?= e($i['url']) ?>" data-key="<?= e($i['key']) ?>"<?= $current === $i['key'] ? ' aria-current="page"' : '' ?>><?= e($i['label']) ?></a></li>
<?php endforeach; ?>
    </ul>
    <button class="nav__toggle" type="button" aria-expanded="false" aria-controls="menu">Menu <span class="nav__toggle-bars" aria-hidden="true"></span></button>
  </nav>
</header>
<div class="menu-sheet" id="menu">
  <ul>
<?php foreach ($items as $i): ?>
    <li><a href="<?= e($i['url']) ?>"><?= e($i['label']) ?></a></li>
<?php endforeach; ?>
  </ul>
  <div class="menu-sheet__social">
<?php foreach (social_links() as $l): ?>
    <a href="<?= e($l['url']) ?>" data-track="<?= e($l['key']) ?>" rel="me noopener" target="_blank"><?= e($l['label']) ?></a>
<?php endforeach; ?>
  </div>
</div>
<?php
}

function site_footer()
{
    $site = c('site');
    ?>
<footer class="foot">
  <div class="wrap">
    <a href="/" class="foot__crest" aria-label="Back to the home page">
      <svg viewBox="0 0 1825 1590" class="crest" aria-hidden="true"><use href="/assets/img/crest.svg#c"/></svg>
      <span class="foot__mark"><?= e($site['name']) ?></span>
    </a>
    <div class="foot__grid">
      <div>
        <h2><?= e($site['location']) ?></h2>
        <p class="foot__note"><?= e($site['footer_note']) ?></p>
      </div>
      <div>
        <h2>On this site</h2>
        <ul>
<?php foreach (nav_items() as $i): ?>
          <li><a href="<?= e($i['url']) ?>"><?= e($i['label']) ?></a></li>
<?php endforeach; ?>
        </ul>
      </div>
<?php if (social_links()): ?>
      <div>
        <h2>Elsewhere</h2>
        <ul>
<?php foreach (social_links() as $l): ?>
          <li><a href="<?= e($l['url']) ?>" data-track="<?= e($l['key']) ?>" rel="me noopener" target="_blank"><?= e($l['label']) ?></a></li>
<?php endforeach; ?>
        </ul>
      </div>
<?php endif; ?>
    </div>
    <div class="foot__base">
      <span>&copy; <?= date('Y') ?> <?= e($site['name']) ?>. All rights reserved.</span>
      <a href="/privacy-policy/">Privacy policy</a>
    </div>
  </div>
</footer>
<script src="/assets/js/lenis.min.js" defer></script>
<script src="/assets/js/site.js?v=<?= asset_version('/assets/js/site.js') ?>" defer></script>
</body>
</html>
<?php
}

function reach_band($title, $text, $withMail = false, $button = 'Open the contact form')
{
    $site = c('site');
    ?>
  <section class="reach" aria-labelledby="reach-title" data-dark>
    <div class="wrap">
      <h2 id="reach-title"><?= e($title) ?></h2>
      <p><?= e($text) ?></p>
<?php if ($withMail && $site['email']): ?>
      <a class="reach__mail" href="mailto:<?= e($site['email']) ?>" data-track="email"><?= e($site['email']) ?></a>
<?php endif; ?>
      <div class="reach__actions">
        <a class="btn btn--light" href="/contact/"><?= e($button) ?></a>
      </div>
<?php if ($withMail): ?>
      <ul class="reach__meta">
        <li class="reach__place"><?= icon('pin') ?><?= e($site['location']) ?></li>
<?php foreach (social_links() as $l): ?>
        <li><a href="<?= e($l['url']) ?>" data-track="<?= e($l['key']) ?>" rel="me noopener" target="_blank"><?= e($l['label']) ?></a></li>
<?php endforeach; ?>
      </ul>
<?php endif; ?>
    </div>
  </section>
<?php
}

// The episode block with the click-to-play video: home page and episode pages.
function episode_feature($ep, $label, $tag = 'h3', $linkTitle = true)
{
    ?>
      <article class="ep"<?= $ep['video_id'] ? ' data-video="' . e($ep['video_id']) . '"' : '' ?> data-title="<?= e($ep['title']) ?>">
        <div class="ep__media">
<?php if (!empty($ep['image']['src'])): ?>
          <?= img($ep['image'], ['sizes' => '(max-width: 760px) 100vw, 40vw', 'phone' => null]) ?>
<?php endif; ?>
<?php if ($ep['video_id']): ?>
          <button class="ep__play" type="button" aria-label="Play <?= e($ep['title']) ?>"><?= icon('play') ?></button>
<?php endif; ?>
        </div>
        <div class="ep__body">
<?php if ($label): ?>
          <p class="ep__label"><?= e($label) ?></p>
<?php endif; ?>
          <<?= $tag ?> class="ep__title"><?php if ($linkTitle): ?><a href="<?= e(episode_url($ep)) ?>"><?= e($ep['title']) ?></a><?php else: ?><?= e($ep['title']) ?><?php endif; ?></<?= $tag ?>>
<?php if ($ep['guest'] || $ep['summary']): ?>
          <p><?= e(trim($ep['guest'] . ($ep['guest'] && $ep['summary'] ? '. ' : '') . $ep['summary'])) ?></p>
<?php endif; ?>
<?php $meta = trim(($ep['duration'] ? $ep['duration'] . ' minutes. ' : '') . $ep['credit']); if ($meta): ?>
          <p class="ep__meta"><?= e($meta) ?></p>
<?php endif; ?>
<?php if ($ep['links']): ?>
          <div class="listen">
<?php foreach ($ep['links'] as $l): if (empty($l['url'])) continue; ?>
            <a <?= link_attrs($l['url'], 'podcast-link') ?>><?= e($l['label']) ?></a>
<?php endforeach; ?>
          </div>
<?php endif; ?>
        </div>
      </article>
<?php
}

function post_card($p, $tag = 'h2', $class = '')
{
    $url = post_url($p);
    $topic = topic_name($p['topic']);
    ?>
<article class="post <?= e($class) ?>" data-topic="<?= e($p['topic']) ?>">
<?php if ($p['card'] === 'photo' && !empty($p['cover']['src'])): ?>
  <a href="<?= e($url) ?>" class="post__cover post__cover--photo" tabindex="-1" aria-hidden="true"><?= img($p['cover'], ['desk' => 'd', 'phone' => null, 'sizes' => '(max-width: 860px) 100vw, 40vw', 'alt' => '']) ?></a>
<?php else: ?>
  <a href="<?= e($url) ?>" class="post__cover<?= $p['card_tone'] !== 'oxblood' ? ' post__cover--' . e($p['card_tone']) : '' ?>" tabindex="-1" aria-hidden="true"><span><?= e($p['card_text'] ?: $p['title']) ?></span></a>
<?php endif; ?>
  <div class="post__meta"><?php if ($topic): ?><span><?= e($topic) ?></span><?php endif; ?><time datetime="<?= e($p['published_at']) ?>"><?= e(fmt_date($p['published_at'], $class === 'post--feature' ? 'long' : 'short')) ?></time></div>
  <<?= $tag ?> class="post__title"><a href="<?= e($url) ?>"><?= e($p['title']) ?></a></<?= $tag ?>>
<?php if ($p['summary'] && $class !== 'post--side'): ?>
  <p class="post__sum"><?= e($p['summary']) ?></p>
<?php endif; ?>
</article>
<?php
}
