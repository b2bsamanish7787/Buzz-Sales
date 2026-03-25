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
$auth->requireAuth(ROLE_ADMIN);
$user      = $auth->getUser();
$db        = Database::getInstance();
$notifier  = new Notifier();
$logoutUrl = '../public/logout.php';
$csrfToken = $auth->generateCSRFToken();

$emailsByRole = [];
foreach (['admin','sales','design','operations'] as $role) {
    $emailsByRole[$role] = $db->fetchAll("SELECT * FROM notification_emails WHERE role = ? ORDER BY created_at DESC", [$role]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notification Emails – <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link rel="stylesheet" href="../public/css/style.css">
<meta name="csrf-token" content="<?= $csrfToken ?>">
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<div style="padding-top:56px; display:flex;">
  <div class="sidebar">
    <div class="sidebar-section">Administration</div>
    <a href="index.php" class="nav-link"><i class="fa fa-tachometer-alt"></i> Dashboard</a>
    <a href="users.php" class="nav-link"><i class="fa fa-users"></i> Users</a>
    <a href="activity-logs.php" class="nav-link"><i class="fa fa-history"></i> Activity Logs</a>
    <a href="notifications-config.php" class="nav-link active"><i class="fa fa-envelope"></i> Notification Emails</a>
  </div>
  <div class="main-content">
    <div class="page-header">
      <h1><i class="fa fa-envelope text-buzz me-2"></i>Notification Email Configuration</h1>
      <button class="btn btn-buzz" data-bs-toggle="modal" data-bs-target="#addEmailModal">
        <i class="fa fa-plus me-1"></i>Add Email
      </button>
    </div>
    <div id="alertBox"></div>

    <div class="row g-3">
      <?php foreach ($emailsByRole as $role => $emails): ?>
      <div class="col-12 col-lg-6">
        <div class="table-card">
          <div class="p-3 border-bottom d-flex align-items-center gap-2">
            <span class="role-badge <?= $role ?>"><?= ucfirst($role) ?></span>
            <h6 class="mb-0 fw-bold">Emails</h6>
            <span class="badge bg-secondary ms-auto"><?= count($emails) ?></span>
          </div>
          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <thead><tr><th>Email</th><th>Status</th><th>Actions</th></tr></thead>
              <tbody>
                <?php if (empty($emails)): ?>
                <tr><td colspan="3" class="text-center text-muted py-3">No emails configured.</td></tr>
                <?php else: foreach ($emails as $e): ?>
                <tr>
                  <td><?= htmlspecialchars($e['email']) ?></td>
                  <td>
                    <?= $e['active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?>
                  </td>
                  <td>
                    <div class="btn-group btn-group-sm">
                      <button class="btn btn-outline-secondary" onclick="toggleEmail(<?= $e['id'] ?>)" title="Toggle">
                        <i class="fa fa-<?= $e['active'] ? 'toggle-on' : 'toggle-off' ?>"></i>
                      </button>
                      <button class="btn btn-outline-danger" onclick="deleteEmail(<?= $e['id'] ?>)" title="Delete">
                        <i class="fa fa-trash"></i>
                      </button>
                    </div>
                  </td>
                </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Add Email Modal -->
<div class="modal fade" id="addEmailModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Add Notification Email</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form id="addEmailForm">
        <div class="modal-body">
          <input type="hidden" name="action" value="add_notif_email">
          <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
          <div class="mb-3">
            <label class="form-label">Role</label>
            <select name="role" class="form-select" required>
              <option value="">Select Role</option>
              <option value="admin">Admin</option>
              <option value="sales">Sales</option>
              <option value="design">Design</option>
              <option value="operations">Operations</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-control" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-buzz">Add Email</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../public/js/app.js"></script>
<script>
var BASE_URL = '..';
BuzzApp.initNotifications(<?= $user['id'] ?>);

$('#addEmailForm').on('submit', function(e) {
    e.preventDefault();
    BuzzApp.ajax('../api/form-submit.php', $(this).serialize(), function(res) {
        BuzzApp.showToast(res.message, res.success ? 'success' : 'danger');
        if (res.success) { bootstrap.Modal.getInstance('#addEmailModal').hide(); setTimeout(() => location.reload(), 1000); }
    });
});

function toggleEmail(id) {
    BuzzApp.ajax('../api/form-submit.php', {action:'toggle_notif_email', email_id:id, csrf_token:BuzzApp.getCsrfToken()}, function(res) {
        BuzzApp.showToast(res.message, res.success ? 'success' : 'danger');
        if (res.success) setTimeout(() => location.reload(), 800);
    });
}

function deleteEmail(id) {
    BuzzApp.confirm('Remove this email?', function() {
        BuzzApp.ajax('../api/form-submit.php', {action:'delete_notif_email', email_id:id, csrf_token:BuzzApp.getCsrfToken()}, function(res) {
            BuzzApp.showToast(res.message, res.success ? 'success' : 'danger');
            if (res.success) setTimeout(() => location.reload(), 800);
        });
    });
}
</script>
</body>
</html>
