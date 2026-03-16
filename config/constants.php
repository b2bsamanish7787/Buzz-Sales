<?php
define('APP_NAME', 'Buzznation Client Requirement Portal');

// APP_URL is auto-detected from the current request so the same codebase works
// on any hostname (dev, staging, production).  Override via the APP_URL
// environment variable if you need to force a specific value.
if (!defined('APP_URL')) {
    $_appUrl = getenv('APP_URL');
    if (!$_appUrl) {
        $_scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        // Sanitize HTTP_HOST: only allow hostname characters (no port injection etc.)
        $_rawHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $_host    = preg_replace('/[^a-zA-Z0-9.\-:]/', '', $_rawHost);
        if (!$_host) { $_host = 'localhost'; }
        $_appUrl  = $_scheme . '://' . $_host;
    }
    define('APP_URL', rtrim($_appUrl, '/'));
    unset($_appUrl, $_scheme, $_rawHost, $_host);
}
define('UPLOAD_PATH', dirname(__DIR__) . '/uploads/');
define('LOG_PATH', dirname(__DIR__) . '/logs/');
define('MAX_FILE_SIZE', 500 * 1024 * 1024);

define('ALLOWED_FILE_TYPES', [
    'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/bmp',
    'image/svg+xml', 'image/tiff',
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'text/plain', 'text/csv',
    'application/zip', 'application/x-rar-compressed', 'application/x-zip-compressed',
    'application/octet-stream',
]);

define('ALLOWED_EXTENSIONS', [
    'jpg','jpeg','png','gif','webp','bmp','svg','tiff','tif',
    'pdf','doc','docx','xls','xlsx','ppt','pptx',
    'txt','csv','zip','rar','ai','psd','eps','indd'
]);

define('SESSION_TIMEOUT', 3600);

define('ROLE_ADMIN', 'admin');
define('ROLE_SALES', 'sales');
define('ROLE_DESIGN', 'design');
define('ROLE_OPERATIONS', 'operations');

define('STATUS_PENDING', 'pending');
define('STATUS_APPROVED', 'approved');
define('STATUS_REJECTED', 'rejected');
define('STATUS_ON_HOLD', 'on_hold');
define('STATUS_ONGOING', 'ongoing');
define('STATUS_DESIGN_COMPLETE', 'design_complete');
define('STATUS_OPS_REVIEW', 'ops_review');
define('STATUS_SALES_REVIEW', 'sales_review');
define('STATUS_CHANGE_REQUESTED', 'change_requested');
define('STATUS_COMPLETED', 'completed');
define('STATUS_CLOSED', 'closed');

define('STATUS_BADGE_MAP', [
    'pending'          => 'warning',
    'approved'         => 'success',
    'rejected'         => 'danger',
    'on_hold'          => 'secondary',
    'ongoing'          => 'info',
    'design_complete'  => 'primary',
    'ops_review'       => 'info',
    'sales_review'     => 'primary',
    'change_requested' => 'warning',
    'completed'        => 'success',
    'closed'           => 'dark',
]);

function getStatusBadge(string $status): string {
    $map = STATUS_BADGE_MAP;
    $color = $map[$status] ?? 'secondary';
    $label = ucwords(str_replace('_', ' ', $status));
    return "<span class=\"badge bg-{$color}\">{$label}</span>";
}
