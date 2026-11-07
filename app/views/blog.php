<?php
$page = c('blog.page');
$posts = published_posts();
$topics = topic_list();
$used = [];
foreach ($posts as $p) $used[$p['topic']] = true;

page_head([
    'title' => $page['seo_title'],
    'description' => $page['seo_description'],
    'path' => '/blogs/',
    'schema' => [
        ['@type' => 'Blog', '@id' => site_url('/blogs/#blog'), 'url' => site_url('/blogs/'), 'name' => $page['seo_title'], 'author' => ['@id' => site_url('/#person')]],
        ['@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => site_url('/')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $page['title'], 'item' => site_url('/blogs/')],
        ]],
    ],
]);
site_header('blogs');
?>

<main id="main" class="page-top">
  <div class="wrap">
    <nav class="crumbs" aria-label="Breadcrumb">
      <ol><li><a href="/">Home</a></li><li aria-current="page"><?= e($page['title']) ?></li></ol>
    </nav>
    <h1 class="page-title"><?= e($page['title']) ?></h1>

    <div class="finder" data-finder>
      <div class="topics" role="group" aria-label="Filter by topic">
        <button class="topic" type="button" data-topic="all" aria-pressed="true">All</button>
<?php foreach ($topics as $t): if (empty($used[$t['slug']])) continue; ?>
        <button class="topic" type="button" data-topic="<?= e($t['slug']) ?>" aria-pressed="false" data-about="<?= e($t['about']) ?>"><?= e($t['name']) ?></button>
<?php endforeach; ?>
      </div>
      <label class="search">
        <span class="sr-only">Search the essays</span>
        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="7" cy="7" r="5"/><path d="M11 11l3.5 3.5"/></svg>
        <input type="search" placeholder="Search the essays" autocomplete="off">
      </label>
    </div>
    <p class="topic-note" data-topic-note aria-live="polite"></p>

    <div class="grid-posts">
<?php foreach ($posts as $p) post_card($p, 'h2'); ?>
    </div>
    <p class="empty" data-empty<?= $posts ? ' hidden' : '' ?>><?= e($posts ? $page['empty'] : 'The first essays are on their way.') ?></p>
  </div>
  <div style="height: clamp(5rem, 12vh, 8rem)"></div>
</main>

<?php site_footer(); ?>
