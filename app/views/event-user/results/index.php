<?php
/**
 * Result entry. One form per heat, each posting its own grid over AJAX so a
 * judge can save a heat the moment it finishes without touching the others.
 */
$outcomes = \Models\Result::OUTCOMES;
$frozen   = $round ? ($round['status'] === 'published') : false;
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <div>
    <h4 class="fw-bold mb-1">Result Entry</h4>
    <p class="text-muted mb-0 small">
      Record a time, a position, or both — blank positions are worked out from the times when you save.
    </p>
  </div>
  <a href="/event-user/results" class="btn btn-outline-secondary">
    <i class="bi bi-grid me-1"></i>Change round
  </a>
</div>

<?php if (!$heats): ?>
  <div class="sms-empty-state">
    <i class="bi bi-grid-3x3"></i>
    <h5>Nothing to score yet</h5>
    <p>This round has no heats. Create them under <strong>Rounds &amp; Heats</strong>.</p>
  </div>

<?php else: ?>

  <div class="sms-card p-3 mb-3" id="roundBar" data-round="<?= e(hid_round((int)$round['id'])) ?>">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div class="min-w-0">
        <strong><?= (int)$round['race_sl_no'] ?>. <?= e($round['race_name']) ?></strong>
        <span class="badge bg-primary-subtle text-primary-emphasis ms-1"><?= e($round['name']) ?></span>
        <?= statusBadge($round['status']) ?>
        <div class="small text-muted mt-1">
          <?= (int)$round['lane_count'] ?> lanes &middot; <?= count($heats) ?> heat<?= count($heats) === 1 ? '' : 's' ?>
          &middot; <?= e(scheduleLabel(roundSchedule($round))) ?>
          &middot; <?= (int)$round['qualify_per_heat'] ?> qualif<?= (int)$round['qualify_per_heat'] === 1 ? 'ier' : 'iers' ?> per heat
          <?php if ($nextRound): ?>
            &middot; advances to <strong><?= e($nextRound['name']) ?></strong>
          <?php endif; ?>
        </div>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <?php if (!$frozen): ?>
          <button type="button" class="btn btn-sm btn-outline-primary" id="btnAutoQualify">
            <i class="bi bi-check2-square me-1"></i>Auto-mark Qualifiers
          </button>
          <button type="button" class="btn btn-sm btn-success round-status" data-status="published">
            <i class="bi bi-megaphone me-1"></i>Publish Round
          </button>
          <?php if ($round['status'] !== 'locked'): ?>
            <button type="button" class="btn btn-sm btn-outline-secondary round-status" data-status="locked">
              <i class="bi bi-lock me-1"></i>Lock
            </button>
          <?php else: ?>
            <button type="button" class="btn btn-sm btn-outline-secondary round-status" data-status="open">
              <i class="bi bi-unlock me-1"></i>Unlock
            </button>
          <?php endif; ?>
        <?php else: ?>
          <span class="badge bg-success align-self-center"><i class="bi bi-megaphone me-1"></i>Published</span>
          <button type="button" class="btn btn-sm btn-outline-secondary round-status" data-status="open">
            <i class="bi bi-unlock me-1"></i>Unpublish to edit
          </button>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php foreach ($heats as $h): $hh = hid_heat((int)$h['id']); ?>
    <form class="sms-card mb-3 heat-form" data-heat="<?= e($hh) ?>">
      <div class="sms-card-header flex-wrap gap-2">
        <strong><i class="bi bi-grid-3x3 me-2"></i><?= e(\Models\Heat::label($h)) ?></strong>
        <div class="d-flex gap-2 align-items-center">
          <span class="small text-muted"><?= count($h['lanes']) ?> boat<?= count($h['lanes']) === 1 ? '' : 's' ?></span>
          <?php if (!$frozen): ?>
            <button type="button" class="btn btn-sm btn-outline-danger heat-clear">
              <i class="bi bi-eraser me-1"></i>Clear
            </button>
            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-check-lg me-1"></i>Save Heat</button>
          <?php endif; ?>
        </div>
      </div>

      <?php if (!$h['lanes']): ?>
        <div class="p-4 text-center text-muted small">
          No boats drawn into this heat yet — allocate lanes first.
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width:64px">Lane</th>
                <th>Boat</th>
                <th style="width:130px">Time</th>
                <th style="width:90px">Position</th>
                <th style="width:140px">Outcome</th>
                <th style="width:90px" class="text-center">Qualified</th>
                <th style="width:170px">Remarks</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($h['lanes'] as $la): $key = (int)$la['id']; ?>
                <tr class="<?= $la['position'] ? e(positionClass((int)$la['position'])) : '' ?>">
                  <td><span class="rg-lane-no"><?= (int)$la['lane_no'] ?></span></td>
                  <td>
                    <div class="fw-semibold"><?= e($la['boat_name']) ?></div>
                    <div class="small text-muted"><?= e($la['club_name']) ?></div>
                  </td>
                  <td>
                    <input type="text" class="form-control form-control-sm"
                           name="rows[<?= $key ?>][time]" value="<?= e($la['race_time']) ?>"
                           placeholder="m:ss.mmm" <?= $frozen ? 'disabled' : '' ?>>
                  </td>
                  <td>
                    <input type="number" class="form-control form-control-sm" min="1" max="99"
                           name="rows[<?= $key ?>][position]" value="<?= e($la['position']) ?>"
                           placeholder="auto" <?= $frozen ? 'disabled' : '' ?>>
                  </td>
                  <td>
                    <select class="form-select form-select-sm" name="rows[<?= $key ?>][outcome]"
                            <?= $frozen ? 'disabled' : '' ?>>
                      <?php foreach ($outcomes as $k => $l): ?>
                        <option value="<?= e($k) ?>" <?= ($la['outcome'] ?? 'ok') === $k ? 'selected' : '' ?>>
                          <?= e($l) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                  <td class="text-center">
                    <input type="checkbox" class="form-check-input" value="1"
                           name="rows[<?= $key ?>][qualified]"
                           <?= !empty($la['qualified']) ? 'checked' : '' ?> <?= $frozen ? 'disabled' : '' ?>>
                  </td>
                  <td>
                    <input type="text" class="form-control form-control-sm" maxlength="255"
                           name="rows[<?= $key ?>][remarks]" value="<?= e($la['remarks']) ?>"
                           <?= $frozen ? 'disabled' : '' ?>>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </form>
  <?php endforeach; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var bar = document.getElementById('roundBar');
  if (!bar) return;
  var ROUND = bar.dataset.round;

  async function call(url, payload, confirmText) {
    if (confirmText && !confirm(confirmText)) return;
    var res = await window.rgPost(url, payload);
    window.rgToast(res.message || 'Done.', res.success);
    if (res.success && res.reload) window.location.reload();
  }

  // Save one heat: FormData carries the whole rows[...] grid as posted.
  document.querySelectorAll('.heat-form').forEach(function (form) {
    form.addEventListener('submit', async function (e) {
      e.preventDefault();
      var btn = form.querySelector('[type=submit]');
      if (btn) btn.disabled = true;
      var fd = new FormData(form);
      fd.append('round', ROUND);
      fd.append('heat', form.dataset.heat);
      var res = await window.rgPost('/event-user/results/heat', fd);
      window.rgToast(res.message || 'Could not save.', res.success);
      if (btn) btn.disabled = false;
      if (res.success && res.reload) window.location.reload();
    });

    var clear = form.querySelector('.heat-clear');
    if (clear) clear.addEventListener('click', function () {
      call('/event-user/results/clear', { round: ROUND, heat: form.dataset.heat },
           'Clear every recorded result for this heat?');
    });
  });

  // Absent while the round is published — the bar renders read-only then.
  var autoQualify = document.getElementById('btnAutoQualify');
  if (autoQualify) autoQualify.addEventListener('click', function () {
    call('/event-user/results/auto-qualify', { round: ROUND },
         'Re-mark qualifiers from the recorded positions? Any manual ticks are replaced.');
  });

  document.querySelectorAll('.round-status').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var status = btn.dataset.status;
      var ask = status === 'published'
        ? 'Publish this round? Its results become visible in reports and on the display screens.'
        : (status === 'open' ? null : 'Lock this round? The lane draw and results become read-only.');
      call('/event-user/results/status', { round: ROUND, status: status }, ask);
    });
  });
});
</script>
<?php endif; ?>
