<?php /** Event User sign in — Event Code + email + password. */ ?>
<div class="mb-4">
  <h4 class="fw-bold mb-1">Event User sign in</h4>
  <p class="text-muted mb-0">Run the race office &mdash; heats, lane draw, results and the display screens.</p>
</div>

<?php $activeRole = 'user'; require APP_ROOT . '/views/partials/role-tabs.php'; ?>

<div class="sms-card p-4">
  <form method="POST" action="/event-user/login" novalidate>
    <?= csrf() ?>
    <div class="mb-3">
      <label class="form-label" for="event_code">Event Code</label>
      <div class="input-group">
        <span class="input-group-text bg-white"><i class="bi bi-hash"></i></span>
        <input type="text" class="form-control text-uppercase" id="event_code" name="event_code"
               value="<?= e(old('event_code')) ?>" placeholder="RG1A2B3C" autocomplete="off" required autofocus>
      </div>
      <div class="form-text">The code issued for your regatta &mdash; ask your organiser if unsure.</div>
    </div>

    <div class="mb-3">
      <label class="form-label" for="email">Email address</label>
      <div class="input-group">
        <span class="input-group-text bg-white"><i class="bi bi-envelope"></i></span>
        <input type="email" class="form-control" id="email" name="email"
               value="<?= e(old('email')) ?>" placeholder="you@example.com" autocomplete="username" required>
      </div>
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

<p class="text-muted small text-center mt-3 mb-0">Event administrators sign in <a href="/event-admin/login" class="text-decoration-none">here</a>.</p>
