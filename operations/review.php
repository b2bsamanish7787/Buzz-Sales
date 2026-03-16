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
$auth->requireAuth(ROLE_OPERATIONS);
$user      = $auth->getUser();
$db        = Database::getInstance();
$notifier  = new Notifier();
$logoutUrl = '../public/logout.php';
$csrfToken = $auth->generateCSRFToken();

$projectId = (int)($_GET['id'] ?? 0);
$projects  = $db->fetchAll("SELECT p.*, u.full_name as sales_name FROM projects p LEFT JOIN users u ON p.sales_user_id=u.id WHERE p.status IN ('ops_review','sales_review','completed') ORDER BY p.updated_at DESC");

$project = $details = $refFiles = $designFiles = $costs = $reviews = null;
$uploader = new FileUpload();

if ($projectId) {
    $project    = $db->fetchOne("SELECT p.*, u.full_name as sales_name FROM projects p LEFT JOIN users u ON p.sales_user_id=u.id WHERE p.id=?", [$projectId]);
    if ($project) {
        $details     = $db->fetchOne("SELECT * FROM project_details WHERE project_id=?", [$projectId]);
        $refFiles    = $uploader->getProjectFiles($projectId, 'requirement');
        $designFiles = $uploader->getProjectFiles($projectId, 'design');
        $costs       = $db->fetchAll("SELECT ct.*, u.full_name FROM cost_tracking ct LEFT JOIN users u ON ct.reviewed_by=u.id WHERE ct.project_id=? ORDER BY ct.created_at DESC", [$projectId]);
        $reviews     = $db->fetchAll("SELECT * FROM design_reviews WHERE project_id=? ORDER BY reviewed_at DESC", [$projectId]);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Operations Review – <?= APP_NAME ?></title>
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
    <a href="dashboard.php" class="nav-link"><i class="fa fa-tachometer-alt"></i> Dashboard</a>
    <a href="review.php" class="nav-link active"><i class="fa fa-search-dollar"></i> Review &amp; Cost</a>
    <div class="sidebar-section">Projects</div>
    <?php foreach ($projects as $p): ?>
    <a href="review.php?id=<?= $p['id'] ?>" class="nav-link <?= $projectId == $p['id'] ? 'active' : '' ?>" style="font-size:.82rem;padding:.5rem 1.5rem;">
      <i class="fa fa-folder me-1"></i><?= htmlspecialchars(substr($p['project_name'], 0, 22)) ?><?= strlen($p['project_name']) > 22 ? '…' : '' ?>
      <?= getStatusBadge($p['status']) ?>
    </a>
    <?php endforeach; ?>
  </div>

  <div class="main-content">
    <?php if (!$project): ?>
    <div class="page-header">
      <h1><i class="fa fa-search-dollar text-buzz me-2"></i>Review &amp; Cost Submission</h1>
    </div>
    <?php if (empty($projects)): ?>
    <div class="empty-state"><i class="fa fa-inbox"></i><p>No projects awaiting operations review.</p></div>
    <?php else: ?>
    <div class="alert alert-info"><i class="fa fa-info-circle me-2"></i>Select a project from the sidebar to review and submit costs.</div>
    <?php endif; ?>

    <?php else: ?>
    <div class="page-header">
      <div>
        <h1><?= htmlspecialchars($project['project_name']) ?></h1>
        <small class="text-muted">Client: <?= htmlspecialchars($project['client_name']) ?> &nbsp;|&nbsp; <?= getStatusBadge($project['status']) ?></small>
      </div>
      <a href="dashboard.php" class="btn btn-outline-secondary"><i class="fa fa-arrow-left me-1"></i>Dashboard</a>
    </div>

    <div id="costAlert"></div>

    <div class="row g-3">
      <!-- Left: Full Details -->
      <div class="col-12 col-lg-8">
        <!-- Requirements -->
        <?php if ($details): ?>
        <div class="form-section mb-3">
          <div class="form-section-title"><i class="fa fa-building text-buzz"></i> Client Requirements</div>
          <div class="row g-2" style="font-size:.9rem;">
            <div class="col-6"><strong>Client:</strong> <?= htmlspecialchars($project['client_name']) ?></div>
            <div class="col-6"><strong>Sales Rep:</strong> <?= htmlspecialchars($project['sales_name'] ?? '—') ?></div>
            <div class="col-6"><strong>Contact:</strong> <?= htmlspecialchars($details['contact_person'] ?? '—') ?></div>
            <div class="col-6"><strong>Type:</strong> <?= htmlspecialchars($details['requirement_type'] ?? '—') ?></div>
            <div class="col-6"><strong>Event:</strong> <?= htmlspecialchars($details['event_name'] ?? '—') ?></div>
            <div class="col-6"><strong>Date:</strong> <?= htmlspecialchars($details['event_date'] ?? '—') ?></div>
            <div class="col-6"><strong>Venue:</strong> <?= htmlspecialchars($details['venue'] ?? '—') ?></div>
            <div class="col-6"><strong>City/Country:</strong> <?= htmlspecialchars(($details['city'] ?? '') . ', ' . ($details['country'] ?? '')) ?></div>
            <div class="col-6"><strong>Booth Size:</strong> <?= htmlspecialchars($details['booth_size'] ?? '—') ?></div>
            <div class="col-6"><strong>Budget:</strong> <?= htmlspecialchars($details['budget'] ?? '—') ?></div>
            <div class="col-6"><strong>Stand Type:</strong> <?= htmlspecialchars($details['stand_type'] ?? '—') ?></div>
            <div class="col-6"><strong>Style:</strong> <?= htmlspecialchars($details['design_style'] ?? '—') ?></div>
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
        <?php if ($refFiles): ?>
        <div class="form-section mb-3">
          <div class="form-section-title"><i class="fa fa-paperclip text-buzz"></i> Reference Files</div>
          <?php foreach ($refFiles as $f): ?>
          <div class="file-item">
            <span class="badge bg-secondary"><?= strtoupper(pathinfo($f['original_name'], PATHINFO_EXTENSION)) ?></span>
            <span class="file-name"><?= htmlspecialchars($f['original_name']) ?></span>
            <span class="file-size"><?= $uploader->formatFileSize($f['file_size'] ?? 0) ?></span>
            <a href="../api/file-upload.php?action=download&id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fa fa-download"></i></a>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Design Files -->
        <?php if ($designFiles): ?>
        <div class="form-section mb-3">
          <div class="form-section-title"><i class="fa fa-paint-brush text-buzz"></i> Design Files</div>
          <?php foreach ($designFiles as $f): ?>
          <div class="file-item">
            <span class="badge bg-primary"><?= strtoupper(pathinfo($f['original_name'], PATHINFO_EXTENSION)) ?></span>
            <span class="file-name"><?= htmlspecialchars($f['original_name']) ?></span>
            <span class="file-size"><?= $uploader->formatFileSize($f['file_size'] ?? 0) ?></span>
            <span class="text-muted small"><?= date('d M Y', strtotime($f['created_at'])) ?></span>
            <a href="../api/file-upload.php?action=download&id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fa fa-download"></i></a>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Design Review History -->
        <?php if ($reviews): ?>
        <div class="form-section mb-3">
          <div class="form-section-title"><i class="fa fa-history text-buzz"></i> Design Review History</div>
          <?php foreach ($reviews as $r): ?>
          <div class="mb-2 p-2 rounded bg-light d-flex flex-wrap gap-3" style="font-size:.85rem;">
            <span><?= getStatusBadge($r['action']) ?></span>
            <?php if ($r['deadline_days']): ?><span><strong>Deadline:</strong> <?= $r['deadline_days'] ?> days</span><?php endif; ?>
            <?php if ($r['remarks']): ?><span><strong>Remarks:</strong> <?= htmlspecialchars($r['remarks']) ?></span><?php endif; ?>
            <span class="ms-auto text-muted"><?= date('d M Y', strtotime($r['reviewed_at'])) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Cost History -->
        <?php if ($costs): ?>
        <div class="form-section mb-3">
          <div class="form-section-title"><i class="fa fa-dollar-sign text-buzz"></i> Cost History</div>
          <table class="table table-sm">
            <thead><tr><th>Cost (USD)</th><th>Remarks</th><th>By</th><th>Date</th></tr></thead>
            <tbody>
              <?php foreach ($costs as $c): ?>
              <tr>
                <td class="fw-bold text-success">$<?= number_format($c['cost_usd'], 2) ?></td>
                <td><?= htmlspecialchars($c['remarks'] ?? '—') ?></td>
                <td><?= htmlspecialchars($c['full_name'] ?? '—') ?></td>
                <td class="text-muted small"><?= date('d M Y H:i', strtotime($c['created_at'])) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>

      <!-- Right: Cost Form -->
      <div class="col-12 col-lg-4">
        <?php if ($project['status'] === 'ops_review'): ?>
        <div class="form-section sticky-top" style="top:70px;">
          <div class="form-section-title"><i class="fa fa-dollar-sign text-buzz"></i> Submit Cost Estimation</div>
          <form id="costForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="project_id" value="<?= $projectId ?>">
            <div class="mb-3">
              <label class="form-label">Cost (USD) <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" class="form-control" name="cost_usd" step="0.01" min="0.01" placeholder="0.00" required>
              </div>
              <div class="invalid-feedback">Please enter a valid cost.</div>
            </div>
            <div class="mb-3">
              <label class="form-label">Remarks</label>
              <textarea class="form-control" name="remarks" rows="3" placeholder="Cost breakdown, notes, assumptions…"></textarea>
            </div>
            <button type="submit" class="btn btn-buzz w-100" id="costBtn">
              <span class="spinner-border spinner-border-sm d-none me-2" id="costSpinner"></span>
              <i class="fa fa-paper-plane me-1"></i>Submit Cost &amp; Notify Sales
            </button>
          </form>
        </div>
        <?php else: ?>
        <div class="form-section">
          <div class="form-section-title"><i class="fa fa-info-circle text-buzz"></i> Status</div>
          <p><?= getStatusBadge($project['status']) ?></p>
          <p class="text-muted small">Cost has been submitted and the project is now with the sales team for review.</p>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../public/js/app.js"></script>
<script>
var BASE_URL = '..';
BuzzApp.initNotifications(<?= $user['id'] ?>);

$('#costForm').on('submit', function(e) {
    e.preventDefault();
    if (!this.checkValidity()) { $(this).addClass('was-validated'); return; }
    var btn = $('#costBtn');
    btn.prop('disabled', true);
    $('#costSpinner').removeClass('d-none');

    BuzzApp.ajax('../api/cost-submit.php', $(this).serialize(), function(res) {
        btn.prop('disabled', false);
        $('#costSpinner').addClass('d-none');
        if (res.success) {
            $('#costAlert').html('<div class="alert alert-success"><i class="fa fa-check-circle me-2"></i>' + res.message + '</div>');
            setTimeout(() => window.location.href = 'dashboard.php', 2500);
        } else {
            $('#costAlert').html('<div class="alert alert-danger">' + (res.message || 'Error.') + '</div>');
            $('html').animate({scrollTop:0}, 400);
        }
    });
});
</script>
</body>
</html>
