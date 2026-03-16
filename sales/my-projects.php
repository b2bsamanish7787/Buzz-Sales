<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/Security.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Notifier.php';
require_once __DIR__ . '/../lib/FileUpload.php';

Security::setSecurityHeaders();
$auth = new Auth();
$auth->requireAuth(ROLE_SALES);
$user      = $auth->getUser();
$db        = Database::getInstance();
$notifier  = new Notifier();
$logoutUrl = '../public/logout.php';
$csrfToken = $auth->generateCSRFToken();

// Single project view
$viewId  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$project = null;
$details = null;
$files   = [];
$designs = [];
$costs   = [];
$changes = [];

if ($viewId) {
    $project = $db->fetchOne("SELECT * FROM projects WHERE id=? AND sales_user_id=?", [$viewId, $user['id']]);
    if ($project) {
        $details = $db->fetchOne("SELECT * FROM project_details WHERE project_id=?", [$viewId]);
        $uploader = new FileUpload();
        $files   = $uploader->getProjectFiles($viewId, 'requirement');
        $designs = $uploader->getProjectFiles($viewId, 'design');
        $costs   = $db->fetchAll("SELECT ct.*, u.full_name FROM cost_tracking ct LEFT JOIN users u ON ct.reviewed_by=u.id WHERE ct.project_id=? ORDER BY ct.created_at DESC", [$viewId]);
        $changes = $db->fetchAll("SELECT * FROM change_requests WHERE project_id=? ORDER BY created_at DESC", [$viewId]);
        $reviews = $db->fetchAll("SELECT * FROM design_reviews WHERE project_id=? ORDER BY reviewed_at DESC", [$viewId]);
    }
}

