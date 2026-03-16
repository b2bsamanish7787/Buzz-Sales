<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/Database.php';

class Logger {
    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function log(?int $userId, string $action, ?int $projectId = null, string $ipAddress = ''): void {
        try {
            $this->db->execute(
                "INSERT INTO activity_logs (user_id, action, project_id, ip_address) VALUES (?, ?, ?, ?)",
                [$userId, $action, $projectId, $ipAddress]
            );
        } catch (Exception $e) {
            // fallback to file
        }

        $this->writeToFile($userId, $action, $projectId, $ipAddress);
    }

    private function writeToFile(?int $userId, string $action, ?int $projectId, string $ip): void {
        if (!is_dir(LOG_PATH)) {
            @mkdir(LOG_PATH, 0755, true);
        }
        $logFile = LOG_PATH . 'activity_' . date('Y-m-d') . '.log';
        $line    = sprintf(
            "[%s] UserID:%s | ProjectID:%s | IP:%s | Action: %s\n",
            date('Y-m-d H:i:s'),
            $userId ?? 'GUEST',
            $projectId ?? 'N/A',
            $ip,
            $action
        );
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }

    public function getLogs(array $filters = [], int $page = 1, int $perPage = 30): array {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[]  = 'al.user_id = ?';
            $params[] = (int)$filters['user_id'];
        }
        if (!empty($filters['project_id'])) {
            $where[]  = 'al.project_id = ?';
            $params[] = (int)$filters['project_id'];
        }
        if (!empty($filters['date_from'])) {
            $where[]  = 'al.created_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[]  = 'al.created_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['search'])) {
            $where[]  = 'al.action LIKE ?';
            $params[] = '%' . $filters['search'] . '%';
        }

        $whereStr = implode(' AND ', $where);
        $offset   = ($page - 1) * $perPage;

        $total = $this->db->fetchOne(
            "SELECT COUNT(*) as cnt FROM activity_logs al WHERE {$whereStr}",
            $params
        )['cnt'] ?? 0;

        $rows = $this->db->fetchAll(
            "SELECT al.*, u.username, u.full_name FROM activity_logs al
             LEFT JOIN users u ON al.user_id = u.id
             WHERE {$whereStr}
             ORDER BY al.created_at DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        );

        return ['rows' => $rows, 'total' => (int)$total, 'pages' => ceil($total / $perPage)];
    }

    public function getRecentLogs(int $limit = 20): array {
        return $this->db->fetchAll(
            "SELECT al.*, u.username, u.full_name FROM activity_logs al
             LEFT JOIN users u ON al.user_id = u.id
             ORDER BY al.created_at DESC
             LIMIT ?",
            [$limit]
        );
    }
}
