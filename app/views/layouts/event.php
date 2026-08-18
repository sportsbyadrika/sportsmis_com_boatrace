<?php
/**
 * Event portal chrome, shared by the two per-event roles. Which nav is drawn
 * depends on which session bucket is live:
 *   $_SESSION['event_admin'] -> event configuration + entries
 *   $_SESSION['event_user']  -> race office, gated by held privileges
 * Expects $event (the tenant row) in the view data.
 */
$ev        = $event ?? [];
$eventCode = $ev['code'] ?? '';
$isAdmin   = \Core\Auth::eventAdminCheck();
$actor     = $isAdmin ? \Core\Auth::eventAdmin() : (\Core\Auth::eventUser() ?? []);
$priv      = $actor['privileges'] ?? [];
$logoutUrl = $isAdmin ? '/event-admin/logout' : '/event-user/logout';
$pwUrl     = $isAdmin ? '/event-admin/password' : '/event-user/password';
$homeUrl   = $isAdmin ? '/event-admin/dashboard' : '/event-user/dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e(csrfToken()) ?>">
  <title><?= e($pageTitle ?? 'Event Portal') ?> – SportsMIS® Regatta</title>
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
    <?php $brandHref = $homeUrl; require APP_ROOT . '/views/partials/brand.php'; ?>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#eventNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="eventNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link <?= activeNav($homeUrl) ?>" href="<?= e($homeUrl) ?>">
            <i class="bi bi-speedometer2 me-1"></i>Dashboard
          </a>
        </li>

        <?php if ($isAdmin): ?>
          <li class="nav-item">
            <a class="nav-link <?= activeNav('/event-admin/details') ?>" href="/event-admin/details">
              <i class="bi bi-sliders me-1"></i>Event Details
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= activeNav('/event-admin/teams') ?>" href="/event-admin/teams">
              <i class="bi bi-people me-1"></i>Teams
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= activeNav('/event-admin/registrations') ?>" href="/event-admin/registrations">
              <i class="bi bi-clipboard-check me-1"></i>Registrations
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= activeNav('/event-admin/order-of-events') ?>" href="/event-admin/order-of-events">
              <i class="bi bi-list-ol me-1"></i>Order of Events
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= activeNav('/event-admin/users') ?>" href="/event-admin/users">
              <i class="bi bi-person-badge me-1"></i>Event Users
            </a>
          </li>

        <?php else: ?>
          <?php if (in_array('rounds_heats', $priv, true)): ?>
            <li class="nav-item">
              <a class="nav-link <?= activeNav('/event-user/rounds') ?>" href="/event-user/rounds">
                <i class="bi bi-diagram-3 me-1"></i>Rounds &amp; Heats
              </a>
            </li>
          <?php endif; ?>
          <?php if (in_array('lane_allocation', $priv, true)): ?>
            <li class="nav-item">
              <a class="nav-link <?= activeNav('/event-user/lane-allocation') ?>" href="/event-user/lane-allocation">
                <i class="bi bi-water me-1"></i>Lane Allocation
              </a>
            </li>
          <?php endif; ?>
          <?php if (in_array('result_entry', $priv, true)): ?>
            <li class="nav-item">
              <a class="nav-link <?= activeNav('/event-user/results') ?>" href="/event-user/results">
                <i class="bi bi-stopwatch me-1"></i>Result Entry
              </a>
            </li>
          <?php endif; ?>
          <?php if (in_array('reports', $priv, true)): ?>
            <li class="nav-item">
              <a class="nav-link <?= activeNav('/event-user/reports') ?>" href="/event-user/reports">
                <i class="bi bi-trophy me-1"></i>Reports
              </a>
            </li>
          <?php endif; ?>
          <?php if (in_array('displays', $priv, true)): ?>
            <li class="nav-item">
              <a class="nav-link <?= activeNav('/event-user/displays') ?>" href="/event-user/displays">
                <i class="bi bi-tv me-1"></i>Displays
              </a>
            </li>
          <?php endif; ?>
        <?php endif; ?>
      </ul>

      <div class="d-none d-lg-flex align-items-center me-3 px-3 py-1 rounded-3 bg-primary-subtle text-primary-emphasis">
        <i class="bi bi-hash me-1"></i>
        <span class="small me-1">Event Code:</span>
        <strong><?= e($eventCode) ?></strong>
      </div>

      <ul class="navbar-nav ms-auto align-items-center">
        <li class="nav-item dropdown">
          <a class="nav-link d-flex align-items-center gap-2 sms-avatar-trigger" href="#"
             role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <div class="sms-avatar"><?= e(avatarInitials($actor['name'] ?? $actor['email'] ?? 'U')) ?></div>
            <span class="d-none d-lg-inline text-truncate" style="max-width:170px">
              <?= e($actor['name'] ?? $actor['email'] ?? '') ?>
            </span>
            <i class="bi bi-chevron-down small"></i>
          </a>
          <ul class="dropdown-menu dropdown-menu-end sms-dropdown shadow-sm">
            <li>
              <h6 class="dropdown-header">
                <?= e($actor['email'] ?? '') ?>
                <br><small class="text-muted fw-normal"><?= $isAdmin ? 'Event Admin' : 'Event User' ?></small>
              </h6>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#modalChangePassword">
              <i class="bi bi-key me-2"></i>Change Password
            </a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="<?= e($logoutUrl) ?>">
              <i class="bi bi-box-arrow-right me-2"></i>Logout
            </a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>

<div class="d-lg-none bg-primary-subtle text-primary-emphasis px-3 py-2 small">
  <i class="bi bi-hash me-1"></i><strong>Event Code:</strong> <?= e($eventCode) ?>
  <?php if (!empty($ev['name'])): ?>
    <span class="text-muted ms-2">&middot; <?= e($ev['name']) ?></span>
  <?php endif; ?>
</div>

<main class="sms-main">
  <div class="container-fluid px-4 py-4">
    <?= flashBag() ?>
    <?php require $content; ?>
  </div>
</main>

<footer class="sms-footer mt-auto">
  <div class="container-fluid px-4">
    <div class="small text-muted py-2 d-flex flex-wrap gap-2 justify-content-between">
      <span>&copy; <?= date('Y') ?> SportsByA Tech (OPC) Private Limited
        &middot; Powered by <strong>SportsMIS<sup style="font-size:.7em">&reg;</sup></strong></span>
      <span><?= e($ev['name'] ?? '') ?><?= !empty($ev['name_regional']) ? ' · ' . e($ev['name_regional']) : '' ?></span>
    </div>
  </div>
</footer>

<div class="modal fade" id="modalChangePassword" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="<?= e($pwUrl) ?>">
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
