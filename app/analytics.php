<?php
// First-party visit counting. No cookies, no IP addresses stored. Each visitor is
// a hash of (daily random salt + IP + browser), so a visitor can be counted once a
// day but never followed from one day to the next, and the salt is deleted after
// two days so old hashes can't be recomputed.

const BOT_PATTERN = '~bot|crawl|spider|slurp|headless|lighthouse|pagespeed|preview|facebookexternalhit|whatsapp|telegram|discord|curl|wget|python|httpclient|monitor|uptime|scan~i';

function daily_salt($day)
{
    $salt = q_val('SELECT salt FROM salts WHERE day = ?', [$day]);
    if ($salt) return $salt;
    $salt = bin2hex(random_bytes(16));
    q('INSERT OR IGNORE INTO salts (day, salt) VALUES (?, ?)', [$day, $salt]);
    q('DELETE FROM salts WHERE day < ?', [date('Y-m-d', strtotime($day . ' -1 day'))]);
    return q_val('SELECT salt FROM salts WHERE day = ?', [$day]);
}

function visitor_hash($day = null)
{
    $day = $day ?: date('Y-m-d');
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    return substr(hash('sha256', daily_salt($day) . '|' . client_ip() . '|' . $ua . '|' . config('secret')), 0, 16);
}

function is_bot()
{
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    return $ua === '' || preg_match(BOT_PATTERN, $ua);
}

function clean_path($p)
{
    $p = (string) parse_url((string) $p, PHP_URL_PATH);
    if ($p === '' || $p[0] !== '/') return null;
    if (strpos($p, '/dashboard') === 0) return null;
    $p = preg_replace('~/+~', '/', $p);
    if (substr($p, -1) !== '/' && strpos(basename($p), '.') === false) $p .= '/';
    return substr($p, 0, 200);
}

function source_name($referrer, $utm)
{
    if ($utm !== '') return mb_substr(ucfirst(trim($utm)), 0, 40);
    $host = strtolower((string) parse_url((string) $referrer, PHP_URL_HOST));
    if ($host === '') return '';
    $host = preg_replace('~^(www\.|m\.|l\.|lm\.|mobile\.)~', '', $host);
    $own = preg_replace('~^www\.~', '', strtolower(parse_url(config('site_url'), PHP_URL_HOST)));
    if ($host === $own) return null;
    $known = [
        'google.' => 'Google', 'bing.com' => 'Bing', 'duckduckgo.com' => 'DuckDuckGo', 'yahoo.' => 'Yahoo', 'ecosia.org' => 'Ecosia',
        'instagram.com' => 'Instagram', 'facebook.com' => 'Facebook', 'fb.me' => 'Facebook', 'linkedin.com' => 'LinkedIn', 'lnkd.in' => 'LinkedIn',
        't.co' => 'X', 'x.com' => 'X', 'twitter.com' => 'X', 'youtube.com' => 'YouTube', 'youtu.be' => 'YouTube', 'whatsapp.com' => 'WhatsApp', 'wa.me' => 'WhatsApp',
        'chatgpt.com' => 'ChatGPT', 'openai.com' => 'ChatGPT', 'perplexity.ai' => 'Perplexity', 'claude.ai' => 'Claude', 'gemini.google.com' => 'Gemini',
        'mid-day.com' => 'mid-day', 'saviansindia.com' => 'SAVIANS', 'reddit.com' => 'Reddit', 'quora.com' => 'Quora',
    ];
    foreach ($known as $needle => $name) {
        if ($host === $needle || strpos($host, $needle) === 0 || substr($host, -strlen('.' . $needle)) === '.' . $needle) return $name;
    }
    return $host;
}

