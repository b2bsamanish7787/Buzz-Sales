<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/Security.php';

Security::setSecurityHeaders();
$auth = new Auth();

if ($auth->isLoggedIn()) {
    $role = $_SESSION['role'] ?? '';
    switch ($role) {
        case 'admin':       header('Location: ../admin/index.php'); break;
        case 'sales':       header('Location: ../sales/dashboard.php'); break;
        case 'design':      header('Location: ../design/dashboard.php'); break;
        case 'operations':  header('Location: ../operations/dashboard.php'); break;
        default:            header('Location: login.php');
    }
    exit;
}

header('Location: login.php');
exit;
