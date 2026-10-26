<?php

define('PRESTIGE_ROOT', dirname(__DIR__));
define('PRESTIGE_APP', PRESTIGE_ROOT . '/app');

$GLOBALS['prestige_config'] = require PRESTIGE_APP . '/config.php';

require PRESTIGE_APP . '/lib/helpers.php';
require PRESTIGE_APP . '/lib/db.php';
require PRESTIGE_APP . '/lib/auth.php';
require PRESTIGE_APP . '/lib/events.php';
require PRESTIGE_APP . '/lib/view.php';
require PRESTIGE_APP . '/lib/qr.php';

$config = $GLOBALS['prestige_config'];

ini_set('display_errors', config('app.debug') ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

date_default_timezone_set(config('app.timezone'));

if (session_status() === PHP_SESSION_NONE) {
    session_name(config('security.session_name'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}
