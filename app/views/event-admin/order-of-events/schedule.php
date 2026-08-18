<?php
/**
 * Round-level scheduling for one race.
 *
 * The race carries one slot. Each round may take its own, so preliminary
 * heats, semi-finals and the final can run at different times or on different
 * days. Leaving a field blank inherits from the race, and the resolved slot is
 * shown alongside so it is obvious what each round will actually run at.
 */
$raceSlot = ['date' => $race['race_date'], 'time' => $race['race_time']];
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/event-admin/order-of-events" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <div class="flex-grow-1 min-w-0">
    <h4 class="fw-bold mb-0 text-truncate">Schedule — <?= e($race['name']) ?></h4>
    <div class="small text-muted">
      Race <?= (int)$race['sl_no'] ?> &middot; <?= e(formatDateTime($race['race_date'], $race['race_time'])) ?>
      &middot; <?= raceStatusBadge($race['status']) ?>
    </div>
  </div>
  <a href="/event-admin/order-of-events/<?= e(hid_race((int)$race['id'])) ?>/entries" class="btn btn-outline-primary">
    <i class="bi bi-people me-1"></i>Entries
  </a>
</div>

<div class="alert alert-info d-flex gap-2 align-items-start">
  <i class="bi bi-info-circle mt-1"></i>
  <div class="small">
    The race is scheduled for <strong><?= e(formatDateTime($race['race_date'], $race['race_time'])) ?></strong>.
    Give a round its own date or time to run it separately — a blank field inherits the race, and
    date and time inherit independently, so a round later the same day only needs a time.
    <?php if (empty($race['race_date']) && empty($race['race_time'])): ?>
      <div class="mt-1 text-warning-emphasis">
        This race has no date or time of its own yet, so anything left blank below stays unscheduled.
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if (!$rounds): ?>
  <div class="sms-empty-state">
    <i class="bi bi-diagram-3"></i>
    <h5>No rounds yet</h5>
    <p>
      Create the standard ladder — Preliminary Heats, Semi-Finals and Final — then give each one
      its slot. Lane counts and heats stay with the race office.
    </p>
    <form method="POST" action="/event-admin/order-of-events/<?= e(hid_race((int)$race['id'])) ?>/seed-rounds">
      <?= csrf() ?>
      <button class="btn btn-primary"><i class="bi bi-magic me-1"></i>Create Default Rounds</button>
    </form>
  </div>
<?php else: ?>
  <form method="POST" action="/event-admin/order-of-events/<?= e(hid_race((int)$race['id'])) ?>/schedule">
    <?= csrf() ?>
    <div class="sms-card">
      <div class="sms-card-header">
        <strong><i class="bi bi-clock me-2"></i><?= count($rounds) ?> round<?= count($rounds) === 1 ? '' : 's' ?></strong>
        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-check-lg me-1"></i>Save Schedule</button>
      </div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:44px">#</th>
              <th>Round</th>
              <th style="width:190px">Date</th>
              <th style="width:160px">Time</th>
              <th>Runs at</th>
              <th class="text-center" style="width:90px">Heats</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rounds as $r):
                    $rh   = hid_round((int)$r['id']);
                    $slot = roundSchedule($r, $race); ?>
              <tr>
                <td class="fw-bold text-muted"><?= (int)$r['sort_order'] ?></td>
                <td>
                  <div class="fw-semibold"><?= e($r['name']) ?></div>
                  <div class="small text-muted">
                    <?= e(\Models\Round::TYPES[$r['round_type']] ?? $r['round_type']) ?>
                    &middot; <?= (int)$r['lane_count'] ?> lanes
                    &middot; <?= statusBadge($r['status']) ?>
                  </div>
                </td>
                <td>
                  <input type="date" class="form-control form-control-sm"
                         name="date[<?= e($rh) ?>]" value="<?= e($r['scheduled_date']) ?>">
                </td>
                <td>
                  <input type="time" class="form-control form-control-sm"
                         name="time[<?= e($rh) ?>]" value="<?= e(substr((string)$r['scheduled_time'], 0, 5)) ?>">
                </td>
                <td class="small">
                  <?php if ($slot['date'] === '' && $slot['time'] === ''): ?>
                    <span class="text-muted fst-italic">Unscheduled</span>
                  <?php else: ?>
                    <strong><?= e(scheduleLabel($slot)) ?></strong>
                    <?php if ($slot['inherited']): ?>
                      <div class="text-muted">
                        <i class="bi bi-arrow-down-right"></i>
                        <?= $slot['own_date'] || $slot['own_time'] ? 'partly from' : 'from' ?> the race
                      </div>
                    <?php endif; ?>
                  <?php endif; ?>
                </td>
                <td class="text-center">
                  <span class="badge bg-secondary-subtle text-secondary-emphasis"><?= (int)$r['heat_count'] ?></span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="px-3 py-2 border-top d-flex justify-content-end">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Schedule</button>
      </div>
    </div>
  </form>

  <p class="small text-muted mt-2 mb-0">
    <i class="bi bi-info-circle me-1"></i>
    An individual heat can override its round again, from the race office under
    <strong>Rounds &amp; Heats</strong> — useful when one heat of a round is moved.
    The printed programme shows these round times under each race.
  </p>
<?php endif; ?>
