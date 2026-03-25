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

$selectedProjectId = (int)($_GET['project_id'] ?? 0);
$myProjects = $db->fetchAll(
    "SELECT * FROM projects WHERE sales_user_id=? AND status NOT IN ('completed','closed') ORDER BY updated_at DESC",
    [$user['id']]
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Change Request – <?= APP_NAME ?></title>
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
    <a href="my-projects.php" class="nav-link"><i class="fa fa-folder-open"></i> My Projects</a>
    <a href="change-request.php" class="nav-link active"><i class="fa fa-exchange-alt"></i> Change Request</a>
  </div>
  <div class="main-content">
    <div class="page-header">
      <h1><i class="fa fa-exchange-alt text-buzz me-2"></i>Submit Change Request</h1>
    </div>

    <div id="formAlert"></div>

    <div class="row justify-content-center">
      <div class="col-12 col-lg-8">
        <div class="form-section">
          <form id="changeRequestForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="change_request">

            <div class="mb-4">
              <label class="form-label">Select Project <span class="text-danger">*</span></label>
              <?php if (empty($myProjects)): ?>
              <div class="alert alert-warning"><i class="fa fa-exclamation-triangle me-2"></i>You have no active projects. <a href="requirement-form.php">Submit a new requirement</a>.</div>
              <?php else: ?>
              <select class="form-select" name="project_id" required>
                <option value="">-- Select a project --</option>
                <?php foreach ($myProjects as $p): ?>
                <option value="<?= $p['id'] ?>" <?= $selectedProjectId == $p['id'] ? 'selected' : '' ?>>
                  #<?= $p['id'] ?> – <?= htmlspecialchars($p['project_name']) ?> (<?= htmlspecialchars($p['client_name']) ?>) [<?= ucwords(str_replace('_',' ',$p['status'])) ?>]
                </option>
                <?php endforeach; ?>
              </select>
              <div class="invalid-feedback">Please select a project.</div>
              <?php endif; ?>
            </div>

            <div class="mb-4">
              <label class="form-label">Change Description <span class="text-danger">*</span></label>
              <textarea class="form-control" name="description" rows="5" required
                placeholder="Describe the change you are requesting in detail…"></textarea>
              <div class="invalid-feedback">Please describe the change request.</div>
            </div>

            <div class="mb-4">
              <label class="form-label">Additional Notes</label>
              <textarea class="form-control" name="notes" rows="3"
                placeholder="Any additional context, references, or notes…"></textarea>
            </div>

            <!-- Optional File Upload -->
            <div class="mb-4">
              <label class="form-label">Attach Reference Files (optional)</label>
              <div class="upload-zone" id="dropZone">
                <i class="fa fa-paperclip d-block"></i>
                <p class="mb-1">Drag &amp; drop files or click to browse</p>
                <input type="file" id="fileInput" multiple style="display:none;">
              </div>
              <div class="file-list" id="fileList"></div>
            </div>

            <div class="d-flex gap-3 justify-content-end">
              <a href="my-projects.php" class="btn btn-outline-secondary">Cancel</a>
              <button type="submit" class="btn btn-buzz" id="submitBtn" <?= empty($myProjects) ? 'disabled' : '' ?>>
                <span class="spinner-border spinner-border-sm d-none me-2" id="submitSpinner"></span>
                <i class="fa fa-paper-plane me-1"></i>Submit Change Request
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../public/js/app.js"></script>
<script>
var BASE_URL = '..';
BuzzApp.initNotifications(<?= $user['id'] ?>);
var uploader = BuzzApp.initFileUpload('#dropZone', '#fileInput', '#fileList', 500);

$('#changeRequestForm').on('submit', function(e) {
    e.preventDefault();
    if (!this.checkValidity()) { $(this).addClass('was-validated'); return; }

    var btn = $('#submitBtn');
    btn.prop('disabled', true);
    $('#submitSpinner').removeClass('d-none');

    var projectId = $('select[name="project_id"]').val();
    var files = uploader.getFiles();

    function doSubmit(fileIds) {
        var data = $(e.target).serialize();
        if (fileIds.length) data += '&file_ids=' + fileIds.join(',');
        BuzzApp.ajax('../api/form-submit.php', data, function(res) {
            btn.prop('disabled', false);
            $('#submitSpinner').addClass('d-none');
            if (res.success) {
                $('#formAlert').html('<div class="alert alert-success"><i class="fa fa-check-circle me-2"></i>' + res.message + '</div>');
                $('#changeRequestForm')[0].reset();
                uploader.reset();
                setTimeout(() => window.location.href = 'my-projects.php', 2000);
            } else {
                $('#formAlert').html('<div class="alert alert-danger">' + (res.message || 'Submission failed.') + '</div>');
                $('html').animate({scrollTop:0}, 400);
            }
        });
    }

    if (files.length && projectId) {
        BuzzApp.uploadFiles(files, parseInt(projectId), 'change_request', null, function(uploaded) {
            doSubmit(uploaded.map(function(f){ return f.file_id; }));
        });
    } else {
        doSubmit([]);
    }
});
</script>
</body>
</html>
