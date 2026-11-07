<?php
// $post is set by the router.
$site = c('site');
$author = c('author');
$url = site_url(post_url($post));
$topic = topic_name($post['topic']);
$title = $post['seo_title'] ?: $post['title'] . ' | ' . $site['name'];
$desc = $post['seo_description'] ?: $post['summary'];
$updated = date('Y-m-d', (int) $post['updated_at']);
$minutes = reading_minutes($post['body']);

$related = [];
foreach (published_posts() as $p) {
    if ($p['id'] === $post['id']) continue;
    if ($p['topic'] === $post['topic']) array_unshift($related, $p); else $related[] = $p;
}
$related = array_slice($related, 0, 3);

$crumbs = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => site_url('/')], ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blogs', 'item' => site_url('/blogs/')]];
if ($topic) $crumbs[] = ['@type' => 'ListItem', 'position' => 3, 'name' => $topic, 'item' => site_url('/blogs/?topic=' . rawurlencode($post['topic']))];
$crumbs[] = ['@type' => 'ListItem', 'position' => count($crumbs) + 1, 'name' => $post['title']];

$article = [
    '@type' => 'BlogPosting',
    '@id' => $url . '#article',
    'headline' => $post['title'],
    'description' => $desc,
    'datePublished' => $post['published_at'],
    'dateModified' => $updated,
    'wordCount' => str_word_count(strip_tags($post['body'])),
    'author' => ['@type' => 'Person', '@id' => site_url('/#person'), 'name' => $site['name'], 'url' => site_url('/about/')],
    'publisher' => ['@id' => site_url('/#person')],
    'mainEntityOfPage' => $url,
];
if ($topic) $article['articleSection'] = $topic;
if ($post['tags']) $article['keywords'] = $post['tags'];
if (!empty($post['cover']['src'])) $article['image'] = abs_url($post['cover']['src']);

$extra = '<meta name="author" content="' . e($site['name']) . '">' . "\n"
    . '<meta property="article:published_time" content="' . e($post['published_at']) . '">' . "\n"
    . '<meta property="article:modified_time" content="' . e($updated) . '">';
if ($topic) $extra .= "\n" . '<meta property="article:section" content="' . e($topic) . '">';

page_head([
    'title' => $title,
    'share_title' => $post['title'],
    'description' => $desc,
    'path' => post_url($post),
    'type' => 'article',
    'image' => !empty($post['cover']['src']) ? $post['cover'] : null,
    'extra' => $extra,
    'schema' => [$article, ['@type' => 'BreadcrumbList', 'itemListElement' => $crumbs]],
]);
site_header('blogs');
$shareUrl = rawurlencode($url);
$shareText = rawurlencode($post['title']);
?>

<main id="main" class="page-top">
  <article>
    <div class="wrap">
      <nav class="crumbs" aria-label="Breadcrumb">
        <ol>
          <li><a href="/">Home</a></li>
          <li><a href="/blogs/">Blogs</a></li>
<?php if ($topic): ?>
          <li><a href="/blogs/?topic=<?= e(rawurlencode($post['topic'])) ?>"><?= e($topic) ?></a></li>
<?php endif; ?>
          <li aria-current="page">This essay</li>
        </ol>
      </nav>
      <header class="article__head">
        <h1><?= e($post['title']) ?></h1>
<?php if ($post['summary']): ?>
        <p class="article__sum"><?= e($post['summary']) ?></p>
<?php endif; ?>
        <div class="article__meta">
          <span>By <a href="/about/" rel="author"><?= e($site['name']) ?></a></span>
          <span>Published <time datetime="<?= e($post['published_at']) ?>"><?= e(fmt_date($post['published_at'])) ?></time></span>
<?php if ($updated > $post['published_at']): ?>
          <span>Updated <time datetime="<?= e($updated) ?>"><?= e(fmt_date($updated)) ?></time></span>
<?php endif; ?>
          <span><?= $minutes ?> minute read</span>
        </div>
      </header>
<?php if (!empty($post['cover']['src'])): ?>
      <figure class="article__hero">
        <?= img($post['cover'], ['priority' => true, 'lazy' => false, 'desk' => 'm', 'phone' => null]) ?>
<?php if ($post['cover_caption']): ?>
        <figcaption><?= e($post['cover_caption']) ?></figcaption>
<?php endif; ?>
      </figure>
