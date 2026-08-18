<?php /** Super Admin sign-in. */ ?>
<div class="mb-4">
  <h4 class="fw-bold mb-1">Welcome back</h4>
  <p class="text-muted mb-0">Sign in to the SportsMIS Regatta platform.</p>
</div>

<?php $activeRole = 'admin'; require APP_ROOT . '/views/partials/role-tabs.php'; ?>

<div class="sms-card p-4">
  <form method="POST" action="/login" novalidate>
    <?= csrf() ?>
    <div class="mb-3">
      <label class="form-label" for="email">Email address</label>
      <div class="input-group">
        <span class="input-group-text bg-white"><i class="bi bi-envelope"></i></span>
        <input type="email" class="form-control <?= hasError('email') ?>" id="email" name="email"
               value="<?= e(old('email')) ?>" placeholder="you@example.com" autocomplete="username" required autofocus>
      </div>
      <?= fieldError('email') ?>
    </div>

    <div class="mb-3">
      <label class="form-label" for="password">Password</label>
      <div class="input-group">
        <span class="input-group-text bg-white"><i class="bi bi-lock"></i></span>
        <input type="password" class="form-control" id="password" name="password"
               placeholder="Your password" autocomplete="current-password" required>
      </div>
    </div>

    <button type="submit" class="btn btn-primary w-100 py-2">
      <i class="bi bi-box-arrow-in-right me-1"></i>Sign in
    </button>
  </form>
</div>

<p class="text-muted small text-center mt-3 mb-0">
  Running an event? Use the <a href="/event-admin/login" class="text-decoration-none">Event Admin</a>
  or <a href="/event-user/login" class="text-decoration-none">Event User</a> sign-in with your Event Code.
</p>
