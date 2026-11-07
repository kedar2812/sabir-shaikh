<?php
$page = c('contact.page');
$site = c('site');
$social = c('social');
$wa = preg_replace('~\D~', '', $site['whatsapp']);

page_head([
    'title' => $page['seo_title'],
    'description' => $page['seo_description'],
    'path' => '/contact/',
    'schema' => [
        ['@type' => 'ContactPage', 'url' => site_url('/contact/'), 'name' => $page['seo_title'], 'about' => ['@id' => site_url('/#person')]],
        ['@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => site_url('/')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $page['title'], 'item' => site_url('/contact/')],
        ]],
    ],
]);
site_header('contact');
?>

<main id="main" class="page-top">
  <div class="wrap">
    <nav class="crumbs" aria-label="Breadcrumb">
      <ol><li><a href="/">Home</a></li><li aria-current="page"><?= e($page['title']) ?></li></ol>
    </nav>
    <h1 class="page-title"><?= e($page['title']) ?></h1>

    <div class="contact">
      <div class="contact__info">
        <p class="profile__lead"><?= e($page['lead']) ?></p>
        <ul class="contact__list">
          <li><span>Based in</span><strong><?= e($site['location']) ?></strong></li>
          <li><span>Email</span><a href="mailto:<?= e($site['email']) ?>" data-track="email"><?= e($site['email']) ?></a></li>
<?php if ($social['linkedin']): ?>
          <li><span>LinkedIn</span><a href="<?= e($social['linkedin']) ?>" target="_blank" rel="me noopener" data-track="linkedin">Message on LinkedIn</a></li>
<?php endif; ?>
<?php if ($social['instagram']): ?>
          <li><span>Instagram</span><a href="<?= e($social['instagram']) ?>" target="_blank" rel="me noopener" data-track="instagram"><?= e(social_handle($social['instagram']) ?: 'Instagram') ?></a></li>
<?php endif; ?>
<?php if ($social['x']): ?>
          <li><span>X</span><a href="<?= e($social['x']) ?>" target="_blank" rel="me noopener" data-track="x"><?= e(social_handle($social['x']) ?: 'X') ?></a></li>
<?php endif; ?>
<?php if ($social['youtube']): ?>
          <li><span>YouTube</span><a href="<?= e($social['youtube']) ?>" target="_blank" rel="me noopener" data-track="youtube"><?= e(social_handle($social['youtube']) ?: 'YouTube') ?></a></li>
<?php endif; ?>
<?php if ($social['facebook']): ?>
          <li><span>Facebook</span><a href="<?= e($social['facebook']) ?>" target="_blank" rel="me noopener" data-track="facebook"><?= e(social_handle($social['facebook']) ?: 'Sabir Shaikh') ?></a></li>
<?php endif; ?>
<?php if ($wa): ?>
          <li><span>WhatsApp</span><a href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener" data-track="whatsapp"><?= e($site['whatsapp']) ?></a></li>
<?php endif; ?>
        </ul>
      </div>

      <form class="form" data-form data-endpoint="" data-mailto="<?= e($site['email']) ?>" novalidate>
        <div class="row">
          <div class="field">
            <label for="f-name">Your name</label>
            <input id="f-name" name="name" type="text" autocomplete="name" required data-msg="Add your name so Sabir knows who's writing.">
            <span class="err" aria-live="polite"></span>
          </div>
          <div class="field">
            <label for="f-email">Email</label>
            <input id="f-email" name="email" type="email" autocomplete="email" required data-msg="Add an email address in the form name@example.com.">
            <span class="err" aria-live="polite"></span>
          </div>
        </div>
        <div class="field">
          <label for="f-reason">What it's about</label>
          <select id="f-reason" name="reason" required data-msg="Pick the closest option.">
<?php foreach ($page['reasons'] as $r): ?>
            <option value="<?= e($r) ?>"><?= e($r) ?></option>
<?php endforeach; ?>
          </select>
          <span class="err" aria-live="polite"></span>
        </div>
        <div class="field">
          <label for="f-message">Message</label>
          <textarea id="f-message" name="message" required minlength="20" data-msg="Write a little more, at least a couple of sentences."></textarea>
          <span class="err" aria-live="polite"></span>
        </div>
        <div class="hp" aria-hidden="true"><label>Leave this empty <input type="text" name="company_website" tabindex="-1" autocomplete="off"></label></div>
        <div>
          <button class="btn" type="submit">Send message</button>
        </div>
        <p class="form__status" role="status"></p>
        <small>Your details are used only to reply to you. Read the <a href="/privacy-policy/">privacy policy</a>.</small>
      </form>
    </div>
  </div>
</main>

<?php site_footer(); ?>
