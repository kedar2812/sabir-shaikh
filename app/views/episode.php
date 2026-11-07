<?php
// $episode is set by the router.
$site = c('site');
$url = site_url(episode_url($episode));
$desc = $episode['seo_description'] ?: trim($episode['guest'] . '. ' . $episode['summary'], '. ');
$others = [];
foreach (published_episodes() as $e) if ($e['id'] !== $episode['id']) $others[] = $e;
$others = array_slice($others, 0, 3);

$node = [
    '@type' => 'PodcastEpisode',
    '@id' => $url . '#episode',
    'url' => $url,
    'name' => $episode['title'],
    'description' => $desc,
    'datePublished' => $episode['published_at'],
    'partOfSeries' => ['@id' => site_url('/podcast/#series')],
];
if ($episode['video_id']) {
    $node['associatedMedia'] = ['@type' => 'VideoObject', 'name' => $episode['title'], 'embedUrl' => 'https://www.youtube-nocookie.com/embed/' . $episode['video_id'], 'thumbnailUrl' => 'https://i.ytimg.com/vi/' . $episode['video_id'] . '/hqdefault.jpg', 'uploadDate' => $episode['published_at'], 'description' => $desc];
    if ($episode['duration']) $node['associatedMedia']['duration'] = 'PT' . (int) $episode['duration'] . 'M';
}

page_head([
    'title' => $episode['title'] . ' | ' . $site['name'],
    'share_title' => $episode['title'],
    'description' => $desc,
    'path' => episode_url($episode),
    'image' => !empty($episode['image']['src']) ? $episode['image'] : null,
    'schema' => [$node, ['@type' => 'BreadcrumbList', 'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => site_url('/')],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Podcast', 'item' => site_url('/podcast/')],
        ['@type' => 'ListItem', 'position' => 3, 'name' => $episode['title']],
    ]]],
]);
site_header('podcast');
?>

<main id="main">
  <section class="section podcast podcast--page" data-dark aria-label="Episode">
    <div class="wrap">
      <nav class="crumbs" aria-label="Breadcrumb">
        <ol><li><a href="/">Home</a></li><li><a href="/podcast/">Podcast</a></li><li aria-current="page">This episode</li></ol>
      </nav>
      <?php episode_feature($episode, fmt_date($episode['published_at']), 'h1', false); ?>
    </div>
  </section>

<?php if (trim(strip_tags($episode['body'])) !== ''): ?>
  <section class="section" aria-labelledby="notes-title">
    <div class="wrap article__body">
      <h2 id="notes-title" class="notes__title">Show notes</h2>
      <div class="prose"><?= $episode['body'] ?></div>
    </div>
  </section>
<?php endif; ?>

<?php if ($others): ?>
  <section class="section" aria-labelledby="more-title">
    <div class="wrap">
      <div class="section__head"><h2 id="more-title">More conversations</h2></div>
      <div class="episodes">
<?php foreach ($others as $e): ?>
        <article class="episode">
          <a class="episode__media" href="<?= e(episode_url($e)) ?>" tabindex="-1" aria-hidden="true"><?= img($e['image'], ['sizes' => '(max-width: 760px) 100vw, 30vw', 'phone' => null, 'alt' => '']) ?></a>
          <div class="post__meta"><time datetime="<?= e($e['published_at']) ?>"><?= e(fmt_date($e['published_at'], 'short')) ?></time><?php if ($e['duration']): ?><span><?= (int) $e['duration'] ?> min</span><?php endif; ?></div>
          <h3 class="post__title"><a href="<?= e(episode_url($e)) ?>"><?= e($e['title']) ?></a></h3>
        </article>
<?php endforeach; ?>
      </div>
    </div>
  </section>
<?php endif; ?>
</main>

<?php site_footer(); ?>
