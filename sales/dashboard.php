<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/Security.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Notifier.php';

Security::setSecurityHeaders();
$auth = new Auth();
$auth->requireAuth(ROLE_SALES);
$user      = $auth->getUser();
$db        = Database::getInstance();
$notifier  = new Notifier();
$logoutUrl = '../public/logout.php';
$csrfToken = $auth->generateCSRFToken();

$myProjects   = $db->fetchOne("SELECT COUNT(*) as c FROM projects WHERE sales_user_id=?", [$user['id']])['c'] ?? 0;
$pendingCount = $db->fetchOne("SELECT COUNT(*) as c FROM projects WHERE sales_user_id=? AND status='pending'", [$user['id']])['c'] ?? 0;
$ongoingCount = $db->fetchOne("SELECT COUNT(*) as c FROM projects WHERE sales_user_id=? AND status='ongoing'", [$user['id']])['c'] ?? 0;
$completedCount = $db->fetchOne("SELECT COUNT(*) as c FROM projects WHERE sales_user_id=? AND status='completed'", [$user['id']])['c'] ?? 0;
$recentProjects = $db->fetchAll("SELECT * FROM projects WHERE sales_user_id=? ORDER BY updated_at DESC LIMIT 8", [$user['id']]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sales Dashboard – <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link rel="stylesheet" href="../public/css/style.css">
<meta name="csrf-token" content="<?= $csrfToken ?>">
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<div style="padding-top:56px; display:flex;">
  <div class="sidebar">
    <div class="sidebar-section">Sales</div>
    <a href="dashboard.php" class="nav-link active"><i class="fa fa-tachometer-alt"></i> Dashboard</a>
    <a href="requirement-form.php" class="nav-link"><i class="fa fa-plus-circle"></i> New Requirement</a>
    <a href="my-projects.php" class="nav-link"><i class="fa fa-folder-open"></i> My Projects</a>
    <a href="change-request.php" class="nav-link"><i class="fa fa-exchange-alt"></i> Change Request</a>
  </div>
  <div class="main-content">
    <div class="page-header">
      <div>
        <h1><i class="fa fa-briefcase text-buzz me-2"></i>Sales Dashboard</h1>
        <small class="text-muted">Welcome back, <?= htmlspecialchars($user['full_name']) ?>!</small>
      </div>
      <a href="requirement-form.php" class="btn btn-buzz">
        <i class="fa fa-plus me-1"></i>New Requirement
      </a>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-6 col-lg-3">
        <div class="stat-card blue">
          <i class="fa fa-folder stat-icon"></i>
          <h3><?= $myProjects ?></h3>
          <p>My Projects</p>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card orange">
          <i class="fa fa-clock stat-icon"></i>
          <h3><?= $pendingCount ?></h3>
          <p>Pending</p>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card teal">
          <i class="fa fa-spinner stat-icon"></i>
          <h3><?= $ongoingCount ?></h3>
          <p>Ongoing</p>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card green">
          <i class="fa fa-check-circle stat-icon"></i>
          <h3><?= $completedCount ?></h3>
          <p>Completed</p>
        </div>
      </div>
    </div>

    <div class="table-card">
      <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
        <h6 class="mb-0 fw-bold"><i class="fa fa-list me-2 text-buzz"></i>Recent Projects</h6>
        <a href="my-projects.php" class="btn btn-sm btn-outline-secondary">View All</a>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr><th>#</th><th>Project Name</th><th>Client</th><th>Status</th><th>Last Update</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php if (empty($recentProjects)): ?>
            <tr><td colspan="6" class="text-center py-5">
              <div class="empty-state">
                <i class="fa fa-folder-open d-block"></i>
                No projects yet. <a href="requirement-form.php">Submit your first requirement</a>.
              </div>
            </td></tr>
            <?php else: foreach ($recentProjects as $p): ?>
            <tr>
              <td class="text-muted">#<?= $p['id'] ?></td>
              <td class="fw-semibold"><?= htmlspecialchars($p['project_name']) ?></td>
              <td><?= htmlspecialchars($p['client_name']) ?></td>
              <td><?= getStatusBadge($p['status']) ?></td>
              <td class="text-muted small"><?= date('d M Y H:i', strtotime($p['updated_at'])) ?></td>
              <td>
                <a href="my-projects.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary">
                  <i class="fa fa-eye"></i>
                </a>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
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
