<?php
/**
 * Database configuration.
 * Credentials are loaded from environment variables first, falling back to
 * the values below.  Set the following environment variables in production:
 *
 *   DB_HOST   – MySQL hostname (default: localhost)
 *   DB_NAME   – Database name  (default: buzz_sales_db)
 *   DB_USER   – MySQL username (default: buzz_user  – change before deployment)
 *   DB_PASS   – MySQL password (REQUIRED in production – no default provided)
 *
 * IMPORTANT: Never run in production with an empty DB_PASS.
 * Create a dedicated MySQL user with least-privilege access instead of root.
 */
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'buzz_sales_db');
define('DB_USER', getenv('DB_USER') ?: 'buzz_user');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

function getPDO(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
    return $pdo;
}
