<?php
// The dashboard's single page. Everything inside it is drawn by js/app.js.
require dirname(__DIR__) . '/app/bootstrap.php';

// The dashboard has one home (config dashboard_url); every other address sends you there.
$home = rtrim((string) config('dashboard_url'), '/');
if ($home !== '' && strcasecmp((string) parse_url($home, PHP_URL_HOST), request_host()) !== 0) {
    header('Location: ' . $home . '/', true, 302);
    exit;
}

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob: https://i.ytimg.com; frame-src 'self' https://www.youtube-nocookie.com; style-src 'self' 'unsafe-inline'; script-src 'self'; connect-src 'self'; font-src 'self'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");

$v = '';
foreach (array_merge([__DIR__ . '/app.css'], glob(__DIR__ . '/js/*.js') ?: []) as $f) $v .= @filemtime($f);
$v = substr(md5($v), 0, 8);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="site-url" content="<?= e(public_url('')) ?>">
<meta name="theme-color" content="#f2f2ee" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0e1015" media="(prefers-color-scheme: dark)">
<title>Dashboard | <?= e(c('site', 'name')) ?></title>
<link rel="icon" type="image/png" href="/assets/img/favicon.png">
<link rel="preload" href="/assets/fonts/switzer-400.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/assets/fonts/switzer-500.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="/dashboard/app.css?v=<?= $v ?>">
<script type="module" src="/dashboard/js/app.js?v=<?= $v ?>"></script>
</head>
<body>
<div id="app" aria-live="polite"></div>
<noscript><p class="noscript">The dashboard needs JavaScript. Turn it on in your browser settings, then reload this page.</p></noscript>
</body>
</html>
