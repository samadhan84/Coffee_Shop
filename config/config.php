<?php
// Database connection settings. Edit these to match your MySQL server
// (XAMPP/WAMP default is user "root" with an empty password).
// Environment variables override them when set.
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'coffeeshop');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');

date_default_timezone_set('Asia/Kolkata');
