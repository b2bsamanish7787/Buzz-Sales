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
if ($user['role'] !== ROLE_DESIGN) {
    echo json_encode(['success' => false, 'message' => 'Permission denied.']);
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (!Security::validateCSRF($token)) {
    echo json_encode(['success' => false, 'message' => 'Security token mismatch.']);
    exit;
}

$db         = Database::getInstance();
$projectId  = (int)($_POST['project_id'] ?? 0);
$action     = Security::sanitizeInput($_POST['action'] ?? '');
$remarks    = Security::sanitizeInput($_POST['remarks'] ?? '');

// Handle design files uploaded - move project to ops_review
if ($action === 'design_uploaded') {
    if (!$projectId) {
        echo json_encode(['success' => false, 'message' => 'No project specified.']);
        exit;
    }
    $project = $db->fetchOne("SELECT * FROM projects WHERE id = ?", [$projectId]);
    if (!$project) {
        echo json_encode(['success' => false, 'message' => 'Project not found.']);
        exit;
    }
    $db->execute("UPDATE projects SET status = 'ops_review', current_stage = 'design', updated_at = NOW() WHERE id = ?", [$projectId]);
    $notifier = new Notifier();
    $logger   = new Logger();
    $notifier->notifyOperations($projectId, "Design files uploaded for project: '{$project['project_name']}'. Please review and submit cost estimation.");
    $logger->log($user['id'], "Design uploaded and project moved to ops_review: ID:{$projectId}", $projectId, $_SERVER['REMOTE_ADDR'] ?? '');
    echo json_encode(['success' => true, 'message' => 'Designs uploaded and operations team notified.']);
    exit;
}

if (!$projectId || !in_array($action, ['approved','rejected','on_hold','resume'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$project = $db->fetchOne("SELECT * FROM projects WHERE id = ?", [$projectId]);
if (!$project) {
    echo json_encode(['success' => false, 'message' => 'Project not found.']);
    exit;
}

$deadlineDays   = (int)($_POST['deadline_days'] ?? 0);
$holdDuration   = (int)($_POST['hold_duration'] ?? 0);
$holdReason     = Security::sanitizeInput($_POST['hold_reason'] ?? '');
$rejectionReason = Security::sanitizeInput($_POST['rejection_reason'] ?? '');

switch ($action) {
    case 'approved':
        if ($deadlineDays < 1) {
            echo json_encode(['success' => false, 'message' => 'Please specify a deadline in days.']);
            exit;
        }
        $newStatus = STATUS_ONGOING;
        break;
    case 'rejected':
        if (!$rejectionReason) {
            echo json_encode(['success' => false, 'message' => 'Please provide a rejection reason.']);
            exit;
        }
        $newStatus = STATUS_REJECTED;
        break;
    case 'on_hold':
        if (!$holdReason) {
            echo json_encode(['success' => false, 'message' => 'Please provide a hold reason.']);
            exit;
        }
        $newStatus = STATUS_ON_HOLD;
        break;
    case 'resume':
        if (!in_array($project['status'], [STATUS_ON_HOLD, STATUS_REJECTED])) {
            echo json_encode(['success' => false, 'message' => 'Project cannot be resumed from its current status.']);
            exit;
        }
        $newStatus = STATUS_PENDING;
        break;
}

try {
    $db->execute(
        "INSERT INTO design_reviews (project_id, action, deadline_days, hold_duration, hold_reason, rejection_reason, remarks, reviewed_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        [$projectId, $action, $deadlineDays ?: null, $holdDuration ?: null, $holdReason, $rejectionReason, $remarks, $user['id']]
    );

    $db->execute("UPDATE projects SET status = ?, current_stage = 'design', updated_at = NOW() WHERE id = ?", [$newStatus, $projectId]);

    $notifier = new Notifier();
    $logger   = new Logger();
    $messages = [
        'approved'  => "Design approved for project: '{$project['project_name']}'. Deadline: {$deadlineDays} days.",
        'rejected'  => "Design rejected for project: '{$project['project_name']}'. Reason: {$rejectionReason}",
        'on_hold'   => "Project '{$project['project_name']}' placed on hold. Reason: {$holdReason}",
        'resume'    => "Project '{$project['project_name']}' has been resumed and is pending review.",
    ];

    $notifier->notifySales($project['sales_user_id'], $projectId, $messages[$action]);
    $logger->log($user['id'], "Design action '{$action}' for project ID:{$projectId}", $projectId, $_SERVER['REMOTE_ADDR'] ?? '');

    echo json_encode(['success' => true, 'message' => 'Action recorded successfully.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
