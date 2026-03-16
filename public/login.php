<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/Security.php';

Security::setSecurityHeaders();
$auth = new Auth();

if ($auth->isLoggedIn()) {
    $role = $_SESSION['role'] ?? '';
    switch ($role) {
        case 'admin':      header('Location: ../admin/index.php'); break;
        case 'sales':      header('Location: ../sales/dashboard.php'); break;
        case 'design':     header('Location: ../design/dashboard.php'); break;
        case 'operations': header('Location: ../operations/dashboard.php'); break;
    }
    exit;
}

$csrfToken = Security::getCSRFToken();
$error     = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login – <?= htmlspecialchars(APP_NAME) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link rel="stylesheet" href="css/style.css">
<style>
body { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%); min-height: 100vh; display:flex; align-items:center; }
.login-card { background: #fff; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.4); overflow:hidden; }
.login-brand { background: linear-gradient(135deg, #e63946, #c1121f); padding: 40px 30px; text-align:center; color:#fff; }
.login-brand h1 { font-size:1.6rem; font-weight:700; margin:0; }
.login-brand p { margin:8px 0 0; opacity:.85; font-size:.9rem; }
.login-body { padding: 40px 30px; }
.buzz-logo { font-size: 3rem; margin-bottom: 10px; }
.btn-login { background: linear-gradient(135deg, #e63946, #c1121f); border:none; padding: 12px; font-size:1rem; font-weight:600; color:#fff; }
.btn-login:hover { background: linear-gradient(135deg, #c1121f, #9d0208); color:#fff; }
.form-control:focus { border-color: #e63946; box-shadow: 0 0 0 0.2rem rgba(230,57,70,.25); }
#loginAlert { display:none; }
</style>
</head>
<body>
<div class="container">
  <div class="row justify-content-center">
    <div class="col-12 col-sm-10 col-md-7 col-lg-5">
      <div class="login-card">
        <div class="login-brand">
          <div class="buzz-logo">🐝</div>
          <h1>BUZZNATION</h1>
          <p>Client Requirement Portal</p>
        </div>
        <div class="login-body">
          <h4 class="text-center mb-4 text-dark fw-bold">Sign In to Your Account</h4>
          <div class="alert alert-danger" id="loginAlert" role="alert"></div>
          <form id="loginForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <div class="mb-3">
              <label class="form-label fw-semibold">Username or Email</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fa fa-user text-muted"></i></span>
                <input type="text" class="form-control" name="username" id="username" placeholder="Enter username or email" required autocomplete="username">
              </div>
              <div class="invalid-feedback">Please enter your username or email.</div>
            </div>
            <div class="mb-4">
              <label class="form-label fw-semibold">Password</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fa fa-lock text-muted"></i></span>
                <input type="password" class="form-control" name="password" id="password" placeholder="Enter password" required autocomplete="current-password">
                <button class="btn btn-outline-secondary" type="button" id="togglePwd"><i class="fa fa-eye"></i></button>
              </div>
              <div class="invalid-feedback">Please enter your password.</div>
            </div>
            <button type="submit" class="btn btn-login w-100 rounded-pill" id="loginBtn">
              <span id="loginSpinner" class="spinner-border spinner-border-sm d-none me-2"></span>
              Sign In
            </button>
          </form>
          <p class="text-center text-muted mt-4 mb-0" style="font-size:.8rem;">
            &copy; <?= date('Y') ?> Buzznation. All rights reserved.
          </p>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
$('#togglePwd').on('click', function() {
    var pwd = $('#password');
    var type = pwd.attr('type') === 'password' ? 'text' : 'password';
    pwd.attr('type', type);
    $(this).find('i').toggleClass('fa-eye fa-eye-slash');
});

$('#loginForm').on('submit', function(e) {
    e.preventDefault();
    var form = this;
    if (!form.checkValidity()) {
        $(form).addClass('was-validated');
        return;
    }
    var btn = $('#loginBtn');
    btn.prop('disabled', true);
    $('#loginSpinner').removeClass('d-none');
    $('#loginAlert').hide();

    $.ajax({
        url: '../api/form-submit.php?action=login',
        method: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(res) {
            if (res.success) {
                window.location.href = res.redirect;
            } else {
                $('#loginAlert').text(res.message || 'Login failed. Please try again.').show();
                btn.prop('disabled', false);
                $('#loginSpinner').addClass('d-none');
            }
        },
        error: function() {
            $('#loginAlert').text('Server error. Please try again.').show();
            btn.prop('disabled', false);
            $('#loginSpinner').addClass('d-none');
        }
    });
});
</script>
</body>
</html>
