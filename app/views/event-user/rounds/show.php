<?php
/**
 * One race's ladder. Each round card carries its own lane count, heat count
 * and qualifier rule, and lists its heats with their draw progress.
 */
$types = \Models\Round::TYPES;
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/event-user/rounds" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <div class="flex-grow-1 min-w-0">
    <h4 class="fw-bold mb-0 text-truncate"><?= (int)$race['sl_no'] ?>. <?= e($race['name']) ?></h4>
    <div class="small text-muted">
      <?= e(formatDateTime($race['race_date'], $race['race_time'])) ?>
      &middot; <?= (int)$race['lane_count'] ?> lanes
      &middot; <?= (int)$entryCount ?> boat<?= $entryCount === 1 ? '' : 's' ?> entered
      &middot; <?= raceStatusBadge($race['status']) ?>
    </div>
  </div>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddRound">
    <i class="bi bi-plus-lg me-1"></i>Add Round
  </button>
</div>

<?php if ($entryCount === 0): ?>
  <div class="alert alert-warning d-flex gap-2 align-items-start">
    <i class="bi bi-exclamation-triangle mt-1"></i>
    <div class="small">
      No boats are entered in this race yet, so the lane draw will have nothing to place.
      Ask your event administrator to set the entries from <strong>Order of Events &rarr; Entries</strong>.
    </div>
  </div>
<?php endif; ?>

<?php if (!$rounds): ?>
  <div class="sms-empty-state">
    <i class="bi bi-diagram-3"></i>
    <h5>No rounds yet</h5>
    <p>Start with the standard ladder — Preliminary Heats, Semi-Finals and Final — or add rounds one by one.</p>
    <div class="d-flex gap-2 justify-content-center flex-wrap">
      <form method="POST" action="/event-user/rounds/<?= e(hid_race((int)$race['id'])) ?>/seed">
        <?= csrf() ?>
        <button class="btn btn-primary"><i class="bi bi-magic me-1"></i>Create Default Ladder</button>
      </form>
      <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalAddRound">
        <i class="bi bi-plus-lg me-1"></i>Add Round
      </button>
    </div>
  </div>