<?php endif; ?>
    </div>

    <div class="wrap article__body">
      <aside class="article__aside" aria-label="Share this essay">
        <div class="share">
          <p>Share</p>
          <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= $shareUrl ?>" target="_blank" rel="noopener" data-track="share-linkedin">
            <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M3.6 5.5H1V15h2.6V5.5zM2.3 1a1.5 1.5 0 100 3 1.5 1.5 0 000-3zM15 9.6c0-2.6-.6-4.3-3.3-4.3-1.3 0-2.2.7-2.6 1.4V5.5H6.6V15h2.6v-4.7c0-1.2.2-2.4 1.8-2.4 1.5 0 1.5 1.4 1.5 2.5V15H15V9.6z"/></svg>LinkedIn</a>
          <a href="https://x.com/intent/post?url=<?= $shareUrl ?>&amp;text=<?= $shareText ?>" target="_blank" rel="noopener" data-track="share-x">
            <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M12.2 1.5h2.3L9.5 7.2l5.9 7.3h-4.6L7.2 9.9l-4.1 4.6H.8l5.3-6.1L.5 1.5h4.7l3.3 4.2 3.7-4.2zm-.8 11.7h1.3L4.6 2.7H3.2l8.2 10.5z"/></svg>X</a>
          <a href="https://wa.me/?text=<?= $shareText ?>%20<?= $shareUrl ?>" target="_blank" rel="noopener" data-track="share-whatsapp">
            <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M13.6 2.3A7.8 7.8 0 001.3 11.7L.2 15.8l4.2-1.1a7.8 7.8 0 003.7.9 7.8 7.8 0 005.5-13.3zM8.1 14.3a6.5 6.5 0 01-3.3-.9l-.2-.1-2.5.6.7-2.4-.2-.3a6.5 6.5 0 1110 .9 6.4 6.4 0 01-4.5 2.2zm3.6-4.9c-.2-.1-1.2-.6-1.3-.6s-.3-.1-.4.1l-.6.7c-.1.1-.2.1-.4 0a5.3 5.3 0 01-2.6-2.3c-.2-.3.2-.3.6-1 .1-.1 0-.2 0-.3l-.6-1.4c-.2-.4-.3-.3-.4-.3h-.4a.7.7 0 00-.5.2 2.1 2.1 0 00-.7 1.6 3.7 3.7 0 00.8 2 8.4 8.4 0 003.2 2.8c1.2.5 1.7.6 2.3.5a2 2 0 001.3-.9 1.6 1.6 0 00.1-.9c0-.1-.2-.2-.4-.3z"/></svg>WhatsApp</a>
          <button type="button" data-copy><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><rect x="5" y="5" width="9" height="9" rx="1.5"/><path d="M11 5V3.5A1.5 1.5 0 009.5 2h-6A1.5 1.5 0 002 3.5v6A1.5 1.5 0 003.5 11H5"/></svg><span>Copy link</span></button>
        </div>
      </aside>

      <div class="prose">
        <?= $post['body'] ?>

<?php if ($topic || $post['tags']): ?>
        <ul class="tags" aria-label="Topics">
<?php if ($topic): ?>
          <li><a class="topic" href="/blogs/?topic=<?= e(rawurlencode($post['topic'])) ?>"><?= e($topic) ?></a></li>
<?php endif; ?>
<?php foreach ($post['tags'] as $tag): ?>
          <li><a class="topic" href="/blogs/?q=<?= e(rawurlencode(mb_strtolower($tag))) ?>"><?= e($tag) ?></a></li>
<?php endforeach; ?>
        </ul>
<?php endif; ?>

        <section class="author" aria-labelledby="author-title">
<?php if (!empty($author['photo']['src'])): ?>
          <?= img($author['photo'], ['alt' => $site['name'], 'phone' => null]) ?>
<?php endif; ?>
          <div>
            <h2 id="author-title"><?= e($site['name']) ?></h2>
            <p><?= e($author['bio']) ?></p>
            <a class="link-arrow" href="/about/">More about Sabir <?= icon('arrow') ?></a>
          </div>
        </section>
      </div>
    </div>
  </article>

<?php if ($related): ?>
  <section class="section" aria-labelledby="related-title">
    <div class="wrap">
      <div class="section__head">
        <h2 id="related-title">Read next</h2>
      </div>
      <div class="grid-posts">
<?php foreach ($related as $p) post_card($p, 'h3', 'post--side'); ?>
      </div>
    </div>
  </section>
<?php else: ?>
  <div style="height: clamp(4rem, 10vh, 7rem)"></div>
<?php endif; ?>
</main>

<?php site_footer(); ?>
