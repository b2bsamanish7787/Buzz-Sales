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

$totalUsers    = $db->fetchOne("SELECT COUNT(*) as c FROM users WHERE status='active'")['c'] ?? 0;
$totalProjects = $db->fetchOne("SELECT COUNT(*) as c FROM projects")['c'] ?? 0;
$pendingProjects = $db->fetchOne("SELECT COUNT(*) as c FROM projects WHERE status='pending'")['c'] ?? 0;
$completedProjects = $db->fetchOne("SELECT COUNT(*) as c FROM projects WHERE status='completed'")['c'] ?? 0;
$recentLogs    = $logger->getRecentLogs(15);
$recentProjects = $db->fetchAll("SELECT p.*, u.full_name as sales_name FROM projects p LEFT JOIN users u ON p.sales_user_id=u.id ORDER BY p.created_at DESC LIMIT 8");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard – <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link rel="stylesheet" href="../public/css/style.css">
<meta name="csrf-token" content="<?= $csrfToken ?>">
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div style="padding-top:56px; display:flex;">
  <!-- Sidebar -->
  <div class="sidebar">
    <div class="sidebar-section">Administration</div>
    <a href="index.php" class="nav-link active"><i class="fa fa-tachometer-alt"></i> Dashboard</a>
    <a href="users.php" class="nav-link"><i class="fa fa-users"></i> Users</a>
    <a href="activity-logs.php" class="nav-link"><i class="fa fa-history"></i> Activity Logs</a>
    <a href="notifications-config.php" class="nav-link"><i class="fa fa-envelope"></i> Notification Emails</a>
    <div class="divider"></div>
    <div class="sidebar-section">Quick Links</div>
    <a href="../sales/dashboard.php" class="nav-link"><i class="fa fa-briefcase"></i> Sales View</a>
    <a href="../design/dashboard.php" class="nav-link"><i class="fa fa-paint-brush"></i> Design View</a>
    <a href="../operations/dashboard.php" class="nav-link"><i class="fa fa-cogs"></i> Operations View</a>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <div class="page-header">
      <div>
        <h1><i class="fa fa-tachometer-alt text-buzz me-2"></i>Admin Dashboard</h1>
        <small class="text-muted">Welcome back, <?= htmlspecialchars($user['full_name']) ?>!</small>
      </div>
      <span class="text-muted small"><?= date('l, d F Y') ?></span>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-lg-3">
        <div class="stat-card red">
          <i class="fa fa-users stat-icon"></i>
          <h3><?= $totalUsers ?></h3>
          <p>Active Users</p>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card blue">
          <i class="fa fa-folder stat-icon"></i>
          <h3><?= $totalProjects ?></h3>
          <p>Total Projects</p>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card orange">
          <i class="fa fa-clock stat-icon"></i>
          <h3><?= $pendingProjects ?></h3>
          <p>Pending Review</p>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card green">
          <i class="fa fa-check-circle stat-icon"></i>
          <h3><?= $completedProjects ?></h3>
          <p>Completed</p>
        </div>
      </div>
    </div>

    <div class="row g-3">
      <!-- Recent Projects -->
      <div class="col-12 col-xl-7">
        <div class="table-card mb-4">
          <div class="d-flex align-items-center justify-content-between p-3 border-bottom">
            <h6 class="mb-0 fw-bold"><i class="fa fa-folder-open me-2 text-buzz"></i>Recent Projects</h6>
            <span class="badge bg-secondary"><?= $totalProjects ?> total</span>
          </div>
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead>
                <tr>
                  <th>#</th><th>Project</th><th>Client</th><th>Sales</th><th>Status</th><th>Date</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($recentProjects)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No projects yet.</td></tr>
                <?php else: foreach ($recentProjects as $p): ?>
                <tr>
                  <td><?= $p['id'] ?></td>
                  <td class="fw-semibold"><?= htmlspecialchars($p['project_name']) ?></td>
                  <td><?= htmlspecialchars($p['client_name']) ?></td>
                  <td><?= htmlspecialchars($p['sales_name'] ?? '—') ?></td>
                  <td><?= getStatusBadge($p['status']) ?></td>
                  <td class="text-muted small"><?= date('d M Y', strtotime($p['created_at'])) ?></td>
                </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Recent Activity -->
      <div class="col-12 col-xl-5">
        <div class="table-card">
          <div class="d-flex align-items-center justify-content-between p-3 border-bottom">
            <h6 class="mb-0 fw-bold"><i class="fa fa-history me-2 text-buzz"></i>Recent Activity</h6>
            <a href="activity-logs.php" class="btn btn-sm btn-outline-secondary">View All</a>
          </div>
          <div style="max-height:420px;overflow-y:auto;">
            <?php if (empty($recentLogs)): ?>
            <div class="p-4 text-center text-muted">No activity yet.</div>
            <?php else: foreach ($recentLogs as $log): ?>
            <div class="px-3 py-2 border-bottom" style="font-size:.85rem;">
              <div class="d-flex justify-content-between">
                <span class="fw-semibold"><?= htmlspecialchars($log['username'] ?? 'System') ?></span>
                <span class="text-muted small"><?= date('d M H:i', strtotime($log['created_at'])) ?></span>
              </div>
              <div class="text-muted"><?= htmlspecialchars($log['action']) ?></div>
            </div>
            <?php endforeach; endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../public/js/app.js"></script>
<script>var BASE_URL='..'; BuzzApp.initNotifications(<?= $user['id'] ?>);</script>
</body>
</html>
