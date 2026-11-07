<?php
// JSON API for the dashboard. Routes look like  GET /dashboard/api/posts/12 .
// Every route except sign-in needs a signed-in session; every change also needs
// the session's CSRF token in the X-CSRF-Token header.

require dirname(__DIR__) . '/app/bootstrap.php';
require APP . '/sanitize.php';
require APP . '/media.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

set_exception_handler(function ($ex) {
    @file_put_contents(DATA . '/error.log', date('c') . ' ' . $ex->getMessage() . ' @ ' . $ex->getFile() . ':' . $ex->getLine() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => ['message' => 'Something went wrong on the server. Try again, and if it keeps happening, let your developer know.']]);
    exit;
});

function out($data = null, $code = 200)
{
    http_response_code($code);
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function fail($message, $code = 400, $fields = [])
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => ['message' => $message, 'fields' => (object) $fields]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function body()
{
    static $b = null;
    if ($b === null) {
        $b = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($b)) $b = [];
    }
    return $b;
}

function same_origin()
{
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
    if ($origin === '') return true;
    return strtolower((string) parse_url($origin, PHP_URL_HOST)) === strtolower(explode(':', $_SERVER['HTTP_HOST'])[0]);
}

$method = $_SERVER['REQUEST_METHOD'];
$route = trim(isset($_GET['r']) ? $_GET['r'] : '', '/');
$parts = $route === '' ? [] : explode('/', $route);
$res = isset($parts[0]) ? $parts[0] : '';
$id = isset($parts[1]) ? $parts[1] : null;

if ($method !== 'GET' && !same_origin()) fail('This request came from another site and was blocked.', 403);

// ----------------------------------------------------------------------------- Sign in
if ($res === 'auth') {
    if ($id === 'login' && $method === 'POST') {
        $b = body();
        list($user, $err) = attempt_login(isset($b['username']) ? $b['username'] : '', isset($b['password']) ? $b['password'] : '');
        if (!$user) fail($err, 401);
        out(['user' => $user, 'csrf' => $_SESSION['csrf']]);
    }
    if ($id === 'logout' && $method === 'POST') {
        logout();
        out();
    }
    if ($id === 'session') {
        $u = current_user();
        if (!$u) fail('Signed out.', 401);
        out(['user' => $u, 'csrf' => csrf_token()]);
    }
    fail('Not found.', 404);
}

$user = current_user();
if (!$user) fail('Your session ended. Sign in again to keep working.', 401);
if ($method !== 'GET') {
    $token = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : '';
    if (!hash_equals(csrf_token(), $token)) fail('Your session ended. Sign in again to keep working.', 401);
}
// Preview writes to the session; everything else only reads it, so release the lock early.
if ($res !== 'preview') session_write_close();

// ----------------------------------------------------------------------------- Validation

function check_field($f, $v, &$errors, $prefix = '')
{
    $name = $prefix . $f['name'];
    $label = $f['label'];
    switch ($f['type']) {
        case 'text':
        case 'textarea':
            $v = trim(str_replace("\r\n", "\n", (string) $v));
            $max = isset($f['max']) ? $f['max'] : ($f['type'] === 'text' ? 300 : 5000);
            if (mb_strlen($v) > $max) $errors[$name] = $label . ' can be at most ' . $max . ' characters. It has ' . mb_strlen($v) . '.';
            return $v;
        case 'richtext':
            return clean_html((string) $v);
        case 'slug':
            return slugify((string) $v);
        case 'email':
            $v = trim((string) $v);
            if ($v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) $errors[$name] = 'Enter an email address like name@example.com.';
            return $v;
        case 'url':
            $v = trim((string) $v);
            if ($v !== '' && !preg_match('~^(https?://[^\s]+|/[^\s]*|mailto:[^\s]+|tel:[^\s]+|#[^\s]*)$~i', $v)) {
                if (preg_match('~^[a-z0-9-]+(\.[a-z0-9-]+)+(/[^\s]*)?$~i', $v)) $v = 'https://' . $v;
                else $errors[$name] = 'Enter a full link, starting with https://';
            }
            return $v;
        case 'date':
            $v = trim((string) $v);
            if ($v !== '' && !preg_match('~^\d{4}-\d{2}-\d{2}$~', $v)) $errors[$name] = 'Pick a date.';
            return $v;
        case 'words':
            $out = [];
            foreach ((array) $v as $w) { $w = trim((string) $w); if ($w !== '') $out[] = mb_substr($w, 0, 80); }
            return array_slice($out, 0, 40);
        case 'image':
            return check_image($v, $name, $errors);
        case 'list':
            $items = is_array($v) ? array_values($v) : [];
            if (!empty($f['max']) && count($items) > $f['max']) $items = array_slice($items, 0, $f['max']);
            $out = [];
            foreach ($items as $i => $item) {
                $row = [];
                foreach ($f['fields'] as $sub) $row[$sub['name']] = check_field($sub, isset($item[$sub['name']]) ? $item[$sub['name']] : '', $errors, $name . '.' . $i . '.');
                $out[] = $row;
            }
            return $out;
    }
    return $v;
}

