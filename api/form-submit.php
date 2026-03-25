<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/Security.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Validator.php';
require_once __DIR__ . '/../lib/Notifier.php';
require_once __DIR__ . '/../lib/Logger.php';

Security::setSecurityHeaders();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ---- LOGIN ----
if ($action === 'login') {
    $auth  = new Auth();
    $token = $_POST['csrf_token'] ?? '';

    if (!Security::validateCSRF($token)) {
        echo json_encode(['success' => false, 'message' => 'Security token mismatch. Please refresh.']);
        exit;
    }

    $username = Security::sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Username and password are required.']);
        exit;
    }

    $result = $auth->login($username, $password);
    if ($result['success']) {
        $redirects = [
            'admin'      => '../admin/index.php',
            'sales'      => '../sales/dashboard.php',
            'design'     => '../design/dashboard.php',
            'operations' => '../operations/dashboard.php',
        ];
        $result['redirect'] = $redirects[$result['role']] ?? '../public/login.php';
    }
    echo json_encode($result);
    exit;
}

// ---- REQUIRE AUTH FOR OTHER ACTIONS ----
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}

$user   = $auth->getUser();
$db     = Database::getInstance();
$token  = $_POST['csrf_token'] ?? '';

if (!Security::validateCSRF($token)) {
    echo json_encode(['success' => false, 'message' => 'Security token mismatch.']);
    exit;
}

// ---- SUBMIT REQUIREMENT FORM ----
if ($action === 'submit_requirement') {
    if ($user['role'] !== ROLE_SALES) {
        echo json_encode(['success' => false, 'message' => 'Permission denied.']);
        exit;
    }

    $v = new Validator();
    $projectName = Security::sanitizeInput($_POST['project_name'] ?? '');
    $clientName  = Security::sanitizeInput($_POST['client_name'] ?? '');

    $v->required($projectName, 'project_name')->required($clientName, 'client_name');
    if ($v->hasErrors()) {
        echo json_encode(['success' => false, 'message' => $v->firstError(), 'errors' => $v->getErrors()]);
        exit;
    }

    $reqType      = isset($_POST['requirement_type']) ? (array)$_POST['requirement_type'] : [];
    $contactPerson = Security::sanitizeInput($_POST['contact_person'] ?? '');
    $contactEmail  = Security::sanitizeInput($_POST['contact_email'] ?? '');
    $contactPhone  = Security::sanitizeInput($_POST['contact_phone'] ?? '');
    $clientCountry = Security::sanitizeInput($_POST['client_country'] ?? '');
    $eventName     = Security::sanitizeInput($_POST['event_name'] ?? '');
    $eventDate     = Security::sanitizeInput($_POST['event_date'] ?? '');
    $venue         = Security::sanitizeInput($_POST['venue'] ?? '');
    $city          = Security::sanitizeInput($_POST['city'] ?? '');
    $country       = Security::sanitizeInput($_POST['country'] ?? '');
    $boothSize     = Security::sanitizeInput($_POST['booth_size'] ?? '');
    $budget        = Security::sanitizeInput($_POST['budget'] ?? '');
    $standType     = Security::sanitizeInput($_POST['stand_type'] ?? '');
    $designStyle   = Security::sanitizeInput($_POST['design_style'] ?? '');
    $colors        = Security::sanitizeInput($_POST['colors'] ?? '');
    $products      = Security::sanitizeInput($_POST['products_to_display'] ?? '');
    $specialReq    = Security::sanitizeInput($_POST['special_requirements'] ?? '');
    $notes         = Security::sanitizeInput($_POST['additional_notes'] ?? '');
    $consent       = !empty($_POST['consent_agreed']) ? 1 : 0;
    $reqTypeStr    = implode(',', array_map('trim', $reqType));

    try {
        $db->execute(
            "INSERT INTO projects (sales_user_id, project_name, client_name, status, current_stage) VALUES (?, ?, ?, 'pending', 'sales')",
            [$user['id'], $projectName, $clientName]
        );
        $projectId = $db->lastInsertId();

        $db->execute(
            "INSERT INTO project_details
             (project_id, requirement_type, contact_person, contact_email, contact_phone, client_country,
              event_name, event_date, venue, city, country, booth_size, budget, stand_type,
              design_style, colors, products_to_display, special_requirements, additional_notes, consent_agreed)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$projectId, $reqTypeStr, $contactPerson, $contactEmail, $contactPhone, $clientCountry,
             $eventName, $eventDate, $venue, $city, $country, $boothSize, $budget, $standType,
             $designStyle, $colors, $products, $specialReq, $notes, $consent]
        );

        $logger   = new Logger();
        $notifier = new Notifier();
        $logger->log($user['id'], "Submitted new project requirement: {$projectName} (ID:{$projectId})", $projectId, $_SERVER['REMOTE_ADDR'] ?? '');
        $notifier->notifyDesign($projectId, "New project requirement submitted: '{$projectName}' by {$user['full_name']}. Please review.");

        echo json_encode(['success' => true, 'message' => 'Requirement submitted successfully!', 'project_id' => $projectId]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
    }
    exit;
}