function parse_agent($ua, $width)
{
    $device = 'Computer';
    if (preg_match('~iPad|Tablet|PlayBook|Silk|(Android(?!.*Mobile))~i', $ua)) $device = 'Tablet';
    elseif (preg_match('~Mobi|iPhone|iPod|Android|Windows Phone~i', $ua)) $device = 'Phone';
    elseif ($width && $width < 760) $device = 'Phone';
    elseif ($width && $width <= 1100 && preg_match('~Macintosh~', $ua) && $width < 1100) $device = 'Tablet';

    $browser = 'Other';
    foreach (['Edg/' => 'Edge', 'OPR/' => 'Opera', 'SamsungBrowser' => 'Samsung Internet', 'Firefox/' => 'Firefox', 'FxiOS' => 'Firefox', 'CriOS' => 'Chrome', 'Chrome/' => 'Chrome', 'Safari/' => 'Safari'] as $needle => $name) {
        if (strpos($ua, $needle) !== false) { $browser = $name; break; }
    }
    if (strpos($ua, 'Instagram') !== false) $browser = 'Instagram app';
    elseif (strpos($ua, 'FBAN') !== false || strpos($ua, 'FBAV') !== false) $browser = 'Facebook app';
    elseif (strpos($ua, 'LinkedInApp') !== false) $browser = 'LinkedIn app';

    $os = 'Other';
    foreach (['iPhone' => 'iOS', 'iPad' => 'iPadOS', 'Android' => 'Android', 'Windows' => 'Windows', 'CrOS' => 'ChromeOS', 'Mac OS X' => 'macOS', 'Linux' => 'Linux'] as $needle => $name) {
        if (strpos($ua, $needle) !== false) { $os = $name; break; }
    }
    return [$device, $browser, $os];
}

function country_from_tz($tz)
{
    static $map = null;
    if ($map === null) $map = require APP . '/tz-countries.php';
    return isset($map[$tz]) ? $map[$tz] : '';
}

function record_hit($in)
{
    if (is_bot()) return null;
    $path = clean_path(isset($in['p']) ? $in['p'] : '');
    if ($path === null) return null;
    $ref = isset($in['r']) ? substr((string) $in['r'], 0, 300) : '';
    $utm = isset($in['us']) ? substr(preg_replace('~[^\w .-]~u', '', (string) $in['us']), 0, 40) : '';
    $source = source_name($ref, $utm);
    if ($source === null) $source = '';
    $campaign = isset($in['uc']) ? substr(preg_replace('~[^\w .-]~u', '', (string) $in['uc']), 0, 60) : '';
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    list($device, $browser, $os) = parse_agent($ua, isset($in['w']) ? (int) $in['w'] : 0);
    $country = country_from_tz(isset($in['tz']) ? (string) $in['tz'] : '');

    $t = now();
    $day = date('Y-m-d', $t);
    $visitor = visitor_hash($day);
    $last = q_one('SELECT session, ts FROM hits WHERE visitor = ? AND ts > ? ORDER BY ts DESC LIMIT 1', [$visitor, $t - 1800]);
    $entry = $last ? 0 : 1;
    $session = $last ? $last['session'] : bin2hex(random_bytes(6));

    q('INSERT INTO hits (ts, day, hour, dow, path, source, referrer, campaign, visitor, session, entry, device, browser, os, country) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
        $t, $day, (int) date('G', $t), (int) date('N', $t), $path, $entry ? $source : '', $entry ? (string) parse_url($ref, PHP_URL_HOST) : '',
        $entry ? $campaign : '', $visitor, $session, $entry, $device, $browser, $os, $country,
    ]);
    $id = (int) db()->lastInsertId();
    if (mt_rand(1, 300) === 1) q('DELETE FROM hits WHERE ts < ?', [$t - 86400 * (int) config('keep_visits_days')]);
    return $id;
}

function record_time($in)
{
    $id = isset($in['id']) ? (int) $in['id'] : 0;
    $seconds = isset($in['s']) ? (int) $in['s'] : 0;
    if ($id <= 0 || $seconds <= 0) return;
    q('UPDATE hits SET duration = ? WHERE id = ? AND visitor = ? AND ts > ?', [min($seconds, 1800), $id, visitor_hash(), now() - 7200]);
}

function record_event($in)
{
    if (is_bot()) return;
    $name = isset($in['n']) ? preg_replace('~[^a-z0-9_-]~', '', strtolower((string) $in['n'])) : '';
    if ($name === '' || strlen($name) > 40) return;
    $label = isset($in['l']) ? mb_substr(trim((string) $in['l']), 0, 80) : '';
    $path = clean_path(isset($in['p']) ? $in['p'] : '/') ?: '/';
    q('INSERT INTO events (ts, day, name, label, path, visitor) VALUES (?,?,?,?,?,?)', [now(), date('Y-m-d'), $name, $label, $path, visitor_hash()]);
}

// ----------------------------------------------------------------------------- Reports

