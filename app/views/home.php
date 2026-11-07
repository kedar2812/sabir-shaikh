<?php
$site = c('site');
$hero = c('home.hero');
$man = c('home.manifesto');
$crest = c('home.crest');
$about = c('home.about');
$biz = c('home.business');
$blogs = c('home.blogs');
$pod = c('home.podcast');
$feat = c('home.featured');
$insta = c('home.instagram');
$reach = c('home.reach');
$seo = c('home.seo');

$nameParts = preg_split('~\s+~', trim($site['name']));
$first = array_shift($nameParts);
$last = $nameParts ? array_pop($nameParts) : '';
$middle = implode(' ', $nameParts);

$posts = published_posts();
$lead = null;
foreach ($posts as $p) if ($p['featured']) { $lead = $p; break; }
if (!$lead && $posts) $lead = $posts[0];
$side = [];
foreach ($posts as $p) if ($lead && $p['id'] !== $lead['id'] && count($side) < 2) $side[] = $p;

$episodes = published_episodes();
$ep = null;
foreach ($episodes as $e) if ($e['featured']) { $ep = $e; break; }
if (!$ep && $episodes) $ep = $episodes[0];

page_head([
    'title' => $seo['title'],
    'description' => $seo['description'],
    'path' => '/',
    'share_title' => $site['name'],
    'preload' => $hero['photo']['src'],
    'schema' => [
        person_schema(),
        ['@type' => 'WebSite', '@id' => site_url('/#website'), 'url' => site_url('/'), 'name' => $site['name'], 'publisher' => ['@id' => site_url('/#person')]],
    ],
]);
site_header();
?>

<main id="main">

  <section class="hero" data-scene="hero" aria-label="Introduction">
    <div class="hero__stage">
      <figure class="hero__arch">
        <?= img($hero['photo'], ['priority' => true, 'lazy' => false]) ?>
      </figure>
      <h1 class="hero__name">
        <span class="hero__first"><?= e($first) ?></span>
<?php if ($middle): ?>
        <span class="hero__middle"><?= e($middle) ?></span>
<?php endif; ?>
        <span class="hero__last"><?= e($last) ?></span>
      </h1>
      <p class="hero__lede"><?= nl2br_e($hero['tagline']) ?></p>
      <div class="hero__cue" aria-hidden="true"><i></i>Scroll</div>
      <div class="hero__after">
        <div class="hero__intro">
          <h2><?= e($hero['heading']) ?></h2>
          <p><?= e($hero['text']) ?></p>
<?php if ($hero['links']): ?>
          <div class="hero__links">
<?php foreach ($hero['links'] as $l): if (empty($l['label']) || empty($l['url'])) continue; ?>
            <a class="link-arrow" <?= link_attrs($l['url']) ?>><?= e($l['label']) ?> <?= icon('arrow') ?></a>
<?php endforeach; ?>
          </div>
<?php endif; ?>
        </div>
        <ul class="hero__social" aria-label="Sabir on social media">
<?php foreach (social_links() as $l): ?>
          <li><a href="<?= e($l['url']) ?>" data-track="<?= e($l['key']) ?>" rel="me noopener" target="_blank"><?= e($l['label']) ?></a></li>
<?php endforeach; ?>
        </ul>
      </div>
    </div>
  </section>

  <section class="manifesto" data-scene="words" aria-label="Why this site exists">
    <div class="wrap">
      <p class="manifesto__text"><span data-split><?= e($man['text']) ?></span></p>
      <div class="manifesto__sign">
        <svg viewBox="0 0 880 1080" aria-hidden="true"><use href="/assets/img/mono.svg#c"/></svg>
        <span><?= e($man['signature']) ?></span>
      </div>
    </div>
  </section>

  <section class="tour" data-scene="tour" data-dark aria-labelledby="tour-title">
    <div class="tour__stage">
      <div class="tour__view">
        <svg viewBox="-38 -155 1900 1900" preserveAspectRatio="xMidYMid meet" role="img" aria-label="The crest: a tiger and a horse either side of a shield, with two flags, a pen nib, crossed rifles and an open book"><use href="/assets/img/crest.svg#c"/></svg>
      </div>
      <div class="tour__captions">
        <div class="tour__cap is-on">
          <h2 id="tour-title"><?= e($crest['intro_title']) ?></h2>
          <p><?= e($crest['intro_text']) ?></p>
        </div>
