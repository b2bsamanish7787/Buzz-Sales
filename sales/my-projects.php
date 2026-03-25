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
        // Load attachments for each change request
        foreach ($changes as &$cr) {
            $cr['files'] = $db->fetchAll(
                "SELECT * FROM file_uploads WHERE change_request_id=? ORDER BY created_at ASC",
                [$cr['id']]
            );
        }
        unset($cr);
        $reviews = $db->fetchAll("SELECT * FROM design_reviews WHERE project_id=? ORDER BY reviewed_at DESC", [$viewId]);
    }
}

$statusFilter = Security::sanitizeInput($_GET['status'] ?? '');
$projects = $db->fetchAll(
    "SELECT * FROM projects WHERE sales_user_id=? " . ($statusFilter ? "AND status=?" : "") . " ORDER BY updated_at DESC",
    $statusFilter ? [$user['id'], $statusFilter] : [$user['id']]
);

/* ---- Helpers for the brief form view ---- */
function briefChk(string $stored, string $value): string {
    $items = array_map('trim', explode(',', $stored));
    return in_array($value, $items, true) ? 'checked' : '';
}
function briefRad(string $stored, string $value): string {
    return (trim($stored) === $value) ? 'checked' : '';
}
function parseSR(string $raw): array {
    $out = [];
    foreach (explode("\n", $raw) as $line) {
        $line = trim($line);
        if ($line !== '' && strpos($line, ':') !== false) {
            [$k, $v] = explode(':', $line, 2);
            $out[trim($k)] = trim($v);
        }
    }
    return $out;
}
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
<style>
/* ===== PROJECT BRIEF FORM – view styles (shared with requirement-form layout) ===== */
.brief-wrap { max-width: 870px; margin: 0 auto; }
.brief-doc {
  background: #fff; border-radius: 10px;
  box-shadow: 0 4px 20px rgba(0,0,0,.11);
  padding: 2rem 2.5rem 1rem; margin-bottom: 1.5rem;
}
.brief-header {
  display: flex; align-items: center;
  justify-content: space-between; gap: 1rem; flex-wrap: wrap;
}
.brief-logo-brand { display:flex; flex-direction:column; align-items:flex-start; line-height:1.1; }
.brief-logo-icon  { font-size:2.4rem; line-height:1; }
.brief-logo-name  { font-size:1.15rem; font-weight:900; color:var(--buzz-red); letter-spacing:3px; text-transform:uppercase; }
.brief-title      { flex:1; text-align:center; font-size:1.55rem; font-weight:900; color:#e65c00; letter-spacing:2px; text-transform:uppercase; }
.brief-date-box   { display:flex; align-items:center; gap:0.4rem; font-weight:600; font-size:0.9rem; white-space:nowrap; }
.brief-date-val   { border:1px solid #999; border-radius:3px; padding:2px 8px; font-size:0.88rem; min-width:145px; background:#fff; }
.brief-divider    { height:3px; background:linear-gradient(90deg,var(--buzz-red),#ff7043); margin:0.65rem 0 1.2rem; border-radius:2px; }
.brow { display:flex; align-items:center; border-bottom:1px solid #e8e8e8; padding:0.42rem 0; gap:0.6rem; flex-wrap:wrap; min-height:38px; }
.brow:last-of-type { border-bottom:none; }
.blabel { font-weight:600; font-size:0.88rem; color:#222; min-width:135px; flex-shrink:0; }
.bfield { flex:1; min-width:0; }
.bf-input { display:inline-block; width:100%; border:1px solid #aaa; border-radius:3px; padding:3px 8px; font-size:0.88rem; min-height:30px; background:#fff; word-break:break-word; }
.bf-sm    { display:inline-block; border:1px solid #aaa; border-radius:3px; padding:3px 6px; font-size:0.88rem; min-height:30px; background:#fff; }
.bf-unit  { font-size:0.82rem; color:#555; white-space:nowrap; flex-shrink:0; }
.bcheck-group { display:flex; flex-wrap:wrap; gap:0.8rem; align-items:center; }
.bcheck-item  { display:flex; align-items:center; gap:4px; font-size:0.88rem; white-space:nowrap; }
.bcheck-item input[type="checkbox"],
.bcheck-item input[type="radio"]  { width:15px; height:15px; accent-color:var(--buzz-red); flex-shrink:0; pointer-events:none; }
.bcheck-item input:disabled       { opacity:1; }
.brief-section-heading { text-align:center; font-weight:900; font-size:1rem; padding:1rem 0 0.75rem; color:#111; border-top:1px solid #e8e8e8; margin-top:0.25rem; }
.bstand-row { display:flex; align-items:center; border-bottom:1px solid #e8e8e8; padding:0.42rem 0; gap:0.6rem; flex-wrap:wrap; }
.bftable { width:100%; border-collapse:collapse; margin-top:0.4rem; }
.bftable + .bftable { margin-top:0; }
.bftable th, .bftable td { border:1px solid #ddd; padding:0.4rem 0.8rem; width:33.33%; vertical-align:top; }
.bftable th { background:#fdecea; font-weight:700; font-size:0.84rem; color:#333; }
.bftable td { font-size:0.88rem; }
.bnotes-view { width:100%; font-size:0.88rem; padding:6px 10px; background:#fafafa; border:1px solid #e0e0e0; border-radius:3px; margin-top:0.5rem; min-height:60px; white-space:pre-wrap; word-break:break-word; }
.brief-footer-text { text-align:center; font-size:0.85rem; color:#555; padding:0.85rem 0 0.6rem; border-top:1px solid #eee; margin-top:0.75rem; }
.brief-footer-bar  { height:14px; background:linear-gradient(90deg,var(--buzz-red),#ff7043); border-radius:0 0 8px 8px; margin:0.5rem -2.5rem -1rem; }
@media (max-width:620px) {
  .brief-header { flex-direction:column; align-items:flex-start; }
  .brief-title  { font-size:1.15rem; text-align:left; }
  .brief-doc    { padding:1.25rem 1rem 0.75rem; }
  .brief-footer-bar { margin:0.5rem -1rem -0.75rem; }
  .blabel       { min-width:100px; font-size:0.82rem; }
  .bftable th,.bftable td { padding:0.3rem 0.5rem; font-size:0.8rem; }
}
@media print {
  .sidebar, .buzz-navbar, .page-header .btn, .no-print { display:none !important; }
  .main-content { margin-left:0 !important; padding:0 !important; }
  .brief-doc { box-shadow:none !important; }
}
</style>
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
    <?php
    /* ---- Parse stored data for the brief form view ---- */
    $d         = $details ?? [];
    $reqType   = $d['requirement_type'] ?? '';
    $boothSize = $d['booth_size']        ?? '';
    $standType = $d['stand_type']        ?? '';
    $standReq  = $d['design_style']      ?? '';
    $delivery  = $d['colors']            ?? '';
    $budget    = $d['budget']            ?? '';
    $venue     = $d['venue']             ?? '';
    $hallNo    = $d['city']              ?? '';
    $eventDate = $d['event_date']        ?? '';
    $boothNo   = $d['country']           ?? '';

    $boothW = $boothH = $totalArea = '';
    if ($boothSize) {
        if (preg_match('/^([\d.?]+)\s*x\s*([\d.?]+)\s*Sqm\/ft/i', $boothSize, $m)) { $boothW = $m[1]; $boothH = $m[2]; }
        if (preg_match('/Total Area:\s*([\d.]+)\s*Sqm\/ft/i', $boothSize, $m))       { $totalArea = $m[1]; }
    }
    $budgetAmt = $budgetCur = '';
    if ($budget) {
        if (preg_match('/^(.+?)\s+([A-Z]{2,4})$/i', trim($budget), $m)) { $budgetAmt = $m[1]; $budgetCur = strtoupper($m[2]); }
        else { $budgetAmt = $budget; }
    }
    $sr          = parseSR($d['special_requirements'] ?? '');
    $floorType   = $sr['Floor Type']                  ?? '';
    $openDisc    = $sr['Open Discussion Area']         ?? '';
    $digAssets   = $sr['Digital Assets (LED+TVs)']    ?? '';
    $demoSt      = $sr['Demo Station']                ?? '';
    $nosOda      = $sr['Nos. Open Discussion Area']   ?? '';
    $touchKiosk  = $sr['Touch Screen Kiosk']          ?? '';
    $meetRoom    = $sr['Meeting Room']                ?? '';
    $totalMr     = $sr['Total Nos. of MR']            ?? '';
    $boothEng    = $sr['Booth Engagement']            ?? '';
    ?>

    <div class="page-header">
      <div>
        <h1><i class="fa fa-file-alt text-buzz me-2"></i>Project Brief</h1>
        <small class="text-muted">Project #<?= $project['id'] ?> &nbsp;|&nbsp; <?= getStatusBadge($project['status']) ?></small>
      </div>
      <div class="d-flex gap-2 no-print">
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm"><i class="fa fa-print me-1"></i>Print</button>
        <a href="my-projects.php" class="btn btn-outline-secondary btn-sm"><i class="fa fa-arrow-left me-1"></i>Back</a>
      </div>
    </div>

    <div class="brief-wrap">

      <!-- ============================================================
           DOCUMENT PAPER
           ============================================================ -->
      <div class="brief-doc">

        <!-- Header -->
        <div class="brief-header">
          <div class="brief-logo-brand">
            <span class="brief-logo-icon">🐝</span>
            <span class="brief-logo-name">BUZZNATION</span>
          </div>
          <div class="brief-title">PROJECT BRIEF FORM</div>
          <div class="brief-date-box">
            Date:&nbsp;<span class="brief-date-val"><?= htmlspecialchars(date('M jS Y', strtotime($project['created_at']))) ?></span>
          </div>
        </div>
        <div class="brief-divider"></div>

        <!-- Requirement + Budget -->
        <div class="brow">
          <span class="blabel">Requirement</span>
          <div class="bfield">
            <div class="bcheck-group">
              <label class="bcheck-item"><input type="checkbox" <?= briefChk($reqType,'Exhibition') ?> disabled> Exhibition</label>
              <label class="bcheck-item"><input type="checkbox" <?= briefChk($reqType,'Event') ?> disabled> Event</label>
              <label class="bcheck-item"><input type="checkbox" <?= briefChk($reqType,'Branding') ?> disabled> Branding</label>
            </div>
          </div>
          <span class="bf-unit ms-2" style="font-weight:600;color:#222;min-width:50px;">Budget</span>
          <span class="bf-sm" style="min-width:90px;"><?= htmlspecialchars($budgetAmt) ?></span>
          <span class="bf-sm" style="min-width:55px;"><?= htmlspecialchars($budgetCur) ?></span>
        </div>

        <!-- Client's Name -->
        <div class="brow">
          <span class="blabel">Client's Name</span>
          <div class="bfield"><span class="bf-input"><?= htmlspecialchars($project['client_name']) ?></span></div>
        </div>

        <!-- Show/Event Name -->
        <div class="brow">
          <span class="blabel">Show/Event Name</span>
          <div class="bfield"><span class="bf-input"><?= htmlspecialchars($d['event_name'] ?? '') ?></span></div>
        </div>

        <!-- Event Venue + Hall No. -->
        <div class="brow">
          <span class="blabel">Event Venue</span>
          <div class="bfield"><span class="bf-input"><?= htmlspecialchars($venue) ?></span></div>
          <span class="bf-unit ms-2" style="font-weight:600;color:#222;min-width:58px;">Hall No.</span>
          <div style="width:130px;"><span class="bf-input"><?= htmlspecialchars($hallNo) ?></span></div>
        </div>

        <!-- Show Date + Booth No. -->
        <div class="brow">
          <span class="blabel">Show Date</span>
          <div class="bfield"><span class="bf-input"><?= htmlspecialchars($eventDate) ?></span></div>
          <span class="bf-unit ms-2" style="font-weight:600;color:#222;min-width:58px;">Booth No.</span>
          <div style="width:130px;"><span class="bf-input"><?= htmlspecialchars($boothNo) ?></span></div>
        </div>

        <!-- Booth Size + Total Area -->
        <div class="brow">
          <span class="blabel">Booth Size</span>
          <span class="bf-sm" style="width:62px;"><?= htmlspecialchars($boothW) ?></span>
          <span class="bf-unit">x</span>
          <span class="bf-sm" style="width:62px;"><?= htmlspecialchars($boothH) ?></span>
          <span class="bf-unit">Sqm/ft</span>
          <span class="blabel ms-3" style="min-width:90px;">Total Area:</span>
          <span class="bf-sm" style="width:72px;"><?= htmlspecialchars($totalArea) ?></span>
          <span class="bf-unit">Sqm/ft</span>
        </div>

        <!-- Stand Orientation -->
        <div class="brow">
          <span class="blabel">Stand Orientation</span>
          <div class="bfield">
            <div class="bcheck-group">
              <label class="bcheck-item"><input type="checkbox" <?= briefChk($standType,'Island') ?> disabled> Island</label>
              <label class="bcheck-item"><input type="checkbox" <?= briefChk($standType,'3 Sides Open') ?> disabled> 3 Sides Open</label>
              <label class="bcheck-item"><input type="checkbox" <?= briefChk($standType,'2 Sides Open') ?> disabled> 2 Sides Open</label>
              <label class="bcheck-item"><input type="checkbox" <?= briefChk($standType,'1 Side Open') ?> disabled> 1 Side Open</label>
            </div>
          </div>
        </div>

        <!-- Additional Requirements heading -->
        <div class="brief-section-heading">Additional Requirements of the Design</div>

        <!-- Stand Requirement -->
        <div class="bstand-row">
          <span class="blabel">Stand Requirement</span>
          <div class="bcheck-group flex-wrap">
            <label class="bcheck-item"><input type="checkbox" <?= briefChk($standReq,'Custom') ?> disabled> Custom</label>
            <label class="bcheck-item"><input type="checkbox" <?= briefChk($standReq,'Modular') ?> disabled> Modular</label>
            <label class="bcheck-item"><input type="checkbox" <?= briefChk($standReq,'Cust+Mod') ?> disabled> Cust+Mod</label>
            <label class="bcheck-item"><input type="checkbox" <?= briefChk($standReq,'Standee') ?> disabled> Standee</label>
          </div>
        </div>

        <!-- Feature table row 1 -->
        <table class="bftable">
          <thead><tr><th>Floor Type</th><th>Open Discussion Area</th><th>Digital Assets (LED + TVs)</th></tr></thead>
          <tbody><tr>
            <td><div class="bcheck-group">
              <label class="bcheck-item"><input type="radio" name="v_ft" <?= briefRad($floorType,'Carpet') ?> disabled> Carpet</label>
              <label class="bcheck-item"><input type="radio" name="v_ft" <?= briefRad($floorType,'Laminate') ?> disabled> Laminate</label>
            </div></td>
            <td><div class="bcheck-group">
              <label class="bcheck-item"><input type="radio" name="v_od" <?= briefRad($openDisc,'Yes') ?> disabled> Yes</label>
              <label class="bcheck-item"><input type="radio" name="v_od" <?= briefRad($openDisc,'No') ?> disabled> No</label>
            </div></td>
            <td><div class="bcheck-group">
              <label class="bcheck-item"><input type="radio" name="v_da" <?= briefRad($digAssets,'Yes') ?> disabled> Yes</label>
              <label class="bcheck-item"><input type="radio" name="v_da" <?= briefRad($digAssets,'No') ?> disabled> No</label>
            </div></td>
          </tr></tbody>
        </table>

        <!-- Feature table row 2 -->
        <table class="bftable">
          <thead><tr><th>Demo Station</th><th>Nos. Open Discussion Area</th><th>Touch Screen Kiosk</th></tr></thead>
          <tbody><tr>
            <td><div class="bcheck-group">
              <label class="bcheck-item"><input type="radio" name="v_ds" <?= briefRad($demoSt,'1') ?> disabled> 1</label>
              <label class="bcheck-item"><input type="radio" name="v_ds" <?= briefRad($demoSt,'2') ?> disabled> 2</label>
              <label class="bcheck-item"><input type="radio" name="v_ds" <?= briefRad($demoSt,'3') ?> disabled> 3</label>
              <label class="bcheck-item"><input type="radio" name="v_ds" <?= briefRad($demoSt,'4') ?> disabled> 4</label>
            </div></td>
            <td><div class="bcheck-group">
              <label class="bcheck-item"><input type="radio" name="v_no" <?= briefRad($nosOda,'1') ?> disabled> 1</label>
              <label class="bcheck-item"><input type="radio" name="v_no" <?= briefRad($nosOda,'2') ?> disabled> 2</label>
              <label class="bcheck-item"><input type="radio" name="v_no" <?= briefRad($nosOda,'3') ?> disabled> 3</label>
              <label class="bcheck-item"><input type="radio" name="v_no" <?= briefRad($nosOda,'4') ?> disabled> 4</label>
            </div></td>
            <td><div class="bcheck-group">
              <label class="bcheck-item"><input type="radio" name="v_tk" <?= briefRad($touchKiosk,'Yes') ?> disabled> Yes</label>
              <label class="bcheck-item"><input type="radio" name="v_tk" <?= briefRad($touchKiosk,'No') ?> disabled> No</label>
            </div></td>
          </tr></tbody>
        </table>

        <!-- Feature table row 3 -->
        <table class="bftable">
          <thead><tr><th>Meeting Room</th><th>Total Nos. of MR</th><th>Booth Engagement</th></tr></thead>
          <tbody><tr>
            <td><div class="bcheck-group">
              <label class="bcheck-item"><input type="radio" name="v_mr" <?= briefRad($meetRoom,'Yes') ?> disabled> Yes</label>
              <label class="bcheck-item"><input type="radio" name="v_mr" <?= briefRad($meetRoom,'No') ?> disabled> No</label>
            </div></td>
            <td><div class="bcheck-group">
              <label class="bcheck-item"><input type="radio" name="v_tm" <?= briefRad($totalMr,'1') ?> disabled> 1</label>
              <label class="bcheck-item"><input type="radio" name="v_tm" <?= briefRad($totalMr,'2') ?> disabled> 2</label>
              <label class="bcheck-item"><input type="radio" name="v_tm" <?= briefRad($totalMr,'3') ?> disabled> 3</label>
              <label class="bcheck-item"><input type="radio" name="v_tm" <?= briefRad($totalMr,'4') ?> disabled> 4</label>
            </div></td>
            <td><div class="bcheck-group">
              <label class="bcheck-item"><input type="radio" name="v_be" <?= briefRad($boothEng,'Yes') ?> disabled> Yes</label>
              <label class="bcheck-item"><input type="radio" name="v_be" <?= briefRad($boothEng,'No') ?> disabled> No</label>
            </div></td>
          </tr></tbody>
        </table>

        <?php if (!empty($d['additional_notes'])): ?>
        <div class="bnotes-view"><?= nl2br(htmlspecialchars($d['additional_notes'])) ?></div>
        <?php endif; ?>

        <!-- Design Delivery Target -->
        <div class="brow" style="border-top:1px solid #e8e8e8;margin-top:0.5rem;">
          <span class="blabel">Design Delivery Target</span>
          <div class="bfield">
            <div class="bcheck-group">
              <label class="bcheck-item"><input type="checkbox" <?= briefChk($delivery,'Urgent') ?> disabled> Urgent</label>
              <label class="bcheck-item"><input type="checkbox" <?= briefChk($delivery,'3 Working Days') ?> disabled> 3 Working Days</label>
              <label class="bcheck-item"><input type="checkbox" <?= briefChk($delivery,'7 Working Days') ?> disabled> 7 Working Days</label>
              <label class="bcheck-item"><input type="checkbox" <?= briefChk($delivery,'Relaxed') ?> disabled> Relaxed</label>
            </div>
          </div>
        </div>

        <div class="brief-footer-text">Additional information and inputs can be attached along with this form</div>
        <div class="brief-footer-bar"></div>
      </div><!-- /.brief-doc -->

      <!-- ---- Files, Costs, Change Requests, Project Info ---- -->
      <div class="row g-3 no-print">
        <div class="col-12 col-lg-8">

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
                  <td class="small text-muted"><?= date('d M Y H:i', strtotime($c['created_at'])) ?></td>
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
            <div class="mb-2"><strong>Submitted:</strong> <?= date('d M Y H:i', strtotime($project['created_at'])) ?></div>
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
              <div class="small fw-semibold"><?= date('d M Y H:i', strtotime($cr['created_at'])) ?> – <?= getStatusBadge($cr['status']) ?></div>
              <div class="small mt-1"><?= nl2br(htmlspecialchars($cr['description'])) ?></div>
              <?php if (!empty($cr['notes'])): ?>
              <div class="small text-muted mt-1"><em><?= nl2br(htmlspecialchars($cr['notes'])) ?></em></div>
              <?php endif; ?>
              <?php if (!empty($cr['files'])): ?>
              <div class="mt-2">
                <?php foreach ($cr['files'] as $f): ?>
                <div class="d-flex align-items-center gap-2 mb-1">
                  <span class="badge bg-secondary text-uppercase" style="font-size:.7rem;"><?= htmlspecialchars(strtoupper(pathinfo($f['original_name'], PATHINFO_EXTENSION))) ?></span>
                  <span class="small text-truncate" style="max-width:200px;" title="<?= htmlspecialchars($f['original_name']) ?>"><?= htmlspecialchars($f['original_name']) ?></span>
                  <span class="small text-muted"><?= $uploader->formatFileSize($f['file_size'] ?? 0) ?></span>
                  <a href="../api/file-upload.php?action=download&id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-1" title="Download"><i class="fa fa-download"></i></a>
                </div>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <!-- Design Review History -->
          <?php if ($reviews): ?>
          <div class="form-section mt-3">
            <div class="form-section-title"><i class="fa fa-history text-buzz"></i> Design Review History</div>
            <?php foreach ($reviews as $r): ?>
            <div class="mb-2 p-2 bg-light rounded">
              <div class="d-flex justify-content-between align-items-center">
                <?= getStatusBadge($r['action']) ?>
                <small class="text-muted"><?= date('d M Y H:i', strtotime($r['reviewed_at'])) ?></small>
              </div>
              <?php if (!empty($r['deadline_days'])): ?>
              <div class="small mt-1"><strong>Timeline:</strong> <?= (int)$r['deadline_days'] ?> day<?= $r['deadline_days'] != 1 ? 's' : '' ?></div>
              <?php endif; ?>
              <?php if (!empty($r['remarks'])): ?>
              <div class="small mt-1"><strong>Comments:</strong> <?= nl2br(htmlspecialchars($r['remarks'])) ?></div>
              <?php endif; ?>
              <?php if (!empty($r['rejection_reason'])): ?>
              <div class="small mt-1"><strong>Reason:</strong> <?= nl2br(htmlspecialchars($r['rejection_reason'])) ?></div>
              <?php endif; ?>
              <?php if (!empty($r['hold_reason'])): ?>
              <div class="small mt-1"><strong>Hold Reason:</strong> <?= nl2br(htmlspecialchars($r['hold_reason'])) ?></div>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div><!-- /.row -->

    </div><!-- /.brief-wrap -->

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
