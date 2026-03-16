<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/Security.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Notifier.php';
require_once __DIR__ . '/../lib/Logger.php';
require_once __DIR__ . '/../lib/FileUpload.php';

Security::setSecurityHeaders();
$auth = new Auth();
$auth->requireAuth(ROLE_DESIGN);
$user      = $auth->getUser();
$db        = Database::getInstance();
$notifier  = new Notifier();
$logoutUrl = '../public/logout.php';
$csrfToken = $auth->generateCSRFToken();

$selectedProjectId = (int)($_GET['project_id'] ?? 0);
$ongoingProjects   = $db->fetchAll("SELECT * FROM projects WHERE status='ongoing' ORDER BY updated_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Upload Designs – <?= APP_NAME ?></title>
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
    <a href="upload-designs.php" class="nav-link active"><i class="fa fa-cloud-upload-alt"></i> Upload Designs</a>
  </div>
  <div class="main-content">
    <div class="page-header">
      <h1><i class="fa fa-cloud-upload-alt text-buzz me-2"></i>Upload Design Files</h1>
    </div>

    <div id="uploadAlert"></div>

    <div class="row justify-content-center">
      <div class="col-12 col-lg-8">
        <div class="form-section">
          <form id="uploadForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

            <div class="mb-4">
              <label class="form-label">Select Project <span class="text-danger">*</span></label>
              <?php if (empty($ongoingProjects)): ?>
              <div class="alert alert-warning"><i class="fa fa-info-circle me-2"></i>No ongoing projects to upload designs for.</div>
              <?php else: ?>
              <select class="form-select" id="projectSelect" required>
                <option value="">-- Select a project --</option>
                <?php foreach ($ongoingProjects as $p): ?>
                <option value="<?= $p['id'] ?>" <?= $selectedProjectId == $p['id'] ? 'selected' : '' ?>>
                  #<?= $p['id'] ?> – <?= htmlspecialchars($p['project_name']) ?> (<?= htmlspecialchars($p['client_name']) ?>)
                </option>
                <?php endforeach; ?>
              </select>
              <?php endif; ?>
            </div>

            <div class="mb-4">
              <label class="form-label">Design Files <span class="text-danger">*</span> <small class="text-muted">(at least 1 file required)</small></label>
              <div class="upload-zone" id="dropZone">
                <i class="fa fa-cloud-upload-alt d-block"></i>
                <p class="mb-1 fw-semibold">Drag &amp; Drop design files here</p>
                <p class="text-muted small mb-3">Supported: Images, PDF, AI, PSD, ZIP – Max 500MB each</p>
                <input type="file" id="fileInput" multiple accept="image/*,.pdf,.ai,.psd,.eps,.zip,.rar,.indd,.svg" style="display:none;">
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="$('#fileInput').click()">Browse Files</button>
              </div>
              <div class="file-list" id="fileList"></div>
              <div id="uploadProgress" style="display:none;" class="mt-2">
                <div class="progress-bar-wrap"><div class="progress-bar-fill" id="progressFill" style="width:0%"></div></div>
                <p class="text-muted small mt-1" id="progressText"></p>
              </div>
              <div id="fileError" class="text-danger small mt-2" style="display:none;">Please add at least one file.</div>
            </div>

            <div class="mb-4">
              <label class="form-label">Notes / Comments</label>
              <textarea class="form-control" id="designNotes" rows="3" placeholder="Any notes about the design files…"></textarea>
            </div>

            <div class="d-flex gap-3 justify-content-end">
              <a href="dashboard.php" class="btn btn-outline-secondary">Cancel</a>
              <button type="submit" class="btn btn-buzz px-5" id="uploadBtn" <?= empty($ongoingProjects) ? 'disabled' : '' ?>>
                <span class="spinner-border spinner-border-sm d-none me-2" id="uploadSpinner"></span>
                <i class="fa fa-cloud-upload-alt me-1"></i>Upload Designs
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

$('#uploadForm').on('submit', function(e) {
    e.preventDefault();
    var projectId = $('#projectSelect').val();
    if (!projectId) { BuzzApp.showToast('Please select a project.', 'warning'); return; }
    var files = uploader.getFiles();
    if (!files.length) { $('#fileError').show(); return; }
    $('#fileError').hide();

    var btn = $('#uploadBtn');
    btn.prop('disabled', true);
    $('#uploadSpinner').removeClass('d-none');
    $('#uploadProgress').show();

    BuzzApp.uploadFiles(files, parseInt(projectId), 'design',
        function(pct, name) {
            $('#progressFill').css('width', pct + '%');
            $('#progressText').text('Uploading: ' + name + ' (' + pct + '%)');
        },
        function(uploaded) {
            $('#uploadProgress').hide();
            if (!uploaded.length) {
                btn.prop('disabled', false);
                $('#uploadSpinner').addClass('d-none');
                BuzzApp.showToast('Upload failed. Please try again.', 'danger');
                return;
            }

            // Update project status to design_complete and notify operations
            BuzzApp.ajax('../api/design-action.php', {
                action: 'design_uploaded',
                project_id: projectId,
                notes: $('#designNotes').val(),
                csrf_token: BuzzApp.getCsrfToken()
            }, function(res) {
                btn.prop('disabled', false);
                $('#uploadSpinner').addClass('d-none');
                $('#uploadAlert').html('<div class="alert alert-success"><i class="fa fa-check-circle me-2"></i>Design files uploaded successfully! Notifying operations team.</div>');
                uploader.reset();
                setTimeout(() => window.location.href = 'dashboard.php', 2500);
            }, function() {
                // Still show success since files were uploaded
                btn.prop('disabled', false);
                $('#uploadSpinner').addClass('d-none');
                $('#uploadAlert').html('<div class="alert alert-success"><i class="fa fa-check-circle me-2"></i>' + uploaded.length + ' file(s) uploaded successfully.</div>');
                uploader.reset();
            });
        }
    );
});
</script>
</body>
</html>