<?php $items = array_slice(array_pad((array) $crest['items'], 4, ['title' => '', 'text' => '']), 0, 4); foreach ($items as $i => $item): ?>
        <div class="tour__cap" aria-hidden="true">
          <p class="tour__count"><?= $i + 1 ?> of 4</p>
          <h3><?= e($item['title']) ?></h3>
          <p><?= e($item['text']) ?></p>
        </div>
<?php endforeach; ?>
        <div class="tour__cap" aria-hidden="true">
          <h3><?= e($crest['outro_title']) ?></h3>
          <p><?= e($crest['outro_text']) ?></p>
          <p><a class="link-arrow" href="/about/"><?= e($crest['outro_link']) ?> <?= icon('arrow') ?></a></p>
        </div>
      </div>
      <div class="tour__progress" aria-hidden="true"><span><i></i></span><span><i></i></span><span><i></i></span><span><i></i></span></div>
    </div>
  </section>

  <section class="section" id="about" aria-labelledby="about-title">
    <div class="wrap about">
      <figure class="about__photo" data-parallax>
        <?= img($about['photo'], ['sizes' => '(max-width: 860px) 100vw, 45vw', 'phone' => null]) ?>
      </figure>
      <div class="about__body">
        <h2 id="about-title" class="curious" data-reel>
          <span class="sr-only">About Sabir. </span><?= e($about['prefix']) ?>
<?php if ($about['words']): ?>
          <span class="reel" aria-hidden="true"><span class="reel__track"><?php foreach ($about['words'] as $w): ?><span><?= e($w) ?></span><?php endforeach; ?></span></span>
          <span class="sr-only"><?= e(implode(', ', array_map(function ($w) { return rtrim($w, '.'); }, $about['words']))) ?>.</span>
<?php endif; ?>
        </h2>
        <p class="lead"><?= e($about['lead']) ?></p>
<?php if ($about['text']): ?>
        <p><?= e($about['text']) ?></p>
<?php endif; ?>
<?php if ($about['facts']): ?>
        <dl class="facts">
<?php foreach ($about['facts'] as $f): ?>
          <div><dt><?= e($f['label']) ?></dt><dd><?= e($f['value']) ?></dd></div>
<?php endforeach; ?>
        </dl>
<?php endif; ?>
        <a class="link-arrow" href="/about/"><?= e($about['link']) ?> <?= icon('arrow') ?></a>
      </div>
    </div>
  </section>

  <section class="section" id="business" aria-labelledby="business-title">
    <div class="wrap">
      <div class="section__head">
        <h2 id="business-title"><?= e($biz['title']) ?></h2>
        <p><?= e($biz['intro']) ?></p>
      </div>
<?php if ($biz['ventures']): ?>
      <ul class="ventures">
<?php foreach ($biz['ventures'] as $v): ?>
        <li class="venture"><a <?= link_attrs($v['url'] ?: '#business', 'venture') ?>>
          <span class="venture__name"><?= e($v['name']) ?></span>
          <span class="venture__what"><?= e($v['what']) ?></span>
          <span class="venture__meta"><?= e($v['meta']) ?></span>
          <span class="venture__go" aria-hidden="true"><?= icon('out') ?></span>
        </a></li>
<?php endforeach; ?>
      </ul>
<?php endif; ?>
<?php if ($biz['figures']): ?>
      <dl class="figures">
<?php foreach ($biz['figures'] as $f): ?>
        <div><dt><?= e($f['label']) ?></dt><dd><?= e($f['value']) ?></dd></div>
<?php endforeach; ?>
      </dl>
<?php endif; ?>
    </div>
  </section>

<?php if ($lead): ?>
  <section class="section" id="blogs" aria-labelledby="blogs-title">
    <div class="wrap">
      <div class="section__head">
        <h2 id="blogs-title"><?= e($blogs['title']) ?></h2>
        <p><?= e($blogs['intro']) ?></p>
      </div>
<?php if (count(topic_list())): ?>
      <ul class="topics" aria-label="Browse by topic">
