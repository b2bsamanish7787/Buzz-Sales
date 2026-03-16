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
$auth->requireAuth(ROLE_DESIGN);
$user      = $auth->getUser();
$db        = Database::getInstance();
$notifier  = new Notifier();
$logoutUrl = '../public/logout.php';
$csrfToken = $auth->generateCSRFToken();

$projectId = (int)($_GET['id'] ?? 0);
if (!$projectId) {
    header('Location: dashboard.php');
    exit;
}

$project = $db->fetchOne("SELECT p.*, u.full_name as sales_name, u.email as sales_email FROM projects p LEFT JOIN users u ON p.sales_user_id=u.id WHERE p.id=?", [$projectId]);
if (!$project) {
    header('Location: dashboard.php');
    exit;
}

$details = $db->fetchOne("SELECT * FROM project_details WHERE project_id=?", [$projectId]);
$uploader = new FileUpload();
$refFiles = $uploader->getProjectFiles($projectId, 'requirement');
$prevReviews = $db->fetchAll("SELECT * FROM design_reviews WHERE project_id=? ORDER BY reviewed_at DESC", [$projectId]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Review Project – <?= APP_NAME ?></title>
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
    <a href="dashboard.php" class="nav-link"><i class="fa fa-tachometer-alt"></i> Dashboard</a>
    <a href="upload-designs.php" class="nav-link"><i class="fa fa-cloud-upload-alt"></i> Upload Designs</a>
  </div>
  <div class="main-content">
    <div class="page-header">
      <div>
        <h1><?= htmlspecialchars($project['project_name']) ?></h1>
        <small class="text-muted">Project #<?= $project['id'] ?> &nbsp;|&nbsp; <?= getStatusBadge($project['status']) ?></small>
      </div>
      <a href="dashboard.php" class="btn btn-outline-secondary"><i class="fa fa-arrow-left me-1"></i>Back</a>
    </div>

    <div id="actionAlert"></div>

    <div class="row g-3">
      <!-- Project Details -->
      <div class="col-12 col-lg-7">
        <div class="form-section">
          <div class="form-section-title"><i class="fa fa-building text-buzz"></i> Client &amp; Project Details</div>
          <div class="row g-2" style="font-size:.9rem;">
            <div class="col-6"><strong>Client:</strong> <?= htmlspecialchars($project['client_name']) ?></div>
            <div class="col-6"><strong>Sales Rep:</strong> <?= htmlspecialchars($project['sales_name'] ?? '—') ?></div>
            <?php if ($details): ?>
            <div class="col-6"><strong>Contact:</strong> <?= htmlspecialchars($details['contact_person'] ?? '—') ?></div>
            <div class="col-6"><strong>Email:</strong> <?= htmlspecialchars($details['contact_email'] ?? '—') ?></div>
            <div class="col-6"><strong>Phone:</strong> <?= htmlspecialchars($details['contact_phone'] ?? '—') ?></div>
            <div class="col-6"><strong>Type:</strong> <?= htmlspecialchars($details['requirement_type'] ?? '—') ?></div>
            <div class="col-6"><strong>Event:</strong> <?= htmlspecialchars($details['event_name'] ?? '—') ?></div>
            <div class="col-6"><strong>Date:</strong> <?= htmlspecialchars($details['event_date'] ?? '—') ?></div>
            <div class="col-6"><strong>Venue:</strong> <?= htmlspecialchars($details['venue'] ?? '—') ?></div>
            <div class="col-6"><strong>City:</strong> <?= htmlspecialchars(($details['city'] ?? '') . ', ' . ($details['country'] ?? '')) ?></div>
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
            <?php if ($details['additional_notes']): ?>
            <div class="col-12"><strong>Notes:</strong><br><span class="text-muted"><?= nl2br(htmlspecialchars($details['additional_notes'])) ?></span></div>
            <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- Reference Files -->
        <?php if ($refFiles): ?>
        <div class="form-section mt-3">
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

        <!-- Previous Reviews -->
        <?php if ($prevReviews): ?>
        <div class="form-section mt-3">
          <div class="form-section-title"><i class="fa fa-history text-buzz"></i> Review History</div>
          <?php foreach ($prevReviews as $r): ?>
          <div class="mb-2 p-2 rounded bg-light">
            <div class="d-flex justify-content-between">
              <span><?= getStatusBadge($r['action']) ?></span>
              <small class="text-muted"><?= date('d M Y H:i', strtotime($r['reviewed_at'])) ?></small>
            </div>
            <?php if ($r['remarks']): ?><div class="small mt-1"><strong>Remarks:</strong> <?= htmlspecialchars($r['remarks']) ?></div><?php endif; ?>
            <?php if ($r['rejection_reason']): ?><div class="small"><strong>Reason:</strong> <?= htmlspecialchars($r['rejection_reason']) ?></div><?php endif; ?>
            <?php if ($r['hold_reason']): ?><div class="small"><strong>Hold Reason:</strong> <?= htmlspecialchars($r['hold_reason']) ?></div><?php endif; ?>
            <?php if ($r['deadline_days']): ?><div class="small"><strong>Deadline:</strong> <?= $r['deadline_days'] ?> days</div><?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Action Panel -->
      <?php if (in_array($project['status'], ['pending', 'change_requested'])): ?>
      <div class="col-12 col-lg-5">
        <div class="form-section sticky-top" style="top:70px;">
          <div class="form-section-title"><i class="fa fa-tasks text-buzz"></i> Review Action</div>

          <div class="mb-3">
            <label class="form-label fw-bold">Action</label>
            <div class="d-grid gap-2">
              <div class="form-check form-check-inline border rounded p-3 cursor-pointer" onclick="setAction('approved')" id="btnApprove">
                <input class="form-check-input" type="radio" name="actionChoice" value="approved" id="actApprove">
                <label class="form-check-label fw-semibold text-success cursor-pointer" for="actApprove">
                  <i class="fa fa-check-circle me-2"></i>Approve &amp; Assign Deadline
                </label>
              </div>
              <div class="form-check form-check-inline border rounded p-3 cursor-pointer" onclick="setAction('rejected')" id="btnReject">
                <input class="form-check-input" type="radio" name="actionChoice" value="rejected" id="actReject">
                <label class="form-check-label fw-semibold text-danger cursor-pointer" for="actReject">
                  <i class="fa fa-times-circle me-2"></i>Reject
                </label>
              </div>
              <div class="form-check form-check-inline border rounded p-3 cursor-pointer" onclick="setAction('on_hold')" id="btnHold">
                <input class="form-check-input" type="radio" name="actionChoice" value="on_hold" id="actHold">
                <label class="form-check-label fw-semibold text-secondary cursor-pointer" for="actHold">
                  <i class="fa fa-pause-circle me-2"></i>Put on Hold
                </label>
              </div>
            </div>
          </div>

          <form id="reviewForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="project_id" value="<?= $projectId ?>">
            <input type="hidden" name="action" id="actionField" value="">

            <div id="approveFields" style="display:none;">
              <div class="mb-3">
                <label class="form-label">Deadline (days) <span class="text-danger">*</span></label>
                <input type="number" class="form-control" name="deadline_days" min="1" placeholder="e.g. 7">
              </div>
            </div>

            <div id="rejectFields" style="display:none;">
              <div class="mb-3">
                <label class="form-label">Rejection Reason <span class="text-danger">*</span></label>
                <textarea class="form-control" name="rejection_reason" rows="3" placeholder="Explain why this is being rejected…"></textarea>
              </div>
            </div>

            <div id="holdFields" style="display:none;">
              <div class="mb-3">
                <label class="form-label">Hold Reason <span class="text-danger">*</span></label>
                <textarea class="form-control" name="hold_reason" rows="3" placeholder="Reason for putting on hold…"></textarea>
              </div>
              <div class="mb-3">
                <label class="form-label">Hold Duration (days)</label>
                <input type="number" class="form-control" name="hold_duration" min="1" placeholder="Optional">
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">Remarks / Comments</label>
              <textarea class="form-control" name="remarks" rows="2" placeholder="Optional notes…"></textarea>
            </div>

            <button type="submit" class="btn btn-buzz w-100" id="reviewBtn" disabled>
              <span class="spinner-border spinner-border-sm d-none me-2" id="reviewSpinner"></span>
              <i class="fa fa-save me-1"></i>Submit Review
            </button>
          </form>
        </div>
      </div>
      <?php else: ?>
      <div class="col-12 col-lg-5">
        <div class="form-section">
          <div class="form-section-title"><i class="fa fa-info-circle text-buzz"></i> Status</div>
          <p><?= getStatusBadge($project['status']) ?></p>
          <?php if ($project['status'] === 'ongoing'): ?>
          <a href="upload-designs.php?project_id=<?= $projectId ?>" class="btn btn-buzz w-100">
            <i class="fa fa-upload me-1"></i>Upload Design Files
          </a>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../public/js/app.js"></script>
<script>
var BASE_URL = '..';
BuzzApp.initNotifications(<?= $user['id'] ?>);

function setAction(action) {
    $('input[name="actionChoice"]').prop('checked', false);
    $('input[value="' + action + '"]').prop('checked', true);
    $('#actionField').val(action);
    $('#approveFields,#rejectFields,#holdFields').hide();
    if (action === 'approved') $('#approveFields').show();
    else if (action === 'rejected') $('#rejectFields').show();
    else if (action === 'on_hold') $('#holdFields').show();
    $('#reviewBtn').prop('disabled', false);
    $('.form-check.border').removeClass('border-danger border-success border-secondary');
    var colorMap = {approved:'border-success', rejected:'border-danger', on_hold:'border-secondary'};
    $('#btn' + action.charAt(0).toUpperCase() + action.slice(1).replace('_h','H')).addClass(colorMap[action]);
}

$('#reviewForm').on('submit', function(e) {
    e.preventDefault();
    var action = $('#actionField').val();
    if (!action) { BuzzApp.showToast('Please select an action.', 'warning'); return; }

    var btn = $('#reviewBtn');
    btn.prop('disabled', true);
    $('#reviewSpinner').removeClass('d-none');

    BuzzApp.ajax('../api/design-action.php', $(this).serialize(), function(res) {
        btn.prop('disabled', false);
        $('#reviewSpinner').addClass('d-none');
        if (res.success) {
            $('#actionAlert').html('<div class="alert alert-success"><i class="fa fa-check-circle me-2"></i>' + res.message + '</div>');
            setTimeout(() => window.location.href = 'dashboard.php', 2000);
        } else {
            $('#actionAlert').html('<div class="alert alert-danger">' + (res.message || 'Error.') + '</div>');
            $('html').animate({scrollTop:0}, 400);
        }
    });
});
</script>
</body>
</html>