function stats_range($range)
{
    $today = strtotime('today');
    switch ($range) {
        case 'today':
            return ['start' => $today, 'end' => $today + 86399, 'unit' => 'hour', 'buckets' => 24];
        case '7d':
            return ['start' => $today - 6 * 86400, 'end' => $today + 86399, 'unit' => 'day', 'buckets' => 7];
        case '90d':
            return ['start' => $today - 89 * 86400, 'end' => $today + 86399, 'unit' => 'day', 'buckets' => 90];
        case '12m':
            $start = strtotime(date('Y-m-01', strtotime('-11 months', $today)));
            return ['start' => $start, 'end' => $today + 86399, 'unit' => 'month', 'buckets' => 12];
        default:
            return ['start' => $today - 29 * 86400, 'end' => $today + 86399, 'unit' => 'day', 'buckets' => 30];
    }
}

function previous_range($r)
{
    if ($r['unit'] === 'month') {
        $start = strtotime('-12 months', $r['start']);
        return ['start' => $start, 'end' => $r['start'] - 1, 'unit' => 'month', 'buckets' => 12];
    }
    $len = $r['end'] - $r['start'] + 1;
    return ['start' => $r['start'] - $len, 'end' => $r['start'] - 1, 'unit' => $r['unit'], 'buckets' => $r['buckets']];
}

// Bucket labels for a range: ISO date-time strings the dashboard formats.
function bucket_keys($r)
{
    $keys = [];
    for ($i = 0; $i < $r['buckets']; $i++) {
        if ($r['unit'] === 'hour') $keys[] = date('Y-m-d', $r['start']) . ' ' . sprintf('%02d', $i);
        elseif ($r['unit'] === 'day') $keys[] = date('Y-m-d', strtotime("+$i day", $r['start']));
        else $keys[] = date('Y-m', strtotime("+$i month", $r['start']));
    }
    return $keys;
}

function bucket_sql($unit)
{
    if ($unit === 'hour') return "day || ' ' || printf('%02d', hour)";
    if ($unit === 'day') return 'day';
    return 'substr(day, 1, 7)';
}

// The four headline numbers, overall and per bucket.
function stats_series($r)
{
    $b = bucket_sql($r['unit']);
    $w = [$r['start'], $r['end']];
    $keys = bucket_keys($r);
    $blank = array_fill_keys($keys, 0);

    $visitors = $blank; $views = $blank; $reached = $blank; $bounce = $blank; $duration = $blank;
    foreach (q_all("SELECT $b AS k, COUNT(DISTINCT visitor) AS v, COUNT(*) AS pv FROM hits WHERE ts BETWEEN ? AND ? GROUP BY k", $w) as $row) {
        if (!isset($visitors[$row['k']])) continue;
        $visitors[$row['k']] = (int) $row['v'];
        $views[$row['k']] = (int) $row['pv'];
    }
    $sessions = q_all("SELECT MIN($b) AS k, COUNT(*) AS pv, SUM(duration) AS dur FROM hits WHERE ts BETWEEN ? AND ? GROUP BY session", $w);
    $perBucket = [];
    foreach ($sessions as $s) {
        if (!isset($blank[$s['k']])) continue;
        if (!isset($perBucket[$s['k']])) $perBucket[$s['k']] = ['n' => 0, 'bounced' => 0, 'dur' => 0];
        $perBucket[$s['k']]['n']++;
        if ((int) $s['pv'] === 1) $perBucket[$s['k']]['bounced']++;
        $perBucket[$s['k']]['dur'] += (int) $s['dur'];
    }
    foreach ($perBucket as $k => $s) {
        $bounce[$k] = round(100 * $s['bounced'] / $s['n']);
        $duration[$k] = (int) round($s['dur'] / $s['n']);
    }
    foreach (q_all("SELECT $b AS k, COUNT(*) AS n FROM events WHERE ts BETWEEN ? AND ? AND " . reached_out_sql() . ' GROUP BY k', $w) as $row) {
        if (isset($reached[$row['k']])) $reached[$row['k']] = (int) $row['n'];
    }

    $totals = q_one('SELECT COUNT(DISTINCT visitor) AS visitors, COUNT(*) AS views FROM hits WHERE ts BETWEEN ? AND ?', $w);
    $nSessions = count($sessions);
    $bounced = 0; $dur = 0;
    foreach ($sessions as $s) { if ((int) $s['pv'] === 1) $bounced++; $dur += (int) $s['dur']; }

    return [
        'keys' => $keys,
        'totals' => [
            'visitors' => (int) $totals['visitors'],
            'views' => (int) $totals['views'],
            'duration' => $nSessions ? (int) round($dur / $nSessions) : 0,
            'bounce' => $nSessions ? (int) round(100 * $bounced / $nSessions) : 0,
            'reached' => (int) q_val('SELECT COUNT(*) FROM events WHERE ts BETWEEN ? AND ? AND ' . reached_out_sql(), $w),
            'sessions' => $nSessions,
        ],
        'series' => [
            'visitors' => array_values($visitors),
            'views' => array_values($views),
            'duration' => array_values($duration),
            'bounce' => array_values($bounce),
            'reached' => array_values($reached),
        ],
    ];
}