function check_image($v, $name, &$errors)
{
    if (!is_array($v) || empty($v['src'])) return null;
    $src = (string) $v['src'];
    if (!preg_match('~^/(uploads|assets)/[A-Za-z0-9/_.-]+\.(webp|jpe?g|png|gif)$~', $src) || strpos($src, '..') !== false) {
        $errors[$name] = 'Choose a photo from the library.';
        return null;
    }
    $focus = [];
    foreach (['d', 'm'] as $k) {
        if (empty($v['focus'][$k])) continue;
        $p = $v['focus'][$k];
        $focus[$k] = [
            'x' => round(max(0, min(100, (float) (isset($p['x']) ? $p['x'] : 50))), 2),
            'y' => round(max(0, min(100, (float) (isset($p['y']) ? $p['y'] : 50))), 2),
            'z' => round(max(1, min(3, (float) (isset($p['z']) ? $p['z'] : 1))), 3),
        ];
    }
    $sizes = [];
    foreach ((array) (isset($v['sizes']) ? $v['sizes'] : []) as $s) {
        if (isset($s['src'], $s['w']) && preg_match('~^/uploads/[A-Za-z0-9/_.-]+\.webp$~', $s['src'])) $sizes[] = ['w' => (int) $s['w'], 'src' => $s['src']];
    }
    return [
        'src' => $src,
        'alt' => mb_substr(trim((string) (isset($v['alt']) ? $v['alt'] : '')), 0, 300),
        'w' => (int) (isset($v['w']) ? $v['w'] : 0),
        'h' => (int) (isset($v['h']) ? $v['h'] : 0),
        'sizes' => $sizes,
        'focus' => $focus ?: ['d' => ['x' => 50, 'y' => 50, 'z' => 1]],
    ];
}

function check_section($section, $input)
{
    $errors = [];
    $values = [];
    foreach ($section['fields'] as $f) {
        $values[$f['name']] = check_field($f, isset($input[$f['name']]) ? $input[$f['name']] : (isset($f['default']) && $f['type'] === 'list' && !empty($f['fixed']) ? $f['default'] : ''), $errors);
        if (!empty($f['fixed']) && $f['type'] === 'list' && count($values[$f['name']]) !== count($f['default'])) {
            $errors[$f['name']] = $f['label'] . ' must have exactly ' . count($f['default']) . ' entries.';
        }
    }
    if ($section['key'] === 'blog.topics') {
        $seen = [];
        foreach ($values['items'] as $i => &$t) {
            if ($t['slug'] === '') $t['slug'] = slugify($t['name']);
            if ($t['name'] === '') $errors['items.' . $i . '.name'] = 'Give the topic a name.';
            if (isset($seen[$t['slug']])) $errors['items.' . $i . '.slug'] = 'Two topics share this web address word.';
            $seen[$t['slug']] = true;
        }
        unset($t);
    }
    return [$values, $errors];
}

