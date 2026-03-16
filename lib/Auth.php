<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/Database.php';

class Auth {
    private Database $db;

    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->db = Database::getInstance();
    }

    public function login(string $username, string $password): array {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        // Brute-force protection: block an IP after 10 failed attempts in 15 minutes
        $windowStart = date('Y-m-d H:i:s', time() - 900);
        $failRow = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM activity_logs
             WHERE ip_address = ? AND action LIKE 'Failed login%' AND created_at >= ?",
            [$ip, $windowStart]
        );
        if (($failRow['cnt'] ?? 0) >= 10) {
            return ['success' => false, 'message' => 'Too many failed attempts. Please try again in 15 minutes.'];
        }

        $user = $this->db->fetchOne(
            "SELECT * FROM users WHERE (username = ? OR email = ?) AND status = 'active'",
            [trim($username), trim($username)]
        );

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->logActivity(null, 'Failed login attempt for username: ' . htmlspecialchars($username), null, $ip);
            return ['success' => false, 'message' => 'Invalid username or password.'];
        }

        $_SESSION['user_id']   = $user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['email']     = $user['email'];
        $_SESSION['login_time'] = time();
        $_SESSION['csrf_token'] = $this->generateCSRFToken();

        session_regenerate_id(true);

        $this->logActivity($user['id'], 'User logged in', null, $ip);

        return ['success' => true, 'role' => $user['role'], 'full_name' => $user['full_name']];
    }

    public function logout(): void {
        if ($this->isLoggedIn()) {
            $this->logActivity($_SESSION['user_id'], 'User logged out', null, $_SERVER['REMOTE_ADDR'] ?? '');
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
    }

    public function isLoggedIn(): bool {
        if (!isset($_SESSION['user_id'], $_SESSION['login_time'])) {
            return false;
        }
        if (time() - $_SESSION['login_time'] > SESSION_TIMEOUT) {
            $this->logout();
            return false;
        }
        $_SESSION['login_time'] = time();
        return true;
    }

    public function getUser(): array {
        return [
            'id'        => $_SESSION['user_id']   ?? 0,
            'username'  => $_SESSION['username']  ?? '',
            'role'      => $_SESSION['role']       ?? '',
            'full_name' => $_SESSION['full_name']  ?? '',
            'email'     => $_SESSION['email']      ?? '',
        ];
    }

    public function hasRole(string $role): bool {
        return isset($_SESSION['role']) && $_SESSION['role'] === $role;
    }

    public function requireAuth(?string $role = null): void {
        if (!$this->isLoggedIn()) {
            header('Location: ' . $this->getLoginPath());
            exit;
        }
        if ($role !== null && !$this->hasRole($role)) {
            http_response_code(403);
            echo '<!DOCTYPE html><html><body><h1>403 Forbidden</h1><p>You do not have permission to access this page.</p><a href="javascript:history.back()">Go Back</a></body></html>';
            exit;
        }
    }

    public function generateCSRFToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public function validateCSRFToken(string $token): bool {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    private function getLoginPath(): string {
        $depth = substr_count($_SERVER['SCRIPT_NAME'] ?? '', '/') - 1;
        return str_repeat('../', max(0, $depth - 1)) . 'public/login.php';
    }

    private function logActivity(?int $userId, string $action, ?int $projectId, string $ip): void {
        try {
            $this->db->execute(
                "INSERT INTO activity_logs (user_id, action, project_id, ip_address) VALUES (?, ?, ?, ?)",
                [$userId, $action, $projectId, $ip]
            );
        } catch (Exception $e) {
            // Silent fail on log
        }
    }
}
