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
<style>
/* ================================================================
   PROJECT BRIEF FORM – document-style layout
   ================================================================ */
.brief-wrap { max-width: 870px; margin: 0 auto; }

/* ---- Paper card ---- */
.brief-doc {
  background: #fff;
  border-radius: 10px;
  box-shadow: 0 4px 20px rgba(0,0,0,.11);
  padding: 2rem 2.5rem 1rem;
  margin-bottom: 1.5rem;
}

/* ---- Header ---- */
.brief-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
}
.brief-logo-brand {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  line-height: 1.1;
}
.brief-logo-icon { font-size: 2.4rem; line-height: 1; }
.brief-logo-name {
  font-size: 1.15rem;
  font-weight: 900;
  color: var(--buzz-red);
  letter-spacing: 3px;
  text-transform: uppercase;
}
.brief-title {
  flex: 1;
  text-align: center;
  font-size: 1.55rem;
  font-weight: 900;
  color: #e65c00;
  letter-spacing: 2px;
  text-transform: uppercase;
}
.brief-date-box {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-weight: 600;
  font-size: 0.9rem;
  white-space: nowrap;
}
.brief-date-box input {
  border: 1px solid #999;
  border-radius: 3px;
  padding: 2px 8px;
  font-size: 0.88rem;
  width: 145px;
}

