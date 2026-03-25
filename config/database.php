<?php
/**
 * Database configuration.
 * Credentials are loaded from environment variables first, falling back to
 * the values below.  Override the following environment variables to change
 * the connection without editing this file:
 *
 *   DB_HOST   – MySQL hostname
 *   DB_NAME   – Database name
 *   DB_USER   – MySQL username
 *   DB_PASS   – MySQL password
 *
 * IMPORTANT: Never commit real credentials to version control.
 * Use environment variables in production wherever possible.
 */
define('DB_HOST', getenv('DB_HOST') ?: 'az1-ts111.a2hosting.com');
define('DB_NAME', getenv('DB_NAME') ?: 'desig102_buzz_sales_db');
define('DB_USER', getenv('DB_USER') ?: 'desig102_buzz_sales_usr');
define('DB_PASS', getenv('DB_PASS') ?: '&zbmEYmauOo9Rj(I');
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
