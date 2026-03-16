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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>New Requirement – <?= APP_NAME ?></title>
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
    <a href="requirement-form.php" class="nav-link active"><i class="fa fa-plus-circle"></i> New Requirement</a>
    <a href="my-projects.php" class="nav-link"><i class="fa fa-folder-open"></i> My Projects</a>
    <a href="change-request.php" class="nav-link"><i class="fa fa-exchange-alt"></i> Change Request</a>
  </div>

  <div class="main-content">
    <div class="page-header">
      <div>
        <h1><i class="fa fa-plus-circle text-buzz me-2"></i>Project Brief Form</h1>
        <small class="text-muted">Submit a new client requirement</small>
      </div>
    </div>

    <div id="formAlert"></div>

    <form id="requirementForm" novalidate>
      <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
      <input type="hidden" name="action" value="submit_requirement">

      <!-- Section 1: Requirement Type -->
      <div class="form-section">
        <div class="form-section-title"><i class="fa fa-tags text-buzz"></i> Requirement Type</div>
        <div class="d-flex flex-wrap gap-4">
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="checkbox" name="requirement_type[]" id="typeExhibition" value="Exhibition">
            <label class="form-check-label fw-semibold" for="typeExhibition">
              <i class="fa fa-store me-1 text-buzz"></i>Exhibition Stand
            </label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="checkbox" name="requirement_type[]" id="typeEvent" value="Event">
            <label class="form-check-label fw-semibold" for="typeEvent">
              <i class="fa fa-calendar-alt me-1 text-buzz"></i>Event
            </label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="checkbox" name="requirement_type[]" id="typeBranding" value="Branding">
            <label class="form-check-label fw-semibold" for="typeBranding">
              <i class="fa fa-bullseye me-1 text-buzz"></i>Branding
            </label>
          </div>
        </div>
        <div id="reqTypeError" class="text-danger small mt-2" style="display:none;">Please select at least one requirement type.</div>
      </div>

      <!-- Section 2: Client Details -->
      <div class="form-section">
        <div class="form-section-title"><i class="fa fa-building text-buzz"></i> Client Details</div>
        <div class="row g-3">
          <div class="col-12 col-md-6">
            <label class="form-label">Client/Company Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="client_name" required placeholder="e.g. ABC Corporation">
            <div class="invalid-feedback">Client name is required.</div>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Project Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="project_name" required placeholder="e.g. Dubai Expo 2024 Stand">
            <div class="invalid-feedback">Project name is required.</div>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Contact Person <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="contact_person" required placeholder="Full name">
            <div class="invalid-feedback">Contact person is required.</div>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Email Address</label>
            <input type="email" class="form-control" name="contact_email" placeholder="client@company.com">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Phone Number</label>
            <input type="tel" class="form-control" name="contact_phone" placeholder="+971 XX XXX XXXX">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Country</label>
            <input type="text" class="form-control" name="client_country" placeholder="e.g. UAE">
          </div>
        </div>
      </div>

      <!-- Section 3: Event/Show Information -->
      <div class="form-section">
        <div class="form-section-title"><i class="fa fa-calendar-alt text-buzz"></i> Event / Show Information</div>
        <div class="row g-3">
          <div class="col-12 col-md-6">
            <label class="form-label">Event/Show Name</label>
            <input type="text" class="form-control" name="event_name" placeholder="e.g. GITEX Global 2024">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Event Date</label>
            <input type="date" class="form-control" name="event_date">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Venue Name</label>
            <input type="text" class="form-control" name="venue" placeholder="e.g. Dubai World Trade Centre">
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label">City</label>
            <input type="text" class="form-control" name="city" placeholder="e.g. Dubai">
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label">Country</label>
            <input type="text" class="form-control" name="country" placeholder="e.g. UAE">
          </div>
        </div>
      </div>

      <!-- Section 4: Booth Specifications -->
      <div class="form-section">
        <div class="form-section-title"><i class="fa fa-ruler-combined text-buzz"></i> Booth Specifications</div>
        <div class="row g-3">
          <div class="col-12 col-md-4">
            <label class="form-label">Booth Size / Area</label>
            <input type="text" class="form-control" name="booth_size" placeholder="e.g. 10x10 meters">
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label">Budget</label>
            <input type="text" class="form-control" name="budget" placeholder="e.g. $15,000 USD">
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label">Stand Type</label>
            <div class="mt-2">
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="stand_type" id="stCustom" value="Custom">
                <label class="form-check-label" for="stCustom">Custom</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="stand_type" id="stModular" value="Modular">
                <label class="form-check-label" for="stModular">Modular</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="stand_type" id="stShell" value="Shell Scheme">
                <label class="form-check-label" for="stShell">Shell Scheme</label>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Section 5: Design Requirements -->
      <div class="form-section">
        <div class="form-section-title"><i class="fa fa-paint-brush text-buzz"></i> Design Requirements</div>
        <div class="row g-3">
          <div class="col-12 col-md-6">
            <label class="form-label">Design Style / Theme</label>
            <input type="text" class="form-control" name="design_style" placeholder="e.g. Modern Minimalist, Bold & Colorful">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Preferred Colors</label>
            <input type="text" class="form-control" name="colors" placeholder="e.g. Blue, White, Silver">
          </div>
          <div class="col-12">
            <label class="form-label">Products / Services to Display</label>
            <textarea class="form-control" name="products_to_display" rows="3" placeholder="List the main products or services to be showcased…"></textarea>
          </div>
          <div class="col-12">
            <label class="form-label">Special Requirements</label>
            <textarea class="form-control" name="special_requirements" rows="3" placeholder="Any special requests, accessibility needs, technical requirements…"></textarea>
          </div>
          <div class="col-12">
            <label class="form-label">Additional Notes</label>
            <textarea class="form-control" name="additional_notes" rows="2" placeholder="Anything else we should know…"></textarea>
          </div>
        </div>
      </div>

      <!-- Section 6: File Uploads -->
      <div class="form-section">
        <div class="form-section-title"><i class="fa fa-cloud-upload-alt text-buzz"></i> Reference Files</div>
        <p class="text-muted small mb-3">Upload reference images, branding guidelines, floor plans, or any other relevant files. Max 500MB per file.</p>
        <div class="upload-zone" id="dropZone">
          <i class="fa fa-cloud-upload-alt d-block"></i>
          <p class="mb-1 fw-semibold">Drag &amp; Drop files here</p>
          <p class="text-muted small mb-3">or click to browse</p>
          <input type="file" id="fileInput" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.ai,.psd" style="display:none;">
          <button type="button" class="btn btn-outline-secondary btn-sm" id="browseBtn">Browse Files</button>
        </div>
        <div class="file-list" id="fileList"></div>
        <div id="uploadProgress" style="display:none;">
          <div class="progress-bar-wrap mt-2"><div class="progress-bar-fill" id="progressFill" style="width:0%"></div></div>
          <p class="text-muted small mt-1" id="progressText"></p>
        </div>
      </div>

      <!-- Section 7: Consent -->
      <div class="form-section">
        <div class="form-section-title"><i class="fa fa-shield-alt text-buzz"></i> Confirmation</div>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="consent_agreed" id="consentCheck" value="1" required>
          <label class="form-check-label" for="consentCheck">
            <strong>I confirm that the above client information is correct and complete.</strong> I understand that this information will be used by the design team to prepare the project brief.
          </label>
          <div class="invalid-feedback">You must confirm the information is correct.</div>
        </div>
      </div>

      <!-- Submit -->
      <div class="d-flex gap-3 justify-content-end pb-4">
        <a href="dashboard.php" class="btn btn-outline-secondary px-4">Cancel</a>
        <button type="submit" class="btn btn-buzz px-5" id="submitBtn">
          <span id="submitSpinner" class="spinner-border spinner-border-sm d-none me-2"></span>
          <i class="fa fa-paper-plane me-2"></i>Submit Requirement
        </button>
      </div>
    </form>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../public/js/app.js"></script>
