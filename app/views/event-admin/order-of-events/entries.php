<?php
/**
 * Which approved boats contest this race.
 *
 * The list is filtered client-side (the shared helper in app.js). Filtering
 * only HIDES rows — a hidden checkbox keeps its state and is still submitted,
 * so narrowing the list can never silently drop a boat from the race. The two
 * bulk buttons act on what is on screen, and the header says so.
 */
$classes      = [];
$hasUnclassed = false;
foreach ($approved as $a) {
    $class = trim((string)($a['boat_class'] ?? ''));
    if ($class === '') { $hasUnclassed = true; continue; }
    $classes[$class] = true;
}
$classes = array_keys($classes);
sort($classes, SORT_NATURAL | SORT_FLAG_CASE);
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/event-admin/order-of-events" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <div class="flex-grow-1 min-w-0">
    <h4 class="fw-bold mb-0 text-truncate">Entries — <?= e($race['name']) ?></h4>
    <div class="small text-muted">
      Race <?= (int)$race['sl_no'] ?> &middot; <?= e(formatDateTime($race['race_date'], $race['race_time'])) ?>
      &middot; <?= (int)$race['lane_count'] ?> lanes &middot; <?= raceStatusBadge($race['status']) ?>
    </div>
  </div>
</div>

<?php if (!$approved): ?>
  <div class="sms-empty-state">
    <i class="bi bi-clipboard-x"></i>
    <h5>No approved teams yet</h5>
    <p>Only approved registrations can be entered into a race.</p>
    <a href="/event-admin/registrations" class="btn btn-primary">Review Registrations</a>
  </div>
