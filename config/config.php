<?php
// Database connection settings.
// On a web host, copy config.local.example.php to config.local.php and put
// your host's details there (that file is not committed to Git).
// Otherwise the defaults below suit XAMPP/WAMP (user "root", empty password),
// and environment variables override them when set.
if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}
defined('DB_HOST') || define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
defined('DB_PORT') || define('DB_PORT', getenv('DB_PORT') ?: '3306');
defined('DB_NAME') || define('DB_NAME', getenv('DB_NAME') ?: 'coffeeshop');
defined('DB_USER') || define('DB_USER', getenv('DB_USER') ?: 'root');
defined('DB_PASS') || define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');

date_default_timezone_set('Asia/Kolkata');
