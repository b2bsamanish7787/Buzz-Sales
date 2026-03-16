<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/Security.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Notifier.php';

Security::setSecurityHeaders();

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}

$user     = $auth->getUser();
$db       = Database::getInstance();
$action   = $_GET['action'] ?? $_POST['action'] ?? 'get';
$notifier = new Notifier();

if ($action === 'get') {
    $notifications = $notifier->getUnreadNotifications($user['id'], $user['role']);
    $count         = $notifier->getUnreadCount($user['id'], $user['role']);
    echo json_encode([
        'success'       => true,
        'notifications' => $notifications,
        'count'         => $count,
    ]);
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (!Security::validateCSRF($token)) {
    echo json_encode(['success' => false, 'message' => 'Security token mismatch.']);
    exit;
}

if ($action === 'read') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) $notifier->markAsRead($id);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'read_all') {
    $notifier->markAllRead($user['id'], $user['role']);
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
