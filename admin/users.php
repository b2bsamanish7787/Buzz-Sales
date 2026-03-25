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

$users = $db->fetchAll("SELECT * FROM users ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Management – <?= APP_NAME ?></title>
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
    <a href="users.php" class="nav-link active"><i class="fa fa-users"></i> Users</a>
    <a href="activity-logs.php" class="nav-link"><i class="fa fa-history"></i> Activity Logs</a>
    <a href="notifications-config.php" class="nav-link"><i class="fa fa-envelope"></i> Notification Emails</a>
  </div>

  <div class="main-content">
    <div class="page-header">
      <h1><i class="fa fa-users text-buzz me-2"></i>User Management</h1>
      <button class="btn btn-buzz" data-bs-toggle="modal" data-bs-target="#createUserModal">
        <i class="fa fa-plus me-1"></i>Add User
      </button>
    </div>

    <div id="alertBox"></div>

    <div class="table-card">
      <div class="p-3 border-bottom d-flex gap-2 flex-wrap align-items-center">
        <input type="text" id="searchInput" class="form-control" style="max-width:280px;" placeholder="Search users…">
        <select id="roleFilter" class="form-select" style="max-width:160px;">
          <option value="">All Roles</option>
          <option>admin</option><option>sales</option><option>design</option><option>operations</option>
        </select>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>#</th><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th><th>Actions</th>
            </tr>
          </thead>
          <tbody id="usersTable">
            <?php foreach ($users as $u): ?>
            <tr class="filterable-row" data-status="<?= $u['status'] ?>" data-role="<?= $u['role'] ?>">
              <td><?= $u['id'] ?></td>
              <td class="fw-semibold"><?= htmlspecialchars($u['full_name'] ?: '—') ?></td>
              <td><?= htmlspecialchars($u['username']) ?></td>
              <td><?= htmlspecialchars($u['email']) ?></td>
              <td><span class="role-badge <?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span></td>
              <td>
                <?php if ($u['status'] === 'active'): ?>
                  <span class="badge bg-success">Active</span>
                <?php else: ?>
                  <span class="badge bg-secondary">Inactive</span>
                <?php endif; ?>
              </td>
              <td class="small text-muted"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
              <td>
                <div class="btn-group btn-group-sm">
                  <button class="btn btn-outline-primary" onclick="editUser(<?= htmlspecialchars(json_encode($u)) ?>)" title="Edit"><i class="fa fa-edit"></i></button>
                  <button class="btn btn-outline-warning" onclick="changePassword(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username']) ?>')" title="Change Password"><i class="fa fa-key"></i></button>
                  <?php if ($u['id'] !== $user['id']): ?>
                  <button class="btn btn-outline-danger" onclick="deleteUser(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username']) ?>')" title="Deactivate"><i class="fa fa-ban"></i></button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Create User Modal -->
<div class="modal fade" id="createUserModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Add New User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form id="createUserForm">
        <div class="modal-body">
          <input type="hidden" name="action" value="create_user">
          <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Full Name</label>
              <input type="text" class="form-control" name="full_name" placeholder="Full Name">
            </div>
            <div class="col-6">
              <label class="form-label">Username <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="username" required>
            </div>
            <div class="col-6">
              <label class="form-label">Role <span class="text-danger">*</span></label>
              <select class="form-select" name="role" required>
                <option value="">Select Role</option>
                <option value="admin">Admin</option>
                <option value="sales">Sales</option>
                <option value="design">Design</option>
                <option value="operations">Operations</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Email <span class="text-danger">*</span></label>
              <input type="email" class="form-control" name="email" required>
            </div>
            <div class="col-12">
              <label class="form-label">Password <span class="text-danger">*</span></label>
              <input type="password" class="form-control" name="password" required minlength="6">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-buzz">Create User</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Edit User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form id="editUserForm">
        <div class="modal-body">
          <input type="hidden" name="action" value="edit_user">
          <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
          <input type="hidden" name="user_id" id="editUserId">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Full Name</label>
              <input type="text" class="form-control" name="full_name" id="editFullName">
            </div>
            <div class="col-6">
              <label class="form-label">Role</label>
              <select class="form-select" name="role" id="editRole">
                <option value="admin">Admin</option>
                <option value="sales">Sales</option>
                <option value="design">Design</option>
                <option value="operations">Operations</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label">Status</label>
              <select class="form-select" name="status" id="editStatus">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Email</label>
              <input type="email" class="form-control" name="email" id="editEmail">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-buzz">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Change Password Modal -->
<div class="modal fade" id="changePwdModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Change Password</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form id="changePwdForm">
        <div class="modal-body">
          <input type="hidden" name="action" value="change_password">
          <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
          <input type="hidden" name="user_id" id="pwdUserId">
          <p class="text-muted small">Changing password for: <strong id="pwdUsername"></strong></p>
          <div class="mb-3">
            <label class="form-label">New Password</label>
            <input type="password" class="form-control" name="new_password" required minlength="6">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-buzz">Update</button>
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

function editUser(u) {
    $('#editUserId').val(u.id);
    $('#editFullName').val(u.full_name);
    $('#editRole').val(u.role);
    $('#editStatus').val(u.status);
    $('#editEmail').val(u.email);
    new bootstrap.Modal('#editUserModal').show();
}

function changePassword(id, username) {
    $('#pwdUserId').val(id);
    $('#pwdUsername').text(username);
    new bootstrap.Modal('#changePwdModal').show();
}

function deleteUser(id, username) {
    BuzzApp.confirm('Deactivate user "' + username + '"? They will no longer be able to log in.', function() {
        BuzzApp.ajax('../api/form-submit.php', {action:'delete_user', user_id:id, csrf_token:BuzzApp.getCsrfToken()}, function(res) {
            BuzzApp.showToast(res.message, res.success ? 'success' : 'danger');
            if (res.success) setTimeout(() => location.reload(), 1200);
        });
    });
}

$('#createUserForm').on('submit', function(e) {
    e.preventDefault();
    BuzzApp.ajax('../api/form-submit.php', $(this).serialize(), function(res) {
        BuzzApp.showToast(res.message, res.success ? 'success' : 'danger');
        if (res.success) { bootstrap.Modal.getInstance('#createUserModal').hide(); setTimeout(() => location.reload(), 1200); }
    });
});

$('#editUserForm').on('submit', function(e) {
    e.preventDefault();
    BuzzApp.ajax('../api/form-submit.php', $(this).serialize(), function(res) {
        BuzzApp.showToast(res.message, res.success ? 'success' : 'danger');
        if (res.success) { bootstrap.Modal.getInstance('#editUserModal').hide(); setTimeout(() => location.reload(), 1200); }
    });
});

$('#changePwdForm').on('submit', function(e) {
    e.preventDefault();
    BuzzApp.ajax('../api/form-submit.php', $(this).serialize(), function(res) {
        BuzzApp.showToast(res.message, res.success ? 'success' : 'danger');
        if (res.success) bootstrap.Modal.getInstance('#changePwdModal').hide();
    });
});

$('#roleFilter').on('change', function() {
    var val = $(this).val();
    $('.filterable-row').each(function() {
        $(this).toggle(!val || $(this).data('role') === val);
    });
});

$('#searchInput').on('keyup', function() {
    var val = $(this).val().toLowerCase();
    $('.filterable-row').each(function() {
        $(this).toggle($(this).text().toLowerCase().includes(val));
    });
});
</script>
</body>
</html>