<?php else: ?>
  <div class="row g-3">
    <?php foreach ($rounds as $ro): $rh = hid_round((int)$ro['id']); $frozen = \Models\Round::isFrozen($ro); ?>
      <div class="col-lg-6">
        <div class="sms-card h-100">
          <div class="sms-card-header flex-wrap gap-2">
            <div class="min-w-0">
              <strong><?= e($ro['name']) ?></strong>
              <span class="badge bg-light text-dark border ms-1"><?= e($types[$ro['round_type']] ?? $ro['round_type']) ?></span>
              <?= statusBadge($ro['status']) ?>
            </div>
            <div class="btn-group btn-group-sm">
              <?php if (in_array('lane_allocation', $privileges, true)): ?>
                <a class="btn btn-outline-primary" href="/event-user/lane-allocation?round=<?= e($rh) ?>" title="Lane draw">
                  <i class="bi bi-water"></i>
                </a>
              <?php endif; ?>
              <button class="btn btn-outline-secondary" data-bs-toggle="modal"
                      data-bs-target="#modalEditRound<?= e($rh) ?>" title="Edit round"><i class="bi bi-pencil"></i></button>
              <form method="POST" action="/event-user/rounds/round/<?= e($rh) ?>/delete" class="d-inline">
                <?= csrf() ?>
                <button class="btn btn-outline-danger" title="Delete round" <?= $frozen ? 'disabled' : '' ?>
                        data-confirm="Delete “<?= e($ro['name']) ?>” with its heats, lane draw and results?">
                  <i class="bi bi-trash"></i>
                </button>
              </form>
            </div>
          </div>

          <div class="px-3 pt-3">
            <?php $roSlot = roundSchedule($ro, $race); ?>
            <div class="small text-muted mb-2">
              <i class="bi bi-clock me-1"></i>
              <?php if ($roSlot['date'] === '' && $roSlot['time'] === ''): ?>
                <span class="fst-italic">Unscheduled</span>
              <?php else: ?>
                <?= e(scheduleLabel($roSlot)) ?>
                <?php if ($roSlot['inherited']): ?>
                  <span class="text-muted">(<?= $roSlot['own_date'] || $roSlot['own_time'] ? 'partly ' : '' ?>from the race)</span>
                <?php endif; ?>
              <?php endif; ?>
            </div>
            <div class="row text-center g-2 small mb-3">
              <div class="col-4"><div class="fw-bold fs-5"><?= (int)$ro['lane_count'] ?></div><div class="text-muted">Lanes</div></div>
              <div class="col-4"><div class="fw-bold fs-5"><?= (int)$ro['heat_count'] ?></div><div class="text-muted">Heats</div></div>
              <div class="col-4"><div class="fw-bold fs-5"><?= (int)$ro['allocated_count'] ?></div><div class="text-muted">Drawn</div></div>
            </div>

            <form method="POST" action="/event-user/rounds/round/<?= e($rh) ?>/heats" class="d-flex gap-2 align-items-end mb-3">
              <?= csrf() ?>
              <div class="flex-grow-1">
                <label class="form-label small mb-1">Number of heats</label>
                <input type="number" name="heat_count" class="form-control form-control-sm" min="1" max="40"
                       value="<?= max(1, (int)$ro['heat_count']) ?>" <?= $frozen ? 'disabled' : '' ?>>
              </div>
              <button class="btn btn-sm btn-outline-primary" <?= $frozen ? 'disabled' : '' ?>>
                <i class="bi bi-arrow-repeat me-1"></i>Apply
              </button>
            </form>
            <?php if ($frozen): ?>
              <p class="small text-muted"><i class="bi bi-lock me-1"></i>This round is <?= e($ro['status']) ?> — unlock it from Result Entry to change its shape.</p>
            <?php endif; ?>
          </div>

          <?php if ($ro['heats']): ?>
            <div class="table-responsive border-top">
              <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                  <tr><th style="width:56px">#</th><th>Heat</th><th>When</th>
                      <th class="text-center">Drawn</th><th class="text-end"></th></tr>
                </thead>
                <tbody>
                  <?php foreach ($ro['heats'] as $ht): $hh = hid_heat((int)$ht['id']); ?>
                    <tr>
                      <td class="fw-bold"><?= (int)$ht['heat_no'] ?></td>
                      <td><?= e(\Models\Heat::label($ht)) ?></td>
                      <td class="small text-muted"><?= e(scheduleLabel(heatSchedule($ht, $ro, $race))) ?></td>
                      <td class="text-center">
                        <span class="badge <?= (int)$ht['allocated_count'] >= (int)$ro['lane_count'] ? 'bg-success' : 'bg-secondary' ?>">
                          <?= (int)$ht['allocated_count'] ?>/<?= (int)$ro['lane_count'] ?>
                        </span>
                      </td>
                      <td class="text-end">
                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal"
                                data-bs-target="#modalHeat<?= e($hh) ?>"><i class="bi bi-pencil"></i></button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div class="px-3 pb-3 small text-muted">No heats yet — set a heat count above.</div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php
  // Shared field set for the add/edit round dialogs.
  $roundFields = function (?array $ro, array $race, array $types) {
      $v = fn(string $k, $d = '') => $ro !== null && ($ro[$k] ?? null) !== null ? $ro[$k] : $d;
      ob_start(); ?>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Round name <span class="text-danger">*</span></label>
          <input type="text" name="name" class="form-control" value="<?= e($v('name')) ?>"
                 placeholder="e.g. Preliminary Heats" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Round type</label>
          <select name="round_type" class="form-select">
            <?php foreach ($types as $k => $l): ?>
              <option value="<?= e($k) ?>" <?= $v('round_type', 'preliminary') === $k ? 'selected' : '' ?>><?= e($l) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Lanes / tracks</label>
          <input type="number" name="lane_count" class="form-control" min="2" max="20"
                 value="<?= e($v('lane_count', (int)$race['lane_count'])) ?>">
          <div class="form-text">How many boats start together in each heat of this round.</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">Qualifiers per heat</label>
          <input type="number" name="qualify_per_heat" class="form-control" min="0" max="20"
                 value="<?= e($v('qualify_per_heat', 2)) ?>">
          <div class="form-text">Auto-ticked when results are saved. 0 for a final.</div>
        </div>
        <div class="col-md-4">
          <label class="form-label"><?= $ro === null ? 'Heats to create' : 'Order in ladder' ?></label>
          <?php if ($ro === null): ?>
            <input type="number" name="heat_count" class="form-control" min="0" max="40" value="1">
          <?php else: ?>
            <input type="number" name="sort_order" class="form-control" min="1" max="50"
                   value="<?= (int)$v('sort_order', 1) ?>">
          <?php endif; ?>
        </div>

        <div class="col-md-4">
          <label class="form-label">Date</label>
          <input type="date" name="scheduled_date" class="form-control" value="<?= e($v('scheduled_date')) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Time</label>
          <input type="time" name="scheduled_time" class="form-control"
                 value="<?= e(substr((string)$v('scheduled_time'), 0, 5)) ?>">
        </div>
        <div class="col-md-4 d-flex align-items-end">
          <div class="form-text mb-2">
            Blank inherits the race&rsquo;s slot
            (<?= e(formatDateTime($race['race_date'], $race['race_time'])) ?>).
          </div>
        </div>
      </div>
      <?php return (string)ob_get_clean();
  };
?>

<!-- Add round -->
<div class="modal fade" id="modalAddRound" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form method="POST" action="/event-user/rounds/<?= e(hid_race((int)$race['id'])) ?>/rounds">
        <?= csrf() ?>
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-plus-lg me-2"></i>Add Round</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body"><?= $roundFields(null, $race, $types) ?></div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add Round</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit round + heat dialogs -->
<?php foreach ($rounds as $ro): $rh = hid_round((int)$ro['id']); ?>
  <div class="modal fade" id="modalEditRound<?= e($rh) ?>" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <form method="POST" action="/event-user/rounds/round/<?= e($rh) ?>">
          <?= csrf() ?>
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Round</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body"><?= $roundFields($ro, $race, $types) ?></div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <?php foreach ($ro['heats'] as $ht): $hh = hid_heat((int)$ht['id']); ?>
    <div class="modal fade" id="modalHeat<?= e($hh) ?>" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <form method="POST" action="/event-user/rounds/heat/<?= e($hh) ?>">
            <?= csrf() ?>
            <div class="modal-header">
              <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Heat <?= (int)$ht['heat_no'] ?></h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label">Heat name</label>
                <input type="text" name="name" class="form-control" value="<?= e($ht['name']) ?>">
              </div>
              <div class="row g-3">
                <div class="col-6">
                  <label class="form-label">Date</label>
                  <input type="date" name="scheduled_date" class="form-control" value="<?= e($ht['scheduled_date']) ?>">
                </div>
                <div class="col-6">
                  <label class="form-label">Time</label>
                  <input type="time" name="scheduled_time" class="form-control"
                         value="<?= e(substr((string)$ht['scheduled_time'], 0, 5)) ?>">
                </div>
              </div>
              <div class="form-text mt-2">
                Leave blank to inherit <strong><?= e($ro['name']) ?></strong>&rsquo;s slot
                (<?= e(scheduleLabel(roundSchedule($ro, $race))) ?>).
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary">Save Heat</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
<?php endforeach; ?>
