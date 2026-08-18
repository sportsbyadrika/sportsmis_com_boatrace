<?php
/**
 * Admin chrome — the Super Admin (platform owner) workspace.
 * Expects $content (absolute path to the view) from Controller::renderWith().
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e(csrfToken()) ?>">
  <title><?= e($pageTitle ?? 'Admin') ?> – SportsMIS® Regatta</title>
  <link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="/assets/css/app.css?v=<?= @filemtime(PUBLIC_ROOT . '/assets/css/app.css') ?: time() ?>" rel="stylesheet">
</head>
<body class="sms-body">

<nav class="navbar navbar-expand-lg sms-navbar sticky-top">
  <div class="container-fluid px-4">
    <?php $brandHref = '/admin/dashboard'; require APP_ROOT . '/views/partials/brand.php'; ?>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link <?= activeNav('/admin/dashboard') ?>" href="/admin/dashboard">
            <i class="bi bi-speedometer2 me-1"></i>Dashboard
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= activeNav('/admin/events') ?>" href="/admin/events">
            <i class="bi bi-calendar-event me-1"></i>Events
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= activeNav('/admin/accounts') ?>" href="/admin/accounts">
            <i class="bi bi-person-badge me-1"></i>Event Admins
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= activeNav('/admin/settings') ?>" href="/admin/settings">
            <i class="bi bi-gear me-1"></i>Settings
          </a>
        </li>
      </ul>

      <ul class="navbar-nav ms-auto align-items-center">
        <li class="nav-item dropdown">
          <a class="nav-link d-flex align-items-center gap-2 sms-avatar-trigger" href="#"
             role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <div class="sms-avatar"><?= e(avatarInitials(\Core\Auth::user()['name'] ?? 'Admin')) ?></div>
            <span class="d-none d-lg-inline text-truncate" style="max-width:150px">
              <?= e(\Core\Auth::user()['name'] ?? '') ?>
            </span>
            <i class="bi bi-chevron-down small"></i>
          </a>
          <ul class="dropdown-menu dropdown-menu-end sms-dropdown shadow-sm">
            <li>
              <h6 class="dropdown-header">
                <?= e(\Core\Auth::user()['email'] ?? '') ?>
                <br><small class="text-muted fw-normal">Super Admin</small>
              </h6>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#modalChangePassword">
              <i class="bi bi-key me-2"></i>Change Password
            </a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="/logout"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>

<main class="sms-main">
  <div class="container-fluid px-4 py-4">
    <?= flashBag() ?>
    <?php require $content; ?>
  </div>
</main>

<footer class="sms-footer mt-auto">
  <div class="container-fluid px-4">
    <div class="row align-items-center">
      <div class="col-md-6 text-center text-md-start">
        <span class="text-muted small">&copy; <?= date('Y') ?> SportsByA Tech (OPC) Private Limited
          &middot; Powered by <strong>SportsMIS<sup style="font-size:.7em">&reg;</sup></strong>
        </span>
      </div>
      <div class="col-md-6 text-center text-md-end mt-2 mt-md-0">
        <span class="text-muted small">Regatta &middot; Boat Race Event Management</span>
      </div>
    </div>
  </div>
</footer>

<!-- Change Password -->
<div class="modal fade" id="modalChangePassword" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="/account/password">
        <?= csrf() ?>
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-key me-2"></i>Change Password</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Current Password</label>
            <input type="password" name="current_password" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">New Password</label>
            <input type="password" name="password" class="form-control" minlength="8" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Confirm New Password</label>
            <input type="password" name="password_confirmation" class="form-control" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Update Password</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/app.js?v=<?= @filemtime(PUBLIC_ROOT . '/assets/js/app.js') ?: time() ?>"></script>
</body>
</html>
