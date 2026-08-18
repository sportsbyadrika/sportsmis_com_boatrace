<?php
/**
 * Shown only when one email and password open more than one account — the
 * same address can hold accounts on several regattas. The password has
 * already been verified by the time this renders, so listing the events
 * discloses nothing to an unauthenticated visitor.
 */
?>
<div class="mb-4">
  <h4 class="fw-bold mb-1">Where would you like to go?</h4>
  <p class="text-muted mb-0">Your sign-in opens more than one workspace.</p>
</div>

<form method="POST" action="/login/choose">
  <?= csrf() ?>
  <div class="d-flex flex-column gap-2">
    <?php foreach ($choices as $i => $c): ?>
      <button type="submit" name="choice" value="<?= (int)$i ?>"
              class="sms-action-card text-start w-100 border-0">
        <div class="sms-action-icon text-water"><i class="bi <?= e($c['icon']) ?>"></i></div>
        <div class="min-w-0 flex-grow-1">
          <div class="fw-semibold text-truncate"><?= e($c['title']) ?></div>
          <div class="small text-muted">
            <?= e($c['sub']) ?>
            <?php if (!empty($c['code'])): ?>
              &middot; <code><?= e($c['code']) ?></code>
            <?php endif; ?>
          </div>
        </div>
        <i class="bi bi-chevron-right ms-auto text-muted"></i>
      </button>
    <?php endforeach; ?>
  </div>
</form>

<p class="text-muted small text-center mt-3 mb-0">
  Not what you expected? <a href="/login" class="text-decoration-none">Start again</a>.
</p>
