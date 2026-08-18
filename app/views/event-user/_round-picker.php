<?php
/**
 * Shared round picker for the lane board and result entry.
 *
 * Both screens used to open on an empty page with a dropdown, which gave no
 * sense of what was left to do. This lists every round as a card grouped by
 * race, with its progress, so the next job is visible at a glance.
 *
 * Expects: $rounds (Round::forEvent), $target ('lane-allocation'|'results'),
 *          $title, $blurb, $emptyHint.
 */
$byRace = [];
foreach ($rounds as $r) {
    $byRace[(int)$r['event_race_id']]['race'] = $r;
    $byRace[(int)$r['event_race_id']]['rounds'][] = $r;
}
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <div>
    <h4 class="fw-bold mb-1"><?= e($title) ?></h4>
    <p class="text-muted mb-0 small"><?= e($blurb) ?></p>
  </div>
  <div class="d-flex gap-2 flex-wrap align-items-center">
    <input type="search" class="form-control w-auto" placeholder="Filter races…"
           data-filter-for="roundPick" data-filter-keys="race,round">
    <select class="form-select w-auto" data-filter-for="roundPick" data-filter-field="status">
      <option value="">All statuses</option>
      <?php foreach (\Models\Round::STATUSES as $key => $label): ?>
        <option value="<?= e($key) ?>"><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="button" class="btn btn-outline-secondary" data-filter-clear="roundPick">Clear</button>
  </div>
</div>

<?php if (!$rounds): ?>
  <div class="sms-empty-state">
    <i class="bi bi-diagram-3"></i>
    <h5>No rounds yet</h5>
    <p><?= e($emptyHint) ?></p>
  </div>
<?php else: ?>

  <!-- A hidden table drives the shared filter helper; the cards mirror its rows. -->
  <table class="d-none" data-filter-table="roundPick">
    <tbody>
      <?php foreach ($rounds as $r): ?>
        <tr data-card="<?= e(hid_round((int)$r['id'])) ?>"
            data-race="<?= e($r['race_sl_no'] . ' ' . $r['race_name']) ?>"
            data-round="<?= e($r['name']) ?>"
            data-status="<?= e($r['status']) ?>"></tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="row g-3" id="roundCards">
    <?php foreach ($rounds as $r):
            $rh    = hid_round((int)$r['id']);
            $lanes = (int)$r['lane_count'] * max(1, (int)$r['heat_count']);
            $drawn = (int)$r['allocated_count'];
            $pct   = $lanes > 0 ? min(100, (int)round($drawn / $lanes * 100)) : 0;
            $slot  = roundSchedule($r);
    ?>
      <div class="col-md-6 col-xl-4 round-card" data-card="<?= e($rh) ?>">
        <a class="sms-card sms-hover-lift h-100 d-block p-3 text-decoration-none text-body"
           href="/event-user/<?= e($target) ?>?round=<?= e($rh) ?>">
          <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
            <div class="min-w-0">
              <div class="small text-muted">Race <?= (int)$r['race_sl_no'] ?></div>
              <div class="fw-bold text-truncate"><?= e($r['race_name']) ?></div>
            </div>
            <?= statusBadge($r['status']) ?>
          </div>

          <div class="mb-2">
            <span class="badge bg-primary-subtle text-primary-emphasis"><?= e($r['name']) ?></span>
            <span class="small text-muted ms-1"><i class="bi bi-clock me-1"></i><?= e(scheduleLabel($slot)) ?></span>
          </div>

          <div class="row text-center g-1 small mb-2">
            <div class="col-4"><div class="fw-bold"><?= (int)$r['lane_count'] ?></div><div class="text-muted">Lanes</div></div>
            <div class="col-4"><div class="fw-bold"><?= (int)$r['heat_count'] ?></div><div class="text-muted">Heats</div></div>
            <div class="col-4"><div class="fw-bold"><?= $drawn ?></div><div class="text-muted">Drawn</div></div>
          </div>

          <div class="progress" style="height:6px" role="progressbar"
               aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"
               aria-label="Lane draw progress">
            <div class="progress-bar <?= $pct >= 100 ? 'bg-success' : 'bg-info' ?>"
                 style="width:<?= $pct ?>%"></div>
          </div>
          <div class="d-flex justify-content-between align-items-center mt-2">
            <span class="small text-muted">
              <?php if ((int)$r['heat_count'] === 0): ?>
                No heats yet
              <?php else: ?>
                <?= $drawn ?> of <?= $lanes ?> lanes drawn
              <?php endif; ?>
            </span>
            <span class="small text-water fw-semibold">Open <i class="bi bi-chevron-right"></i></span>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="small text-muted mt-3" data-filter-count="roundPick"></div>

  <script>
  // The filter helper works on table rows; mirror its decisions onto the cards.
  document.addEventListener('DOMContentLoaded', function () {
    var table = document.querySelector('[data-filter-table="roundPick"]');
    if (!table) return;
    function sync() {
      Array.prototype.forEach.call(table.tBodies[0].rows, function (row) {
        var card = document.querySelector('.round-card[data-card="' + row.dataset.card + '"]');
        if (card) card.classList.toggle('d-none', row.classList.contains('d-none'));
      });
    }
    table.addEventListener('rg:filtered', sync);
    sync();
  });
  </script>
<?php endif; ?>
