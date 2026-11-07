<?php
// Dashboard sign-in. Passwords are stored as password_hash() hashes; sessions live
// in /data/sessions so they aren't shared with other sites on the server.

function session_cookie_name()
{
    return 'ss_session';
}

function start_session()
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $dir = DATA . '/sessions';
    if (!is_dir($dir)) mkdir($dir, 0700, true);
    session_save_path($dir);
    session_name(session_cookie_name());
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    // PHP 7.3 accepts the options array, including SameSite.
    $params = [
        'lifetime' => 60 * 60 * 24 * 14,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    // One sign-in for the dashboard subdomain and the public site (for previews).
    $domain = (string) config('cookie_domain');
    if ($domain !== '' && substr('.' . request_host(), -strlen($domain)) === $domain) $params['domain'] = $domain;
    session_set_cookie_params($params);
    ini_set('session.gc_maxlifetime', (string) (60 * 60 * 24 * 14));
    ini_set('session.use_strict_mode', '1');
    session_start();
}

function current_user()
{
    start_session();
    if (empty($_SESSION['user_id'])) return null;
    $u = q_one('SELECT id, username, name FROM users WHERE id = ?', [$_SESSION['user_id']]);
    if (!$u) {
        $_SESSION = [];
        return null;
    }
    return $u;
}

function csrf_token()
{
    start_session();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf'];
}

function client_ip()
{
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
}

// The first account comes from config.local.php, created the first time anyone
// tries to sign in on a fresh database.
function ensure_first_user()
{
    if ((int) q_val('SELECT COUNT(*) FROM users') > 0) return;
    $u = config('first_user');
    if (empty($u['username']) || empty($u['password_hash'])) return;
    q('INSERT INTO users (username, name, password_hash, created_at) VALUES (?,?,?,?)', [$u['username'], $u['name'] ?: 'Sabir', $u['password_hash'], now()]);
}

// Returns [user, error]. Five wrong tries from one address lock it out for 15 minutes.
function attempt_login($username, $password)
{
    ensure_first_user();
    $ip = client_ip();
    q('DELETE FROM login_attempts WHERE at < ?', [now() - 900]);
    $tries = (int) q_val('SELECT COUNT(*) FROM login_attempts WHERE ip = ?', [$ip]);
    if ($tries >= 5) return [null, 'Too many tries. Wait 15 minutes, then sign in again.'];

    $u = q_one('SELECT * FROM users WHERE username = ?', [trim($username)]);
    // Verify against a dummy hash when the user doesn't exist, so timing doesn't give it away.
    $hash = $u ? $u['password_hash'] : '$2y$10$4Zr/6SouzqGG.jZkbdG/XOUqInqkBVwyfFx8zBSTQWAKi5oRaCuv6';
    if (!password_verify((string) $password, $hash) || !$u) {
        q('INSERT INTO login_attempts (ip, at) VALUES (?, ?)', [$ip, now()]);
        $left = 4 - $tries;
        return [null, $left > 0 ? 'That username and password don\'t match. ' . $left . ($left === 1 ? ' try' : ' tries') . ' left before a 15-minute pause.' : 'Too many tries. Wait 15 minutes, then sign in again.'];
    }

    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $u['id']]);
    }
    q('DELETE FROM login_attempts WHERE ip = ?', [$ip]);
    q('UPDATE users SET last_login_at = ? WHERE id = ?', [now(), $u['id']]);

    start_session();
    session_regenerate_id(true);
    $_SESSION = ['user_id' => (int) $u['id'], 'csrf' => bin2hex(random_bytes(24))];
    return [['id' => (int) $u['id'], 'username' => $u['username'], 'name' => $u['name']], null];
}

function logout()
{
    start_session();
    $_SESSION = [];
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    session_destroy();
}
