<?php
// SQLite storage. One file in /data holds the site's content, the blog, the podcast,
// the media library, the dashboard account and the visit counts.

function db()
{
    static $pdo = null;
    if ($pdo) return $pdo;
    if (!is_dir(DATA)) mkdir(DATA, 0750, true);
    if (!is_file(DATA . '/.htaccess')) file_put_contents(DATA . '/.htaccess', "Require all denied
");
    $pdo = new PDO('sqlite:' . DATA . '/site.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('PRAGMA foreign_keys = ON');
    migrate($pdo);
    return $pdo;
}

function q($sql, $params = [])
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function q_all($sql, $params = [])
{
    return q($sql, $params)->fetchAll();
}

function q_one($sql, $params = [])
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function q_val($sql, $params = [])
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

function migrate(PDO $pdo)
{
    $version = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
    if ($version >= 1) return;

    $pdo->beginTransaction();
    $pdo->exec("
        CREATE TABLE content (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL,
            updated_at INTEGER NOT NULL
        );
        CREATE TABLE posts (
            id INTEGER PRIMARY KEY,
            slug TEXT NOT NULL UNIQUE,
            title TEXT NOT NULL,
            summary TEXT NOT NULL DEFAULT '',
            topic TEXT NOT NULL DEFAULT '',
            card TEXT NOT NULL DEFAULT 'words',
            card_text TEXT NOT NULL DEFAULT '',
            card_tone TEXT NOT NULL DEFAULT 'oxblood',
            cover TEXT NOT NULL DEFAULT '',
            cover_caption TEXT NOT NULL DEFAULT '',
            body TEXT NOT NULL DEFAULT '',
            tags TEXT NOT NULL DEFAULT '[]',
            seo_title TEXT NOT NULL DEFAULT '',
            seo_description TEXT NOT NULL DEFAULT '',
            status TEXT NOT NULL DEFAULT 'draft',
            featured INTEGER NOT NULL DEFAULT 0,
            published_at TEXT,
            created_at INTEGER NOT NULL,
            updated_at INTEGER NOT NULL
        );
        CREATE TABLE episodes (
            id INTEGER PRIMARY KEY,
            slug TEXT NOT NULL UNIQUE,
            title TEXT NOT NULL,
            guest TEXT NOT NULL DEFAULT '',
            summary TEXT NOT NULL DEFAULT '',
            body TEXT NOT NULL DEFAULT '',
            video_id TEXT NOT NULL DEFAULT '',
            duration INTEGER NOT NULL DEFAULT 0,
            credit TEXT NOT NULL DEFAULT '',
            image TEXT NOT NULL DEFAULT '',
            links TEXT NOT NULL DEFAULT '[]',
            seo_description TEXT NOT NULL DEFAULT '',
            status TEXT NOT NULL DEFAULT 'draft',
            featured INTEGER NOT NULL DEFAULT 0,
            published_at TEXT,
            created_at INTEGER NOT NULL,
            updated_at INTEGER NOT NULL
        );
        CREATE TABLE redirects (
            from_path TEXT PRIMARY KEY,
            to_path TEXT NOT NULL,
            created_at INTEGER NOT NULL
        );
        CREATE TABLE media (
            id INTEGER PRIMARY KEY,
            path TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL DEFAULT '',
            alt TEXT NOT NULL DEFAULT '',
            width INTEGER NOT NULL DEFAULT 0,
            height INTEGER NOT NULL DEFAULT 0,
            bytes INTEGER NOT NULL DEFAULT 0,
            sizes TEXT NOT NULL DEFAULT '[]',
            created_at INTEGER NOT NULL
        );
        CREATE TABLE users (
            id INTEGER PRIMARY KEY,
            username TEXT NOT NULL UNIQUE COLLATE NOCASE,
            name TEXT NOT NULL DEFAULT '',
            password_hash TEXT NOT NULL,
            created_at INTEGER NOT NULL,
            last_login_at INTEGER
        );
        CREATE TABLE login_attempts (
            ip TEXT NOT NULL,
            at INTEGER NOT NULL
        );
        CREATE INDEX login_attempts_ip ON login_attempts (ip, at);
        CREATE TABLE hits (
            id INTEGER PRIMARY KEY,
            ts INTEGER NOT NULL,
            day TEXT NOT NULL,
            hour INTEGER NOT NULL,
            dow INTEGER NOT NULL,
            path TEXT NOT NULL,
            source TEXT NOT NULL DEFAULT '',
            referrer TEXT NOT NULL DEFAULT '',
            campaign TEXT NOT NULL DEFAULT '',
            visitor TEXT NOT NULL,
            session TEXT NOT NULL,
            entry INTEGER NOT NULL DEFAULT 0,
            device TEXT NOT NULL DEFAULT '',
            browser TEXT NOT NULL DEFAULT '',
            os TEXT NOT NULL DEFAULT '',
            country TEXT NOT NULL DEFAULT '',
            duration INTEGER NOT NULL DEFAULT 0
        );
        CREATE INDEX hits_ts ON hits (ts);
        CREATE INDEX hits_visitor ON hits (visitor, ts);
        CREATE INDEX hits_session ON hits (session);
        CREATE TABLE events (
            id INTEGER PRIMARY KEY,
            ts INTEGER NOT NULL,
            day TEXT NOT NULL,
            name TEXT NOT NULL,
            label TEXT NOT NULL DEFAULT '',
            path TEXT NOT NULL DEFAULT '',
            visitor TEXT NOT NULL
        );
        CREATE INDEX events_ts ON events (ts);
        CREATE TABLE salts (
            day TEXT PRIMARY KEY,
            salt TEXT NOT NULL
        );
    ");
    seed($pdo);
    $pdo->exec('PRAGMA user_version = 1');
    $pdo->commit();
}

function seed(PDO $pdo)
{
    $t = now();
    require_once APP . '/seed.php';
    $seed = seed_data();

    $ins = $pdo->prepare('INSERT INTO posts (slug, title, summary, topic, card, card_text, card_tone, cover, cover_caption, body, tags, seo_title, seo_description, status, featured, published_at, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($seed['posts'] as $p) {
        $ins->execute([$p['slug'], $p['title'], $p['summary'], $p['topic'], $p['card'], $p['card_text'], $p['card_tone'],
            $p['cover'] ? jenc($p['cover']) : '', $p['cover_caption'], $p['body'], jenc($p['tags']),
            $p['seo_title'], $p['seo_description'], $p['status'], $p['featured'], $p['published_at'], $t, $t]);
    }

    $ins = $pdo->prepare('INSERT INTO episodes (slug, title, guest, summary, body, video_id, duration, credit, image, links, seo_description, status, featured, published_at, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($seed['episodes'] as $e) {
        $ins->execute([$e['slug'], $e['title'], $e['guest'], $e['summary'], $e['body'], $e['video_id'], $e['duration'], $e['credit'],
            jenc($e['image']), jenc($e['links']), $e['seo_description'], $e['status'], $e['featured'], $e['published_at'], $t, $t]);
    }

    $ins = $pdo->prepare('INSERT INTO media (path, name, alt, width, height, bytes, sizes, created_at) VALUES (?,?,?,?,?,?,?,?)');
    foreach ($seed['media'] as $m) {
        $file = ROOT . $m['path'];
        $size = is_file($file) ? @getimagesize($file) : false;
        $ins->execute([$m['path'], $m['name'], $m['alt'], $size ? $size[0] : 0, $size ? $size[1] : 0, is_file($file) ? filesize($file) : 0, '[]', $t]);
    }

    $u = config('first_user');
    if (!empty($u['username']) && !empty($u['password_hash'])) {
        $pdo->prepare('INSERT INTO users (username, name, password_hash, created_at) VALUES (?,?,?,?)')
            ->execute([$u['username'], $u['name'] ?: 'Sabir', $u['password_hash'], $t]);
    }
}