function youtube_id($v)
{
    $v = trim((string) $v);
    if (preg_match('~^[A-Za-z0-9_-]{11}$~', $v)) return $v;
    if (preg_match('~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/|v/))([A-Za-z0-9_-]{11})~', $v, $m)) return $m[1];
    return '';
}

function unique_slug($table, $slug, $id)
{
    $base = $slug;
    $n = 2;
    while (q_val("SELECT id FROM $table WHERE slug = ? AND id != ?", [$slug, (int) $id])) $slug = $base . '-' . $n++;
    return $slug;
}

// Shared by save and preview.
function check_post($in, $id = 0)
{
    $errors = [];
    $title = trim((string) (isset($in['title']) ? $in['title'] : ''));
    if ($title === '') $errors['title'] = 'Give the essay a title.';
    if (mb_strlen($title) > 200) $errors['title'] = 'Keep the title under 200 characters.';
    $slug = slugify(isset($in['slug']) && $in['slug'] !== '' ? $in['slug'] : $title);
    if ($slug === '') $errors['slug'] = 'The web address needs at least one letter or number.';
    elseif (q_val('SELECT id FROM posts WHERE slug = ? AND id != ?', [$slug, (int) $id])) $errors['slug'] = 'Another essay already uses this web address.';
    $status = (isset($in['status']) && $in['status'] === 'published') ? 'published' : 'draft';
    $date = trim((string) (isset($in['published_at']) ? $in['published_at'] : ''));
    if ($date === '' && $status === 'published') $date = date('Y-m-d');
    if ($date !== '' && !preg_match('~^\d{4}-\d{2}-\d{2}$~', $date)) $errors['published_at'] = 'Pick a date.';
    $topic = (string) (isset($in['topic']) ? $in['topic'] : '');
    $tags = [];
    foreach ((array) (isset($in['tags']) ? $in['tags'] : []) as $t) { $t = trim((string) $t); if ($t !== '') $tags[] = mb_substr($t, 0, 40); }
    $p = [
        'id' => (int) $id,
        'slug' => $slug,
        'title' => $title,
        'summary' => mb_substr(trim((string) (isset($in['summary']) ? $in['summary'] : '')), 0, 600),
        'topic' => $topic,
        'card' => (isset($in['card']) && $in['card'] === 'photo') ? 'photo' : 'words',
        'card_text' => mb_substr(trim((string) (isset($in['card_text']) ? $in['card_text'] : '')), 0, 60),
        'card_tone' => in_array(isset($in['card_tone']) ? $in['card_tone'] : '', ['oxblood', 'lacquer', 'saffron', 'bone'], true) ? $in['card_tone'] : 'oxblood',
        'cover' => check_image(isset($in['cover']) ? $in['cover'] : null, 'cover', $errors),
        'cover_caption' => mb_substr(trim((string) (isset($in['cover_caption']) ? $in['cover_caption'] : '')), 0, 200),
        'body' => clean_html(isset($in['body']) ? $in['body'] : ''),
        'tags' => array_slice(array_values(array_unique($tags)), 0, 8),
        'seo_title' => mb_substr(trim((string) (isset($in['seo_title']) ? $in['seo_title'] : '')), 0, 120),
        'seo_description' => mb_substr(trim((string) (isset($in['seo_description']) ? $in['seo_description'] : '')), 0, 300),
        'status' => $status,
        'featured' => !empty($in['featured']) ? 1 : 0,
        'published_at' => $date ?: null,
        'updated_at' => now(),
    ];
    if ($p['card'] === 'photo' && empty($p['cover'])) $errors['cover'] = 'Add a photo, or switch the card to words on colour.';
    return [$p, $errors];
}