function reached_out_sql()
{
    return "(name = 'contact_form' OR (name = 'click' AND label IN ('email', 'whatsapp')))";
}

function page_title_map()
{
    $map = ['/' => 'Home', '/about/' => 'About Sabir', '/blogs/' => 'Blog', '/podcast/' => 'Podcast', '/contact/' => 'Contact', '/privacy-policy/' => 'Privacy policy'];
    foreach (q_all('SELECT slug, title FROM posts') as $p) $map['/blogs/' . $p['slug'] . '/'] = $p['title'];
    foreach (q_all('SELECT slug, title FROM episodes') as $e) $map['/podcast/' . $e['slug'] . '/'] = $e['title'];
    return $map;
}

function stats_report($rangeKey, $limit = 8)
{
    $r = stats_range($rangeKey);
    $p = previous_range($r);
    $w = [$r['start'], $r['end']];
    $cur = stats_series($r);
    $prev = stats_series($p);
    $titles = page_title_map();

    $pages = [];
    foreach (q_all('SELECT path, COUNT(*) AS views, COUNT(DISTINCT visitor) AS visitors, AVG(CASE WHEN duration > 0 THEN duration END) AS avg FROM hits WHERE ts BETWEEN ? AND ? GROUP BY path ORDER BY views DESC LIMIT ' . (int) $limit, $w) as $row) {
        $pages[] = ['path' => $row['path'], 'title' => isset($titles[$row['path']]) ? $titles[$row['path']] : $row['path'], 'views' => (int) $row['views'], 'visitors' => (int) $row['visitors'], 'avg' => (int) round((float) $row['avg'])];
    }

    $group = function ($col, $where = '') use ($w, $limit) {
        $out = [];
        $rows = q_all("SELECT $col AS name, COUNT(DISTINCT visitor) AS n FROM hits WHERE ts BETWEEN ? AND ? $where GROUP BY $col ORDER BY n DESC LIMIT " . (int) $limit, $w);
        foreach ($rows as $row) $out[] = ['name' => (string) $row['name'], 'value' => (int) $row['n']];
        return $out;
    };

    $events = [];
    foreach (q_all("SELECT name, label, COUNT(*) AS n FROM events WHERE ts BETWEEN ? AND ? GROUP BY name, label ORDER BY n DESC LIMIT 40", $w) as $row) {
        $events[] = ['name' => $row['name'], 'label' => $row['label'], 'value' => (int) $row['n']];
    }

    $heat = [];
    foreach (q_all('SELECT dow, hour, COUNT(*) AS n FROM hits WHERE ts BETWEEN ? AND ? GROUP BY dow, hour', $w) as $row) {
        $heat[] = [(int) $row['dow'], (int) $row['hour'], (int) $row['n']];
    }

    return [
        'range' => $rangeKey,
        'unit' => $r['unit'],
        'start' => date('Y-m-d', $r['start']),
        'end' => date('Y-m-d', $r['end']),
        'current' => $cur,
        'previous' => $prev,
        'pages' => $pages,
        'sources' => $group("CASE WHEN source = '' THEN 'Direct' ELSE source END", 'AND entry = 1'),
        'countries' => $group('country', "AND country != ''"),
        'devices' => $group('device'),
        'browsers' => $group('browser'),
        'os' => $group('os'),
        'campaigns' => $group('campaign', "AND campaign != ''"),
        'entries' => $group('path', 'AND entry = 1'),
        'events' => $events,
        'heat' => $heat,
        'live' => (int) q_val('SELECT COUNT(DISTINCT visitor) FROM hits WHERE ts > ?', [now() - 300]),
        'first_visit' => q_val('SELECT MIN(day) FROM hits'),
    ];
}
