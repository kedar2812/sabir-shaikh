<?php
// Defaults for every install. Secrets and the first login live in config.local.php,
// which is never committed: copy config.local.example.php and fill it in.
return [
    'site_url' => 'https://www.sabirshaikh.in',
    'timezone' => 'Asia/Kolkata',
    // Mixed into visitor hashes and session tokens. config.local.php must override it.
    'secret' => '',
    // Created on first run when no account exists yet. Change it from Settings in the dashboard.
    'first_user' => [
        'username' => '',
        'name' => 'Sabir',
        'password_hash' => '',
    ],
    // Set on the server: '.sabirshaikh.in' shares the sign-in between the dashboard
    // subdomain and www, so previews of unsaved edits work on the public pages.
    'cookie_domain' => '',
    // Where the dashboard lives, e.g. https://dashboard.sabirshaikh.in. When set,
    // /dashboard/ on any other host redirects there. Leave empty to use /dashboard/.
    'dashboard_url' => '',
    // Analytics rows older than this are deleted.
    'keep_visits_days' => 400,
];
