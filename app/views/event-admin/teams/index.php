<?php /** Event Admin — the event's boats, with their registration state. */ ?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <div>
    <h4 class="fw-bold mb-1">Teams</h4>
    <p class="text-muted mb-0 small">Clubs, boats and captains entered in this regatta.</p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a href="/event-admin/teams/import" class="btn btn-outline-primary">
      <i class="bi bi-file-earmark-arrow-up me-1"></i>Bulk Upload
    </a>
    <a href="/event-admin/teams/create" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add Team</a>
  </div>
</div>

<form class="sms-card p-3 mb-3" method="GET" action="/event-admin/teams">
  <div class="row g-2 align-items-end">
    <div class="col-md-6">
      <label class="form-label" for="q">Search</label>
      <input type="search" class="form-control" id="q" name="q" value="<?= e($search) ?>"
             placeholder="Club, boat, captain or code">
    </div>
    <div class="col-md-3">
      <label class="form-label" for="status">Registration status</label>
      <select class="form-select" id="status" name="status">
        <option value="">All statuses</option>
        <?php foreach (\Models\TeamRegistration::STATUSES as $key => $label): ?>
          <option value="<?= e($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3 d-flex gap-2">
      <button class="btn btn-primary flex-grow-1"><i class="bi bi-funnel me-1"></i>Filter</button>
      <a href="/event-admin/teams" class="btn btn-outline-secondary">Clear</a>
    </div>
  </div>
</form>

<?php if (!$teams): ?>
  <div class="sms-empty-state">
    <i class="bi bi-people"></i>
    <h5>No teams yet</h5>
    <p>Add the clubs and boats taking part in this regatta — one at a time, or many at once from a spreadsheet.</p>
    <div class="d-flex gap-2 justify-content-center flex-wrap">
      <a href="/event-admin/teams/create" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add Team</a>
      <a href="/event-admin/teams/import" class="btn btn-outline-primary">
        <i class="bi bi-file-earmark-arrow-up me-1"></i>Bulk Upload
      </a>
    </div>
  </div>
<?php else: ?>
  <div class="sms-card">
    <div class="sms-card-header">
      <strong><i class="bi bi-people me-2"></i><?= count($teams) ?> team<?= count($teams) === 1 ? '' : 's' ?></strong>
      <input type="search" class="form-control form-control-sm w-auto" placeholder="Quick filter…"
             data-filter-for="teamsTable">
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0" data-filter-table="teamsTable" data-sortable>
        <thead class="table-light">
          <tr>
            <th data-sort="boat">Boat</th>
            <th data-sort="club">Club</th>
            <th>Captain</th>
            <th>Contact</th>
            <th>Registration</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($teams as $t): $h = hid_team((int)$t['id']); ?>
            <tr data-boat="<?= e($t['boat_name']) ?>" data-club="<?= e($t['club_name']) ?>">
              <td>
                <div class="d-flex align-items-center gap-2">
                  <?php if (!empty($t['logo'])): ?>
                    <img src="<?= e($t['logo']) ?>" class="rounded border" width="34" height="34"
                         style="object-fit:cover" alt="">
                  <?php else: ?>
                    <div class="sms-avatar sms-avatar-sm"><?= e(avatarInitials($t['boat_name'])) ?></div>
                  <?php endif; ?>
                  <div class="min-w-0">
                    <div class="fw-semibold text-truncate"><?= e($t['boat_name']) ?></div>
                    <?php if (!empty($t['short_code'])): ?>
                      <code class="small"><?= e($t['short_code']) ?></code>
                    <?php endif; ?>
                    <?php if (!empty($t['boat_class'])): ?>
                      <span class="small text-muted"><?= e($t['boat_class']) ?></span>
                    <?php endif; ?>
                  </div>
                </div>
              </td>
              <td><?= e($t['club_name']) ?>
                <?php if (!empty($t['home_place'])): ?>
                  <div class="small text-muted"><?= e($t['home_place']) ?></div>
                <?php endif; ?>
              </td>
              <td class="small"><?= e($t['captain_name']) ?></td>
              <td class="small text-muted">
                <?= e($t['contact_phone'] ?: '—') ?>
                <?php if (!empty($t['contact_email'])): ?>
                  <div class="text-truncate" style="max-width:180px"><?= e($t['contact_email']) ?></div>
                <?php endif; ?>
              </td>
              <td><?= statusBadge($t['registration_status'] ?? 'draft') ?></td>
              <td class="text-end">
                <div class="btn-group btn-group-sm">
                  <a class="btn btn-outline-secondary" href="/event-admin/teams/<?= e($h) ?>/edit" title="Edit">
                    <i class="bi bi-pencil"></i>
                  </a>
                  <form method="POST" action="/event-admin/teams/<?= e($h) ?>/delete" class="d-inline">
                    <?= csrf() ?>
                    <button class="btn btn-outline-danger" title="Remove"
                            data-confirm="Remove “<?= e($t['boat_name']) ?>” from this event?">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="px-3 py-2 small text-muted border-top" data-filter-count="teamsTable"></div>
  </div>
<?php endif; ?>
