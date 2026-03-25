<?php
/**
 * Shared navbar include.
 * Expects: $user (array with id, username, full_name, role), $notifier (Notifier instance)
 * $activeNav – current nav item slug
 */
$unreadCount = isset($notifier) ? $notifier->getUnreadCount($user['id'], $user['role']) : 0;
?>
<nav class="navbar buzz-navbar fixed-top">
  <div class="container-fluid">
    <span class="brand-name"><span>🐝 BUZZ</span>NATION &nbsp;<small class="fw-normal opacity-75" style="font-size:.7rem;">Client Portal</small></span>
    <div class="d-flex align-items-center gap-3">
      <!-- Notifications -->
      <div class="dropdown">
        <button class="btn btn-link text-white p-0 notif-bell" data-bs-toggle="dropdown" aria-expanded="false">
          <i class="fa fa-bell fa-lg"></i>
          <span class="notif-badge" <?= $unreadCount === 0 ? 'style="display:none"' : '' ?>>
            <?= $unreadCount > 99 ? '99+' : $unreadCount ?>
          </span>
        </button>
        <div class="dropdown-menu dropdown-menu-end notif-dropdown shadow">
          <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
            <span class="fw-bold">Notifications</span>
            <button class="btn btn-link btn-sm p-0 text-muted" id="markAllRead">Mark all read</button>
          </div>
          <div id="notifList">
            <div class="p-3 text-center text-muted small">Loading…</div>
          </div>
        </div>
      </div>
      <!-- User Info -->
      <div class="dropdown">
        <button class="btn btn-link text-white p-0 d-flex align-items-center gap-2" data-bs-toggle="dropdown">
          <div class="rounded-circle bg-buzz d-flex align-items-center justify-content-center" style="width:34px;height:34px;font-weight:700;font-size:.9rem;">
            <?= strtoupper(substr($user['full_name'] ?: $user['username'], 0, 1)) ?>
          </div>
          <div class="d-none d-md-block text-start">
            <div style="font-size:.85rem;font-weight:600;line-height:1.1;"><?= htmlspecialchars($user['full_name'] ?: $user['username']) ?></div>
            <span class="role-badge <?= htmlspecialchars($user['role']) ?>"><?= ucfirst($user['role']) ?></span>
          </div>
          <i class="fa fa-chevron-down ms-1" style="font-size:.7rem;opacity:.7;"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow">
          <li><span class="dropdown-item-text text-muted small"><?= htmlspecialchars($user['email'] ?? '') ?></span></li>
          <li><hr class="dropdown-divider my-1"></li>
          <li><a class="dropdown-item" href="<?= $logoutUrl ?? '../public/logout.php' ?>"><i class="fa fa-sign-out-alt me-2 text-danger"></i>Logout</a></li>
        </ul>
      </div>
    </div>
  </div>
</nav>
