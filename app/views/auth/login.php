<?php
/**
 * The one sign-in form. Email + password only — the server resolves which
 * account the credentials belong to, so this page reveals nothing about the
 * roles behind it.
 */
?>
<div class="mb-4">
  <h4 class="fw-bold mb-1">Sign in</h4>
  <p class="text-muted mb-0">Welcome back to SportsMIS Regatta.</p>
</div>

<div class="sms-card p-4">
  <form method="POST" action="/login" novalidate>
    <?= csrf() ?>

    <div class="mb-3">
      <label class="form-label" for="email">Email address</label>
      <div class="input-group">
        <span class="input-group-text bg-white"><i class="bi bi-envelope"></i></span>
        <input type="email" class="form-control <?= hasError('email') ?>" id="email" name="email"
               value="<?= e(old('email')) ?>" placeholder="you@example.com"
               autocomplete="username" required autofocus>
      </div>
      <?= fieldError('email') ?>
    </div>

    <div class="mb-4">
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
  Looking for results? <a href="/results" class="text-decoration-none">View live results</a>.
  Opening a screen at the venue?
  <a href="/display" class="text-decoration-none">Display screens</a>
  are opened with the Event Code, not an account.
</p>
