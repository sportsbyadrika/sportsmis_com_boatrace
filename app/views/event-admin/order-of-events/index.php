<?php
/**
 * The programme. Sortable + filterable client-side (app.js), with an inline
 * call-room status control that saves over AJAX and swaps the badge in place.
 */
$statuses = \Models\EventRace::STATUSES;
$genders  = \Models\EventRace::GENDERS;
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <div>
    <h4 class="fw-bold mb-1">Order of Events</h4>
    <p class="text-muted mb-0 small">The programme — serial, date, time and call-room status.</p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <div class="btn-group">
      <a class="btn btn-outline-dark" target="_blank" rel="noopener"
         href="/event-admin/order-of-events/print<?= $date !== '' ? '?date=' . e($date) : '' ?>">
        <i class="bi bi-printer me-1"></i>Print
      </a>
      <button type="button" class="btn btn-outline-dark dropdown-toggle dropdown-toggle-split"
              data-bs-toggle="dropdown" aria-expanded="false"><span class="visually-hidden">More</span></button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" target="_blank" rel="noopener"
               href="/event-admin/order-of-events/pdf<?= $date !== '' ? '?date=' . e($date) : '' ?>">
          <i class="bi bi-file-earmark-pdf me-2"></i>Download PDF<?= $date !== '' ? ' (this date)' : ' (all dates)' ?>
        </a></li>
        <?php foreach ($dates as $d): ?>
          <li><a class="dropdown-item" target="_blank" rel="noopener"
                 href="/event-admin/order-of-events/pdf?date=<?= e($d) ?>">
            <i class="bi bi-calendar3 me-2"></i>PDF — <?= e(formatDate($d)) ?>
          </a></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <form method="POST" action="/event-admin/order-of-events/resequence" class="d-inline">
      <?= csrf() ?>
      <button class="btn btn-outline-secondary"
              data-confirm="Renumber every race 1..n in its current date/time order?">
        <i class="bi bi-sort-numeric-down me-1"></i>Renumber
      </button>
    </form>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddRace">
      <i class="bi bi-plus-lg me-1"></i>Add Race
    </button>
  </div>
</div>

