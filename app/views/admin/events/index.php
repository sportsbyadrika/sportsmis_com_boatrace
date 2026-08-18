<?php /** Super Admin — all events, filterable. */ ?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <div>
    <h4 class="fw-bold mb-1">Events</h4>
    <p class="text-muted mb-0 small"><?= count($events) ?> event<?= count($events) === 1 ? '' : 's' ?> on the platform.</p>
  </div>
  <a href="/admin/events/create" class="btn btn-primary">
    <i class="bi bi-plus-lg me-1"></i>Create Event
  </a>
</div>

<form class="sms-card p-3 mb-3" method="GET" action="/admin/events">
  <div class="row g-2 align-items-end">
    <div class="col-md-6">
      <label class="form-label" for="q">Search</label>
      <input type="search" class="form-control" id="q" name="q" value="<?= e($search) ?>"
             placeholder="Event name, regional name, code or venue">
    </div>
    <div class="col-md-3">
      <label class="form-label" for="status">Status</label>
      <select class="form-select" id="status" name="status">
        <option value="">All statuses</option>
        <?php foreach (\Models\Event::STATUSES as $key => $label): ?>
          <option value="<?= e($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3 d-flex gap-2">
      <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-funnel me-1"></i>Filter</button>
      <a href="/admin/events" class="btn btn-outline-secondary">Clear</a>
    </div>
  </div>
</form>

<?php if (!$events): ?>
  <div class="sms-empty-state">
    <i class="bi bi-calendar-x"></i>
    <h5>No events match</h5>
    <p>Try a different search, or create a new regatta.</p>
    <a href="/admin/events/create" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Create Event</a>
  </div>
<?php else: ?>
  <div class="row g-3">
    <?php foreach ($events as $ev): ?>
      <div class="col-md-6 col-xl-4">
        <div class="sms-event-card h-100 d-flex flex-column">
          <div class="sms-event-status-bar status-<?= e($ev['status']) ?>"></div>
          <div class="sms-event-card-body flex-grow-1">
            <div class="d-flex align-items-start gap-3 mb-3">
              <div class="sms-event-icon sms-event-icon-lg">
                <?php if (!empty($ev['image'])): ?>
                  <img src="<?= e($ev['image']) ?>" alt="">
                <?php else: ?>
                  <i class="bi bi-water"></i>
                <?php endif; ?>
              </div>
              <div class="min-w-0 flex-grow-1">
                <h6 class="fw-bold mb-1 text-truncate"><?= e($ev['name']) ?></h6>
                <?php if (!empty($ev['name_regional'])): ?>
                  <div class="small text-muted text-truncate mb-1"><?= e($ev['name_regional']) ?></div>
                <?php endif; ?>
                <?= statusBadge($ev['status']) ?>
                <span class="badge bg-primary-subtle text-primary-emphasis ms-1">
                  <i class="bi bi-hash"></i><?= e($ev['code'] ?? '—') ?>
                </span>
              </div>
            </div>

            <div class="small text-muted mb-3">
              <div class="sms-info-item mb-1">
                <i class="bi bi-calendar3"></i>
                <span><?= e(formatDate($ev['start_date'])) ?> &ndash; <?= e(formatDate($ev['end_date'])) ?></span>
              </div>
              <?php if (!empty($ev['venue'])): ?>
                <div class="sms-info-item">
                  <i class="bi bi-geo-alt"></i><span><?= e($ev['venue']) ?></span>
                </div>
              <?php endif; ?>
            </div>

            <div class="row text-center g-2 small">
              <div class="col-3"><div class="fw-bold"><?= (int)$ev['team_count'] ?></div><div class="text-muted">Teams</div></div>
              <div class="col-3"><div class="fw-bold"><?= (int)$ev['race_count'] ?></div><div class="text-muted">Races</div></div>
              <div class="col-3"><div class="fw-bold"><?= (int)$ev['admin_count'] ?></div><div class="text-muted">Admins</div></div>
              <div class="col-3"><div class="fw-bold"><?= (int)$ev['user_count'] ?></div><div class="text-muted">Users</div></div>
            </div>
          </div>
          <div class="px-3 pb-3 d-flex gap-2">
            <a href="/admin/events/<?= e(hid_event((int)$ev['id'])) ?>" class="btn btn-sm btn-primary flex-grow-1">Open</a>
            <a href="/admin/events/<?= e(hid_event((int)$ev['id'])) ?>/edit" class="btn btn-sm btn-outline-secondary">
              <i class="bi bi-pencil"></i>
            </a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