function check_episode($in, $id = 0)
{
    $errors = [];
    $title = trim((string) (isset($in['title']) ? $in['title'] : ''));
    if ($title === '') $errors['title'] = 'Give the episode a title.';
    $slug = slugify(isset($in['slug']) && $in['slug'] !== '' ? $in['slug'] : $title);
    if ($slug === '') $errors['slug'] = 'The web address needs at least one letter or number.';
    elseif (q_val('SELECT id FROM episodes WHERE slug = ? AND id != ?', [$slug, (int) $id])) $errors['slug'] = 'Another episode already uses this web address.';
    $video = isset($in['video']) ? (string) $in['video'] : '';
    $vid = youtube_id($video);
    if ($video !== '' && $vid === '') $errors['video'] = 'That doesn\'t look like a YouTube link. Copy it from the Share button on YouTube.';
    $status = (isset($in['status']) && $in['status'] === 'published') ? 'published' : 'draft';
    $date = trim((string) (isset($in['published_at']) ? $in['published_at'] : ''));
    if ($date === '' && $status === 'published') $date = date('Y-m-d');
    $links = [];
    foreach ((array) (isset($in['links']) ? $in['links'] : []) as $i => $l) {
        $label = trim((string) (isset($l['label']) ? $l['label'] : ''));
        $url = trim((string) (isset($l['url']) ? $l['url'] : ''));
        if ($label === '' && $url === '') continue;
        if (!preg_match('~^https?://~i', $url)) $errors['links.' . $i . '.url'] = 'Enter a full link, starting with https://';
        $links[] = ['label' => $label ?: 'Listen', 'url' => $url];
    }
    $e = [
        'id' => (int) $id,
        'slug' => $slug,
        'title' => $title,
        'guest' => mb_substr(trim((string) (isset($in['guest']) ? $in['guest'] : '')), 0, 200),
        'summary' => mb_substr(trim((string) (isset($in['summary']) ? $in['summary'] : '')), 0, 800),
        'body' => clean_html(isset($in['body']) ? $in['body'] : ''),
        'video_id' => $vid,
        'duration' => max(0, min(1000, (int) (isset($in['duration']) ? $in['duration'] : 0))),
        'credit' => mb_substr(trim((string) (isset($in['credit']) ? $in['credit'] : '')), 0, 200),
        'image' => check_image(isset($in['image']) ? $in['image'] : null, 'image', $errors),
        'links' => array_slice($links, 0, 6),
        'seo_description' => mb_substr(trim((string) (isset($in['seo_description']) ? $in['seo_description'] : '')), 0, 300),
        'status' => $status,
        'featured' => !empty($in['featured']) ? 1 : 0,
        'published_at' => $date ?: null,
        'updated_at' => now(),
    ];
    return [$e, $errors];
}

function post_for_api($p)
{
    return $p;
}

function remember_redirect($from, $to)
{
    if ($from === $to) return;
    q('DELETE FROM redirects WHERE from_path = ?', [$to]);
    q('INSERT OR REPLACE INTO redirects (from_path, to_path, created_at) VALUES (?,?,?)', [$from, $to, now()]);
    q('UPDATE redirects SET to_path = ? WHERE to_path = ?', [$to, $from]);
}

function page_path_for_group($group)
{
    $map = ['home' => '/', 'about' => '/about/', 'blog' => '/blogs/', 'podcast' => '/podcast/', 'contact' => '/contact/', 'privacy' => '/privacy-policy/', 'site' => '/'];
    return isset($map[$group]) ? $map[$group] : '/';
}

function views_by_path($days)
{
    require_once APP . '/analytics.php';
    $out = [];
    foreach (q_all('SELECT path, COUNT(*) AS n FROM hits WHERE ts > ? GROUP BY path', [now() - 86400 * $days]) as $r) $out[$r['path']] = (int) $r['n'];
    return $out;
}

// ----------------------------------------------------------------------------- Routes