<div class="sms-card p-3 mb-3">
  <div class="row g-2 align-items-end">
    <div class="col-md-3">
      <label class="form-label" for="fDate">Date</label>
      <select class="form-select" id="fDate" onchange="location.href='/event-admin/order-of-events' + (this.value ? '?date=' + this.value : '')">
        <option value="">All dates</option>
        <?php foreach ($dates as $d): ?>
          <option value="<?= e($d) ?>" <?= $date === $d ? 'selected' : '' ?>><?= e(formatDate($d)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label" for="fStatus">Call-room status</label>
      <select class="form-select" id="fStatus" data-filter-for="raceTable" data-filter-field="status">
        <option value="">All statuses</option>
        <?php foreach ($statuses as $key => $label): ?>
          <option value="<?= e($key) ?>"><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label" for="fSearch">Search</label>
      <input type="search" class="form-control" id="fSearch" data-filter-for="raceTable"
             placeholder="Race name, class or category">
    </div>
    <div class="col-md-2 d-grid">
      <button type="button" class="btn btn-outline-secondary" data-filter-clear="raceTable">Clear</button>
    </div>
  </div>
</div>

<?php if (!$races): ?>
  <div class="sms-empty-state">
    <i class="bi bi-list-ol"></i>
    <h5>The programme is empty</h5>
    <p>Add the races that will be run at this regatta.</p>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddRace">
      <i class="bi bi-plus-lg me-1"></i>Add Race
    </button>
  </div>
<?php else: ?>
  <div class="sms-card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0" data-filter-table="raceTable" data-sortable>
        <thead class="table-light">
          <tr>
            <th style="width:60px" data-sort="serial">Sl.</th>
            <th style="width:64px" title="Shown on the public results card">Image</th>
            <th data-sort="name">Race</th>
            <th data-sort="when">Date &amp; Time</th>
            <th class="text-center">Lanes</th>
            <th class="text-center">Entries</th>
            <th class="text-center">Rounds</th>
            <th style="min-width:190px">Call-room status</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($races as $r): $h = hid_race((int)$r['id']); ?>
            <tr data-status="<?= e($r['status']) ?>"
                data-serial="<?= (int)$r['sl_no'] ?>"
                data-name="<?= e($r['name']) ?>"
                data-when="<?= e(($r['race_date'] ?? '9999-12-31') . ' ' . ($r['race_time'] ?? '23:59')) ?>">
              <td class="fw-bold"><?= (int)$r['sl_no'] ?></td>
              <td>
                <?php $img = (string)($r['image'] ?? ''); ?>
                <button type="button" class="btn btn-sm btn-link p-0 border-0 race-photo"
                        data-race="<?= e($h) ?>"
                        data-name="<?= e($r['name']) ?>"
                        data-image="<?= e($img) ?>"
                        title="<?= $img !== '' ? 'Change this race&rsquo;s image' : 'Add an image for this race' ?>">
                  <?php if ($img !== ''): ?>
                    <img src="<?= e($img) ?>" class="rounded border" width="48" height="34"
                         style="object-fit:cover" alt="">
                  <?php else: ?>
                    <span class="d-inline-flex align-items-center justify-content-center rounded border text-muted"
                          style="width:48px;height:34px;background:#f8fafc">
                      <i class="bi bi-image"></i>
                    </span>
                  <?php endif; ?>
                </button>
              </td>
              <td>
                <div class="fw-semibold"><?= e($r['name']) ?></div>
                <?php if (!empty($r['name_regional'])): ?>
                  <div class="small text-muted"><?= e($r['name_regional']) ?></div>
                <?php endif; ?>
                <div class="small text-muted">
                  <?php if (!empty($r['code'])): ?><code><?= e($r['code']) ?></code> <?php endif; ?>
                  <?php if (!empty($r['boat_class'])): ?><?= e($r['boat_class']) ?> &middot; <?php endif; ?>
                  <?= e($genders[$r['gender']] ?? $r['gender']) ?>
                  <?php if (!empty($r['distance_m'])): ?> &middot; <?= (int)$r['distance_m'] ?> m<?php endif; ?>
                </div>
              </td>
              <td class="small"><?= e(formatDateTime($r['race_date'], $r['race_time'])) ?></td>
              <td class="text-center"><?= (int)$r['lane_count'] ?></td>
              <td class="text-center">
                <a href="/event-admin/order-of-events/<?= e($h) ?>/entries"
                   class="badge bg-primary-subtle text-primary-emphasis text-decoration-none">
                  <?= (int)$r['entry_count'] ?>
                </a>
              </td>
              <td class="text-center">
                <a href="/event-admin/order-of-events/<?= e($h) ?>/schedule"
                   class="badge <?= (int)$r['round_count'] ? 'bg-primary-subtle text-primary-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' ?> text-decoration-none"
                   title="Round dates and times">
                  <?= (int)$r['round_count'] ?>
                </a>
              </td>
              <td>
                <select class="form-select form-select-sm race-status" data-race="<?= e($h) ?>">
                  <?php foreach ($statuses as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $r['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td class="text-end">
                <div class="btn-group btn-group-sm">
                  <a class="btn btn-outline-primary" href="/event-admin/order-of-events/<?= e($h) ?>/entries"
                     title="Entries"><i class="bi bi-people"></i></a>
                  <a class="btn btn-outline-primary" href="/event-admin/order-of-events/<?= e($h) ?>/schedule"
                     title="Round schedule"><i class="bi bi-clock"></i></a>
                  <button class="btn btn-outline-secondary" data-bs-toggle="modal"
                          data-bs-target="#modalEditRace<?= e($h) ?>" title="Edit"><i class="bi bi-pencil"></i></button>
                  <form method="POST" action="/event-admin/order-of-events/<?= e($h) ?>/delete" class="d-inline">
                    <?= csrf() ?>
                    <button class="btn btn-outline-danger" title="Remove"
                            data-confirm="Remove “<?= e($r['name']) ?>” from the programme?"><i class="bi bi-trash"></i></button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="px-3 py-2 small text-muted border-top" data-filter-count="raceTable"></div>
  </div>
<?php endif; ?>

<?php
  // The add and edit dialogs share one field set.
  $raceFields = function (?array $r, array $event, array $genders, array $statuses, int $nextSerial) {
      $v = fn(string $k, $d = '') => $r !== null && ($r[$k] ?? null) !== null ? $r[$k] : $d;
      ob_start(); ?>
      <div class="row g-3">
        <div class="col-md-2">
          <label class="form-label">Sl. No</label>
          <input type="number" name="sl_no" class="form-control" min="0"
                 value="<?= e($v('sl_no', $nextSerial)) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Race name <span class="text-danger">*</span></label>
          <input type="text" name="name" class="form-control" value="<?= e($v('name')) ?>" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Regional name</label>
          <input type="text" name="name_regional" class="form-control" value="<?= e($v('name_regional')) ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">Code</label>
          <input type="text" name="code" class="form-control text-uppercase" value="<?= e($v('code')) ?>" maxlength="30">
        </div>

        <div class="col-md-3">
          <label class="form-label">Boat class</label>
          <input type="text" name="boat_class" class="form-control" value="<?= e($v('boat_class')) ?>"
                 placeholder="e.g. Chundan Vallam">
        </div>
        <div class="col-md-3">
          <label class="form-label">Category</label>
          <input type="text" name="category" class="form-control" value="<?= e($v('category')) ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">Gender</label>
          <select name="gender" class="form-select">
            <?php foreach ($genders as $k => $l): ?>
              <option value="<?= e($k) ?>" <?= $v('gender', 'open') === $k ? 'selected' : '' ?>><?= e($l) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Distance (m)</label>
          <input type="number" name="distance_m" class="form-control" min="0" value="<?= e($v('distance_m')) ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">Lanes</label>
          <input type="number" name="lane_count" class="form-control" min="2" max="20"
                 value="<?= e($v('lane_count', (int)$event['default_lanes'])) ?>">
        </div>

        <div class="col-md-3">
          <label class="form-label">Date</label>
          <input type="date" name="race_date" class="form-control" value="<?= e($v('race_date')) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Time</label>
          <input type="time" name="race_time" class="form-control" value="<?= e(substr((string)$v('race_time'), 0, 5)) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Call-room status</label>
          <select name="status" class="form-select">
            <?php foreach ($statuses as $k => $l): ?>
              <option value="<?= e($k) ?>" <?= $v('status', 'scheduled') === $k ? 'selected' : '' ?>><?= e($l) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Remarks</label>
          <input type="text" name="remarks" class="form-control" value="<?= e($v('remarks')) ?>" maxlength="500">
        </div>
      </div>
      <?php return (string)ob_get_clean();
  };
?>

<!-- Add -->
<div class="modal fade" id="modalAddRace" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content">
      <form method="POST" action="/event-admin/order-of-events">
        <?= csrf() ?>
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-plus-lg me-2"></i>Add Race</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body"><?= $raceFields(null, $event, $genders, $statuses, $nextSerial) ?></div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add to Programme</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit -->
<?php foreach ($races as $r): $h = hid_race((int)$r['id']); ?>
  <div class="modal fade" id="modalEditRace<?= e($h) ?>" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl">
      <div class="modal-content">
        <form method="POST" action="/event-admin/order-of-events/<?= e($h) ?>">
          <?= csrf() ?>
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Race</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body"><?= $raceFields($r, $event, $genders, $statuses, $nextSerial) ?></div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>
<?php endforeach; ?>

<!-- One image dialog, retargeted per row. This picture is what the public
     results card shows for the race. -->
<div class="modal fade" id="modalRacePhoto" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" id="racePhotoForm" enctype="multipart/form-data">
        <?= csrf() ?>
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-image me-2"></i>Race image</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="small text-muted">
            Shown on the public results card for <strong id="racePhotoName"></strong>.
            Landscape works best — the card crops to 16:7.
          </p>
          <div class="text-center mb-3">
            <img id="racePhotoPreview" src="" class="rounded border d-none"
                 style="max-width:100%;max-height:200px;object-fit:cover" alt="">
          </div>
          <input type="file" class="form-control" name="image" accept="image/*"
                 data-preview="racePhotoPreview" data-max-mb="7" required>
          <div class="form-text">JPG, PNG or WebP, up to 7&nbsp;MB.</div>
        </div>
        <div class="modal-footer justify-content-between">
          <button type="button" class="btn btn-outline-danger d-none" id="racePhotoRemove">
            <i class="bi bi-trash me-1"></i>Remove image
          </button>
          <div class="ms-auto d-flex gap-2">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Upload</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<form method="POST" id="racePhotoDeleteForm" class="d-none"><?= csrf() ?></form>

<script>
// Inline call-room status change — posts through the shared rgPost() helper
// so the CSRF token and the toast behave exactly as everywhere else.
document.addEventListener('DOMContentLoaded', function () {
  // ── Race image: one modal, retargeted per row ──────────────────────────
  var photoModal = document.getElementById('modalRacePhoto');
  var photoForm  = document.getElementById('racePhotoForm');
  var deleteForm = document.getElementById('racePhotoDeleteForm');
  var removeBtn  = document.getElementById('racePhotoRemove');
  var preview    = document.getElementById('racePhotoPreview');
  var nameEl     = document.getElementById('racePhotoName');

  document.addEventListener('click', function (e) {
    var btn = e.target.closest && e.target.closest('.race-photo');
    if (!btn || !window.bootstrap) return;

    var base = '/event-admin/order-of-events/' + btn.dataset.race + '/image';
    photoForm.setAttribute('action', base);
    deleteForm.setAttribute('action', base + '/delete');
    nameEl.textContent = btn.dataset.name;

    // An empty src makes the browser re-request the page, so only set it
    // when there is actually an image.
    var current = btn.dataset.image || '';
    if (current !== '') preview.src = current;
    preview.classList.toggle('d-none', current === '');
    removeBtn.classList.toggle('d-none', current === '');

    photoForm.reset();
    bootstrap.Modal.getOrCreateInstance(photoModal).show();
  });

  removeBtn.addEventListener('click', function () {
    if (confirm('Remove the image for this race?')) deleteForm.submit();
  });

  document.querySelectorAll('.race-status').forEach(function (sel) {
    sel.addEventListener('change', async function () {
      var row = sel.closest('tr');
      sel.disabled = true;
      var data = await window.rgPost('/event-admin/order-of-events/' + sel.dataset.race + '/status',
                                     { status: sel.value });
      sel.disabled = false;
      window.rgToast(data.message || 'Could not update.', data.success);
      if (data.success && row) row.dataset.status = sel.value;
    });
  });
});
</script>
