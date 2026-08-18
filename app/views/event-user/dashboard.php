<?php /** Race-office home. Cards are built from the privileges this account holds. */ ?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <div class="min-w-0">
    <h4 class="fw-bold mb-1 text-truncate">Race Office</h4>
    <p class="text-muted mb-0 small">
      <?= e($event['name']) ?>
      <?php if (!empty($event['venue'])): ?> &middot; <?= e($event['venue']) ?><?php endif; ?>
    </p>
  </div>
  <?= statusBadge($event['status']) ?>
</div>

<div class="row g-3 mb-4">
  <?php
    $cardStats = [
      ['Races',           $stats['races'],     'bi-list-ol',   '#eff6ff', '#1d4ed8'],
      ['Rounds',          $stats['rounds'],    'bi-diagram-3', '#f5f3ff', '#6d28d9'],
      ['Heats',           $stats['heats'],     'bi-grid-3x3',  '#ecfeff', '#0e7490'],
      ['Lanes Allocated', $stats['allocated'], 'bi-water',     '#f0fdf4', '#15803d'],
      ['Published',       $stats['published'], 'bi-megaphone', '#fffbeb', '#b45309'],
      ['Approved Boats',  $stats['approved'],  'bi-people',    '#fef2f2', '#b91c1c'],
    ];
    foreach ($cardStats as [$label, $value, $icon, $bg, $fg]):
  ?>
    <div class="col-6 col-lg-2">
      <div class="sms-stat-card">
        <div class="sms-stat-icon" style="background:<?= e($bg) ?>;color:<?= e($fg) ?>"><i class="bi <?= e($icon) ?>"></i></div>
        <div class="sms-stat-body">
          <div class="sms-stat-value"><?= (int)$value ?></div>
          <div class="sms-stat-label"><?= e($label) ?></div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php if (!$cards): ?>
  <div class="sms-empty-state">
    <i class="bi bi-shield-lock"></i>
    <h5>No privileges assigned</h5>
    <p>Your account is active but has no privileges yet. Ask your event administrator to grant them.</p>
  </div>
<?php else: ?>
  <div class="row g-3 mb-4">
    <?php foreach ($cards as $c): ?>
      <div class="col-md-6 col-xl-4">
        <a class="sms-action-card h-100" href="<?= e($c['href']) ?>">
          <div class="sms-action-icon text-water"><i class="bi <?= e($c['icon']) ?>"></i></div>
          <div class="min-w-0">
            <div class="fw-semibold"><?= e($c['title']) ?></div>
            <div class="small text-muted"><?= e($c['blurb']) ?></div>
          </div>
          <i class="bi bi-chevron-right ms-auto text-muted"></i>
        </a>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($rounds): ?>
  <div class="sms-card">
    <div class="sms-card-header">
      <strong><i class="bi bi-diagram-3 me-2"></i>Rounds in this event</strong>
      <?php if (in_array('rounds_heats', $privileges, true)): ?>
        <a href="/event-user/rounds" class="btn btn-sm btn-outline-primary">Manage</a>
      <?php endif; ?>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light">
          <tr><th>Race</th><th>Round</th><th class="text-center">Lanes</th>
              <th class="text-center">Heats</th><th class="text-center">Drawn</th><th>Status</th></tr>
        </thead>
        <tbody>
          <?php foreach ($rounds as $r): ?>
            <tr>
              <td>
                <span class="fw-semibold"><?= (int)$r['race_sl_no'] ?>. <?= e($r['race_name']) ?></span>
                <div class="small text-muted"><?= e(formatDateTime($r['race_date'], $r['race_time'])) ?></div>
              </td>
              <td><?= e($r['name']) ?></td>
              <td class="text-center"><?= (int)$r['lane_count'] ?></td>
              <td class="text-center"><?= (int)$r['heat_count'] ?></td>
              <td class="text-center"><?= (int)$r['allocated_count'] ?></td>
              <td><?= statusBadge($r['status']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