/* Red divider under header */
.brief-divider {
  height: 3px;
  background: linear-gradient(90deg, var(--buzz-red), #ff7043);
  margin: 0.65rem 0 1.2rem;
  border-radius: 2px;
}

/* ---- Document rows ---- */
.brow {
  display: flex;
  align-items: center;
  border-bottom: 1px solid #e8e8e8;
  padding: 0.42rem 0;
  gap: 0.6rem;
  flex-wrap: wrap;
  min-height: 38px;
}
.brow:last-of-type { border-bottom: none; }
.blabel {
  font-weight: 600;
  font-size: 0.88rem;
  color: #222;
  min-width: 135px;
  flex-shrink: 0;
}
.bfield { flex: 1; min-width: 0; }

/* Inline input boxes */
.bf-input {
  width: 100%;
  border: 1px solid #aaa;
  border-radius: 3px;
  padding: 3px 8px;
  font-size: 0.88rem;
  height: 30px;
  background: #fff;
}
.bf-input:focus { outline: none; border-color: var(--buzz-red); }

.bf-sm {
  display: inline-block;
  border: 1px solid #aaa;
  border-radius: 3px;
  padding: 3px 6px;
  font-size: 0.88rem;
  height: 30px;
  background: #fff;
}
.bf-sm:focus { outline: none; border-color: var(--buzz-red); }

.bf-unit { font-size: 0.82rem; color: #555; white-space: nowrap; flex-shrink: 0; }

/* Checkbox / radio groups */
.bcheck-group {
  display: flex;
  flex-wrap: wrap;
  gap: 0.8rem;
  align-items: center;
}
.bcheck-item {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 0.88rem;
  cursor: pointer;
  white-space: nowrap;
}
.bcheck-item input[type="checkbox"],
.bcheck-item input[type="radio"] {
  width: 15px;
  height: 15px;
  cursor: pointer;
  accent-color: var(--buzz-red);
  flex-shrink: 0;
}

/* ---- Section heading inside doc ---- */
.brief-section-heading {
  text-align: center;
  font-weight: 900;
  font-size: 1rem;
  padding: 1rem 0 0.75rem;
  color: #111;
  border-top: 1px solid #e8e8e8;
  margin-top: 0.25rem;
}

/* Stand requirement row */
.bstand-row {
  display: flex;
  align-items: center;
  border-bottom: 1px solid #e8e8e8;
  padding: 0.42rem 0;
  gap: 0.6rem;
  flex-wrap: wrap;
}

/* ---- 3-column feature tables ---- */
.bftable {
  width: 100%;
  border-collapse: collapse;
  margin-top: 0.4rem;
}
.bftable + .bftable { margin-top: 0; }
.bftable th,
.bftable td {
  border: 1px solid #ddd;
  padding: 0.4rem 0.8rem;
  width: 33.33%;
  vertical-align: top;
}
.bftable th {
  background: #fdecea;
  font-weight: 700;
  font-size: 0.84rem;
  color: #333;
}
.bftable td { font-size: 0.88rem; }

/* Notes */
.bnotes {
  width: 100%;
  border: 1px solid #aaa;
  border-radius: 3px;
  padding: 6px 10px;
  font-size: 0.88rem;
  resize: vertical;
  min-height: 80px;
  background: #fafafa;
  margin-top: 0.5rem;
}
.bnotes:focus { outline: none; border-color: var(--buzz-red); }

/* Footer */
.brief-footer-text {
  text-align: center;
  font-size: 0.85rem;
  color: #555;
  padding: 0.85rem 0 0.6rem;
  border-top: 1px solid #eee;
  margin-top: 0.75rem;
}
.brief-footer-bar {
  height: 14px;
  background: linear-gradient(90deg, var(--buzz-red), #ff7043);
  border-radius: 0 0 8px 8px;
  margin: 0.5rem -2.5rem -1rem;
}

/* File upload + submit section */
.submit-section {
  background: #fff;
  border-radius: 10px;
  box-shadow: 0 2px 10px rgba(0,0,0,.07);
  padding: 1.5rem 2rem;
  margin-bottom: 1.5rem;
}

/* Responsive */
@media (max-width: 620px) {
  .brief-header { flex-direction: column; align-items: flex-start; }
  .brief-title   { font-size: 1.15rem; text-align: left; }
  .brief-doc     { padding: 1.25rem 1rem 0.75rem; }
  .brief-footer-bar { margin: 0.5rem -1rem -0.75rem; }
  .blabel        { min-width: 100px; font-size: 0.82rem; }
  .bftable th, .bftable td { padding: 0.3rem 0.5rem; font-size: 0.8rem; }
}
</style>
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
    <div id="formAlert"></div>

    <form id="requirementForm" novalidate>
      <input type="hidden" name="csrf_token"           value="<?= $csrfToken ?>">
      <input type="hidden" name="action"               value="submit_requirement">
      <!-- Hidden API fields – populated by JS just before form serialization -->
      <input type="hidden" name="project_name"         id="hidProjectName">
      <input type="hidden" name="event_name"           id="hidEventName">
      <input type="hidden" name="booth_size"           id="hidBoothSize">
      <input type="hidden" name="stand_type"           id="hidStandType">
      <input type="hidden" name="design_style"         id="hidDesignStyle">
      <input type="hidden" name="colors"               id="hidColors">
      <input type="hidden" name="budget"               id="hidBudget">
      <input type="hidden" name="special_requirements" id="hidSpecialReq">
      <!-- Unused fields kept for API compatibility -->
      <input type="hidden" name="contact_person"       value="">
      <input type="hidden" name="contact_email"        value="">
      <input type="hidden" name="contact_phone"        value="">
      <input type="hidden" name="client_country"       value="">
      <input type="hidden" name="products_to_display"  value="">
      <input type="hidden" name="consent_agreed"       value="1">

      <!-- ============================================================
           DOCUMENT PAPER
           ============================================================ -->
      <div class="brief-wrap">
        <div class="brief-doc">

          <!-- ---- Header ---- -->
          <div class="brief-header">
            <div class="brief-logo-brand">
              <span class="brief-logo-icon">🐝</span>
              <span class="brief-logo-name">BUZZNATION</span>
            </div>
            <div class="brief-title">PROJECT BRIEF FORM</div>
            <div class="brief-date-box">
              Date:&nbsp;<input type="text" value="<?= date('M jS Y') ?>" readonly>
            </div>
          </div>
          <div class="brief-divider"></div>

          <!-- ---- Requirement + Budget ---- -->
          <div class="brow">
            <span class="blabel">Requirement</span>
            <div class="bfield">
              <div class="bcheck-group">
                <label class="bcheck-item"><input type="checkbox" name="requirement_type[]" value="Exhibition"> Exhibition</label>
                <label class="bcheck-item"><input type="checkbox" name="requirement_type[]" value="Event"> Event</label>
                <label class="bcheck-item"><input type="checkbox" name="requirement_type[]" value="Branding"> Branding</label>
              </div>
            </div>
            <span class="bf-unit ms-2" style="font-weight:600;color:#222;min-width:50px;">Budget</span>
            <input type="text" class="bf-sm" id="budgetAmount" style="width:90px;" placeholder="e.g. 8k-12k">
            <select class="bf-sm" id="budgetCurrency" style="width:72px;padding:2px 4px;">
              <option>USD</option>
              <option>EUR</option>
              <option>GBP</option>
              <option>AED</option>
              <option>INR</option>
            </select>
          </div>
          <div id="reqTypeError" class="text-danger small" style="display:none;padding:0 0 4px 140px;">Please select at least one requirement type.</div>

          <!-- ---- Client's Name ---- -->
          <div class="brow">
            <span class="blabel">Client's Name</span>
            <div class="bfield"><input type="text" class="bf-input" name="client_name" required placeholder=""></div>
          </div>

          <!-- ---- Show / Event Name ---- -->
          <div class="brow">
            <span class="blabel">Show/Event Name</span>
            <div class="bfield"><input type="text" class="bf-input" id="showEventName" required placeholder=""></div>
          </div>

          <!-- ---- Event Venue + Hall No. ---- -->
          <div class="brow">
            <span class="blabel">Event Venue</span>
            <div class="bfield"><input type="text" class="bf-input" name="venue" placeholder=""></div>
            <span class="bf-unit ms-2" style="font-weight:600;color:#222;min-width:58px;">Hall No.</span>
            <div style="width:130px;"><input type="text" class="bf-input" name="city" placeholder=""></div>
          </div>

          <!-- ---- Show Date + Booth No. ---- -->
          <div class="brow">
            <span class="blabel">Show Date</span>
            <div class="bfield"><input type="text" class="bf-input" name="event_date" placeholder="e.g. Sept 29th to Oct 1st 2025"></div>
            <span class="bf-unit ms-2" style="font-weight:600;color:#222;min-width:58px;">Booth No.</span>
            <div style="width:130px;"><input type="text" class="bf-input" name="country" placeholder=""></div>
          </div>

          <!-- ---- Booth Size + Total Area ---- -->
          <div class="brow">
            <span class="blabel">Booth Size</span>
            <input type="number" class="bf-sm" id="boothW" style="width:62px;" placeholder="10" min="0">
            <span class="bf-unit">x</span>
            <input type="number" class="bf-sm" id="boothH" style="width:62px;" placeholder="10" min="0">
            <span class="bf-unit">Sqm/ft</span>
            <span class="blabel ms-3" style="min-width:90px;">Total Area:</span>
            <input type="number" class="bf-sm" id="totalArea" style="width:72px;" placeholder="100" min="0">
            <span class="bf-unit">Sqm/ft</span>
          </div>

          <!-- ---- Stand Orientation ---- -->
          <div class="brow">
            <span class="blabel">Stand Orientation</span>
            <div class="bfield">
              <div class="bcheck-group">
                <label class="bcheck-item"><input type="checkbox" name="stand_orientation[]" value="Island"> Island</label>
                <label class="bcheck-item"><input type="checkbox" name="stand_orientation[]" value="3 Sides Open"> 3 Sides Open</label>
                <label class="bcheck-item"><input type="checkbox" name="stand_orientation[]" value="2 Sides Open"> 2 Sides Open</label>
                <label class="bcheck-item"><input type="checkbox" name="stand_orientation[]" value="1 Side Open"> 1 Side Open</label>
              </div>
            </div>
          </div>

          <!-- ================================================================
               ADDITIONAL REQUIREMENTS OF THE DESIGN
               ================================================================ -->
          <div class="brief-section-heading">Additional Requirements of the Design</div>

          <!-- Stand Requirement -->
          <div class="bstand-row">
            <span class="blabel">Stand Requirement</span>
            <div class="bcheck-group flex-wrap">
              <label class="bcheck-item"><input type="checkbox" name="stand_req[]" value="Custom"> Custom</label>
              <label class="bcheck-item"><input type="checkbox" name="stand_req[]" value="Modular"> Modular</label>
              <label class="bcheck-item"><input type="checkbox" name="stand_req[]" value="Cust+Mod"> Cust+Mod</label>
              <label class="bcheck-item"><input type="checkbox" name="stand_req[]" value="Standee"> Standee</label>
            </div>
          </div>

          <!-- Feature table row 1: Floor Type | Open Discussion Area | Digital Assets -->
          <table class="bftable">
            <thead>
              <tr>
                <th>Floor Type</th>
                <th>Open Discussion Area</th>
                <th>Digital Assets (LED + TVs)</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>
                  <div class="bcheck-group">
                    <label class="bcheck-item"><input type="radio" name="floor_type" value="Carpet"> Carpet</label>
                    <label class="bcheck-item"><input type="radio" name="floor_type" value="Laminate"> Laminate</label>
                  </div>
                </td>
                <td>
                  <div class="bcheck-group">
                    <label class="bcheck-item"><input type="radio" name="open_disc_area" value="Yes"> Yes</label>
                    <label class="bcheck-item"><input type="radio" name="open_disc_area" value="No"> No</label>
                  </div>
                </td>
                <td>
                  <div class="bcheck-group">
                    <label class="bcheck-item"><input type="radio" name="digital_assets" value="Yes"> Yes</label>
                    <label class="bcheck-item"><input type="radio" name="digital_assets" value="No"> No</label>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>

          <!-- Feature table row 2: Demo Station | Nos. ODA | Touch Screen Kiosk -->
          <table class="bftable">
            <thead>
              <tr>
                <th>Demo Station</th>
                <th>Nos. Open Discussion Area</th>
                <th>Touch Screen Kiosk</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>
                  <div class="bcheck-group">
                    <label class="bcheck-item"><input type="radio" name="demo_station" value="1"> 1</label>
                    <label class="bcheck-item"><input type="radio" name="demo_station" value="2"> 2</label>
                    <label class="bcheck-item"><input type="radio" name="demo_station" value="3"> 3</label>
                    <label class="bcheck-item"><input type="radio" name="demo_station" value="4"> 4</label>
                  </div>
                </td>
                <td>
                  <div class="bcheck-group">
                    <label class="bcheck-item"><input type="radio" name="nos_open_disc" value="1"> 1</label>
                    <label class="bcheck-item"><input type="radio" name="nos_open_disc" value="2"> 2</label>
                    <label class="bcheck-item"><input type="radio" name="nos_open_disc" value="3"> 3</label>
                    <label class="bcheck-item"><input type="radio" name="nos_open_disc" value="4"> 4</label>
                  </div>
                </td>
                <td>
                  <div class="bcheck-group">
                    <label class="bcheck-item"><input type="radio" name="touch_kiosk" value="Yes"> Yes</label>
                    <label class="bcheck-item"><input type="radio" name="touch_kiosk" value="No"> No</label>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>

          <!-- Feature table row 3: Meeting Room | Total Nos. of MR | Booth Engagement -->
          <table class="bftable">
            <thead>
              <tr>
                <th>Meeting Room</th>
                <th>Total Nos. of MR</th>
                <th>Booth Engagement</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>
                  <div class="bcheck-group">
                    <label class="bcheck-item"><input type="radio" name="meeting_room" value="Yes"> Yes</label>
                    <label class="bcheck-item"><input type="radio" name="meeting_room" value="No"> No</label>
                  </div>
                </td>
                <td>
                  <div class="bcheck-group">
                    <label class="bcheck-item"><input type="radio" name="total_mr" value="1"> 1</label>
                    <label class="bcheck-item"><input type="radio" name="total_mr" value="2"> 2</label>
                    <label class="bcheck-item"><input type="radio" name="total_mr" value="3"> 3</label>
                    <label class="bcheck-item"><input type="radio" name="total_mr" value="4"> 4</label>
                  </div>
                </td>
                <td>
                  <div class="bcheck-group">
                    <label class="bcheck-item"><input type="radio" name="booth_engagement" value="Yes"> Yes</label>
                    <label class="bcheck-item"><input type="radio" name="booth_engagement" value="No"> No</label>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>

          <!-- Free-text notes -->
          <textarea class="bnotes" name="additional_notes" rows="4"
            placeholder="Size of the booth, layout notes, furniture requirements, etc.…"></textarea>

          <!-- Design Delivery Target -->
          <div class="brow" style="border-top:1px solid #e8e8e8; margin-top:0.5rem;">
            <span class="blabel">Design Delivery Target</span>
            <div class="bfield">
              <div class="bcheck-group">
                <label class="bcheck-item"><input type="checkbox" name="delivery_target[]" value="Urgent"> Urgent</label>
                <label class="bcheck-item"><input type="checkbox" name="delivery_target[]" value="3 Working Days"> 3 Working Days</label>
                <label class="bcheck-item"><input type="checkbox" name="delivery_target[]" value="7 Working Days"> 7 Working Days</label>
                <label class="bcheck-item"><input type="checkbox" name="delivery_target[]" value="Relaxed"> Relaxed</label>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="brief-footer-text">Additional information and inputs can be attached along with this form</div>
          <div class="brief-footer-bar"></div>

        </div><!-- /.brief-doc -->

        <!-- ---- File upload + Submit ---- -->
        <div class="submit-section">
          <div class="form-section-title mb-3">
            <i class="fa fa-cloud-upload-alt text-buzz"></i> Attach Reference Files
            <span class="text-muted fw-normal small ms-2">(optional – max 500 MB each)</span>
          </div>
          <div class="upload-zone" id="dropZone">
            <i class="fa fa-cloud-upload-alt d-block"></i>
            <p class="mb-1 fw-semibold">Drag &amp; Drop files here</p>
            <p class="text-muted small mb-3">or click to browse</p>
            <input type="file" id="fileInput" multiple
              accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.ai,.psd"
              style="display:none;">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="browseBtn">Browse Files</button>
          </div>
          <div class="file-list" id="fileList"></div>
          <div id="uploadProgress" style="display:none;">
            <div class="progress-bar-wrap mt-2">
              <div class="progress-bar-fill" id="progressFill" style="width:0%"></div>
            </div>
            <p class="text-muted small mt-1" id="progressText"></p>
          </div>

          <div class="d-flex gap-3 justify-content-end mt-3">
            <a href="dashboard.php" class="btn btn-outline-secondary px-4">Cancel</a>
            <button type="submit" class="btn btn-buzz px-5" id="submitBtn">
              <span id="submitSpinner" class="spinner-border spinner-border-sm d-none me-2"></span>
              <i class="fa fa-paper-plane me-2"></i>Submit Requirement
            </button>
          </div>
        </div>

      </div><!-- /.brief-wrap -->
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

$('#requirementForm').on('submit', function (e) {
    e.preventDefault();
    var form = this;

    /* 1. Validate requirement type */
    var reqTypes = $('input[name="requirement_type[]"]:checked').length;
    if (reqTypes === 0) {
        $('#reqTypeError').show();
        $('html').animate({scrollTop: $('#reqTypeError').offset().top - 100}, 400);
        return;
    }
    $('#reqTypeError').hide();

    /* 2. Validate visible required fields */
    var clientName   = $('[name="client_name"]').val().trim();
    var showEvtName  = $('#showEventName').val().trim();
    if (!clientName) {
        BuzzApp.showToast("Client's Name is required.", 'warning');
        $('[name="client_name"]').focus();
        return;
    }
    if (!showEvtName) {
        BuzzApp.showToast('Show/Event Name is required.', 'warning');
        $('#showEventName').focus();
        return;
    }

    /* 3. Populate hidden API fields */
    $('#hidProjectName').val(showEvtName);
    $('#hidEventName').val(showEvtName);

    /* Booth size */
    var bw = $('#boothW').val();
    var bh = $('#boothH').val();
    var ta = $('#totalArea').val();
    var boothStr = '';
    if (bw || bh) { boothStr = (bw || '?') + ' x ' + (bh || '?') + ' Sqm/ft'; }
    if (ta)       { boothStr += (boothStr ? ', Total Area: ' : 'Total Area: ') + ta + ' Sqm/ft'; }
    $('#hidBoothSize').val(boothStr);

    /* Stand type = orientation selection */
    var orientations = $('input[name="stand_orientation[]"]:checked').map(function () { return this.value; }).get();
    $('#hidStandType').val(orientations.join(', '));

    /* Design style = stand requirement selection */
    var standReqs = $('input[name="stand_req[]"]:checked').map(function () { return this.value; }).get();
    $('#hidDesignStyle').val(standReqs.join(', '));

    /* Colors = delivery target */
    var delivery = $('input[name="delivery_target[]"]:checked').map(function () { return this.value; }).get();
    $('#hidColors').val(delivery.join(', '));

    /* Budget */
    var bAmt  = $('#budgetAmount').val().trim();
    var bCur  = $('#budgetCurrency').val();
    $('#hidBudget').val(bAmt ? bAmt + ' ' + bCur : '');

    /* Special requirements – all additional-design checkboxes as readable text */
    var sr = {};
    var floorType = $('input[name="floor_type"]:checked').val();
    if (floorType)          { sr['Floor Type']                  = floorType; }
    var oda = $('input[name="open_disc_area"]:checked').val();
    if (oda)                { sr['Open Discussion Area']         = oda; }
    var da = $('input[name="digital_assets"]:checked').val();
    if (da)                 { sr['Digital Assets (LED+TVs)']     = da; }
    var ds = $('input[name="demo_station"]:checked').val();
    if (ds)                 { sr['Demo Station']                 = ds; }
    var noda = $('input[name="nos_open_disc"]:checked').val();
    if (noda)               { sr['Nos. Open Discussion Area']    = noda; }
    var tk = $('input[name="touch_kiosk"]:checked').val();
    if (tk)                 { sr['Touch Screen Kiosk']           = tk; }
    var mr = $('input[name="meeting_room"]:checked').val();
    if (mr)                 { sr['Meeting Room']                 = mr; }
    var tmr = $('input[name="total_mr"]:checked').val();
    if (tmr)                { sr['Total Nos. of MR']             = tmr; }
    var be = $('input[name="booth_engagement"]:checked').val();
    if (be)                 { sr['Booth Engagement']             = be; }
    var srLines = Object.keys(sr).map(function (k) { return k + ': ' + sr[k]; });
    $('#hidSpecialReq').val(srLines.join('\n'));

    /* 4. Submit */
    var btn = $('#submitBtn');
    btn.prop('disabled', true);
    $('#submitSpinner').removeClass('d-none');

    var files    = uploader.getFiles();
    var formData = $(form).serializeArray();

    if (!files.length) {
        submitForm(formData, []);
        return;
    }

    var uploadedIds = [];
    var total = files.length;
    var done  = 0;
    $('#uploadProgress').show();

    BuzzApp.uploadFiles(files, 0, 'requirement',
        function (pct, name) {
            $('#progressFill').css('width', Math.round((done / total) * 100 + pct / total) + '%');
            $('#progressText').text('Uploading: ' + name + ' (' + pct + '%)');
        },
        function (uploaded) {
            uploaded.forEach(function (f) { uploadedIds.push(f.file_id); });
            $('#uploadProgress').hide();
            submitForm(formData, uploadedIds);
        }
    );
});

function submitForm(formData, fileIds) {
    var data = {};
    formData.forEach(function (f) {
        if (f.name === 'requirement_type[]') {
            if (!data['requirement_type[]']) { data['requirement_type[]'] = []; }
            data['requirement_type[]'].push(f.value);
        } else {
            data[f.name] = f.value;
        }
    });
    if (fileIds.length) { data.file_ids = fileIds.join(','); }

    BuzzApp.ajax('../api/form-submit.php', data, function (res) {
        $('#submitBtn').prop('disabled', false);
        $('#submitSpinner').addClass('d-none');
        if (res.success) {
            if (fileIds.length && res.project_id) {
                $.post('../api/file-upload.php', {
                    action: 'link', project_id: res.project_id,
                    file_ids: fileIds.join(','), csrf_token: BuzzApp.getCsrfToken()
                });
            }
            $('#formAlert').html(
                '<div class="alert alert-success alert-dismissible">' +
                '<i class="fa fa-check-circle me-2"></i>' + res.message +
                '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>'
            );
            $('html').animate({scrollTop: 0}, 400);
            $('#requirementForm')[0].reset();
            uploader.reset();
            setTimeout(function () { window.location.href = 'my-projects.php'; }, 2000);
        } else {
            $('#formAlert').html(
                '<div class="alert alert-danger"><i class="fa fa-times-circle me-2"></i>' +
                (res.message || 'Submission failed.') + '</div>'
            );
            $('html').animate({scrollTop: 0}, 400);
        }
    });
}
</script>
</body>
</html>
