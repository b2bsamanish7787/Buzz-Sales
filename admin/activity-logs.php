<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/Security.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Logger.php';
require_once __DIR__ . '/../lib/Notifier.php';

Security::setSecurityHeaders();
$auth = new Auth();
$auth->requireAuth(ROLE_ADMIN);
$user      = $auth->getUser();
$db        = Database::getInstance();
$logger    = new Logger();
$notifier  = new Notifier();
$logoutUrl = '../public/logout.php';
$csrfToken = $auth->generateCSRFToken();

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$filters = [
    'user_id'   => $_GET['user_id'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to'   => $_GET['date_to'] ?? '',
    'search'    => Security::sanitizeInput($_GET['search'] ?? ''),
];

$result   = $logger->getLogs($filters, $page, $perPage);
$logs     = $result['rows'];
$total    = $result['total'];
$pages    = $result['pages'];
$allUsers = $db->fetchAll("SELECT id, username, full_name FROM users ORDER BY username");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Activity Logs – <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link rel="stylesheet" href="../public/css/style.css">
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<div style="padding-top:56px; display:flex;">
  <div class="sidebar">
    <div class="sidebar-section">Administration</div>
    <a href="index.php" class="nav-link"><i class="fa fa-tachometer-alt"></i> Dashboard</a>
    <a href="users.php" class="nav-link"><i class="fa fa-users"></i> Users</a>
    <a href="activity-logs.php" class="nav-link active"><i class="fa fa-history"></i> Activity Logs</a>
    <a href="notifications-config.php" class="nav-link"><i class="fa fa-envelope"></i> Notification Emails</a>
  </div>
  <div class="main-content">
    <div class="page-header">
      <h1><i class="fa fa-history text-buzz me-2"></i>Activity Logs</h1>
      <span class="badge bg-secondary"><?= number_format($total) ?> records</span>
    </div>

    <!-- Filters -->
    <div class="form-section mb-4">
      <form method="GET" class="row g-3 align-items-end">
        <div class="col-12 col-md-3">
          <label class="form-label">User</label>
          <select name="user_id" class="form-select">
            <option value="">All Users</option>
            <?php foreach ($allUsers as $u): ?>
            <option value="<?= $u['id'] ?>" <?= $filters['user_id'] == $u['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($u['username']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label">From Date</label>
          <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($filters['date_from']) ?>">
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label">To Date</label>
          <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($filters['date_to']) ?>">
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label">Search</label>
          <input type="text" name="search" class="form-control" placeholder="Search action…" value="<?= htmlspecialchars($filters['search']) ?>">
        </div>
        <div class="col-12 col-md-2 d-flex gap-2">
          <button type="submit" class="btn btn-buzz w-100"><i class="fa fa-filter me-1"></i>Filter</button>
          <a href="activity-logs.php" class="btn btn-outline-secondary"><i class="fa fa-times"></i></a>
        </div>
      </form>
    </div>

    <div class="table-card">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr><th>#</th><th>User</th><th>Action</th><th>Project</th><th>IP Address</th><th>Date/Time</th></tr>
          </thead>
          <tbody>
            <?php if (empty($logs)): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">No logs found.</td></tr>
            <?php else: foreach ($logs as $log): ?>
            <tr>
              <td class="text-muted small"><?= $log['id'] ?></td>
              <td><?= htmlspecialchars($log['username'] ?? 'System') ?></td>
              <td style="max-width:400px;"><?= htmlspecialchars($log['action']) ?></td>
              <td><?= $log['project_id'] ? '#' . $log['project_id'] : '—' ?></td>
              <td class="text-muted small"><?= htmlspecialchars($log['ip_address'] ?? '') ?></td>
              <td class="text-muted small"><?= date('d M Y H:i', strtotime($log['created_at'])) ?></td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <?php if ($pages > 1): ?>
      <div class="p-3 border-top d-flex justify-content-center">
        <nav>
          <ul class="pagination pagination-sm mb-0">
            <?php for ($i = 1; $i <= $pages; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
              <a class="page-link" href="?<?= http_build_query(array_merge($filters, ['page' => $i])) ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
          </ul>
        </nav>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../public/js/app.js"></script>
<script>var BASE_URL='..'; BuzzApp.initNotifications(<?= $user['id'] ?>);</script>
</body>
</html>
