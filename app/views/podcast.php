<?php
$page = c('podcast.page');
$episodes = published_episodes();
$latest = $episodes ? array_shift($episodes) : null;

page_head([
    'title' => $page['seo_title'],
    'description' => $page['seo_description'],
    'path' => '/podcast/',
    'schema' => [
        ['@type' => 'PodcastSeries', '@id' => site_url('/podcast/#series'), 'url' => site_url('/podcast/'), 'name' => $page['seo_title'], 'author' => ['@id' => site_url('/#person')]],
        ['@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => site_url('/')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $page['title'], 'item' => site_url('/podcast/')],
        ]],
    ],
]);
site_header('podcast');
?>

<main id="main">
  <section class="section podcast podcast--page" data-dark aria-labelledby="podcast-title">
    <div class="wrap">
      <nav class="crumbs" aria-label="Breadcrumb">
        <ol><li><a href="/">Home</a></li><li aria-current="page"><?= e($page['title']) ?></li></ol>
      </nav>
      <div class="section__head">
        <h1 id="podcast-title" class="page-title"><?= e($page['title']) ?></h1>
        <p><?= e($page['intro']) ?></p>
      </div>
<?php if ($latest): ?>
      <?php episode_feature($latest, 'Latest conversation', 'h2'); ?>
<?php else: ?>
      <p class="empty">The first conversation is on its way.</p>
<?php endif; ?>
    </div>
  </section>

<?php if ($episodes): ?>
  <section class="section" aria-labelledby="more-title">
    <div class="wrap">
      <div class="section__head"><h2 id="more-title">Earlier conversations</h2></div>
      <div class="episodes">
<?php foreach ($episodes as $e): ?>
        <article class="episode">
          <a class="episode__media" href="<?= e(episode_url($e)) ?>" tabindex="-1" aria-hidden="true"><?= img($e['image'], ['sizes' => '(max-width: 760px) 100vw, 30vw', 'phone' => null, 'alt' => '']) ?></a>
          <div class="post__meta"><time datetime="<?= e($e['published_at']) ?>"><?= e(fmt_date($e['published_at'], 'short')) ?></time><?php if ($e['duration']): ?><span><?= (int) $e['duration'] ?> min</span><?php endif; ?></div>
          <h3 class="post__title"><a href="<?= e(episode_url($e)) ?>"><?= e($e['title']) ?></a></h3>
<?php if ($e['guest']): ?>
          <p class="post__sum"><?= e($e['guest']) ?></p>
<?php endif; ?>
        </article>
<?php endforeach; ?>
      </div>
    </div>
  </section>
<?php endif; ?>
</main>

<?php site_footer(); ?>
