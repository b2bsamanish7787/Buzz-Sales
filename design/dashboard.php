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
$auth->requireAuth(ROLE_DESIGN);
$user      = $auth->getUser();
$db        = Database::getInstance();
$notifier  = new Notifier();
$logoutUrl = '../public/logout.php';
$csrfToken = $auth->generateCSRFToken();

// Get projects grouped by sales user
$pending  = $db->fetchAll("SELECT p.*, u.full_name as sales_name FROM projects p LEFT JOIN users u ON p.sales_user_id=u.id WHERE p.status='pending' ORDER BY p.created_at ASC");
$ongoing  = $db->fetchAll("SELECT p.*, u.full_name as sales_name, dr.deadline_days, dr.reviewed_at FROM projects p LEFT JOIN users u ON p.sales_user_id=u.id LEFT JOIN (SELECT project_id, deadline_days, reviewed_at FROM design_reviews WHERE action='approved' ORDER BY reviewed_at DESC LIMIT 1) dr ON dr.project_id=p.id WHERE p.status='ongoing' ORDER BY p.updated_at ASC");
$completed = $db->fetchAll("SELECT p.*, u.full_name as sales_name FROM projects p LEFT JOIN users u ON p.sales_user_id=u.id WHERE p.status IN ('design_complete','ops_review','sales_review','completed','closed') ORDER BY p.updated_at DESC LIMIT 20");

function groupBySales(array $projects): array {
    $grouped = [];
    foreach ($projects as $p) {
        $name = $p['sales_name'] ?? 'Unknown';
        $grouped[$name][] = $p;
    }
    return $grouped;
}

