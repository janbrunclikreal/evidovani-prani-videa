<?php
// Evidence přání videií v3.2.0
// Konfigurační soubor

// Databázové připojení
define('DB_HOST', 'localhost');
define('DB_NAME', 'janbrunclik_evidence');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Aplikační nastavení
define('APP_NAME', 'Evidence přání videií');
define('APP_VERSION', '3.2.0');
define('RECORDS_PER_PAGE', 10);

// Bezpečnostní nastavení
define('SESSION_TIMEOUT', 3600); // 1 hodina

// Cesty
define('BASE_URL', 'http://localhost:7001');
define('UPLOAD_DIR', 'uploads/');

// Časová zóna
date_default_timezone_set('Europe/Prague');

// Error reporting (pro development)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>