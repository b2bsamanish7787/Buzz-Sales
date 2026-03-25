<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/Security.php';
require_once __DIR__ . '/../lib/FileUpload.php';
require_once __DIR__ . '/../lib/Logger.php';
require_once __DIR__ . '/../lib/Database.php';

Security::setSecurityHeaders();

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}

$getAction = $_GET['action'] ?? '';

// Handle file download
if ($getAction === 'download') {
    $fileId = (int)($_GET['id'] ?? 0);
    if (!$fileId) { http_response_code(400); exit; }
    $db   = Database::getInstance();
    $file = $db->fetchOne("SELECT * FROM file_uploads WHERE id = ?", [$fileId]);
    if (!$file || !file_exists($file['file_path'])) { http_response_code(404); echo '404 Not Found'; exit; }
    $mime = mime_content_type($file['file_path']) ?: 'application/octet-stream';
    $safeFilename = rawurlencode($file['original_name']);
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename*=UTF-8\'\'' . $safeFilename);
    header('Content-Length: ' . filesize($file['file_path']));
    header('Cache-Control: no-cache');
    readfile($file['file_path']);
    exit;
}

// Handle inline file view
if ($getAction === 'view') {
    $fileId = (int)($_GET['id'] ?? 0);
    if (!$fileId) { http_response_code(400); exit; }
    $db   = Database::getInstance();
    $file = $db->fetchOne("SELECT * FROM file_uploads WHERE id = ?", [$fileId]);
    if (!$file || !file_exists($file['file_path'])) { http_response_code(404); echo '404 Not Found'; exit; }
    $mime = mime_content_type($file['file_path']) ?: 'application/octet-stream';
    $safeFilename = rawurlencode($file['original_name']);
    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename*=UTF-8\'\'' . $safeFilename);
    header('Content-Length: ' . filesize($file['file_path']));
    header('Cache-Control: no-cache');
    readfile($file['file_path']);
    exit;
}

header('Content-Type: application/json');

$user       = $auth->getUser();
$postAction = Security::sanitizeInput($_POST['action'] ?? '');

// Link uploaded files to a project (called after form-submit creates the project)
if ($postAction === 'link') {
    $projectId = (int)($_POST['project_id'] ?? 0);
    $fileIds   = array_filter(array_map('intval', explode(',', $_POST['file_ids'] ?? '')));
    if ($projectId && !empty($fileIds)) {
        $db = Database::getInstance();
        foreach ($fileIds as $fid) {
            $db->execute("UPDATE file_uploads SET project_id=? WHERE id=? AND project_id=0", [$projectId, $fid]);
        }
    }
    echo json_encode(['success' => true]);
    exit;
}

// Standard file upload
$projectId  = (int)($_POST['project_id'] ?? 0);
$uploadType = Security::sanitizeInput($_POST['upload_type'] ?? 'requirement');

if (!in_array($uploadType, ['requirement','design','change_request'])) {
    $uploadType = 'requirement';
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
    echo json_encode(['success' => false, 'message' => 'No file received.']);
    exit;
}

$uploader = new FileUpload();
$result   = $uploader->upload($_FILES['file'], $projectId, $user['role'], $uploadType);

if ($result['success']) {
    $logger = new Logger();
    $logger->log($user['id'], "Uploaded file: {$result['original']} for project ID:{$projectId}", $projectId, $_SERVER['REMOTE_ADDR'] ?? '');
    echo json_encode([
        'success'   => true,
        'file_id'   => $result['file_id'],
        'file_name' => $result['file_name'],
        'original'  => $result['original'],
        'message'   => 'File uploaded successfully.'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => $result['message']]);
}