<?php foreach (topic_list() as $t): ?>
        <li><a class="topic" href="/blogs/?topic=<?= e(rawurlencode($t['slug'])) ?>"><?= e($t['name']) ?></a></li>
<?php endforeach; ?>
      </ul>
<?php endif; ?>
      <div class="posts<?= $side ? '' : ' posts--single' ?>">
        <?php post_card($lead, 'h3', 'post--feature'); ?>
<?php if ($side): ?>
        <div class="posts__side">
<?php foreach ($side as $p) post_card($p, 'h3', 'post--side'); ?>
        </div>
<?php endif; ?>
      </div>
      <p class="posts__more"><a class="btn" href="/blogs/"><?= e($blogs['button']) ?> <?= icon('arrow') ?></a></p>
    </div>
  </section>
<?php endif; ?>

<?php if ($ep): ?>
  <section class="section podcast" id="podcast" data-dark aria-labelledby="podcast-title">
    <div class="wrap">
      <div class="section__head">
        <h2 id="podcast-title"><?= e($pod['title']) ?></h2>
        <p><?= e($pod['intro']) ?></p>
      </div>
      <?php episode_feature($ep, $pod['label'], 'h3'); ?>
    </div>
  </section>
<?php endif; ?>

  <section class="section" id="featured" aria-labelledby="featured-title">
    <div class="wrap">
      <div class="section__head">
        <h2 id="featured-title"><?= e($feat['title']) ?></h2>
        <p><?= e($feat['intro']) ?></p>
      </div>
<?php if ($feat['spot_title']): ?>
      <div class="spot">
<?php if (!empty($feat['spot_photo']['src'])): ?>
        <figure class="spot__photo" data-parallax><?= img($feat['spot_photo'], ['sizes' => '(max-width: 760px) 100vw, 35vw', 'phone' => null]) ?></figure>
<?php endif; ?>
        <div class="spot__body">
          <p class="spot__label"><?= e($feat['spot_label']) ?></p>
          <h3><?= e($feat['spot_title']) ?></h3>
          <p><?= e($feat['spot_text']) ?></p>
<?php if ($feat['spot_link_url']): ?>
          <a class="link-arrow" <?= link_attrs($feat['spot_link_url'], 'press') ?>><?= e($feat['spot_link_label']) ?> <?= icon('arrow') ?></a>
<?php endif; ?>
        </div>
      </div>
<?php endif; ?>
<?php if ($feat['press']): ?>
      <ul class="press">
<?php foreach ($feat['press'] as $item): ?>
        <li><span class="press__outlet"><?= e($item['outlet']) ?></span><?php if ($item['url']): ?><a class="press__head" <?= link_attrs($item['url'], 'press') ?>><?= e($item['headline']) ?></a><?php else: ?><span class="press__head"><?= e($item['headline']) ?></span><?php endif; ?><span class="press__kind"><?= e($item['kind']) ?></span><span class="press__year"><?= e($item['year']) ?></span></li>
<?php endforeach; ?>
      </ul>
<?php endif; ?>
    </div>
  </section>

  <section class="section insta" aria-labelledby="insta-title" data-drift data-feed="<?= e($insta['feed_url']) ?>">
    <div class="wrap">
      <div class="section__head">
        <h2 id="insta-title"><?= e($insta['title']) ?></h2>
        <p><?= e($insta['intro']) ?></p>
      </div>
    </div>
    <div class="insta__row">
<?php foreach ($insta['tiles'] as $t): if (empty($t['image']['src'])) continue; ?>
      <a class="insta__tile" <?= link_attrs($t['url'] ?: c('social', 'instagram'), 'instagram-post') ?>><?= img($t['image'], ['sizes' => '340px', 'phone' => null]) ?></a>
<?php endforeach; ?>
    </div>
<?php $ig = c('social', 'instagram'); if ($ig): ?>
    <div class="wrap insta__foot">
      <a class="link-arrow" href="<?= e($ig) ?>" target="_blank" rel="me noopener" data-track="instagram">Follow <?= e(social_handle($ig)) ?> on Instagram <?= icon('arrow') ?></a>
    </div>
<?php endif; ?>
  </section>

  <?php reach_band($reach['title'], $reach['text'], true, $reach['button']); ?>

</main>

<?php site_footer(); ?>
