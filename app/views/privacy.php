<?php
$page = c('privacy.page');
$name = c('site', 'name');

page_head([
    'title' => 'Privacy policy | ' . $name,
    'description' => 'How sabirshaikh.in collects, uses and protects your information, including the contact form and how visits are counted.',
    'path' => '/privacy-policy/',
    'schema' => [['@type' => 'BreadcrumbList', 'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => site_url('/')],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Privacy policy', 'item' => site_url('/privacy-policy/')],
    ]]],
]);
site_header();
?>

<main id="main" class="page-top">
  <div class="wrap">
    <nav class="crumbs" aria-label="Breadcrumb">
      <ol><li><a href="/">Home</a></li><li aria-current="page">Privacy policy</li></ol>
    </nav>
    <h1 class="page-title">Privacy</h1>
    <div class="prose" style="padding-block: 3rem 6rem">
<?php if ($page['updated']): ?>
      <p class="prose__date">Last updated <?= e(fmt_date($page['updated'])) ?>.</p>
<?php endif; ?>
      <?= $page['body'] ?>
    </div>
  </div>
</main>

<?php site_footer(); ?>
