<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/Security.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Notifier.php';
require_once __DIR__ . '/../lib/Logger.php';

Security::setSecurityHeaders();

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}

$user = $auth->getUser();
if ($user['role'] !== ROLE_OPERATIONS) {
    echo json_encode(['success' => false, 'message' => 'Permission denied.']);
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (!Security::validateCSRF($token)) {
    echo json_encode(['success' => false, 'message' => 'Security token mismatch.']);
    exit;
}

$db        = Database::getInstance();
$projectId = (int)($_POST['project_id'] ?? 0);
$costUsd   = Security::sanitizeInput($_POST['cost_usd'] ?? '');
$remarks   = Security::sanitizeInput($_POST['remarks'] ?? '');

if (!$projectId || !is_numeric($costUsd) || $costUsd <= 0) {
    echo json_encode(['success' => false, 'message' => 'Valid project and cost are required.']);
    exit;
}

$project = $db->fetchOne("SELECT * FROM projects WHERE id = ? AND status = 'ops_review'", [$projectId]);
if (!$project) {
    echo json_encode(['success' => false, 'message' => 'Project not found or not in ops review stage.']);
    exit;
}

try {
    $db->execute(
        "INSERT INTO cost_tracking (project_id, cost_usd, remarks, reviewed_by) VALUES (?, ?, ?, ?)",
        [$projectId, (float)$costUsd, $remarks, $user['id']]
    );

    $db->execute("UPDATE projects SET status = 'sales_review', current_stage = 'operations', updated_at = NOW() WHERE id = ?", [$projectId]);

    $notifier = new Notifier();
    $logger   = new Logger();

    $msg = "Cost estimation submitted for project: '{$project['project_name']}'. Cost: \${$costUsd} USD. Ready for sales review.";
    $notifier->notifySales($project['sales_user_id'], $projectId, $msg);
    $logger->log($user['id'], "Cost submitted for project ID:{$projectId} - \${$costUsd}", $projectId, $_SERVER['REMOTE_ADDR'] ?? '');

    echo json_encode(['success' => true, 'message' => 'Cost submitted. Project moved to sales review.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
