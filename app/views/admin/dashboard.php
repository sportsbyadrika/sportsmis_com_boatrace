<?php /** Super Admin dashboard — platform-wide overview. */ ?>

<?php if (!empty($defaultPassword)): ?>
  <div class="alert alert-warning d-flex align-items-center gap-2">
    <i class="bi bi-shield-exclamation fs-5"></i>
    <div>
      <strong>Change the bootstrap password.</strong>
      This account still uses the password shipped with the installer.
      Open the avatar menu &rarr; <em>Change Password</em>.
    </div>
  </div>
<?php endif; ?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <div>
    <h4 class="fw-bold mb-1">Platform Overview</h4>
    <p class="text-muted mb-0 small">Every regatta running on SportsMIS Regatta.</p>
  </div>
  <a href="/admin/events/create" class="btn btn-primary">
    <i class="bi bi-plus-lg me-1"></i>Create Event
  </a>
</div>

<div class="row g-3 mb-4">
  <?php
    $cards = [
      ['Events',        $stats['events'], 'bi-calendar-event', '#eff6ff', '#1d4ed8'],
      ['Active Events', $stats['active'], 'bi-broadcast',      '#f0fdf4', '#15803d'],
      ['Event Admins',  $stats['admins'], 'bi-person-badge',   '#fffbeb', '#b45309'],
      ['Event Users',   $stats['users'],  'bi-people',         '#f5f3ff', '#6d28d9'],
    ];
    foreach ($cards as [$label, $value, $icon, $bg, $fg]):
  ?>
    <div class="col-6 col-lg-3">
      <div class="sms-stat-card">
        <div class="sms-stat-icon" style="background:<?= e($bg) ?>;color:<?= e($fg) ?>">
          <i class="bi <?= e($icon) ?>"></i>
        </div>
        <div class="sms-stat-body">
          <div class="sms-stat-value"><?= (int)$value ?></div>
          <div class="sms-stat-label"><?= e($label) ?></div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="sms-card">
  <div class="sms-card-header">
    <strong><i class="bi bi-calendar-event me-2"></i>Recent Events</strong>
    <a href="/admin/events" class="btn btn-sm btn-outline-secondary">View all</a>
  </div>

  <?php if (!$recentEvents): ?>
    <div class="p-5 text-center">
      <i class="bi bi-water d-block mb-2" style="font-size:2.6rem;color:#cbd5e1"></i>
      <h6 class="fw-semibold">No events yet</h6>
      <p class="text-muted small mb-3">Create your first regatta to get started.</p>
      <a href="/admin/events/create" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>Create Event
      </a>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Event</th>
            <th>Code</th>
            <th>Dates</th>
            <th class="text-center">Teams</th>
            <th class="text-center">Races</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentEvents as $ev): ?>
            <tr>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <div class="sms-event-icon">
                    <?php if (!empty($ev['image'])): ?>
                      <img src="<?= e($ev['image']) ?>" alt="">
                    <?php else: ?>
                      <i class="bi bi-water"></i>
                    <?php endif; ?>
                  </div>
                  <div class="min-w-0">
                    <div class="fw-semibold text-truncate"><?= e($ev['name']) ?></div>
                    <?php if (!empty($ev['name_regional'])): ?>
                      <div class="small text-muted text-truncate"><?= e($ev['name_regional']) ?></div>
                    <?php endif; ?>
                  </div>
                </div>
              </td>
              <td><code><?= e($ev['code'] ?? '—') ?></code></td>
              <td class="small text-muted"><?= e(formatDate($ev['start_date'])) ?> &ndash; <?= e(formatDate($ev['end_date'])) ?></td>
              <td class="text-center"><?= (int)$ev['team_count'] ?></td>
              <td class="text-center"><?= (int)$ev['race_count'] ?></td>
              <td><?= statusBadge($ev['status']) ?></td>
              <td class="text-end">
                <a href="/admin/events/<?= e(hid_event((int)$ev['id'])) ?>" class="btn btn-sm btn-outline-primary">
                  Open <i class="bi bi-chevron-right"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