// ---- CHANGE REQUEST ----
if ($action === 'change_request') {
    if ($user['role'] !== ROLE_SALES) {
        echo json_encode(['success' => false, 'message' => 'Permission denied.']);
        exit;
    }

    $projectId  = (int)($_POST['project_id'] ?? 0);
    $description = Security::sanitizeInput($_POST['description'] ?? '');
    $notes       = Security::sanitizeInput($_POST['notes'] ?? '');

    if (!$projectId || !$description) {
        echo json_encode(['success' => false, 'message' => 'Project and description are required.']);
        exit;
    }

    $project = $db->fetchOne("SELECT * FROM projects WHERE id = ? AND sales_user_id = ?", [$projectId, $user['id']]);
    if (!$project) {
        echo json_encode(['success' => false, 'message' => 'Project not found.']);
        exit;
    }

    $db->execute(
        "INSERT INTO change_requests (project_id, sales_user_id, description, notes) VALUES (?, ?, ?, ?)",
        [$projectId, $user['id'], $description, $notes]
    );
    $changeRequestId = $db->lastInsertId();
    $db->execute("UPDATE projects SET status = 'change_requested', updated_at = NOW() WHERE id = ?", [$projectId]);

    // Link any uploaded files to this change request
    $fileIds = array_filter(array_map('intval', explode(',', $_POST['file_ids'] ?? '')));
    foreach ($fileIds as $fid) {
        $db->execute(
            "UPDATE file_uploads SET change_request_id=? WHERE id=? AND project_id=?",
            [$changeRequestId, $fid, $projectId]
        );
    }

    $logger   = new Logger();
    $notifier = new Notifier();
    $logger->log($user['id'], "Change request submitted for project ID:{$projectId}", $projectId, $_SERVER['REMOTE_ADDR'] ?? '');
    $notifier->notifyDesign($projectId, "Change request submitted for project: '{$project['project_name']}' by {$user['full_name']}.");

    echo json_encode(['success' => true, 'message' => 'Change request submitted.']);
    exit;
}

// ---- CLOSE PROJECT ----
if ($action === 'close_project') {
    $projectId       = (int)($_POST['project_id'] ?? 0);
    $closingComments = Security::sanitizeInput($_POST['closing_comments'] ?? '');

    if ($user['role'] === ROLE_SALES) {
        $project = $db->fetchOne("SELECT * FROM projects WHERE id = ? AND sales_user_id = ?", [$projectId, $user['id']]);
    } else {
        $project = $db->fetchOne("SELECT * FROM projects WHERE id = ?", [$projectId]);
    }

    if (!$project) {
        echo json_encode(['success' => false, 'message' => 'Project not found.']);
        exit;
    }

    $db->execute(
        "UPDATE projects SET status = 'completed', closing_comments = ?, updated_at = NOW() WHERE id = ?",
        [$closingComments, $projectId]
    );
    $logger = new Logger();
    $logger->log($user['id'], "Project completed & closed: ID:{$projectId}", $projectId, $_SERVER['REMOTE_ADDR'] ?? '');

    echo json_encode(['success' => true, 'message' => 'Project marked as completed.']);
    exit;
}