switch ($res) {

    case 'stats':
        require APP . '/analytics.php';
        $range = isset($_GET['range']) ? $_GET['range'] : '30d';
        if (!in_array($range, ['today', '7d', '30d', '90d', '12m'], true)) $range = '30d';
        $report = stats_report($range, isset($_GET['full']) ? 30 : 8);
        $last = q_one("SELECT title, published_at FROM posts WHERE status = 'published' ORDER BY published_at DESC LIMIT 1");
        $report['content'] = [
            'posts' => (int) q_val("SELECT COUNT(*) FROM posts WHERE status = 'published'"),
            'drafts' => (int) q_val("SELECT COUNT(*) FROM posts WHERE status = 'draft'"),
            'episodes' => (int) q_val("SELECT COUNT(*) FROM episodes WHERE status = 'published'"),
            'last_post' => $last,
        ];
        out($report);

    case 'content':
        if ($method === 'GET' && $id === null) {
            out(['schema' => content_schema(), 'frames' => content_frames(), 'values' => content_all(), 'saved' => q_all('SELECT key, updated_at FROM content')]);
        }
        $key = $id;
        $section = content_section($key);
        if (!$section) fail('That part of the site doesn\'t exist.', 404);
        if ($method === 'PUT') {
            list($values, $errors) = check_section($section, body());
            if ($errors) fail('Some fields need a look before this can be saved.', 422, $errors);
            q('INSERT OR REPLACE INTO content (key, value, updated_at) VALUES (?,?,?)', [$key, jenc($values), now()]);
            out(['key' => $key, 'values' => $values, 'updated_at' => now()]);
        }
        break;

    case 'preview':
        if ($method !== 'POST') break;
        $b = body();
        $kind = isset($b['kind']) ? $b['kind'] : '';
        if (!isset($_SESSION['preview'])) $_SESSION['preview'] = [];
        if ($kind === 'content') {
            // sections: { key: values } for every section with unsaved edits on the page being previewed.
            $draft = [];
            $group = isset($b['group']) ? $b['group'] : 'home';
            foreach ((array) (isset($b['sections']) ? $b['sections'] : []) as $key => $value) {
                $section = content_section($key);
                if (!$section) continue;
                list($values) = check_section($section, is_array($value) ? $value : []);
                $draft[$key] = $values;
            }
            $_SESSION['preview']['content'] = $draft;
            out(['url' => public_url(page_path_for_group($group) . '?preview=' . time())]);
        }
        if ($kind === 'post') {
            list($p) = check_post(isset($b['value']) ? $b['value'] : [], isset($b['id']) ? $b['id'] : 0);
            $p['created_at'] = now();
            if (!$p['published_at']) $p['published_at'] = date('Y-m-d');
            $_SESSION['preview']['post'] = $p;
            out(['url' => public_url(post_url($p) . '?preview=' . time())]);
        }
        if ($kind === 'episode') {
            list($e) = check_episode(isset($b['value']) ? $b['value'] : [], isset($b['id']) ? $b['id'] : 0);
            $e['created_at'] = now();
            if (!$e['published_at']) $e['published_at'] = date('Y-m-d');
            $_SESSION['preview']['episode'] = $e;
            out(['url' => public_url(episode_url($e) . '?preview=' . time())]);
        }
        fail('Nothing to preview.');

    case 'posts':
        if ($method === 'GET' && $id === null) {
            $views = views_by_path(30);
            $rows = [];
            foreach (q_all('SELECT * FROM posts ORDER BY CASE status WHEN \'draft\' THEN 0 ELSE 1 END, published_at DESC, id DESC') as $p) {
                $p = decode_post($p);
                $rows[] = ['id' => (int) $p['id'], 'title' => $p['title'], 'slug' => $p['slug'], 'status' => $p['status'], 'topic' => $p['topic'], 'featured' => $p['featured'],
                    'published_at' => $p['published_at'], 'updated_at' => (int) $p['updated_at'], 'cover' => $p['cover'], 'card' => $p['card'], 'card_text' => $p['card_text'], 'card_tone' => $p['card_tone'],
                    'views' => isset($views[post_url($p)]) ? $views[post_url($p)] : 0, 'words' => str_word_count(strip_tags($p['body']))];
            }
            out($rows);
        }
        if ($method === 'POST' && $id === null) {
            list($p, $errors) = check_post(body());
            if ($errors) fail('Some fields need a look before this can be saved.', 422, $errors);
            if ($p['featured']) q('UPDATE posts SET featured = 0');
            q('INSERT INTO posts (slug, title, summary, topic, card, card_text, card_tone, cover, cover_caption, body, tags, seo_title, seo_description, status, featured, published_at, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
                $p['slug'], $p['title'], $p['summary'], $p['topic'], $p['card'], $p['card_text'], $p['card_tone'], $p['cover'] ? jenc($p['cover']) : '', $p['cover_caption'], $p['body'], jenc($p['tags']), $p['seo_title'], $p['seo_description'], $p['status'], $p['featured'], $p['published_at'], now(), now(),
            ]);
            out(decode_post(q_one('SELECT * FROM posts WHERE id = ?', [db()->lastInsertId()])), 201);
        }
        $old = decode_post(q_one('SELECT * FROM posts WHERE id = ?', [(int) $id]));
        if (!$old) fail('That essay no longer exists. It may have been deleted in another window.', 404);
        if ($method === 'GET') out($old);
        if ($method === 'PUT') {
            list($p, $errors) = check_post(body(), $old['id']);
            if ($errors) fail('Some fields need a look before this can be saved.', 422, $errors);
            if ($p['featured']) q('UPDATE posts SET featured = 0 WHERE id != ?', [$old['id']]);
            q('UPDATE posts SET slug=?, title=?, summary=?, topic=?, card=?, card_text=?, card_tone=?, cover=?, cover_caption=?, body=?, tags=?, seo_title=?, seo_description=?, status=?, featured=?, published_at=?, updated_at=? WHERE id=?', [
                $p['slug'], $p['title'], $p['summary'], $p['topic'], $p['card'], $p['card_text'], $p['card_tone'], $p['cover'] ? jenc($p['cover']) : '', $p['cover_caption'], $p['body'], jenc($p['tags']), $p['seo_title'], $p['seo_description'], $p['status'], $p['featured'], $p['published_at'], now(), $old['id'],
            ]);
            if ($old['slug'] !== $p['slug'] && $old['status'] === 'published') remember_redirect(post_url($old), post_url($p));
            out(decode_post(q_one('SELECT * FROM posts WHERE id = ?', [$old['id']])));
        }
        if ($method === 'DELETE') {
            q('DELETE FROM posts WHERE id = ?', [$old['id']]);
            if ($old['status'] === 'published') remember_redirect(post_url($old), '/blogs/');
            out(['deleted' => (int) $old['id']]);
        }
        break;

    case 'episodes':
        if ($method === 'GET' && $id === null) {
            $views = views_by_path(30);
            $plays = [];
            foreach (q_all("SELECT label, COUNT(*) AS n FROM events WHERE name = 'podcast_play' AND ts > ? GROUP BY label", [now() - 86400 * 30]) as $r) $plays[$r['label']] = (int) $r['n'];
            $rows = [];
            foreach (q_all('SELECT * FROM episodes ORDER BY CASE status WHEN \'draft\' THEN 0 ELSE 1 END, published_at DESC, id DESC') as $e) {
                $e = decode_episode($e);
                $rows[] = ['id' => (int) $e['id'], 'title' => $e['title'], 'slug' => $e['slug'], 'status' => $e['status'], 'featured' => $e['featured'], 'guest' => $e['guest'],
                    'published_at' => $e['published_at'], 'updated_at' => (int) $e['updated_at'], 'image' => $e['image'], 'duration' => $e['duration'], 'video_id' => $e['video_id'],
                    'views' => isset($views[episode_url($e)]) ? $views[episode_url($e)] : 0, 'plays' => isset($plays[$e['title']]) ? $plays[$e['title']] : 0];
            }
            out($rows);
        }
        if ($method === 'POST' && $id === null) {
            list($e, $errors) = check_episode(body());
            if ($errors) fail('Some fields need a look before this can be saved.', 422, $errors);
            if ($e['featured']) q('UPDATE episodes SET featured = 0');
            q('INSERT INTO episodes (slug, title, guest, summary, body, video_id, duration, credit, image, links, seo_description, status, featured, published_at, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
                $e['slug'], $e['title'], $e['guest'], $e['summary'], $e['body'], $e['video_id'], $e['duration'], $e['credit'], $e['image'] ? jenc($e['image']) : '', jenc($e['links']), $e['seo_description'], $e['status'], $e['featured'], $e['published_at'], now(), now(),
            ]);
            out(decode_episode(q_one('SELECT * FROM episodes WHERE id = ?', [db()->lastInsertId()])), 201);
        }
        $old = decode_episode(q_one('SELECT * FROM episodes WHERE id = ?', [(int) $id]));
        if (!$old) fail('That episode no longer exists. It may have been deleted in another window.', 404);
        if ($method === 'GET') out($old);
        if ($method === 'PUT') {
            list($e, $errors) = check_episode(body(), $old['id']);
            if ($errors) fail('Some fields need a look before this can be saved.', 422, $errors);
            if ($e['featured']) q('UPDATE episodes SET featured = 0 WHERE id != ?', [$old['id']]);
            q('UPDATE episodes SET slug=?, title=?, guest=?, summary=?, body=?, video_id=?, duration=?, credit=?, image=?, links=?, seo_description=?, status=?, featured=?, published_at=?, updated_at=? WHERE id=?', [
                $e['slug'], $e['title'], $e['guest'], $e['summary'], $e['body'], $e['video_id'], $e['duration'], $e['credit'], $e['image'] ? jenc($e['image']) : '', jenc($e['links']), $e['seo_description'], $e['status'], $e['featured'], $e['published_at'], now(), $old['id'],
            ]);
            if ($old['slug'] !== $e['slug'] && $old['status'] === 'published') remember_redirect(episode_url($old), episode_url($e));
            out(decode_episode(q_one('SELECT * FROM episodes WHERE id = ?', [$old['id']])));
        }
        if ($method === 'DELETE') {
            q('DELETE FROM episodes WHERE id = ?', [$old['id']]);
            if ($old['status'] === 'published') remember_redirect(episode_url($old), '/podcast/');
            out(['deleted' => (int) $old['id']]);
        }
        break;

    case 'slug':
        // Live check while someone edits a web address.
        $type = isset($_GET['type']) && $_GET['type'] === 'episode' ? 'episodes' : 'posts';
        $slug = slugify(isset($_GET['slug']) ? $_GET['slug'] : '');
        $taken = $slug !== '' && q_val("SELECT id FROM $type WHERE slug = ? AND id != ?", [$slug, (int) (isset($_GET['id']) ? $_GET['id'] : 0)]);
        out(['slug' => $slug, 'available' => $slug !== '' && !$taken, 'suggestion' => $taken ? unique_slug($type, $slug, isset($_GET['id']) ? $_GET['id'] : 0) : $slug]);

    case 'youtube':
        // Fills in an episode from a pasted YouTube link.
        $vid = youtube_id(isset($_GET['url']) ? $_GET['url'] : '');
        if (!$vid) fail('That doesn\'t look like a YouTube link. Copy it from the Share button on YouTube.', 422);
        $info = ['id' => $vid, 'title' => '', 'channel' => '', 'thumbnail' => 'https://i.ytimg.com/vi/' . $vid . '/hqdefault.jpg'];
        if (function_exists('curl_init')) {
            $ch = curl_init('https://www.youtube.com/oembed?format=json&url=' . rawurlencode('https://www.youtube.com/watch?v=' . $vid));
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_FOLLOWLOCATION => true]);
            $json = json_decode((string) curl_exec($ch), true);
            curl_close($ch);
            if (is_array($json)) {
                $info['title'] = isset($json['title']) ? $json['title'] : '';
                $info['channel'] = isset($json['author_name']) ? $json['author_name'] : '';
            }
        }
        out($info);

    case 'media':
        if ($method === 'GET' && $id === null) {
            $rows = [];
            foreach (q_all('SELECT id FROM media ORDER BY created_at DESC, id DESC') as $r) $rows[] = media_row($r['id']);
            out($rows);
        }
        if ($method === 'POST' && $id === 'youtube') {
            // Saves a YouTube thumbnail into the library, for an episode photo.
            $b = body();
            $vid = youtube_id(isset($b['id']) ? $b['id'] : '');
            if (!$vid) fail('That doesn\'t look like a YouTube link.', 422);
            $tmp = tempnam(sys_get_temp_dir(), 'yt');
            foreach (['maxresdefault', 'hqdefault'] as $size) {
                $ch = curl_init('https://i.ytimg.com/vi/' . $vid . '/' . $size . '.jpg');
                $fh = fopen($tmp, 'w');
                curl_setopt_array($ch, [CURLOPT_FILE => $fh, CURLOPT_TIMEOUT => 15, CURLOPT_FAILONERROR => true]);
                $ok = curl_exec($ch);
                curl_close($ch);
                fclose($fh);
                if ($ok && filesize($tmp) > 2000) break;
            }
            list($m, $err) = media_store($tmp, 'youtube-' . $vid . '.jpg');
            @unlink($tmp);
            if (!$m) fail($err, 422);
            out($m, 201);
        }
        if ($method === 'POST' && $id === null) {
            if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                $code = empty($_FILES['file']) ? UPLOAD_ERR_NO_FILE : $_FILES['file']['error'];
                fail($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE ? 'That photo is over 10 MB. Export a smaller copy and try again.' : 'The photo didn\'t arrive. Try uploading it again.', 422);
            }
            list($m, $err) = media_store($_FILES['file']['tmp_name'], $_FILES['file']['name']);
            if (!$m) fail($err, 422);
            out($m, 201);
        }
        $m = media_row((int) $id);
        if (!$m) fail('That photo no longer exists.', 404);
        if ($method === 'GET' && isset($parts[2]) && $parts[2] === 'usage') out(media_usage($m['path']));
        if ($method === 'PUT') {
            $b = body();
            q('UPDATE media SET alt = ?, name = ? WHERE id = ?', [mb_substr(trim((string) (isset($b['alt']) ? $b['alt'] : $m['alt'])), 0, 300), mb_substr(trim((string) (isset($b['name']) ? $b['name'] : $m['name'])), 0, 80), $m['id']]);
            out(media_row($m['id']));
        }
        if ($method === 'DELETE') {
            if (strpos($m['path'], '/uploads/') === 0) {
                @unlink(ROOT . $m['path']);
                foreach ($m['sizes'] as $s) @unlink(ROOT . $s['src']);
            }
            q('DELETE FROM media WHERE id = ?', [$m['id']]);
            out(['deleted' => $m['id']]);
        }
        break;

    case 'account':
        if ($method === 'PUT' && $id === null) {
            $b = body();
            $name = trim((string) (isset($b['name']) ? $b['name'] : ''));
            $username = trim((string) (isset($b['username']) ? $b['username'] : ''));
            $errors = [];
            if ($name === '') $errors['name'] = 'Add the name the dashboard should greet.';
            if (!preg_match('~^[A-Za-z0-9._@-]{3,60}$~', $username)) $errors['username'] = 'Use 3 to 60 letters, numbers, dots, dashes or @.';
            elseif (q_val('SELECT id FROM users WHERE username = ? AND id != ?', [$username, $user['id']])) $errors['username'] = 'That username is taken.';
            if ($errors) fail('Some fields need a look.', 422, $errors);
            q('UPDATE users SET name = ?, username = ? WHERE id = ?', [mb_substr($name, 0, 60), $username, $user['id']]);
            out(q_one('SELECT id, username, name FROM users WHERE id = ?', [$user['id']]));
        }
        if ($method === 'POST' && $id === 'password') {
            $b = body();
            $row = q_one('SELECT password_hash FROM users WHERE id = ?', [$user['id']]);
            if (!password_verify((string) (isset($b['current']) ? $b['current'] : ''), $row['password_hash'])) fail('The current password isn\'t right.', 422, ['current' => 'The current password isn\'t right.']);
            $next = (string) (isset($b['next']) ? $b['next'] : '');
            if (mb_strlen($next) < 10) fail('Use at least 10 characters.', 422, ['next' => 'Use at least 10 characters. A short sentence works well.']);
            q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($next, PASSWORD_DEFAULT), $user['id']]);
            out(['changed' => true]);
        }
        break;
}

fail('Not found.', 404);
