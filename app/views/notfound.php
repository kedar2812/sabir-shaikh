<?php
page_head(['title' => 'Page not found | ' . c('site', 'name'), 'path' => '/404/', 'robots' => 'noindex']);
site_header();
?>

<main id="main" class="lost">
  <div>
    <svg viewBox="0 0 1825 1590" role="img" aria-label="The crest"><use href="/assets/img/crest.svg#c"/></svg>
    <h1>This page isn't here.</h1>
    <p>The link may be old, or the page may have moved. The essays are all on the blog, or you can start again from the home page.</p>
    <div class="reach__actions">
      <a class="btn" href="/blogs/">Go to the blog</a>
      <a class="link-arrow" href="/">Back to the home page</a>
    </div>
  </div>
</main>

<?php site_footer(); ?>
