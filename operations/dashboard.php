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
$auth->requireAuth(ROLE_OPERATIONS);
$user      = $auth->getUser();
$db        = Database::getInstance();
$notifier  = new Notifier();
$logoutUrl = '../public/logout.php';
$csrfToken = $auth->generateCSRFToken();

$pending  = $db->fetchAll("SELECT p.*, u.full_name as sales_name FROM projects p LEFT JOIN users u ON p.sales_user_id=u.id WHERE p.status='ops_review' ORDER BY p.updated_at ASC");
$reviewed = $db->fetchAll("SELECT p.*, u.full_name as sales_name FROM projects p LEFT JOIN users u ON p.sales_user_id=u.id WHERE p.status IN ('sales_review','completed','closed') ORDER BY p.updated_at DESC LIMIT 10");
$pendingCount = count($pending);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Operations Dashboard – <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link rel="stylesheet" href="../public/css/style.css">
<meta name="csrf-token" content="<?= $csrfToken ?>">
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<div style="padding-top:56px; display:flex;">
  <div class="sidebar">
    <div class="sidebar-section">Operations</div>
    <a href="dashboard.php" class="nav-link active"><i class="fa fa-tachometer-alt"></i> Dashboard</a>
    <a href="review.php" class="nav-link"><i class="fa fa-search-dollar"></i> Review &amp; Cost</a>
  </div>
  <div class="main-content">
    <div class="page-header">
      <div>
        <h1><i class="fa fa-cogs text-buzz me-2"></i>Operations Dashboard</h1>
        <small class="text-muted">Welcome, <?= htmlspecialchars($user['full_name']) ?>!</small>
      </div>
      <span class="badge bg-warning text-dark p-2 fs-6"><?= $pendingCount ?> Pending Reviews</span>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-lg-4">
        <div class="stat-card orange">
          <i class="fa fa-clock stat-icon"></i>
          <h3><?= $pendingCount ?></h3>
          <p>Pending Cost Review</p>
        </div>
      </div>
      <div class="col-6 col-lg-4">
        <div class="stat-card blue">
          <i class="fa fa-check-circle stat-icon"></i>
          <h3><?= count($reviewed) ?></h3>
          <p>Recently Reviewed</p>
        </div>
      </div>
    </div>

    <!-- Pending Reviews -->
    <div class="mb-4">
      <h5 class="fw-bold mb-3"><i class="fa fa-inbox me-2 text-buzz"></i>Pending Cost Reviews</h5>
      <?php if (empty($pending)): ?>
      <div class="empty-state"><i class="fa fa-check-circle"></i><p>All caught up! No pending reviews.</p></div>
      <?php else: ?>
      <div class="row g-3">
        <?php foreach ($pending as $p): ?>
        <div class="col-12 col-md-6 col-xl-4">
          <div class="project-card h-100" style="border-left-color: #f4a261;">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <h6 class="project-title"><?= htmlspecialchars($p['project_name']) ?></h6>
              <?= getStatusBadge($p['status']) ?>
            </div>
            <div class="project-meta mb-1"><i class="fa fa-building me-1"></i><?= htmlspecialchars($p['client_name']) ?></div>
            <div class="project-meta mb-3">
              <i class="fa fa-user me-1"></i>Sales: <?= htmlspecialchars($p['sales_name'] ?? '—') ?>
              &nbsp;|&nbsp; <i class="fa fa-calendar me-1"></i><?= date('d M Y', strtotime($p['updated_at'])) ?>
            </div>
            <a href="review.php?id=<?= $p['id'] ?>" class="btn btn-buzz btn-sm w-100">
              <i class="fa fa-search-dollar me-1"></i>Review &amp; Submit Cost
            </a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Recently Reviewed -->
    <?php if ($reviewed): ?>
    <div>
      <h5 class="fw-bold mb-3"><i class="fa fa-history me-2 text-buzz"></i>Recently Reviewed</h5>
      <div class="table-card">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>Project</th><th>Client</th><th>Sales</th><th>Status</th><th>Date</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($reviewed as $p): ?>
              <tr>
                <td><?= $p['id'] ?></td>
                <td class="fw-semibold"><?= htmlspecialchars($p['project_name']) ?></td>
                <td><?= htmlspecialchars($p['client_name']) ?></td>
                <td><?= htmlspecialchars($p['sales_name'] ?? '—') ?></td>
                <td><?= getStatusBadge($p['status']) ?></td>
                <td class="text-muted small"><?= date('d M Y', strtotime($p['updated_at'])) ?></td>
                <td><a href="review.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="fa fa-eye"></i></a></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../public/js/app.js"></script>
<script>var BASE_URL='..'; BuzzApp.initNotifications(<?= $user['id'] ?>);</script>
</body>
</html>
