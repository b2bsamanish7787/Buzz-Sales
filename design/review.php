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
$changeRequests = $db->fetchAll("SELECT * FROM change_requests WHERE project_id=? ORDER BY created_at DESC", [$projectId]);
foreach ($changeRequests as &$cr) {
    $cr['files'] = $db->fetchAll(
        "SELECT * FROM file_uploads WHERE change_request_id=? ORDER BY created_at ASC",
        [$cr['id']]
    );
}
unset($cr);

/* ---- Helpers ---- */
function parseSRDesign(string $raw): array {
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
// Returns comma-separated list of selected values from a comma-stored field
function selectedFromList(string $stored, array $options): string {
    $items = array_map('trim', explode(',', $stored));
    $sel   = array_filter($options, fn($o) => in_array(trim($o), $items, true));
    return implode(', ', $sel);
}
// For Yes/No or single-value radio stored directly
function selectedVal(string $stored): string {
    return trim($stored);
}

/* ---- Parse stored detail fields ---- */
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
$sr          = parseSRDesign($d['special_requirements'] ?? '');
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
<style>
/* ===== PROJECT BRIEF FORM – view styles ===== */
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
.bsel-tags { display:flex; flex-wrap:wrap; gap:0.5rem; align-items:center; }
.bsel-tag  { background:#fdecea; color:#c0392b; border:1px solid #f5b7b1; border-radius:4px; padding:2px 10px; font-size:0.84rem; font-weight:600; }
.bsel-tag.neutral { background:#eef2ff; color:#3b5bdb; border-color:#c5cbe9; }
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
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<div style="padding-top:56px; display:flex;">
  <div class="sidebar">
    <div class="sidebar-section">Design</div>
    <a href="dashboard.php" class="nav-link"><i class="fa fa-tachometer-alt"></i> Dashboard</a>
    <a href="reports.php" class="nav-link"><i class="fa fa-chart-bar"></i> Reports</a>
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
      <!-- Project Brief Document -->
      <div class="col-12 col-lg-7">
        <div class="brief-wrap">
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
                <?php $selReq = selectedFromList($reqType, ['Exhibition','Event','Branding']); ?>
                <?php if ($selReq): ?>
                <div class="bsel-tags">
                  <?php foreach (array_map('trim', explode(',', $selReq)) as $tag): ?>
                  <span class="bsel-tag"><?= htmlspecialchars($tag) ?></span>
                  <?php endforeach; ?>
                </div>
                <?php else: ?><span class="text-muted small">—</span><?php endif; ?>
              </div>
              <span class="bf-unit ms-2" style="font-weight:600;color:#222;min-width:50px;">Budget</span>
              <span class="bf-sm" style="min-width:90px;"><?= htmlspecialchars($budgetAmt) ?></span>
              <?php if ($budgetCur): ?><span class="bf-sm" style="min-width:55px;"><?= htmlspecialchars($budgetCur) ?></span><?php endif; ?>
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
                <?php $selSO = selectedFromList($standType, ['Island','3 Sides Open','2 Sides Open','1 Side Open']); ?>
                <?php if ($selSO): ?>
                <div class="bsel-tags">
                  <?php foreach (array_map('trim', explode(',', $selSO)) as $tag): ?>
                  <span class="bsel-tag"><?= htmlspecialchars($tag) ?></span>
                  <?php endforeach; ?>
                </div>
                <?php else: ?><span class="text-muted small">—</span><?php endif; ?>
              </div>
            </div>

            <!-- Additional Requirements heading -->
            <div class="brief-section-heading">Additional Requirements of the Design</div>

            <!-- Stand Requirement -->
            <div class="bstand-row">
              <span class="blabel">Stand Requirement</span>
              <?php $selSR = selectedFromList($standReq, ['Custom','Modular','Cust+Mod','Standee']); ?>
              <?php if ($selSR): ?>
              <div class="bsel-tags">
                <?php foreach (array_map('trim', explode(',', $selSR)) as $tag): ?>
                <span class="bsel-tag neutral"><?= htmlspecialchars($tag) ?></span>
                <?php endforeach; ?>
              </div>
              <?php else: ?><span class="text-muted small">—</span><?php endif; ?>
            </div>

            <!-- Feature table row 1 -->
            <table class="bftable">
              <thead><tr><th>Floor Type</th><th>Open Discussion Area</th><th>Digital Assets (LED + TVs)</th></tr></thead>
              <tbody><tr>
                <td><?php $v = selectedVal($floorType); echo $v ? '<span class="bsel-tag">'.htmlspecialchars($v).'</span>' : '<span class="text-muted">—</span>'; ?></td>
                <td><?php $v = selectedVal($openDisc);  echo $v ? '<span class="bsel-tag">'.htmlspecialchars($v).'</span>' : '<span class="text-muted">—</span>'; ?></td>
                <td><?php $v = selectedVal($digAssets); echo $v ? '<span class="bsel-tag">'.htmlspecialchars($v).'</span>' : '<span class="text-muted">—</span>'; ?></td>
              </tr></tbody>
            </table>

            <!-- Feature table row 2 -->
            <table class="bftable">
              <thead><tr><th>Demo Station</th><th>Nos. Open Discussion Area</th><th>Touch Screen Kiosk</th></tr></thead>
              <tbody><tr>
                <td><?php $v = selectedVal($demoSt);    echo $v ? '<span class="bsel-tag neutral">'.htmlspecialchars($v).'</span>' : '<span class="text-muted">—</span>'; ?></td>
                <td><?php $v = selectedVal($nosOda);    echo $v ? '<span class="bsel-tag neutral">'.htmlspecialchars($v).'</span>' : '<span class="text-muted">—</span>'; ?></td>
                <td><?php $v = selectedVal($touchKiosk);echo $v ? '<span class="bsel-tag">'.htmlspecialchars($v).'</span>' : '<span class="text-muted">—</span>'; ?></td>
              </tr></tbody>
            </table>

            <!-- Feature table row 3 -->
            <table class="bftable">
              <thead><tr><th>Meeting Room</th><th>Total Nos. of MR</th><th>Booth Engagement</th></tr></thead>
              <tbody><tr>
                <td><?php $v = selectedVal($meetRoom);  echo $v ? '<span class="bsel-tag">'.htmlspecialchars($v).'</span>' : '<span class="text-muted">—</span>'; ?></td>
                <td><?php $v = selectedVal($totalMr);   echo $v ? '<span class="bsel-tag neutral">'.htmlspecialchars($v).'</span>' : '<span class="text-muted">—</span>'; ?></td>
                <td><?php $v = selectedVal($boothEng);  echo $v ? '<span class="bsel-tag">'.htmlspecialchars($v).'</span>' : '<span class="text-muted">—</span>'; ?></td>
              </tr></tbody>
            </table>

            <?php if (!empty($d['additional_notes'])): ?>
            <div class="bnotes-view"><?= nl2br(htmlspecialchars($d['additional_notes'])) ?></div>
            <?php endif; ?>

            <!-- Design Delivery Target -->
            <div class="brow" style="border-top:1px solid #e8e8e8;margin-top:0.5rem;">
              <span class="blabel">Design Delivery Target</span>
              <div class="bfield">
                <?php $selDDT = selectedFromList($delivery, ['Urgent','3 Working Days','7 Working Days','Relaxed']); ?>
                <?php if ($selDDT): ?>
                <div class="bsel-tags">
                  <?php foreach (array_map('trim', explode(',', $selDDT)) as $tag): ?>
                  <span class="bsel-tag"><?= htmlspecialchars($tag) ?></span>
                  <?php endforeach; ?>
                </div>
                <?php else: ?><span class="text-muted small">—</span><?php endif; ?>
              </div>
            </div>

            <div class="brief-footer-text">Additional information and inputs can be attached along with this form</div>
            <div class="brief-footer-bar"></div>
          </div><!-- /.brief-doc -->

          <!-- Reference Files -->
          <?php if ($refFiles): ?>
          <div class="form-section mt-3">
            <div class="form-section-title"><i class="fa fa-paperclip text-buzz"></i> Reference Files</div>
            <?php foreach ($refFiles as $f): ?>
            <div class="file-item">
              <span class="badge bg-secondary"><?= strtoupper(pathinfo($f['original_name'], PATHINFO_EXTENSION)) ?></span>
              <span class="file-name"><?= htmlspecialchars($f['original_name']) ?></span>
              <span class="file-size"><?= $uploader->formatFileSize($f['file_size'] ?? 0) ?></span>
              <a href="../api/file-upload.php?action=view&id=<?= $f['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="View"><i class="fa fa-eye"></i> View</a>
              <a href="../api/file-upload.php?action=download&id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-primary" title="Download"><i class="fa fa-download"></i> Download</a>
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

          <!-- Change Requests -->
          <?php if ($changeRequests): ?>
          <div class="form-section mt-3">
            <div class="form-section-title"><i class="fa fa-exchange-alt text-buzz"></i> Client Change Requests</div>
            <?php foreach ($changeRequests as $cr): ?>
            <div class="mb-2 p-2 bg-light rounded">
              <div class="small fw-semibold"><?= date('d M Y', strtotime($cr['created_at'])) ?> – <?= getStatusBadge($cr['status']) ?></div>
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
                  <a href="../api/file-upload.php?action=view&id=<?= $f['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary py-0 px-1" title="View"><i class="fa fa-eye"></i></a>
                  <a href="../api/file-upload.php?action=download&id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-1" title="Download"><i class="fa fa-download"></i></a>
                </div>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div><!-- /.brief-wrap -->
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
