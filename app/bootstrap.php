<?php
// Shared setup for the public site, the dashboard and its API.
// The server runs PHP 7.3, so nothing here may use 7.4+ syntax (arrow functions,
// typed properties, match, str_contains and friends).

define('ROOT', dirname(__DIR__));
define('APP', __DIR__);
define('DATA', ROOT . '/data');

$GLOBALS['config'] = require APP . '/config.php';
if (is_file(APP . '/config.local.php')) {
    $GLOBALS['config'] = array_replace_recursive($GLOBALS['config'], require APP . '/config.local.php');
}

date_default_timezone_set(config('timezone'));
mb_internal_encoding('UTF-8');

require APP . '/db.php';
require APP . '/content.php';
require APP . '/helpers.php';
require APP . '/auth.php';

function config($key, $default = null)
{
    $value = $GLOBALS['config'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) return $default;
        $value = $value[$part];
    }
    return $value;
}

function now()
{
    return time();
}
