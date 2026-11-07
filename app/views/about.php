<?php
$intro = c('about.intro');
$story = c('about.story');
$tl = c('about.timeline');
$faq = c('about.faq', 'items');
$reach = c('about.reach');
$seo = c('about.seo');

$schema = [
    ['@type' => 'ProfilePage', '@id' => site_url('/about/#page'), 'url' => site_url('/about/'), 'name' => $seo['title'], 'mainEntity' => ['@id' => site_url('/#person')]],
    person_schema(),
    ['@type' => 'BreadcrumbList', 'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => site_url('/')],
        ['@type' => 'ListItem', 'position' => 2, 'name' => $intro['title'], 'item' => site_url('/about/')],
    ]],
];
if ($faq) {
    $qs = [];
    foreach ($faq as $f) if ($f['q'] && $f['a']) $qs[] = ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']]];
    if ($qs) $schema[] = ['@type' => 'FAQPage', 'mainEntity' => $qs];
}

page_head(['title' => $seo['title'], 'description' => $seo['description'], 'path' => '/about/', 'type' => 'profile', 'schema' => $schema]);
site_header('about');

function timeline_section($id, $title, $items)
{
    if (!$items) return;
    ?>
    <section class="split" aria-labelledby="<?= e($id) ?>">
      <h2 id="<?= e($id) ?>"><?= e($title) ?></h2>
      <ul class="timeline">
<?php foreach ($items as $i): ?>
        <li><time><?= e($i['when']) ?></time><div><h3><?php if (!empty($i['url'])): ?><a <?= link_attrs($i['url']) ?>><?= e($i['title']) ?></a><?php else: ?><?= e($i['title']) ?><?php endif; ?></h3><?php if ($i['text']): ?><p><?= e($i['text']) ?></p><?php endif; ?></div></li>
<?php endforeach; ?>
      </ul>
    </section>
<?php
}
?>

<main id="main" class="page-top">
  <div class="wrap">
    <nav class="crumbs" aria-label="Breadcrumb">
      <ol><li><a href="/">Home</a></li><li aria-current="page"><?= e($intro['title']) ?></li></ol>
    </nav>

    <div class="profile">
      <div>
        <h1 class="page-title"><?= e($intro['title']) ?></h1>
        <p class="profile__lead"><?= e($intro['lead']) ?></p>
      </div>
<?php if (!empty($intro['photo']['src'])): ?>
      <figure class="profile__photo">
        <?= img($intro['photo'], ['priority' => true, 'lazy' => false, 'sizes' => '(max-width: 860px) 100vw, 40vw', 'phone' => null]) ?>
      </figure>
<?php endif; ?>
    </div>

<?php if (trim(strip_tags($story['bio'])) !== ''): ?>
    <section class="split" aria-labelledby="bio">
      <h2 id="bio">Biography</h2>
      <div class="prose"><?= $story['bio'] ?></div>
    </section>
<?php endif; ?>

<?php
timeline_section('now', 'Now', $tl['now']);
timeline_section('experience', 'Experience', $tl['experience']);
timeline_section('education', 'Education', $tl['education']);
timeline_section('recognition', 'Recognition', $tl['recognition']);
?>

<?php if ($faq): ?>
    <section class="split faq" aria-labelledby="questions">
      <h2 id="questions">Questions</h2>
      <div>
<?php foreach ($faq as $f): ?>
        <details><summary><?= e($f['q']) ?></summary><p><?= e($f['a']) ?></p></details>
<?php endforeach; ?>
      </div>
    </section>
<?php endif; ?>
  </div>

  <?php reach_band($reach['title'], $reach['text']); ?>
</main>

<?php site_footer(); ?>
