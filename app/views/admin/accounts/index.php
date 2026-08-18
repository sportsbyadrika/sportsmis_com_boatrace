<?php /** Super Admin — every event-admin account, across all events. */ ?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <div>
    <h4 class="fw-bold mb-1">Event Admin Accounts</h4>
    <p class="text-muted mb-0 small"><?= count($accounts) ?> account<?= count($accounts) === 1 ? '' : 's' ?> across all events.</p>
  </div>
  <form class="d-flex gap-2" method="GET" action="/admin/accounts">
    <input type="search" class="form-control" name="q" value="<?= e($search) ?>" placeholder="Name, email or event">
    <button class="btn btn-primary"><i class="bi bi-search"></i></button>
  </form>
</div>

<?php if (!$accounts): ?>
  <div class="sms-empty-state">
    <i class="bi bi-person-x"></i>
    <h5>No accounts found</h5>
    <p>Open an event and add an Event Admin from there.</p>
    <a href="/admin/events" class="btn btn-primary">Go to Events</a>
  </div>
<?php else: ?>
  <div class="sms-card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr><th>Event</th><th>Code</th><th>Name</th><th>Email</th><th>Status</th><th>Last login</th><th class="text-end"></th></tr>
        </thead>
        <tbody>
          <?php foreach ($accounts as $a): ?>
            <tr>
              <td class="fw-semibold"><?= e($a['event_name']) ?></td>
              <td><code><?= e($a['event_code'] ?: '—') ?></code></td>
              <td><?= e($a['name']) ?></td>
              <td class="small text-muted"><?= e($a['email']) ?></td>
              <td><?= statusBadge($a['status']) ?></td>
              <td class="small text-muted">
                <?= $a['last_login_at'] ? e(formatDate($a['last_login_at'], 'd M Y, g:i A')) : 'Never' ?>
              </td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary"
                   href="/admin/events/<?= e(hid_event((int)$a['event_id'])) ?>/admins">Manage</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
