<?php /** Which approved boats contest this race. */ ?>

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
  <form method="POST" action="/event-admin/order-of-events/<?= e(hid_race((int)$race['id'])) ?>/entries">
    <?= csrf() ?>
    <div class="sms-card">
      <div class="sms-card-header flex-wrap gap-2">
        <strong><i class="bi bi-people me-2"></i>Approved boats
          <span class="badge bg-secondary ms-1"><?= count($approved) ?></span></strong>
        <div class="d-flex align-items-center gap-2">
          <span class="small text-muted"><span id="selCount"><?= count($entered) ?></span> selected</span>
          <button type="button" class="btn btn-sm btn-outline-secondary" id="selAll">Select all</button>
          <button type="button" class="btn btn-sm btn-outline-secondary" id="selNone">Clear</button>
          <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-check-lg me-1"></i>Save Entries</button>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr><th style="width:50px"></th><th>Boat</th><th>Club</th><th>Captain</th><th>Class</th></tr>
          </thead>
          <tbody>
            <?php foreach ($approved as $a): $checked = in_array((int)$a['id'], $entered, true); ?>
              <tr>
                <td>
                  <input class="form-check-input entry-check" type="checkbox" name="registrations[]"
                         value="<?= (int)$a['id'] ?>" <?= $checked ? 'checked' : '' ?>>
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
                <td class="small text-muted"><?= e($a['boat_class'] ?: '—') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </form>

  <p class="small text-muted mt-2 mb-0">
    <i class="bi bi-info-circle me-1"></i>
    Entries feed the lane draw: the race office picks from these boats when allocating lanes
    in this race&rsquo;s first round.
  </p>

  <script>
  document.addEventListener('DOMContentLoaded', function () {
    var boxes   = document.querySelectorAll('.entry-check');
    var counter = document.getElementById('selCount');
    function recount() {
      counter.textContent = document.querySelectorAll('.entry-check:checked').length;
    }
    boxes.forEach(function (b) { b.addEventListener('change', recount); });
    document.getElementById('selAll').addEventListener('click', function () {
      boxes.forEach(function (b) { b.checked = true; }); recount();
    });
    document.getElementById('selNone').addEventListener('click', function () {
      boxes.forEach(function (b) { b.checked = false; }); recount();
    });
  });
  </script>
<?php endif; ?>
