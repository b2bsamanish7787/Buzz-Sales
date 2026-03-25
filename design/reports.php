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
$csrfToken = $auth->generateCSRFToken();

// ── Sanitise filter inputs ────────────────────────────────────────────────
$fSales      = (int)($_GET['sales_user']  ?? 0);           // 0 = ALL
$fStage      = trim($_GET['stage']        ?? '');          // pending|ongoing|completed|''
$fReview     = trim($_GET['review_action']?? '');          // approved|rejected|on_hold|''
$fSearch     = trim($_GET['search']       ?? '');
$fDateFrom   = trim($_GET['date_from']    ?? '');
$fDateTo     = trim($_GET['date_to']      ?? '');
$sortCol     = trim($_GET['sort']         ?? 'p.created_at');
$sortDir     = strtoupper(trim($_GET['dir'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
$page        = max(1, (int)($_GET['page'] ?? 1));
$perPage     = 25;
$isExport    = isset($_GET['export']) && $_GET['export'] === 'csv';

// Whitelist allowed sort columns
$allowedSorts = ['p.id','p.project_name','p.client_name','p.status','p.created_at','p.updated_at','sales_name','last_review_action'];
if (!in_array($sortCol, $allowedSorts, true)) {
    $sortCol = 'p.created_at';
}

// ── Stage → status mapping ─────────────────────────────────────────────────
$stageSQLMap = [
    'pending'   => "p.status = 'pending'",
    'ongoing'   => "p.status = 'ongoing'",
    'completed' => "p.status IN ('design_complete','ops_review','sales_review','completed','closed')",
];

// ── Build WHERE clause ─────────────────────────────────────────────────────
$where  = [];
$params = [];

// All design-related projects (exclude fully-sales statuses)
$where[] = "p.status NOT IN ('approved','rejected','change_requested')";

if ($fSales) {
    $where[]  = 'p.sales_user_id = ?';
    $params[] = $fSales;
}

if ($fStage !== '' && isset($stageSQLMap[$fStage])) {
    $where[] = $stageSQLMap[$fStage];
}

if ($fReview !== '' && in_array($fReview, ['approved','rejected','on_hold'], true)) {
    $where[]  = 'dr.action = ?';
    $params[] = $fReview;
} elseif ($fReview === 'none') {
    $where[] = 'dr.action IS NULL';
}

if ($fSearch !== '') {
    $where[]  = '(p.project_name LIKE ? OR p.client_name LIKE ?)';
    $like     = '%' . $fSearch . '%';
    $params[] = $like;
    $params[] = $like;
}

if ($fDateFrom !== '') {
    $where[]  = 'DATE(p.created_at) >= ?';
    $params[] = $fDateFrom;
}
if ($fDateTo !== '') {
    $where[]  = 'DATE(p.created_at) <= ?';
    $params[] = $fDateTo;
}

$whereSQL = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// ── Base query with latest design_review joined ────────────────────────────
$baseFrom = "
    FROM projects p
    LEFT JOIN users u ON p.sales_user_id = u.id
    LEFT JOIN (
        SELECT dr1.project_id, dr1.action, dr1.remarks, dr1.deadline_days,
               dr1.hold_reason, dr1.rejection_reason, dr1.reviewed_at
        FROM design_reviews dr1
        INNER JOIN (
            SELECT project_id, MAX(reviewed_at) AS max_rev
            FROM design_reviews GROUP BY project_id
        ) dr2 ON dr1.project_id = dr2.project_id AND dr1.reviewed_at = dr2.max_rev
    ) dr ON dr.project_id = p.id
    {$whereSQL}
";

// ── Count total rows ───────────────────────────────────────────────────────
$totalRow = $db->fetchOne("SELECT COUNT(*) AS cnt {$baseFrom}", $params);
$total    = (int)($totalRow['cnt'] ?? 0);
$pages    = max(1, (int)ceil($total / $perPage));
$page     = min($page, $pages);
$offset   = ($page - 1) * $perPage;

// ── SELECT columns ─────────────────────────────────────────────────────────
$selectCols = "
    p.id, p.project_name, p.client_name, p.status, p.created_at, p.updated_at,
    u.full_name AS sales_name,
    dr.action   AS last_review_action,
    dr.remarks  AS last_review_remarks,
    dr.reviewed_at AS last_reviewed_at
";

// ── Fetch rows (or export) ─────────────────────────────────────────────────
$orderSQL = "ORDER BY {$sortCol} {$sortDir}";

if ($isExport) {
    // Fetch ALL matching rows for export (no pagination)
    $rows = $db->fetchAll("SELECT {$selectCols} {$baseFrom} {$orderSQL}", $params);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="design-report-' . date('Ymd-His') . '.csv"');
    header('Pragma: no-cache');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
    fputcsv($out, ['#','Project Name','Client','Sales User','Stage/Status','Design Review Action','Review Remarks','Created','Last Updated']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'],
            $r['project_name'],
            $r['client_name'],
            $r['sales_name'] ?? '—',
            ucwords(str_replace('_', ' ', $r['status'])),
            $r['last_review_action'] ? ucwords(str_replace('_', ' ', $r['last_review_action'])) : '—',
            $r['last_review_remarks'] ?? '',
            $r['created_at'] ? date('d M Y', strtotime($r['created_at'])) : '',
            $r['updated_at'] ? date('d M Y', strtotime($r['updated_at'])) : '',
        ]);
    }
    fclose($out);
    exit;
}

$fetchParams   = array_merge($params, [$perPage, $offset]);
$rows          = $db->fetchAll("SELECT {$selectCols} {$baseFrom} {$orderSQL} LIMIT ? OFFSET ?", $fetchParams);

// ── Sales users list for filter dropdown ──────────────────────────────────
$salesUsers = $db->fetchAll("SELECT id, full_name FROM users WHERE role='sales' AND status='active' ORDER BY full_name ASC");

// ── Helper: build URL preserving all filters ─────────────────────────────
function reportUrl(array $overrides = []): string {
    $base = array_filter([
        'sales_user'    => $_GET['sales_user']   ?? '',
        'stage'         => $_GET['stage']         ?? '',
        'review_action' => $_GET['review_action'] ?? '',
        'search'        => $_GET['search']        ?? '',
        'date_from'     => $_GET['date_from']     ?? '',
        'date_to'       => $_GET['date_to']       ?? '',
        'sort'          => $_GET['sort']           ?? '',
        'dir'           => $_GET['dir']            ?? '',
        'page'          => $_GET['page']           ?? '',
    ]);
    $merged = array_merge($base, $overrides);
    $merged = array_filter($merged, fn($v) => $v !== '' && $v !== '0' && $v !== 0);
    return 'reports.php?' . http_build_query($merged);
}

function sortLink(string $col, string $currentCol, string $currentDir): string {
    $newDir = ($col === $currentCol && $currentDir === 'ASC') ? 'DESC' : 'ASC';
    $icon   = '';
    if ($col === $currentCol) {
        $icon = $currentDir === 'ASC' ? ' <i class="fa fa-sort-up"></i>' : ' <i class="fa fa-sort-down"></i>';
    } else {
        $icon = ' <i class="fa fa-sort text-muted"></i>';
    }
    $url = reportUrl(['sort' => $col, 'dir' => $newDir, 'page' => 1]);
    return "<a href=\"" . htmlspecialchars($url) . "\" class=\"text-decoration-none text-dark\">{$icon}</a>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Design Reports – <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link rel="stylesheet" href="../public/css/style.css">
<meta name="csrf-token" content="<?= $csrfToken ?>">
<style>
.filter-bar { background:#fff; border:1px solid #dee2e6; border-radius:8px; padding:1rem 1.25rem; margin-bottom:1.25rem; }
.filter-bar label { font-size:.8rem; font-weight:600; color:#555; margin-bottom:.2rem; }
th.sortable { white-space:nowrap; }
.badge-review-approved  { background:#198754; color:#fff; }
.badge-review-rejected  { background:#dc3545; color:#fff; }
.badge-review-on_hold   { background:#ffc107; color:#333; }
.badge-review-none      { background:#6c757d; color:#fff; }
.pagination .page-link  { color:var(--buzz-red); }
.pagination .page-item.active .page-link { background:var(--buzz-red); border-color:var(--buzz-red); color:#fff; }
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<div style="padding-top:56px; display:flex;">
  <div class="sidebar">
    <div class="sidebar-section">Design</div>
    <a href="dashboard.php" class="nav-link"><i class="fa fa-tachometer-alt"></i> Dashboard</a>
    <a href="reports.php" class="nav-link active"><i class="fa fa-chart-bar"></i> Reports</a>
  </div>
  <div class="main-content">

    <div class="page-header">
      <div>
        <h1><i class="fa fa-chart-bar text-buzz me-2"></i>Design Reports</h1>
        <small class="text-muted"><?= $total ?> project<?= $total !== 1 ? 's' : '' ?> found</small>
      </div>
      <div class="d-flex gap-2">
        <a href="<?= htmlspecialchars(reportUrl(['export' => 'csv', 'page' => ''])) ?>"
           class="btn btn-success btn-sm">
          <i class="fa fa-file-csv me-1"></i>Export CSV
        </a>
      </div>
    </div>

    <!-- ── Filter Bar ── -->
    <form method="GET" action="reports.php" class="filter-bar">
      <div class="row g-2 align-items-end">

        <div class="col-12 col-sm-6 col-md-3 col-xl-2">
          <label>Sales User</label>
          <select name="sales_user" class="form-select form-select-sm">
            <option value="">All Sales</option>
            <?php foreach ($salesUsers as $su): ?>
            <option value="<?= $su['id'] ?>" <?= $fSales == $su['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($su['full_name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-sm-6 col-md-2 col-xl-2">
          <label>Stage</label>
          <select name="stage" class="form-select form-select-sm">
            <option value="">All Stages</option>
            <option value="pending"   <?= $fStage === 'pending'   ? 'selected' : '' ?>>Pending</option>
            <option value="ongoing"   <?= $fStage === 'ongoing'   ? 'selected' : '' ?>>Ongoing</option>
            <option value="completed" <?= $fStage === 'completed' ? 'selected' : '' ?>>Completed</option>
          </select>
        </div>

        <div class="col-12 col-sm-6 col-md-2 col-xl-2">
          <label>Review Status</label>
          <select name="review_action" class="form-select form-select-sm">
            <option value="">All</option>
            <option value="approved"  <?= $fReview === 'approved'  ? 'selected' : '' ?>>Approved</option>
            <option value="rejected"  <?= $fReview === 'rejected'  ? 'selected' : '' ?>>Rejected</option>
            <option value="on_hold"   <?= $fReview === 'on_hold'   ? 'selected' : '' ?>>Put on Hold</option>
            <option value="none"      <?= $fReview === 'none'      ? 'selected' : '' ?>>Not Reviewed</option>
          </select>
        </div>

        <div class="col-12 col-sm-6 col-md-2 col-xl-2">
          <label>From Date</label>
          <input type="date" name="date_from" class="form-control form-control-sm"
                 value="<?= htmlspecialchars($fDateFrom) ?>">
        </div>

        <div class="col-12 col-sm-6 col-md-2 col-xl-2">
          <label>To Date</label>
          <input type="date" name="date_to" class="form-control form-control-sm"
                 value="<?= htmlspecialchars($fDateTo) ?>">
        </div>

        <div class="col-12 col-sm-6 col-md-3 col-xl-2">
          <label>Search</label>
          <input type="text" name="search" class="form-control form-control-sm"
                 placeholder="Project or Client…"
                 value="<?= htmlspecialchars($fSearch) ?>">
        </div>

        <!-- preserve sort -->
        <?php if ($sortCol !== 'p.created_at'): ?>
        <input type="hidden" name="sort" value="<?= htmlspecialchars($sortCol) ?>">
        <?php endif; ?>
        <?php if ($sortDir !== 'DESC'): ?>
        <input type="hidden" name="dir"  value="<?= htmlspecialchars($sortDir) ?>">
        <?php endif; ?>

        <div class="col-auto d-flex gap-2">
          <button type="submit" class="btn btn-buzz btn-sm"><i class="fa fa-search me-1"></i>Filter</button>
          <a href="reports.php" class="btn btn-outline-secondary btn-sm"><i class="fa fa-times me-1"></i>Reset</a>
        </div>
      </div>
    </form>

    <!-- ── Results Table ── -->
    <?php if (empty($rows)): ?>
    <div class="empty-state"><i class="fa fa-chart-bar"></i><p>No projects match the selected filters.</p></div>
    <?php else: ?>
    <div class="table-card">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th class="sortable">#<?= sortLink('p.id', $sortCol, $sortDir) ?></th>
              <th class="sortable">Project<?= sortLink('p.project_name', $sortCol, $sortDir) ?></th>
              <th class="sortable">Client<?= sortLink('p.client_name', $sortCol, $sortDir) ?></th>
              <th class="sortable">Sales User<?= sortLink('sales_name', $sortCol, $sortDir) ?></th>
              <th class="sortable">Stage<?= sortLink('p.status', $sortCol, $sortDir) ?></th>
              <th class="sortable">Review<?= sortLink('last_review_action', $sortCol, $sortDir) ?></th>
              <th class="sortable">Created<?= sortLink('p.created_at', $sortCol, $sortDir) ?></th>
              <th class="sortable">Updated<?= sortLink('p.updated_at', $sortCol, $sortDir) ?></th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
            <?php
              // Stage badge
              $stage = $r['status'];
              if (in_array($stage, ['design_complete','ops_review','sales_review','completed','closed'])) {
                  $stageBadge = '<span class="badge bg-success">Completed</span>';
              } elseif ($stage === 'ongoing') {
                  $stageBadge = '<span class="badge bg-info text-dark">Ongoing</span>';
              } elseif ($stage === 'pending') {
                  $stageBadge = '<span class="badge bg-warning text-dark">Pending</span>';
              } else {
                  $stageBadge = getStatusBadge($stage);
              }
              // Review badge
              $ra = $r['last_review_action'] ?? '';
              if ($ra === 'approved') {
                  $reviewBadge = '<span class="badge badge-review-approved">Approved</span>';
              } elseif ($ra === 'rejected') {
                  $reviewBadge = '<span class="badge badge-review-rejected">Rejected</span>';
              } elseif ($ra === 'on_hold') {
                  $reviewBadge = '<span class="badge badge-review-on_hold">On Hold</span>';
              } else {
                  $reviewBadge = '<span class="badge badge-review-none">—</span>';
              }
            ?>
            <tr>
              <td><?= $r['id'] ?></td>
              <td class="fw-semibold"><?= htmlspecialchars($r['project_name']) ?></td>
              <td><?= htmlspecialchars($r['client_name']) ?></td>
              <td><?= htmlspecialchars($r['sales_name'] ?? '—') ?></td>
              <td><?= $stageBadge ?></td>
              <td>
                <?= $reviewBadge ?>
                <?php if (!empty($r['last_review_remarks'])): ?>
                <i class="fa fa-info-circle text-muted ms-1"
                   title="<?= htmlspecialchars($r['last_review_remarks']) ?>"
                   data-bs-toggle="tooltip"></i>
                <?php endif; ?>
              </td>
              <td class="text-muted small"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
              <td class="text-muted small"><?= date('d M Y', strtotime($r['updated_at'])) ?></td>
              <td>
                <a href="review.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-secondary">
                  <i class="fa fa-eye me-1"></i>View
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── Pagination ── -->
    <?php if ($pages > 1): ?>
    <nav class="mt-3">
      <ul class="pagination pagination-sm justify-content-center flex-wrap">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
          <a class="page-link" href="<?= htmlspecialchars(reportUrl(['page' => $page - 1])) ?>">‹ Prev</a>
        </li>
        <?php
          $rangeStart = max(1, $page - 3);
          $rangeEnd   = min($pages, $page + 3);
          if ($rangeStart > 1): ?>
          <li class="page-item disabled"><span class="page-link">…</span></li>
        <?php endif;
          for ($pg = $rangeStart; $pg <= $rangeEnd; $pg++): ?>
          <li class="page-item <?= $pg === $page ? 'active' : '' ?>">
            <a class="page-link" href="<?= htmlspecialchars(reportUrl(['page' => $pg])) ?>"><?= $pg ?></a>
          </li>
        <?php endfor;
          if ($rangeEnd < $pages): ?>
          <li class="page-item disabled"><span class="page-link">…</span></li>
        <?php endif; ?>
        <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
          <a class="page-link" href="<?= htmlspecialchars(reportUrl(['page' => $page + 1])) ?>">Next ›</a>
        </li>
      </ul>
      <p class="text-center text-muted small">
        Showing <?= (($page - 1) * $perPage) + 1 ?>–<?= min($page * $perPage, $total) ?> of <?= $total ?> projects
      </p>
    </nav>
    <?php endif; ?>

    <?php endif; ?>

  </div><!-- /.main-content -->
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../public/js/app.js"></script>
<script>
var BASE_URL = '..';
BuzzApp.initNotifications(<?= $user['id'] ?>);
// Init Bootstrap tooltips
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
    new bootstrap.Tooltip(el);
});
</script>
</body>
</html>