<script>
var BASE_URL = '..';
BuzzApp.initNotifications(<?= $user['id'] ?>);

var uploader = BuzzApp.initFileUpload('#dropZone', '#fileInput', '#fileList', 500);

$('#requirementForm').on('submit', function(e) {
    e.preventDefault();
    var form = this;

    // Validate checkboxes
    var reqTypes = $('input[name="requirement_type[]"]:checked').length;
    if (reqTypes === 0) {
        $('#reqTypeError').show();
        $('html').animate({scrollTop: $('#reqTypeError').offset().top - 100}, 400);
        return;
    }
    $('#reqTypeError').hide();

    if (!form.checkValidity()) {
        $(form).addClass('was-validated');
        var first = $(form).find(':invalid').first();
        if (first.length) $('html').animate({scrollTop: first.offset().top - 120}, 400);
        return;
    }

    var btn = $('#submitBtn');
    btn.prop('disabled', true);
    $('#submitSpinner').removeClass('d-none');

    var files = uploader.getFiles();
    var formData = $(form).serializeArray();

    // If no files, submit directly
    if (!files.length) {
        submitForm(formData, []);
        return;
    }

    // Upload files first
    var uploadedIds = [];
    var total = files.length;
    var done  = 0;
    $('#uploadProgress').show();

    BuzzApp.uploadFiles(files, 0, 'requirement',
        function(pct, name) {
            $('#progressFill').css('width', Math.round(((done / total) * 100 + pct / total)) + '%');
            $('#progressText').text('Uploading: ' + name + ' (' + pct + '%)');
        },
        function(uploaded) {
            uploaded.forEach(function(f) { uploadedIds.push(f.file_id); });
            $('#uploadProgress').hide();
            submitForm(formData, uploadedIds);
        }
    );
});

function submitForm(formData, fileIds) {
    var data = {};
    formData.forEach(function(f) {
        if (f.name === 'requirement_type[]') {
            if (!data['requirement_type[]']) data['requirement_type[]'] = [];
            data['requirement_type[]'].push(f.value);
        } else {
            data[f.name] = f.value;
        }
    });
    if (fileIds.length) data.file_ids = fileIds.join(',');

    BuzzApp.ajax('../api/form-submit.php', data, function(res) {
        $('#submitBtn').prop('disabled', false);
        $('#submitSpinner').addClass('d-none');
        if (res.success) {
            if (fileIds.length && res.project_id) {
                // Update file project IDs
                $.post('../api/file-upload.php', {action:'link', project_id: res.project_id, file_ids: fileIds.join(','), csrf_token: BuzzApp.getCsrfToken()});
            }
            $('#formAlert').html('<div class="alert alert-success alert-dismissible"><i class="fa fa-check-circle me-2"></i>' + res.message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
            $('html').animate({scrollTop: 0}, 400);
            $('#requirementForm')[0].reset();
            uploader.reset();
            setTimeout(() => window.location.href = 'my-projects.php', 2000);
        } else {
            $('#formAlert').html('<div class="alert alert-danger"><i class="fa fa-times-circle me-2"></i>' + (res.message || 'Submission failed.') + '</div>');
            $('html').animate({scrollTop: 0}, 400);
        }
    });
}
</script>
</body>
</html>