$statusFilter = Security::sanitizeInput($_GET['status'] ?? '');
$projects = $db->fetchAll(
    "SELECT * FROM projects WHERE sales_user_id=? " . ($statusFilter ? "AND status=?" : "") . " ORDER BY updated_at DESC",
    $statusFilter ? [$user['id'], $statusFilter] : [$user['id']]
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Projects – <?= APP_NAME ?></title>
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
    <a href="dashboard.php" class="nav-link"><i class="fa fa-tachometer-alt"></i> Dashboard</a>
    <a href="requirement-form.php" class="nav-link"><i class="fa fa-plus-circle"></i> New Requirement</a>
    <a href="my-projects.php" class="nav-link active"><i class="fa fa-folder-open"></i> My Projects</a>
    <a href="change-request.php" class="nav-link"><i class="fa fa-exchange-alt"></i> Change Request</a>
  </div>
  <div class="main-content">

    <?php if ($project): // --- DETAIL VIEW --- ?>
    <div class="page-header">
      <div>
        <h1><?= htmlspecialchars($project['project_name']) ?></h1>
        <small class="text-muted">Project #<?= $project['id'] ?> &nbsp;|&nbsp; <?= getStatusBadge($project['status']) ?></small>
      </div>
      <a href="my-projects.php" class="btn btn-outline-secondary"><i class="fa fa-arrow-left me-1"></i>Back</a>
    </div>

    <div class="row g-3">
      <div class="col-12 col-lg-8">
        <!-- Client Details -->
        <?php if ($details): ?>
        <div class="form-section mb-3">
          <div class="form-section-title"><i class="fa fa-building text-buzz"></i> Client &amp; Event Details</div>
          <div class="row g-2" style="font-size:.9rem;">
            <div class="col-6"><strong>Client:</strong> <?= htmlspecialchars($project['client_name']) ?></div>
            <div class="col-6"><strong>Contact:</strong> <?= htmlspecialchars($details['contact_person'] ?? '—') ?></div>
            <div class="col-6"><strong>Email:</strong> <?= htmlspecialchars($details['contact_email'] ?? '—') ?></div>
            <div class="col-6"><strong>Phone:</strong> <?= htmlspecialchars($details['contact_phone'] ?? '—') ?></div>
            <div class="col-6"><strong>Event:</strong> <?= htmlspecialchars($details['event_name'] ?? '—') ?></div>
            <div class="col-6"><strong>Date:</strong> <?= htmlspecialchars($details['event_date'] ?? '—') ?></div>
            <div class="col-6"><strong>Venue:</strong> <?= htmlspecialchars($details['venue'] ?? '—') ?></div>
            <div class="col-6"><strong>City/Country:</strong> <?= htmlspecialchars(($details['city'] ?? '') . ', ' . ($details['country'] ?? '')) ?></div>
            <div class="col-6"><strong>Booth Size:</strong> <?= htmlspecialchars($details['booth_size'] ?? '—') ?></div>
            <div class="col-6"><strong>Budget:</strong> <?= htmlspecialchars($details['budget'] ?? '—') ?></div>
            <div class="col-6"><strong>Stand Type:</strong> <?= htmlspecialchars($details['stand_type'] ?? '—') ?></div>
            <div class="col-6"><strong>Type:</strong> <?= htmlspecialchars($details['requirement_type'] ?? '—') ?></div>
            <?php if ($details['design_style']): ?>
            <div class="col-12"><strong>Design Style:</strong> <?= htmlspecialchars($details['design_style']) ?></div>
            <?php endif; ?>
            <?php if ($details['colors']): ?>
            <div class="col-12"><strong>Colors:</strong> <?= htmlspecialchars($details['colors']) ?></div>
            <?php endif; ?>
            <?php if ($details['products_to_display']): ?>
            <div class="col-12"><strong>Products:</strong><br><span class="text-muted"><?= nl2br(htmlspecialchars($details['products_to_display'])) ?></span></div>
            <?php endif; ?>
            <?php if ($details['special_requirements']): ?>
            <div class="col-12"><strong>Special Requirements:</strong><br><span class="text-muted"><?= nl2br(htmlspecialchars($details['special_requirements'])) ?></span></div>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Reference Files -->
        <?php if ($files): ?>
        <div class="form-section mb-3">
          <div class="form-section-title"><i class="fa fa-paperclip text-buzz"></i> Reference Files</div>
          <?php $fu = new FileUpload(); foreach ($files as $f): ?>
          <div class="file-item">
            <span class="badge bg-secondary"><?= strtoupper(pathinfo($f['original_name'], PATHINFO_EXTENSION)) ?></span>
            <span class="file-name"><?= htmlspecialchars($f['original_name']) ?></span>
            <span class="file-size"><?= $fu->formatFileSize($f['file_size'] ?? 0) ?></span>
            <a href="../api/file-upload.php?action=download&id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-primary" title="Download"><i class="fa fa-download"></i></a>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Design Files -->
        <?php if ($designs): ?>
        <div class="form-section mb-3">
          <div class="form-section-title"><i class="fa fa-paint-brush text-buzz"></i> Design Files</div>
          <?php $fu = new FileUpload(); foreach ($designs as $f): ?>
          <div class="file-item">
            <span class="badge bg-primary"><?= strtoupper(pathinfo($f['original_name'], PATHINFO_EXTENSION)) ?></span>
            <span class="file-name"><?= htmlspecialchars($f['original_name']) ?></span>
            <span class="file-size"><?= $fu->formatFileSize($f['file_size'] ?? 0) ?></span>
            <a href="../api/file-upload.php?action=download&id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fa fa-download"></i></a>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Cost Tracking -->
        <?php if ($costs): ?>
        <div class="form-section mb-3">
          <div class="form-section-title"><i class="fa fa-dollar-sign text-buzz"></i> Cost Estimates</div>
          <table class="table table-sm">
            <thead><tr><th>Cost (USD)</th><th>Remarks</th><th>By</th><th>Date</th></tr></thead>
            <tbody>
              <?php foreach ($costs as $c): ?>
              <tr>
                <td class="fw-bold">$<?= number_format($c['cost_usd'], 2) ?></td>
                <td><?= htmlspecialchars($c['remarks'] ?? '—') ?></td>
                <td><?= htmlspecialchars($c['full_name'] ?? 'Operations') ?></td>
                <td class="small text-muted"><?= date('d M Y', strtotime($c['created_at'])) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>

      <!-- Right Panel -->
      <div class="col-12 col-lg-4">
        <div class="form-section">
          <div class="form-section-title"><i class="fa fa-info-circle text-buzz"></i> Project Info</div>
          <div class="mb-2"><strong>Status:</strong> <?= getStatusBadge($project['status']) ?></div>
          <div class="mb-2"><strong>Stage:</strong> <?= ucfirst(htmlspecialchars($project['current_stage'])) ?></div>
          <div class="mb-2"><strong>Submitted:</strong> <?= date('d M Y', strtotime($project['created_at'])) ?></div>
          <div class="mb-3"><strong>Updated:</strong> <?= date('d M Y H:i', strtotime($project['updated_at'])) ?></div>
          <div class="d-grid gap-2">
            <?php if (!in_array($project['status'], ['completed','closed'])): ?>
            <a href="change-request.php?project_id=<?= $project['id'] ?>" class="btn btn-outline-warning btn-sm">
              <i class="fa fa-exchange-alt me-1"></i>Submit Change Request
            </a>
            <?php endif; ?>
            <?php if (!in_array($project['status'], ['closed'])): ?>
            <button class="btn btn-outline-danger btn-sm" onclick="closeProject(<?= $project['id'] ?>)">
              <i class="fa fa-times-circle me-1"></i>Close Project
            </button>
            <?php endif; ?>
          </div>
        </div>

        <!-- Change Requests -->
        <?php if ($changes): ?>
        <div class="form-section mt-3">
          <div class="form-section-title"><i class="fa fa-exchange-alt text-buzz"></i> Change Requests</div>
          <?php foreach ($changes as $cr): ?>
          <div class="mb-2 p-2 bg-light rounded">
            <div class="small fw-semibold"><?= date('d M Y', strtotime($cr['created_at'])) ?> – <?= getStatusBadge($cr['status']) ?></div>
            <div class="small mt-1"><?= nl2br(htmlspecialchars($cr['description'])) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <?php else: // --- LIST VIEW --- ?>
    <div class="page-header">
      <h1><i class="fa fa-folder-open text-buzz me-2"></i>My Projects</h1>
      <a href="requirement-form.php" class="btn btn-buzz"><i class="fa fa-plus me-1"></i>New</a>
    </div>

    <!-- Filter Bar -->
    <div class="form-section mb-4 py-2">
      <div class="d-flex gap-2 flex-wrap align-items-center">
        <label class="fw-semibold me-2 mb-0">Filter:</label>
        <?php $statuses = ['','pending','approved','rejected','on_hold','ongoing','design_complete','ops_review','sales_review','completed','closed']; ?>
        <?php foreach ($statuses as $s): ?>
        <a href="?status=<?= $s ?>" class="btn btn-sm <?= $statusFilter === $s ? 'btn-buzz' : 'btn-outline-secondary' ?>">
          <?= $s ? ucwords(str_replace('_',' ',$s)) : 'All' ?>
        </a>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if (empty($projects)): ?>
    <div class="empty-state">
      <i class="fa fa-folder-open"></i>
      <p>No projects found. <a href="requirement-form.php">Submit your first requirement</a>.</p>
    </div>
    <?php else: ?>
    <div class="row g-3">
      <?php foreach ($projects as $p): ?>
      <div class="col-12 col-md-6 col-xl-4">
        <div class="project-card h-100">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <h6 class="project-title mb-0"><?= htmlspecialchars($p['project_name']) ?></h6>
            <?= getStatusBadge($p['status']) ?>
          </div>
          <div class="project-meta mb-2">
            <i class="fa fa-building me-1"></i><?= htmlspecialchars($p['client_name']) ?>
            &nbsp;|&nbsp; <i class="fa fa-calendar me-1"></i><?= date('d M Y', strtotime($p['created_at'])) ?>
          </div>
          <div class="d-flex gap-2 mt-3">
            <a href="?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary flex-fill"><i class="fa fa-eye me-1"></i>View</a>
            <?php if (!in_array($p['status'], ['completed','closed'])): ?>
            <a href="change-request.php?project_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-warning flex-fill"><i class="fa fa-exchange-alt me-1"></i>Change</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../public/js/app.js"></script>
<script>
var BASE_URL = '..';
BuzzApp.initNotifications(<?= $user['id'] ?>);

function closeProject(id) {
    BuzzApp.confirm('Close this project? This action cannot be undone.', function() {
        BuzzApp.ajax('../api/form-submit.php', {action:'close_project', project_id:id, csrf_token:BuzzApp.getCsrfToken()}, function(res) {
            BuzzApp.showToast(res.message, res.success ? 'success' : 'danger');
            if (res.success) setTimeout(() => location.reload(), 1200);
        });
    });
}
</script>
</body>
</html>