// ---- USER MANAGEMENT (Admin) ----
if ($action === 'create_user') {
    if ($user['role'] !== ROLE_ADMIN) {
        echo json_encode(['success' => false, 'message' => 'Permission denied.']);
        exit;
    }
    $username  = Security::sanitizeInput($_POST['username'] ?? '');
    $email     = Security::sanitizeInput($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $role      = Security::sanitizeInput($_POST['role'] ?? '');
    $fullName  = Security::sanitizeInput($_POST['full_name'] ?? '');

    $v = new Validator();
    $v->required($username, 'username')
      ->required($email, 'email')
      ->email($email)
      ->required($password, 'password')
      ->minLength($password, 6, 'password')
      ->required($role, 'role');

    if ($v->hasErrors()) {
        echo json_encode(['success' => false, 'message' => $v->firstError()]);
        exit;
    }
    if (!in_array($role, ['admin','sales','design','operations'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid role.']);
        exit;
    }

    $exists = $db->fetchOne("SELECT id FROM users WHERE username = ? OR email = ?", [$username, $email]);
    if ($exists) {
        echo json_encode(['success' => false, 'message' => 'Username or email already exists.']);
        exit;
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $db->execute("INSERT INTO users (username, email, password_hash, role, full_name) VALUES (?, ?, ?, ?, ?)",
        [$username, $email, $hash, $role, $fullName]);

    $logger = new Logger();
    $logger->log($user['id'], "Created user: {$username} ({$role})", null, $_SERVER['REMOTE_ADDR'] ?? '');
    echo json_encode(['success' => true, 'message' => 'User created successfully.']);
    exit;
}

if ($action === 'edit_user') {
    if ($user['role'] !== ROLE_ADMIN) {
        echo json_encode(['success' => false, 'message' => 'Permission denied.']);
        exit;
    }
    $userId   = (int)($_POST['user_id'] ?? 0);
    $email    = Security::sanitizeInput($_POST['email'] ?? '');
    $fullName = Security::sanitizeInput($_POST['full_name'] ?? '');
    $role     = Security::sanitizeInput($_POST['role'] ?? '');
    $status   = Security::sanitizeInput($_POST['status'] ?? 'active');

    if (!in_array($role, ['admin','sales','design','operations'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid role.']);
        exit;
    }

    $db->execute("UPDATE users SET email = ?, full_name = ?, role = ?, status = ? WHERE id = ?",
        [$email, $fullName, $role, $status, $userId]);

    $logger = new Logger();
    $logger->log($user['id'], "Edited user ID:{$userId}", null, $_SERVER['REMOTE_ADDR'] ?? '');
    echo json_encode(['success' => true, 'message' => 'User updated.']);
    exit;
}

if ($action === 'delete_user') {
    if ($user['role'] !== ROLE_ADMIN) {
        echo json_encode(['success' => false, 'message' => 'Permission denied.']);
        exit;
    }
    $userId = (int)($_POST['user_id'] ?? 0);
    if ($userId === $user['id']) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete your own account.']);
        exit;
    }
    $db->execute("UPDATE users SET status = 'inactive' WHERE id = ?", [$userId]);
    $logger = new Logger();
    $logger->log($user['id'], "Deactivated user ID:{$userId}", null, $_SERVER['REMOTE_ADDR'] ?? '');
    echo json_encode(['success' => true, 'message' => 'User deactivated.']);
    exit;
}

if ($action === 'change_password') {
    if ($user['role'] !== ROLE_ADMIN) {
        echo json_encode(['success' => false, 'message' => 'Permission denied.']);
        exit;
    }
    $userId   = (int)($_POST['user_id'] ?? 0);
    $password = $_POST['new_password'] ?? '';
    if (strlen($password) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
        exit;
    }
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $db->execute("UPDATE users SET password_hash = ? WHERE id = ?", [$hash, $userId]);
    $logger = new Logger();
    $logger->log($user['id'], "Changed password for user ID:{$userId}", null, $_SERVER['REMOTE_ADDR'] ?? '');
    echo json_encode(['success' => true, 'message' => 'Password changed.']);
    exit;
}

// ---- NOTIFICATION EMAIL CONFIG ----
if ($action === 'add_notif_email') {
    if ($user['role'] !== ROLE_ADMIN) {
        echo json_encode(['success' => false, 'message' => 'Permission denied.']); exit;
    }
    $role  = Security::sanitizeInput($_POST['role'] ?? '');
    $email = Security::sanitizeInput($_POST['email'] ?? '');
    $v = new Validator();
    $v->required($role, 'role')->required($email, 'email')->email($email);
    if ($v->hasErrors()) {
        echo json_encode(['success' => false, 'message' => $v->firstError()]); exit;
    }
    $db->execute("INSERT INTO notification_emails (role, email) VALUES (?, ?)", [$role, $email]);
    echo json_encode(['success' => true, 'message' => 'Email added.', 'id' => $db->lastInsertId()]);
    exit;
}

if ($action === 'delete_notif_email') {
    if ($user['role'] !== ROLE_ADMIN) {
        echo json_encode(['success' => false, 'message' => 'Permission denied.']); exit;
    }
    $id = (int)($_POST['email_id'] ?? 0);
    $db->execute("DELETE FROM notification_emails WHERE id = ?", [$id]);
    echo json_encode(['success' => true, 'message' => 'Email removed.']);
    exit;
}

if ($action === 'toggle_notif_email') {
    if ($user['role'] !== ROLE_ADMIN) {
        echo json_encode(['success' => false, 'message' => 'Permission denied.']); exit;
    }
    $id = (int)($_POST['email_id'] ?? 0);
    $db->execute("UPDATE notification_emails SET active = 1 - active WHERE id = ?", [$id]);
    echo json_encode(['success' => true, 'message' => 'Email toggled.']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