$pendingGrouped = groupBySales($pending);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Design Dashboard – <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link rel="stylesheet" href="../public/css/style.css">
<meta name="csrf-token" content="<?= $csrfToken ?>">
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<div style="padding-top:56px; display:flex;">
  <div class="sidebar">
    <div class="sidebar-section">Design</div>
    <a href="dashboard.php" class="nav-link active"><i class="fa fa-tachometer-alt"></i> Dashboard</a>
    <a href="upload-designs.php" class="nav-link"><i class="fa fa-cloud-upload-alt"></i> Upload Designs</a>
  </div>
  <div class="main-content">
    <div class="page-header">
      <div>
        <h1><i class="fa fa-paint-brush text-buzz me-2"></i>Design Dashboard</h1>
        <small class="text-muted">Welcome, <?= htmlspecialchars($user['full_name']) ?>!</small>
      </div>
      <div class="d-flex gap-2">
        <span class="badge bg-warning text-dark p-2"><?= count($pending) ?> Pending</span>
        <span class="badge bg-info text-dark p-2"><?= count($ongoing) ?> Ongoing</span>
      </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4" id="designTabs">
      <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabPending">
          <i class="fa fa-inbox me-1"></i>Pending Requests
          <span class="badge bg-warning text-dark ms-1"><?= count($pending) ?></span>
        </button>
      </li>
      <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabOngoing">
          <i class="fa fa-spinner me-1"></i>Ongoing
          <span class="badge bg-info text-dark ms-1"><?= count($ongoing) ?></span>
        </button>
      </li>
      <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabCompleted">
          <i class="fa fa-check-circle me-1"></i>Completed
        </button>
      </li>
    </ul>

    <div class="tab-content">

      <!-- PENDING TAB -->
      <div class="tab-pane fade show active" id="tabPending">
        <?php if (empty($pending)): ?>
        <div class="empty-state"><i class="fa fa-check-circle"></i><p>No pending requests. Great work!</p></div>
        <?php else: ?>

        <!-- Sales User Tabs -->
        <?php if (count($pendingGrouped) > 1): ?>
        <ul class="nav nav-pills mb-3" id="salesTabs">
          <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#salesAll">All (<?= count($pending) ?>)</button>
          </li>
          <?php foreach ($pendingGrouped as $salesName => $projs): ?>
          <li class="nav-item">
            <button class="nav-link" data-bs-toggle="pill"
              data-bs-target="#sales<?= preg_replace('/\W+/', '', $salesName) ?>">
              <?= htmlspecialchars($salesName) ?> (<?= count($projs) ?>)
            </button>
          </li>
          <?php endforeach; ?>
        </ul>

        <div class="tab-content">
          <div class="tab-pane fade show active" id="salesAll">
            <div class="row g-3">
              <?php foreach ($pending as $p): renderPendingCard($p); endforeach; ?>
            </div>
          </div>
          <?php foreach ($pendingGrouped as $salesName => $projs): ?>
          <div class="tab-pane fade" id="sales<?= preg_replace('/\W+/', '', $salesName) ?>">
            <div class="row g-3">
              <?php foreach ($projs as $p): renderPendingCard($p); endforeach; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="row g-3">
          <?php foreach ($pending as $p): renderPendingCard($p); endforeach; ?>
        </div>
        <?php endif; ?>

        <?php endif; ?>
      </div>

      <!-- ONGOING TAB -->
      <div class="tab-pane fade" id="tabOngoing">
        <?php if (empty($ongoing)): ?>
        <div class="empty-state"><i class="fa fa-spinner"></i><p>No ongoing projects.</p></div>
        <?php else: ?>
        <div class="row g-3">
          <?php foreach ($ongoing as $p):
            $daysLeft = null;
            if ($p['reviewed_at'] && $p['deadline_days']) {
                $deadlineDate = strtotime($p['reviewed_at']) + ($p['deadline_days'] * 86400);
                $daysLeft     = (int)ceil(($deadlineDate - time()) / 86400);
            }
          ?>
          <div class="col-12 col-md-6 col-xl-4">
            <div class="project-card h-100" style="border-left-color: #0dcaf0;">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <h6 class="project-title"><?= htmlspecialchars($p['project_name']) ?></h6>
                <?php if ($daysLeft !== null): ?>
                <span class="deadline-badge <?= $daysLeft <= 2 ? 'urgent' : ($daysLeft <= 5 ? 'warning' : 'ok') ?>">
                  <?= $daysLeft <= 0 ? 'OVERDUE' : $daysLeft . ' days left' ?>
                </span>
                <?php endif; ?>
              </div>
              <div class="project-meta mb-2">
                <i class="fa fa-building me-1"></i><?= htmlspecialchars($p['client_name']) ?>
                &nbsp;|&nbsp; <small class="text-muted">Sales: <?= htmlspecialchars($p['sales_name'] ?? '—') ?></small>
              </div>
              <div class="d-flex gap-2 mt-3">
                <a href="review.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-info flex-fill"><i class="fa fa-eye me-1"></i>View</a>
                <a href="upload-designs.php?project_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary flex-fill"><i class="fa fa-upload me-1"></i>Upload</a>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- COMPLETED TAB -->
      <div class="tab-pane fade" id="tabCompleted">
        <?php if (empty($completed)): ?>
        <div class="empty-state"><i class="fa fa-check-circle"></i><p>No completed projects yet.</p></div>
        <?php else: ?>
        <div class="table-card">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead><tr><th>#</th><th>Project</th><th>Client</th><th>Sales</th><th>Status</th><th>Date</th></tr></thead>
              <tbody>
                <?php foreach ($completed as $p): ?>
                <tr>
                  <td><?= $p['id'] ?></td>
                  <td class="fw-semibold"><?= htmlspecialchars($p['project_name']) ?></td>
                  <td><?= htmlspecialchars($p['client_name']) ?></td>
                  <td><?= htmlspecialchars($p['sales_name'] ?? '—') ?></td>
                  <td><?= getStatusBadge($p['status']) ?></td>
                  <td class="text-muted small"><?= date('d M Y', strtotime($p['updated_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php
function renderPendingCard(array $p): void {
    echo '<div class="col-12 col-md-6 col-xl-4">';
    echo '<div class="project-card h-100">';
    echo '<div class="d-flex justify-content-between align-items-start mb-2">';
    echo '<h6 class="project-title">' . htmlspecialchars($p['project_name']) . '</h6>';
    echo getStatusBadge($p['status']);
    echo '</div>';
    echo '<div class="project-meta mb-1"><i class="fa fa-building me-1"></i>' . htmlspecialchars($p['client_name']) . '</div>';
    echo '<div class="project-meta mb-3"><i class="fa fa-user me-1"></i>Sales: ' . htmlspecialchars($p['sales_name'] ?? '—') . '&nbsp;|&nbsp;<i class="fa fa-calendar me-1"></i>' . date('d M Y', strtotime($p['created_at'])) . '</div>';
    echo '<a href="review.php?id=' . $p['id'] . '" class="btn btn-buzz btn-sm w-100"><i class="fa fa-tasks me-1"></i>Review Project</a>';
    echo '</div></div>';
}
?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../public/js/app.js"></script>
<script>var BASE_URL='..'; BuzzApp.initNotifications(<?= $user['id'] ?>);</script>
</body>
</html>
