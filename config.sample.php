<?php
/**
 * RepairHub — Local / Production Configuration Sample
 * 
 * Copy this file to `config.local.php` on your server:
 *   cp config.sample.php config.local.php
 * 
 * Then update the settings below with your production server details.
 * Note: `config.local.php` is ignored by Git, so your credentials remain safe.
 */

// Production App URL (leave empty '' if hosted at the root domain / virtual host)
// e.g., define('APP_URL', ''); or define('APP_URL', 'https://repairhub.yourdomain.com');
define('APP_URL', '');

// Production Database Settings
define('DB_HOST', 'localhost');
define('DB_NAME', 'repairhub');
define('DB_USER', 'repairhub_user');
define('DB_PASS', 'YourStrongPasswordHere');
define('DB_CHARSET', 'utf8mb4');

// Production Error Reporting (hide detailed errors from visitors)
ini_set('display_errors', 0);
error_reporting(0);
