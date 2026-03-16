<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/email.php';
require_once __DIR__ . '/Database.php';

class Notifier {
    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function notifyDesign(int $projectId, string $message): void {
        $this->createRoleNotification('design', $projectId, $message);
        $this->sendEmailNotification('design', 'New Project Submitted - Action Required', $message);
    }

    public function notifySales(int $userId, int $projectId, string $message): void {
        $this->createUserNotification($userId, $projectId, $message);
        $user = $this->db->fetchOne("SELECT email, full_name FROM users WHERE id = ?", [$userId]);
        if ($user) {
            sendEmail($user['email'], 'Project Update Notification', $message, $user['full_name']);
        }
    }

    public function notifyOperations(int $projectId, string $message): void {
        $this->createRoleNotification('operations', $projectId, $message);
        $this->sendEmailNotification('operations', 'Design Approved - Review Required', $message);
    }

    public function notifyAdmin(int $projectId, string $message): void {
        $this->createRoleNotification('admin', $projectId, $message);
        $this->sendEmailNotification('admin', 'Portal Notification', $message);
    }

    public function getUnreadNotifications(int $userId, string $role): array {
        return $this->db->fetchAll(
            "SELECT * FROM notifications
             WHERE (user_id = ? OR target_role = ?)
             AND is_read = 0
             ORDER BY created_at DESC
             LIMIT 50",
            [$userId, $role]
        );
    }

    public function getUnreadCount(int $userId, string $role): int {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) as cnt FROM notifications WHERE (user_id = ? OR target_role = ?) AND is_read = 0",
            [$userId, $role]
        );
        return (int)($row['cnt'] ?? 0);
    }

    public function markAsRead(int $notificationId): void {
        $this->db->execute("UPDATE notifications SET is_read = 1 WHERE id = ?", [$notificationId]);
    }

    public function markAllRead(int $userId, string $role): void {
        $this->db->execute(
            "UPDATE notifications SET is_read = 1 WHERE user_id = ? OR target_role = ?",
            [$userId, $role]
        );
    }

    public function sendEmailNotification(string $role, string $subject, string $message): void {
        $emails = $this->db->fetchAll(
            "SELECT email FROM notification_emails WHERE role = ? AND active = 1",
            [$role]
        );
        foreach ($emails as $emailRow) {
            sendEmail($emailRow['email'], $subject, $message);
        }
    }

    private function createRoleNotification(string $role, int $projectId, string $message): void {
        // Insert one user-specific notification row per active user in the role.
        // The target_role column is kept so that queries can filter by role even
        // without resolving individual user IDs (e.g. for shared-login teams).
        $users = $this->db->fetchAll("SELECT id FROM users WHERE role = ? AND status = 'active'", [$role]);
        if ($users) {
            foreach ($users as $u) {
                $this->db->execute(
                    "INSERT INTO notifications (user_id, target_role, project_id, message) VALUES (?, ?, ?, ?)",
                    [$u['id'], $role, $projectId, $message]
                );
            }
        } else {
            // No active users for this role yet – store a role-only record so the
            // notification is visible as soon as a user with that role logs in.
            $this->db->execute(
                "INSERT INTO notifications (target_role, project_id, message) VALUES (?, ?, ?)",
                [$role, $projectId, $message]
            );
        }
    }

    private function createUserNotification(int $userId, int $projectId, string $message): void {
        $this->db->execute(
            "INSERT INTO notifications (user_id, project_id, message) VALUES (?, ?, ?)",
            [$userId, $projectId, $message]
        );
    }
}
