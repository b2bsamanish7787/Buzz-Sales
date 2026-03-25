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

// Fetch all three status buckets (with sales_uid for grouping)
$pending   = $db->fetchAll("SELECT p.*, u.full_name as sales_name, u.id as sales_uid FROM projects p LEFT JOIN users u ON p.sales_user_id=u.id WHERE p.status IN ('pending','change_requested') ORDER BY p.created_at ASC");
$ongoing   = $db->fetchAll("
    SELECT p.*, u.full_name as sales_name, u.id as sales_uid, dr.deadline_days, dr.reviewed_at
    FROM projects p
    LEFT JOIN users u ON p.sales_user_id = u.id
    LEFT JOIN (
        SELECT d1.project_id, d1.deadline_days, d1.reviewed_at
        FROM design_reviews d1
        INNER JOIN (
            SELECT project_id, MAX(reviewed_at) AS max_rev
            FROM design_reviews WHERE action='approved' GROUP BY project_id
        ) d2 ON d1.project_id = d2.project_id AND d1.reviewed_at = d2.max_rev
        WHERE d1.action = 'approved'
    ) dr ON dr.project_id = p.id
    WHERE p.status = 'ongoing'
    ORDER BY p.updated_at ASC
");
$completed = $db->fetchAll("SELECT p.*, u.full_name as sales_name, u.id as sales_uid FROM projects p LEFT JOIN users u ON p.sales_user_id=u.id WHERE p.status IN ('design_complete','ops_review','sales_review','completed','closed') ORDER BY p.updated_at DESC LIMIT 50");

// Build ordered list of sales users who appear in any bucket
$salesUsers = [];
foreach (array_merge($pending, $ongoing, $completed) as $p) {
    $uid = (int)($p['sales_uid'] ?? 0);
    if ($uid && !isset($salesUsers[$uid])) {
        $salesUsers[$uid] = $p['sales_name'] ?? 'Unknown';
    }
}
asort($salesUsers);

// Filter helpers
function filterBySales(array $projects, int $uid): array {
    return array_values(array_filter($projects, fn($p) => (int)($p['sales_uid'] ?? 0) === $uid));
}
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
<style>
/* ── Sales-user outer tabs ── */
.sales-tabs .nav-link          { font-weight:600; color:#555; border-radius:8px 8px 0 0; padding:.55rem 1.1rem; }
.sales-tabs .nav-link.active   { background:var(--buzz-red); color:#fff; border-color:var(--buzz-red); }
.sales-tabs .nav-link:hover:not(.active) { background:#f8d7d5; color:var(--buzz-red); }

/* ── Status inner tabs ── */
.status-tabs .nav-link         { font-size:.87rem; color:#555; padding:.38rem .9rem; border-radius:20px; border:1px solid transparent; }
.status-tabs .nav-link.active  { background:#fff; border-color:#dee2e6; font-weight:700; color:#222; }
.status-tabs .nav-link:hover:not(.active) { background:#f0f0f0; }
.status-tabs .badge            { font-size:.75rem; }
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<div style="padding-top:56px; display:flex;">
  <div class="sidebar">
    <div class="sidebar-section">Design</div>
    <a href="dashboard.php" class="nav-link active"><i class="fa fa-tachometer-alt"></i> Dashboard</a>
    <a href="reports.php" class="nav-link"><i class="fa fa-chart-bar"></i> Reports</a>
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
        <span class="badge bg-success p-2"><?= count($completed) ?> Completed</span>
      </div>
    </div>

    <?php
    // Build tab definitions: [id_suffix, label, pending[], ongoing[], completed[]]
    $tabDefs = [];

    // ALL tab
    $tabDefs[] = [
        'id'        => 'all',
        'label'     => 'ALL',
        'pending'   => $pending,
        'ongoing'   => $ongoing,
        'completed' => $completed,
    ];

    // One tab per sales user
    foreach ($salesUsers as $uid => $name) {
        $tabDefs[] = [
            'id'        => 'u' . $uid,
            'label'     => $name,
            'pending'   => filterBySales($pending,   $uid),
            'ongoing'   => filterBySales($ongoing,   $uid),
            'completed' => filterBySales($completed, $uid),
        ];
    }
    ?>

    <!-- ══ OUTER TABS: Sales Users ══ -->
    <ul class="nav nav-tabs sales-tabs mb-0" id="salesUserTabs" role="tablist">
      <?php foreach ($tabDefs as $i => $tab): ?>
      <li class="nav-item" role="presentation">
        <button class="nav-link <?= $i === 0 ? 'active' : '' ?>"
                id="stab-<?= $tab['id'] ?>-btn"
                data-bs-toggle="tab"
                data-bs-target="#stab-<?= $tab['id'] ?>"
                type="button" role="tab">
          <?= htmlspecialchars($tab['label']) ?>
          <span class="badge bg-secondary ms-1">
            <?= count($tab['pending']) + count($tab['ongoing']) + count($tab['completed']) ?>
          </span>
        </button>
      </li>
      <?php endforeach; ?>
    </ul>

    <div class="tab-content border border-top-0 rounded-bottom bg-white p-3 mb-4" id="salesUserTabContent">
      <?php foreach ($tabDefs as $i => $tab):
        $tid = $tab['id'];
      ?>
      <div class="tab-pane fade <?= $i === 0 ? 'show active' : '' ?>"
           id="stab-<?= $tid ?>"
           role="tabpanel">

        <!-- ── INNER STATUS TABS ── -->
        <ul class="nav nav-pills status-tabs mb-3 mt-1" id="status-nav-<?= $tid ?>" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active"
                    data-bs-toggle="pill"
                    data-bs-target="#<?= $tid ?>-pending"
                    type="button">
              <i class="fa fa-inbox me-1"></i>Pending
              <span class="badge bg-warning text-dark ms-1"><?= count($tab['pending']) ?></span>
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link"
                    data-bs-toggle="pill"
                    data-bs-target="#<?= $tid ?>-ongoing"
                    type="button">
              <i class="fa fa-spinner me-1"></i>Ongoing
              <span class="badge bg-info text-dark ms-1"><?= count($tab['ongoing']) ?></span>
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link"
                    data-bs-toggle="pill"
                    data-bs-target="#<?= $tid ?>-completed"
                    type="button">
              <i class="fa fa-check-circle me-1"></i>Completed
              <span class="badge bg-success ms-1"><?= count($tab['completed']) ?></span>
            </button>
          </li>
        </ul>

        <div class="tab-content" id="status-content-<?= $tid ?>">

          <!-- Pending -->
          <div class="tab-pane fade show active" id="<?= $tid ?>-pending" role="tabpanel">
            <?php if (empty($tab['pending'])): ?>
            <div class="empty-state"><i class="fa fa-check-circle"></i><p>No pending requests.</p></div>
            <?php else: ?>
            <div class="row g-3">
              <?php foreach ($tab['pending'] as $p): renderPendingCard($p); endforeach; ?>
            </div>
            <?php endif; ?>
          </div>

          <!-- Ongoing -->
          <div class="tab-pane fade" id="<?= $tid ?>-ongoing" role="tabpanel">
            <?php if (empty($tab['ongoing'])): ?>
            <div class="empty-state"><i class="fa fa-spinner"></i><p>No ongoing projects.</p></div>
            <?php else: ?>
            <div class="row g-3">
              <?php foreach ($tab['ongoing'] as $p):
                $daysLeft = null;
                if (!empty($p['reviewed_at']) && !empty($p['deadline_days'])) {
                    $deadlineTs = strtotime($p['reviewed_at']) + ((int)$p['deadline_days'] * 86400);
                    $daysLeft   = (int)ceil(($deadlineTs - time()) / 86400);
                }
              ?>
              <div class="col-12 col-md-6 col-xl-4">
                <div class="project-card h-100" style="border-left-color:#0dcaf0;">
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
                    &nbsp;|&nbsp;<small class="text-muted">Sales: <?= htmlspecialchars($p['sales_name'] ?? '—') ?></small>
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

          <!-- Completed -->
          <div class="tab-pane fade" id="<?= $tid ?>-completed" role="tabpanel">
            <?php if (empty($tab['completed'])): ?>
            <div class="empty-state"><i class="fa fa-check-circle"></i><p>No completed projects yet.</p></div>
            <?php else: ?>
            <div class="table-card">
              <div class="table-responsive">
                <table class="table table-hover mb-0">
                  <thead><tr><th>#</th><th>Project</th><th>Client</th><th>Sales</th><th>Status</th><th>Date</th></tr></thead>
                  <tbody>
                    <?php foreach ($tab['completed'] as $p): ?>
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

        </div><!-- /.status tab-content -->
      </div><!-- /.outer tab-pane -->
      <?php endforeach; ?>
    </div><!-- /.outer tab-content -->

  </div><!-- /.main-content -->
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