<?php else: ?>

  <div class="sms-card p-3 mb-3">
    <div class="row g-2 align-items-end">
      <div class="col-md-6">
        <label class="form-label" for="entrySearch">Search</label>
        <div class="input-group">
          <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
          <input type="search" class="form-control" id="entrySearch"
                 placeholder="Boat, club, captain or code"
                 data-filter-for="entryTable" data-filter-keys="boat,club,captain,code">
        </div>
      </div>
      <div class="col-md-4">
        <label class="form-label" for="entryClass">Boat class</label>
        <select class="form-select" id="entryClass"
                data-filter-for="entryTable" data-filter-field="boatclass">
          <option value="">All classes</option>
          <?php foreach ($classes as $class): ?>
            <option value="<?= e($class) ?>"><?= e($class) ?></option>
          <?php endforeach; ?>
          <?php if ($hasUnclassed): ?>
            <option value="__none">(No class set)</option>
          <?php endif; ?>
        </select>
      </div>
      <div class="col-md-2 d-grid">
        <button type="button" class="btn btn-outline-secondary" data-filter-clear="entryTable">
          <i class="bi bi-x-lg me-1"></i>Clear
        </button>
      </div>
    </div>
  </div>

  <form method="POST" action="/event-admin/order-of-events/<?= e(hid_race((int)$race['id'])) ?>/entries">
    <?= csrf() ?>
    <div class="sms-card">
      <div class="sms-card-header flex-wrap gap-2">
        <strong><i class="bi bi-people me-2"></i>Approved boats
          <span class="badge bg-secondary ms-1"><?= count($approved) ?></span></strong>
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <span class="small text-muted">
            <span id="selCount"><?= count($entered) ?></span> selected<span id="selHidden"></span>
          </span>
          <button type="button" class="btn btn-sm btn-outline-secondary" id="selAll">Select shown</button>
          <button type="button" class="btn btn-sm btn-outline-secondary" id="selNone">Clear shown</button>
          <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-check-lg me-1"></i>Save Entries</button>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="entryTable" data-filter-table="entryTable" data-sortable>
          <thead class="table-light">
            <tr>
              <th style="width:50px"></th>
              <th style="width:74px">Photo</th>
              <th data-sort="boat">Boat</th>
              <th data-sort="club">Club</th>
              <th data-sort="captain">Captain</th>
              <th data-sort="boatclass">Class</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($approved as $a):
                    $checked = in_array((int)$a['id'], $entered, true);
                    $class   = trim((string)($a['boat_class'] ?? '')); ?>
              <tr data-boat="<?= e($a['boat_name']) ?>"
                  data-club="<?= e($a['club_name']) ?>"
                  data-captain="<?= e($a['captain_name']) ?>"
                  data-code="<?= e($a['short_code']) ?>"
                  data-boatclass="<?= e($class !== '' ? $class : '__none') ?>">
                <td>
                  <input class="form-check-input entry-check" type="checkbox" name="registrations[]"
                         value="<?= (int)$a['id'] ?>" <?= $checked ? 'checked' : '' ?>>
                </td>
                <td>
                  <?php $entry = $entryMap[(int)$a['id']] ?? null; ?>
                  <?php if (!$entry): ?>
                    <span class="text-muted small" title="Tick this boat and save the entries first">
                      <i class="bi bi-dash-circle"></i>
                    </span>
                  <?php else: ?>
                    <button type="button" class="btn btn-sm btn-link p-0 border-0 entry-photo"
                            data-entry="<?= e(hid_entry((int)$entry['entry_id'])) ?>"
                            data-boat="<?= e($a['boat_name']) ?>"
                            data-image="<?= e($entry['image']) ?>"
                            title="<?= $entry['image'] !== '' ? 'Change this boat&rsquo;s photo' : 'Add a photo of this boat' ?>">
                      <?php if ($entry['image'] !== ''): ?>
                        <img src="<?= e($entry['image']) ?>" class="rounded border" width="48" height="34"
                             style="object-fit:cover" alt="">
                      <?php else: ?>
                        <span class="d-inline-flex align-items-center justify-content-center rounded border text-muted"
                              style="width:48px;height:34px;background:#f8fafc">
                          <i class="bi bi-camera"></i>
                        </span>
                      <?php endif; ?>
                    </button>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <?php if (!empty($a['logo'])): ?>
                      <img src="<?= e($a['logo']) ?>" class="rounded border" width="30" height="30"
                           style="object-fit:cover" alt="">
                    <?php else: ?>
                      <div class="sms-avatar sms-avatar-sm"><?= e(avatarInitials($a['boat_name'])) ?></div>
                    <?php endif; ?>
                    <span class="fw-semibold"><?= e($a['boat_name']) ?></span>
                    <?php if (!empty($a['short_code'])): ?>
                      <code class="small"><?= e($a['short_code']) ?></code>
                    <?php endif; ?>
                  </div>
                </td>
                <td class="small"><?= e($a['club_name']) ?></td>
                <td class="small text-muted"><?= e($a['captain_name']) ?></td>
                <td class="small text-muted"><?= e($class !== '' ? $class : '—') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="px-3 py-2 small text-muted border-top" data-filter-count="entryTable"></div>
    </div>
  </form>

  <p class="small text-muted mt-2 mb-0">
    <i class="bi bi-info-circle me-1"></i>
    Entries feed the lane draw: the race office picks from these boats when allocating lanes
    in this race&rsquo;s first round. Filtering only changes what you see — boats ticked but
    filtered out stay entered. A boat&rsquo;s photo is per race, so a club fielding a different
    boat in another race gets its own picture there; the club crest lives on the team itself.
  </p>

  <div class="modal fade" id="modalBoatPhoto" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form method="POST" id="boatPhotoForm" enctype="multipart/form-data">
          <?= csrf() ?>
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-camera me-2"></i>Boat photo</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <p class="small text-muted">
              Photo of <strong id="boatPhotoName"></strong> in this race.
            </p>
            <div class="text-center mb-3">
              <img id="boatPhotoPreview" src="" class="rounded border d-none"
                   style="max-width:100%;max-height:200px;object-fit:cover" alt="">
            </div>
            <input type="file" class="form-control" name="image" accept="image/*"
                   data-preview="boatPhotoPreview" data-max-mb="7" required>
            <div class="form-text">JPG, PNG or WebP, up to 7&nbsp;MB.</div>
          </div>
          <div class="modal-footer justify-content-between">
            <button type="button" class="btn btn-outline-danger d-none" id="boatPhotoRemove">
              <i class="bi bi-trash me-1"></i>Remove photo
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

  <form method="POST" id="boatPhotoDeleteForm" class="d-none"><?= csrf() ?></form>

  <script>
  document.addEventListener('DOMContentLoaded', function () {
    var table   = document.getElementById('entryTable');
    var counter = document.getElementById('selCount');
    var hidden  = document.getElementById('selHidden');
    if (!table) return;

    function isHidden(box) {
      var row = box.closest('tr');
      return !!row && row.classList.contains('d-none');
    }
    function boxes() {
      return Array.prototype.slice.call(document.querySelectorAll('.entry-check'));
    }

    // The count is of everything that would be saved, not just what is on
    // screen — and it says so when the filter is hiding some of it.
    function recount() {
      var all    = boxes();
      var picked = all.filter(function (b) { return b.checked; });
      counter.textContent = picked.length;
      var out = picked.filter(isHidden).length;
      hidden.textContent = out ? ' (' + out + ' hidden by the filter)' : '';
    }

    table.addEventListener('change', function (e) {
      if (e.target.classList.contains('entry-check')) recount();
    });
    table.addEventListener('rg:filtered', recount);

    document.getElementById('selAll').addEventListener('click', function () {
      boxes().forEach(function (b) { if (!isHidden(b)) b.checked = true; });
      recount();
    });
    document.getElementById('selNone').addEventListener('click', function () {
      boxes().forEach(function (b) { if (!isHidden(b)) b.checked = false; });
      recount();
    });

    recount();

    // ── Boat photo: one modal, retargeted per row ────────────────────────
    var RACE       = <?= json_encode(hid_race((int)$race['id'])) ?>;
    var photoModal = document.getElementById('modalBoatPhoto');
    var photoForm  = document.getElementById('boatPhotoForm');
    var deleteForm = document.getElementById('boatPhotoDeleteForm');
    var removeBtn  = document.getElementById('boatPhotoRemove');
    var preview    = document.getElementById('boatPhotoPreview');
    var nameEl     = document.getElementById('boatPhotoName');

    table.addEventListener('click', function (e) {
      var btn = e.target.closest('.entry-photo');
      if (!btn || !window.bootstrap) return;

      var base = '/event-admin/order-of-events/' + RACE + '/entries/' + btn.dataset.entry + '/image';
      photoForm.setAttribute('action', base);
      deleteForm.setAttribute('action', base + '/delete');
      nameEl.textContent = btn.dataset.boat;

      // Assigning an empty src makes the browser re-request the page, so
      // only set it when there is actually an image.
      var current = btn.dataset.image || '';
      if (current !== '') preview.src = current;
      preview.classList.toggle('d-none', current === '');
      removeBtn.classList.toggle('d-none', current === '');

      photoForm.reset();
      bootstrap.Modal.getOrCreateInstance(photoModal).show();
    });

    removeBtn.addEventListener('click', function () {
      if (confirm('Remove this boat\'s photo for this race?')) deleteForm.submit();
    });
  });
  </script>
<?php endif; ?>
