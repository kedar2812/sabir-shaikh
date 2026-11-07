<?php
// Front door for every public address. Real files (CSS, photos, fonts) are served
// by Apache directly; everything else comes here (see .htaccess).

require __DIR__ . '/app/bootstrap.php';

$path = (string) parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);
$path = '/' . ltrim(rawurldecode($path), '/');
$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: SAMEORIGIN');

// ----------------------------------------------------------------- Visit counting
// Named neutrally so content blockers that guess at "analytics" leave it alone.
if (strpos($path, '/api/') === 0) {
    require APP . '/analytics.php';
    header('Cache-Control: no-store');
    if ($method !== 'POST') { http_response_code(405); exit; }
    $in = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($in)) { http_response_code(400); exit; }
    try {
        if ($path === '/api/v') {
            $id = record_hit($in);
            header('Content-Type: application/json');
            echo json_encode(['id' => $id]);
        } elseif ($path === '/api/t') {
            record_time($in);
            http_response_code(204);
        } elseif ($path === '/api/e') {
            record_event($in);
            http_response_code(204);
        } else {
            http_response_code(404);
        }
    } catch (Exception $ex) {
        http_response_code(204);
    }
    exit;
}

if ($path === '/sitemap.xml') {
    header('Content-Type: application/xml; charset=utf-8');
    $urls = [['/', null], ['/about/', null], ['/blogs/', null], ['/podcast/', null], ['/contact/', null], ['/privacy-policy/', null]];
    foreach (published_posts() as $p) $urls[] = [post_url($p), date('Y-m-d', (int) $p['updated_at'])];
    foreach (published_episodes() as $e) $urls[] = [episode_url($e), date('Y-m-d', (int) $e['updated_at'])];
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($urls as $u) {
        echo '  <url><loc>' . e(site_url($u[0])) . '</loc>' . ($u[1] ? '<lastmod>' . $u[1] . '</lastmod>' : '') . "</url>\n";
    }
    echo "</urlset>\n";
    exit;
}

// Addresses without a trailing slash get one, so every page has one canonical URL.
if ($path !== '/' && substr($path, -1) !== '/' && strpos(basename($path), '.') === false) {
    $qs = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? '?' . $_SERVER['QUERY_STRING'] : '';
    header('Location: ' . $path . '/' . $qs, true, 301);
    exit;
}

$previewing = !empty($_GET['preview']) && current_user();
header($previewing ? 'Cache-Control: no-store' : 'Cache-Control: no-cache');
if ($previewing) header('X-Robots-Tag: noindex');

require APP . '/views/layout.php';

function render($view, $vars = [])
{
    extract($vars);
    require APP . '/views/' . $view . '.php';
    exit;
}

function not_found()
{
    // A slug that was renamed in the dashboard keeps working via a redirect.
    $to = q_val('SELECT to_path FROM redirects WHERE from_path = ?', [$GLOBALS['path']]);
    if ($to) {
        header('Location: ' . $to, true, 301);
        exit;
    }
    http_response_code(404);
    render('notfound');
}

$pages = ['/' => 'home', '/about/' => 'about', '/blogs/' => 'blog', '/podcast/' => 'podcast', '/contact/' => 'contact', '/privacy-policy/' => 'privacy'];
if (isset($pages[$path])) render($pages[$path]);

if (preg_match('~^/blogs/([a-z0-9-]+)/$~', $path, $m)) {
    $draft = preview_overrides('post');
    if ($draft && $draft['slug'] === $m[1]) render('post', ['post' => $draft]);
    $post = decode_post(q_one('SELECT * FROM posts WHERE slug = ?', [$m[1]]));
    if ($post && ($previewing || ($post['status'] === 'published' && $post['published_at'] <= date('Y-m-d')))) render('post', ['post' => $post]);
    not_found();
}

if (preg_match('~^/podcast/([a-z0-9-]+)/$~', $path, $m)) {
    $draft = preview_overrides('episode');
    if ($draft && $draft['slug'] === $m[1]) render('episode', ['episode' => $draft]);
    $ep = decode_episode(q_one('SELECT * FROM episodes WHERE slug = ?', [$m[1]]));
    if ($ep && ($previewing || ($ep['status'] === 'published' && $ep['published_at'] <= date('Y-m-d')))) render('episode', ['episode' => $ep]);
    not_found();
}

// Old static-site addresses.
if (in_array(rtrim($path, '/'), ['/404.html', '/index.html'], true)) {
    header('Location: /', true, 301);
    exit;
}

not_found();
