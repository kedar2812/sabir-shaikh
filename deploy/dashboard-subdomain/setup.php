<?php
// One-time setup for dashboard.sabirshaikh.in. Upload as setup-<token>.php with
// TOKEN replaced, open https://dashboard.sabirshaikh.in/setup-<token>.php?t=<token>
// once, then delete it. It links this folder to the main site's code and photos.
// PHP 7.3: no newer syntax.

$token = 'TOKEN';
if (!isset($_GET['t']) || !hash_equals($token, (string) $_GET['t']) || $token === 'TOKEN') { http_response_code(404); exit; }
header('Content-Type: application/json');

$main = dirname(__DIR__) . '/sabirshaikh.in';
$out = ['main' => $main, 'main_ok' => is_file($main . '/app/bootstrap.php'), 'links' => []];
if ($out['main_ok']) {
    foreach (['dashboard', 'assets', 'uploads'] as $name) {
        $link = __DIR__ . '/' . $name;
        if (is_link($link) && readlink($link) === $main . '/' . $name) { $out['links'][$name] = 'already linked'; continue; }
        if (file_exists($link) || is_link($link)) { $out['links'][$name] = 'something is already there; left alone'; continue; }
        $out['links'][$name] = @symlink($main . '/' . $name, $link) ? 'linked' : 'failed';
    }
}
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
