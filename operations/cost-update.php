<?php
// Redirect to cost-submit API - legacy endpoint kept for compatibility
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/Auth.php';
$auth = new Auth();
if (!$auth->isLoggedIn() || $auth->getUser()['role'] !== ROLE_OPERATIONS) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}
// Forward to the API
include __DIR__ . '/../api/cost-submit.php';
