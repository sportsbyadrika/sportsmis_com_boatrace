<?php /** Super Admin — one event: summary, accounts, quick actions. */ ?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/admin/events" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <div class="flex-grow-1 min-w-0">
    <h4 class="fw-bold mb-0 text-truncate"><?= e($event['name']) ?></h4>
    <div class="small text-muted">
      <?php if (!empty($event['name_regional'])): ?><?= e($event['name_regional']) ?> &middot; <?php endif; ?>
      <span class="badge bg-primary-subtle text-primary-emphasis"><i class="bi bi-hash"></i><?= e($event['code']) ?></span>
      <?= statusBadge($event['status']) ?>
    </div>
  </div>
  <a href="/admin/events/<?= e(hid_event((int)$event['id'])) ?>/edit" class="btn btn-outline-primary">
    <i class="bi bi-pencil me-1"></i>Edit
  </a>
  <a href="/admin/events/<?= e(hid_event((int)$event['id'])) ?>/admins" class="btn btn-primary">
    <i class="bi bi-person-badge me-1"></i>Manage Event Admins
  </a>
</div>

<div class="row g-3 mb-3">
  <?php
    $cards = [
      ['Teams',            $stats['teams'],       'bi-people',        '#eff6ff', '#1d4ed8'],
      ['Approved Entries', $stats['approved'],    'bi-check2-circle', '#f0fdf4', '#15803d'],
      ['Races',            $stats['races'],       'bi-list-ol',       '#fffbeb', '#b45309'],
      ['Rounds',           $stats['rounds'],      'bi-diagram-3',     '#f5f3ff', '#6d28d9'],
      ['Heats',            $stats['heats'],       'bi-grid-3x3',      '#ecfeff', '#0e7490'],
      ['Published Rounds', $stats['published'],   'bi-megaphone',     '#fef2f2', '#b91c1c'],
    ];
    foreach ($cards as [$label, $value, $icon, $bg, $fg]):
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

<div class="row g-3">
  <div class="col-lg-5">
    <div class="sms-card p-4 h-100">
      <h6 class="fw-bold mb-3"><i class="bi bi-info-circle me-2"></i>Details</h6>
      <?php if (!empty($event['image'])): ?>
        <img src="<?= e($event['image']) ?>" class="rounded border mb-3 w-100" style="max-height:190px;object-fit:cover" alt="">
      <?php endif; ?>
      <dl class="row small mb-0">
        <dt class="col-5 text-muted fw-normal">Event Code</dt><dd class="col-7"><code><?= e($event['code']) ?></code></dd>
        <dt class="col-5 text-muted fw-normal">Dates</dt>
        <dd class="col-7"><?= e(formatDate($event['start_date'])) ?> &ndash; <?= e(formatDate($event['end_date'])) ?></dd>
        <dt class="col-5 text-muted fw-normal">Venue</dt><dd class="col-7"><?= e($event['venue'] ?: '—') ?></dd>
        <dt class="col-5 text-muted fw-normal">District</dt><dd class="col-7"><?= e($event['district'] ?: '—') ?></dd>
        <dt class="col-5 text-muted fw-normal">Organiser</dt><dd class="col-7"><?= e($event['organiser'] ?: '—') ?></dd>
        <dt class="col-5 text-muted fw-normal">Default lanes</dt><dd class="col-7"><?= (int)$event['default_lanes'] ?></dd>
        <dt class="col-5 text-muted fw-normal">Chroma colour</dt>
        <dd class="col-7">
          <span class="d-inline-block rounded border align-middle me-1"
                style="width:14px;height:14px;background:<?= e($event['chroma_color']) ?>"></span>
          <code><?= e($event['chroma_color']) ?></code>
        </dd>
        <dt class="col-5 text-muted fw-normal">Display PIN</dt>
        <dd class="col-7"><?= $event['display_pin'] ? '<code>' . e($event['display_pin']) . '</code>' : '<span class="text-muted">Not set</span>' ?></dd>
      </dl>
      <?php if (!empty($event['description'])): ?>
        <hr>
        <p class="small text-muted mb-0"><?= nl2br(e($event['description'])) ?></p>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="sms-card mb-3">
      <div class="sms-card-header">
        <strong><i class="bi bi-person-badge me-2"></i>Event Admins <span class="badge bg-secondary ms-1"><?= count($admins) ?></span></strong>
        <a href="/admin/events/<?= e(hid_event((int)$event['id'])) ?>/admins" class="btn btn-sm btn-outline-primary">Manage</a>
      </div>
      <?php if (!$admins): ?>
        <div class="p-4 text-center text-muted small">
          No admin account yet — the organiser can&rsquo;t sign in until you create one.
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th>Name</th><th>Email</th><th>Status</th><th>Last login</th></tr></thead>
            <tbody>
              <?php foreach ($admins as $a): ?>
                <tr>
                  <td class="fw-semibold"><?= e($a['name']) ?></td>
                  <td class="small text-muted"><?= e($a['email']) ?></td>
                  <td><?= statusBadge($a['status']) ?></td>
                  <td class="small text-muted"><?= $a['last_login_at'] ? e(formatDate($a['last_login_at'], 'd M Y, g:i A')) : 'Never' ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="sms-card">
      <div class="sms-card-header">
        <strong><i class="bi bi-people me-2"></i>Event Users <span class="badge bg-secondary ms-1"><?= count($users) ?></span></strong>
        <span class="small text-muted">Created by the event admin</span>
      </div>
      <?php if (!$users): ?>
        <div class="p-4 text-center text-muted small">No race-office accounts yet.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th>Name</th><th>Email</th><th>Privileges</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach ($users as $u): ?>
                <tr>
                  <td class="fw-semibold"><?= e($u['name']) ?></td>
                  <td class="small text-muted"><?= e($u['email']) ?></td>
                  <td>
                    <?php foreach ($u['privileges'] as $p): ?>
                      <span class="badge bg-light text-dark border me-1">
                        <?= e(\Models\EventUser::PRIVILEGES[$p] ?? $p) ?>
                      </span>
                    <?php endforeach; ?>
                    <?php if (!$u['privileges']): ?><span class="text-muted small">None</span><?php endif; ?>
                  </td>
                  <td><?= statusBadge($u['status']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if (($event['status'] ?? '') === 'draft'): ?>
  <div class="sms-card p-3 mt-3 border-danger-subtle">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div>
        <strong class="text-danger">Delete this event</strong>
        <div class="small text-muted">Permanently removes the event and every account, team and race under it.</div>
      </div>
      <form method="POST" action="/admin/events/<?= e(hid_event((int)$event['id'])) ?>/delete">
        <?= csrf() ?>
        <button type="submit" class="btn btn-outline-danger"
                data-confirm="Delete “<?= e($event['name']) ?>” and everything under it? This cannot be undone.">
          <i class="bi bi-trash me-1"></i>Delete Event
        </button>
      </form>
    </div>
  </div>
<?php endif; ?>
