<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/Database.php';

class FileUpload {
    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function upload(array $file, int $projectId, string $role, string $uploadType = 'requirement'): array {
        $validation = $this->validate($file);
        if (!$validation['valid']) {
            return ['success' => false, 'message' => $validation['message']];
        }

        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $fileName = uniqid('file_', true) . '_' . time() . '.' . $ext;
        $dir      = UPLOAD_PATH . $projectId . '/';

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filePath = $dir . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            return ['success' => false, 'message' => 'Failed to save file. Please try again.'];
        }

        $userId = $_SESSION['user_id'] ?? null;

        $this->db->execute(
            "INSERT INTO file_uploads (project_id, original_name, file_name, file_path, file_size, file_type, upload_type, uploaded_by, uploaded_by_role)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$projectId, $file['name'], $fileName, $filePath, $file['size'], $file['type'], $uploadType, $userId, $role]
        );

        return [
            'success'   => true,
            'file_id'   => $this->db->lastInsertId(),
            'file_name' => $fileName,
            'original'  => $file['name'],
            'file_path' => $filePath,
        ];
    }

    public function validate(array $file): array {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors = [
                UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
                UPLOAD_ERR_FORM_SIZE  => 'File exceeds form upload limit.',
                UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
                UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                UPLOAD_ERR_EXTENSION  => 'Upload stopped by extension.',
            ];
            return ['valid' => false, 'message' => $errors[$file['error']] ?? 'Unknown upload error.'];
        }

        if ($file['size'] > MAX_FILE_SIZE) {
            return ['valid' => false, 'message' => 'File size exceeds 500MB limit.'];
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_EXTENSIONS, true)) {
            return ['valid' => false, 'message' => "File type '.{$ext}' is not allowed."];
        }

        return ['valid' => true, 'message' => ''];
    }

    public function getFilePath(string $filename): string {
        return UPLOAD_PATH . $filename;
    }

    public function getProjectFiles(int $projectId, string $uploadType = ''): array {
        $sql    = "SELECT * FROM file_uploads WHERE project_id = ?";
        $params = [$projectId];
        if ($uploadType) {
            $sql    .= " AND upload_type = ?";
            $params[] = $uploadType;
        }
        $sql .= " ORDER BY created_at DESC";
        return $this->db->fetchAll($sql, $params);
    }

    public function deleteFile(int $fileId, int $projectId): bool {
        $file = $this->db->fetchOne(
            "SELECT * FROM file_uploads WHERE id = ? AND project_id = ?",
            [$fileId, $projectId]
        );
        if (!$file) return false;

        if (file_exists($file['file_path'])) {
            unlink($file['file_path']);
        }

        $this->db->execute("DELETE FROM file_uploads WHERE id = ?", [$fileId]);
        return true;
    }

    public function formatFileSize(int $bytes): string {
        if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576)    return number_format($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024)       return number_format($bytes / 1024, 2) . ' KB';
        return $bytes . ' B';
    }
}
