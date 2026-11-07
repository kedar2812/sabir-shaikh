<?php
// Small helpers shared by the page templates and the API.

// JSON as stored in the database: readable slashes and characters, so searches
// for a photo path find it.
function jenc($v)
{
    return json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function e($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function site_url($path = '/')
{
    return rtrim(config('site_url'), '/') . $path;
}

function request_host()
{
    return isset($_SERVER['HTTP_HOST']) ? strtolower(explode(':', $_SERVER['HTTP_HOST'])[0]) : '';
}

// A public page's address as the dashboard should link to it: the live www
// address, except on a local test server where the same host serves both.
function public_url($path)
{
    $host = request_host();
    if ($host === 'localhost' || $host === '127.0.0.1' || $host === '[::1]') return $path;
    return site_url($path);
}

function is_external($url)
{
    return (bool) preg_match('~^https?://~i', $url) && stripos($url, parse_url(config('site_url'), PHP_URL_HOST)) === false;
}

// target/rel for links people paste into the dashboard.
function link_attrs($url, $track = '')
{
    $out = 'href="' . e($url) . '"';
    if (is_external($url)) $out .= ' target="_blank" rel="noopener"';
    if ($track !== '') $out .= ' data-track="' . e($track) . '"';
    return $out;
}

function icon($name)
{
    $icons = [
        'arrow' => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M2 8h11M9 4l4 4-4 4"/></svg>',
        'out' => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M4 12L12 4M5 4h7v7"/></svg>',
        'play' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 4.5v15l13-7.5z"/></svg>',
        'pin' => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.3" aria-hidden="true"><path d="M8 14.5s4.5-4.1 4.5-7.7a4.5 4.5 0 0 0-9 0c0 3.6 4.5 7.7 4.5 7.7z"/><circle cx="8" cy="6.8" r="1.6"/></svg>',
    ];
    return isset($icons[$name]) ? $icons[$name] : '';
}

// ----------------------------------------------------------------------------- Photos

// Inline custom properties that place a photo inside its frame. `.fx` in site.css
// turns them into object-position plus a zoom around the same point; the phone
// values take over below 860px.
function focus_style($image, $desk = 'd', $phone = 'm')
{
    $f = isset($image['focus']) && is_array($image['focus']) ? $image['focus'] : [];
    $d = isset($f[$desk]) ? $f[$desk] : ['x' => 50, 'y' => 50, 'z' => 1];
    $style = sprintf('--fx:%s%%;--fy:%s%%;--fz:%s', num($d['x']), num($d['y']), num(isset($d['z']) ? $d['z'] : 1));
    if ($phone && isset($f[$phone])) {
        $m = $f[$phone];
        $style .= sprintf(';--mfx:%s%%;--mfy:%s%%;--mfz:%s', num($m['x']), num($m['y']), num(isset($m['z']) ? $m['z'] : 1));
    }
    return $style;
}

function num($n)
{
    return rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
}

// <img> for a stored photo value. $opts: lazy, priority, sizes, desk, phone, class.
function img($image, $opts = [])
{
    if (empty($image['src'])) return '';
    $o = array_replace(['lazy' => true, 'priority' => false, 'sizes' => '100vw', 'desk' => 'd', 'phone' => 'm', 'class' => '', 'alt' => null], $opts);
    $attrs = ['src="' . e($image['src']) . '"'];
    if (!empty($image['sizes']) && is_array($image['sizes'])) {
        $set = [];
        foreach ($image['sizes'] as $s) $set[] = e($s['src']) . ' ' . (int) $s['w'] . 'w';
        $set[] = e($image['src']) . ' ' . (int) $image['w'] . 'w';
        $attrs[] = 'srcset="' . implode(', ', $set) . '"';
        $attrs[] = 'sizes="' . e($o['sizes']) . '"';
    }
    if (!empty($image['w']) && !empty($image['h'])) $attrs[] = 'width="' . (int) $image['w'] . '" height="' . (int) $image['h'] . '"';
    $attrs[] = 'alt="' . e($o['alt'] !== null ? $o['alt'] : (isset($image['alt']) ? $image['alt'] : '')) . '"';
    $attrs[] = 'class="' . trim('fx ' . $o['class']) . '"';
    $attrs[] = 'style="' . focus_style($image, $o['desk'], $o['phone']) . '"';
    if ($o['priority']) $attrs[] = 'fetchpriority="high"';
    elseif ($o['lazy']) $attrs[] = 'loading="lazy"';
    $attrs[] = 'decoding="async"';
    return '<img ' . implode(' ', $attrs) . '>';
}

function abs_url($path)
{
    return preg_match('~^https?://~', $path) ? $path : site_url($path);
}

// ----------------------------------------------------------------------------- Text

function nl2br_e($text)
{
    return nl2br(e(trim($text)), false);
}

function fmt_date($ymd, $style = 'long')
{
    $t = strtotime((string) $ymd);
    if (!$t) return '';
    return date($style === 'short' ? 'j M Y' : 'j F Y', $t);
}

function reading_minutes($html)
{
    $words = str_word_count(strip_tags($html));
    return max(1, (int) round($words / 220));
}

function slugify($text)
{
    $text = mb_strtolower(trim($text));
    $text = strtr($text, ['’' => '', "'" => '', '&' => ' and ']);
    if (function_exists('transliterator_transliterate')) {
        $t = transliterator_transliterate('Any-Latin; Latin-ASCII', $text);
        if ($t !== false) $text = $t;
    }
    $text = preg_replace('~[^a-z0-9]+~', '-', $text);
    $text = trim(preg_replace('~-+~', '-', $text), '-');
    return rtrim((string) substr($text, 0, 80), '-');
}

function social_handle($url)
{
    $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
    $last = basename($path);
    if ($last === '' || $last === 'profile.php') return '';
    return '@' . ltrim($last, '@');
}

function json_ld($data)
{
    return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_HEX_TAG) . '</script>';
}

// ----------------------------------------------------------------------------- Blog and podcast

function decode_post($p)
{
    if (!$p) return null;
    $p['cover'] = $p['cover'] ? json_decode($p['cover'], true) : null;
    $p['tags'] = json_decode($p['tags'], true) ?: [];
    $p['featured'] = (int) $p['featured'];
    return $p;
}

function decode_episode($e)
{
    if (!$e) return null;
    $e['image'] = $e['image'] ? json_decode($e['image'], true) : null;
    $e['links'] = json_decode($e['links'], true) ?: [];
    $e['featured'] = (int) $e['featured'];
    $e['duration'] = (int) $e['duration'];
    return $e;
}

function published_posts($limit = 0)
{
    $sql = "SELECT * FROM posts WHERE status = 'published' AND published_at <= ? ORDER BY published_at DESC, id DESC" . ($limit ? ' LIMIT ' . (int) $limit : '');
    return array_map('decode_post', q_all($sql, [date('Y-m-d')]));
}

function published_episodes($limit = 0)
{
    $sql = "SELECT * FROM episodes WHERE status = 'published' AND published_at <= ? ORDER BY published_at DESC, id DESC" . ($limit ? ' LIMIT ' . (int) $limit : '');
    return array_map('decode_episode', q_all($sql, [date('Y-m-d')]));
}

function topic_list()
{
    $items = c('blog.topics', 'items');
    return is_array($items) ? $items : [];
}

function topic_name($slug)
{
    foreach (topic_list() as $t) if ($t['slug'] === $slug) return $t['name'];
    return '';
}

function post_url($p)
{
    return '/blogs/' . $p['slug'] . '/';
}

function episode_url($e)
{
    return '/podcast/' . $e['slug'] . '/';
}
