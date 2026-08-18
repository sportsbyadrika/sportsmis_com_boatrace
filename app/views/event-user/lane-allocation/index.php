<?php
/**
 * The lane board.
 *
 * Left: heat strip. Middle: the lanes of the selected heat, each row a drop
 * target. Right: the pool of boats still to be drawn. Boats can be dragged,
 * or clicked once in the pool and once on a lane (which is what actually
 * works on a trackpad or a tablet at the riverbank).
 *
 * Every mutation goes through window.rgPost(), so the CSRF token, the toast
 * and the error shape are the same as everywhere else in the app.
 */
$frozen   = $round ? \Models\Round::isFrozen($round) : false;
$laneCount= $round ? (int)$round['lane_count'] : 0;
$drawnIds = [];
foreach (($lanes ?? []) as $heatLanes) {
    foreach ($heatLanes as $la) $drawnIds[] = (int)$la['team_registration_id'];
}
$available = array_values(array_filter($pool, fn($p) => !in_array((int)$p['registration_id'], $drawnIds, true)));
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <div>
    <h4 class="fw-bold mb-1">Lane Allocation</h4>
    <p class="text-muted mb-0 small">Drag a boat onto a lane — or tap the boat, then tap the lane.</p>
  </div>
  <form class="d-flex gap-2" method="GET" action="/event-user/lane-allocation">
    <select class="form-select" name="round" style="min-width:320px"
            onchange="this.form.submit()">
      <option value="">Choose a round…</option>
      <?php
        $currentRace = null;
        foreach ($rounds as $r):
          if ($currentRace !== $r['race_name']) {
              if ($currentRace !== null) echo '</optgroup>';
              $currentRace = $r['race_name'];
              echo '<optgroup label="' . e((int)$r['race_sl_no'] . '. ' . $r['race_name']) . '">';
          }
      ?>
        <option value="<?= e(hid_round((int)$r['id'])) ?>"
                <?= $round && (int)$round['id'] === (int)$r['id'] ? 'selected' : '' ?>>
          <?= e($r['name']) ?> — <?= (int)$r['heat_count'] ?> heat<?= (int)$r['heat_count'] === 1 ? '' : 's' ?>,
          <?= (int)$r['allocated_count'] ?> drawn
        </option>
      <?php endforeach; if ($currentRace !== null) echo '</optgroup>'; ?>
    </select>
  </form>
</div>

<?php if (!$round): ?>
  <div class="sms-empty-state">
    <i class="bi bi-water"></i>
    <h5>Choose a round</h5>
    <p>Pick a race round above to open its lane board.</p>
    <?php if (!$rounds): ?>
      <p class="small">No rounds exist yet — create them under <strong>Rounds &amp; Heats</strong>.</p>
    <?php endif; ?>
  </div>

<?php elseif (!$heats): ?>
  <div class="sms-empty-state">
    <i class="bi bi-grid-3x3"></i>
    <h5>This round has no heats</h5>
    <p>Set a heat count for <strong><?= e($round['name']) ?></strong> before drawing lanes.</p>
    <a href="/event-user/rounds/<?= e(hid_race((int)$round['event_race_id'])) ?>" class="btn btn-primary">
      Open Rounds &amp; Heats
    </a>
  </div>

<?php else: ?>

  <div class="sms-card p-3 mb-3">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div class="min-w-0">
        <strong><?= (int)$round['race_sl_no'] ?>. <?= e($round['race_name']) ?></strong>
        <span class="badge bg-primary-subtle text-primary-emphasis ms-1"><?= e($round['name']) ?></span>
        <?= statusBadge($round['status']) ?>
        <div class="small text-muted mt-1">
          <?= $laneCount ?> lanes &middot; <?= count($heats) ?> heat<?= count($heats) === 1 ? '' : 's' ?>
          &middot; <?= e(scheduleLabel(roundSchedule($round))) ?>
        </div>
      </div>
      <?php if (!$frozen): ?>
        <div class="d-flex gap-2 flex-wrap">
          <button type="button" class="btn btn-sm btn-water" id="btnAutoRandom">
            <i class="bi bi-shuffle me-1"></i>Draw Lots
          </button>
          <button type="button" class="btn btn-sm btn-outline-secondary" id="btnAutoList">
            <i class="bi bi-list-ol me-1"></i>Fill In Order
          </button>
          <button type="button" class="btn btn-sm btn-outline-danger" id="btnClear">
            <i class="bi bi-eraser me-1"></i>Clear Draw
          </button>
        </div>
      <?php else: ?>
        <span class="badge bg-secondary"><i class="bi bi-lock me-1"></i>Draw locked</span>
      <?php endif; ?>
    </div>
  </div>

  <div class="row g-3" id="laneBoard"
       data-round="<?= e(hid_round((int)$round['id'])) ?>"
       data-lanes="<?= $laneCount ?>"
       data-frozen="<?= $frozen ? '1' : '0' ?>">

    <!-- Heats + lanes -->
    <div class="col-lg-7">
      <div class="sms-card p-3 mb-3">
        <div class="rg-heat-strip">
          <?php foreach ($heats as $i => $h): ?>
            <button type="button" class="btn btn-sm <?= $i === 0 ? 'btn-primary' : 'btn-outline-primary' ?> heat-btn"
                    data-heat="<?= e(hid_heat((int)$h['id'])) ?>">
              <?= e(\Models\Heat::label($h)) ?>
              <span class="badge bg-light text-dark ms-1 heat-count"
                    data-heat="<?= e(hid_heat((int)$h['id'])) ?>"><?= (int)$h['allocated_count'] ?></span>
            </button>
          <?php endforeach; ?>
        </div>
      </div>

      <?php foreach ($heats as $i => $h): $hh = hid_heat((int)$h['id']); ?>
        <div class="sms-card heat-panel <?= $i === 0 ? '' : 'd-none' ?>" data-heat="<?= e($hh) ?>">
          <div class="sms-card-header">
            <strong><i class="bi bi-grid-3x3 me-2"></i><?= e(\Models\Heat::label($h)) ?></strong>
            <span class="small text-muted"><?= e(scheduleLabel(heatSchedule($h, $round))) ?></span>
          </div>
          <div class="table-responsive">
            <table class="table align-middle mb-0">
              <thead class="table-light">
                <tr><th style="width:70px">Lane</th><th>Boat</th><th>Club</th><th style="width:52px"></th></tr>
              </thead>
              <tbody>
                <?php for ($lane = 1; $lane <= $laneCount; $lane++):
                        $la = $lanes[(int)$h['id']][$lane] ?? null; ?>
                  <tr class="rg-lane-row" data-heat="<?= e($hh) ?>" data-lane="<?= $lane ?>"
                      <?php if ($la): ?>
                        data-allocation="<?= e(hid_alloc((int)$la['id'])) ?>"
                        data-registration="<?= (int)$la['team_registration_id'] ?>"
                        data-boat="<?= e($la['boat_name']) ?>"
                        data-club="<?= e($la['club_name']) ?>"
                        data-code="<?= e($la['short_code']) ?>"
                      <?php endif; ?>>
                    <td><span class="rg-lane-no"><?= $lane ?></span></td>
                    <?php if ($la): ?>
                      <td>
                        <span class="fw-semibold"><?= e($la['boat_name']) ?></span>
                        <?php if (!empty($la['short_code'])): ?>
                          <code class="small ms-1"><?= e($la['short_code']) ?></code>
                        <?php endif; ?>
                      </td>
                      <td class="small text-muted"><?= e($la['club_name']) ?></td>
                      <td class="text-end">
                        <?php if (!$frozen): ?>
                          <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1 lane-clear" title="Remove">
                            <i class="bi bi-x-lg"></i>
                          </button>
                        <?php endif; ?>
                      </td>
                    <?php else: ?>
                      <td colspan="2" class="text-muted small fst-italic lane-empty">— drop a boat here —</td>
                      <td></td>
                    <?php endif; ?>
                  </tr>
                <?php endfor; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Pool -->
    <div class="col-lg-5">
      <div class="sms-card p-3 h-100">
        <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
          <strong><i class="bi bi-people me-1"></i>Boats to draw</strong>
          <span class="badge bg-secondary-subtle text-secondary-emphasis ms-auto" id="poolCount">
            <?= count($available) ?>
          </span>
        </div>

        <?php if ($poolNote !== ''): ?>
          <div class="alert alert-info py-2 small mb-2"><i class="bi bi-info-circle me-1"></i><?= e($poolNote) ?></div>
        <?php endif; ?>

        <?php if (!$frozen): ?>
          <div class="small text-muted mb-2">
            <i class="bi bi-hand-index me-1"></i>Drag onto an empty lane, or tap a boat then tap the lane.
          </div>
        <?php endif; ?>

        <input type="search" class="form-control form-control-sm mb-2" id="poolSearch" placeholder="Filter boats…">

        <div id="poolList" class="d-flex flex-column gap-1 rg-pool-list">
          <?php foreach ($available as $p): ?>
            <div class="border rounded px-2 py-1 rg-pool-item d-flex align-items-center gap-2"
                 <?= $frozen ? '' : 'draggable="true"' ?>
                 data-registration="<?= (int)$p['registration_id'] ?>"
                 data-boat="<?= e($p['boat_name']) ?>"
                 data-club="<?= e($p['club_name']) ?>"
                 data-code="<?= e($p['short_code']) ?>">
              <?php if (!empty($p['short_code'])): ?>
                <code class="small"><?= e($p['short_code']) ?></code>
              <?php endif; ?>
              <span class="small fw-medium text-truncate"><?= e($p['boat_name']) ?></span>
              <span class="small text-muted text-truncate ms-auto"><?= e($p['club_name']) ?></span>
              <?php if (isset($p['position'])): ?>
                <span class="badge bg-light text-dark border">P<?= (int)$p['position'] ?></span>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>

        <?php if (!$available): ?>
          <p class="text-muted small text-center mb-0 py-3">
            <?= $pool ? 'Every boat has been drawn.' : 'Nothing to draw yet.' ?>
          </p>
        <?php endif; ?>
      </div>
    </div>
  </div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var board = document.getElementById('laneBoard');
  if (!board) return;

  var ROUND  = board.dataset.round;
  var FROZEN = board.dataset.frozen === '1';

  // ── Heat switching ─────────────────────────────────────────────────────
  document.querySelectorAll('.heat-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.heat-btn').forEach(function (b) {
        b.classList.remove('btn-primary'); b.classList.add('btn-outline-primary');
      });
      btn.classList.remove('btn-outline-primary'); btn.classList.add('btn-primary');
      document.querySelectorAll('.heat-panel').forEach(function (p) {
        p.classList.toggle('d-none', p.dataset.heat !== btn.dataset.heat);
      });
    });
  });

  if (FROZEN) return;   // read-only board: no drag wiring at all

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function isFilled(row) { return !!row.dataset.allocation; }

  function recount() {
    document.querySelectorAll('.heat-count').forEach(function (badge) {
      var n = document.querySelectorAll(
        '.heat-panel[data-heat="' + badge.dataset.heat + '"] .rg-lane-row[data-allocation]').length;
      badge.textContent = n;
    });
    document.getElementById('poolCount').textContent =
      document.querySelectorAll('#poolList .rg-pool-item').length;
  }

  // ── Row rendering ──────────────────────────────────────────────────────
  function fillRow(row, d) {
    row.dataset.allocation   = d.allocation;
    row.dataset.registration = d.registration;
    row.dataset.boat = d.boat; row.dataset.club = d.club; row.dataset.code = d.code || '';
    row.innerHTML =
      '<td><span class="rg-lane-no">' + row.dataset.lane + '</span></td>' +
      '<td><span class="fw-semibold">' + esc(d.boat) + '</span>' +
        (d.code ? ' <code class="small ms-1">' + esc(d.code) + '</code>' : '') + '</td>' +
      '<td class="small text-muted">' + esc(d.club) + '</td>' +
      '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger py-0 px-1 lane-clear" ' +
        'title="Remove"><i class="bi bi-x-lg"></i></button></td>';
    wireRow(row);
  }

  function emptyRow(row) {
    ['allocation', 'registration', 'boat', 'club', 'code'].forEach(function (k) { delete row.dataset[k]; });
    row.innerHTML =
      '<td><span class="rg-lane-no">' + row.dataset.lane + '</span></td>' +
      '<td colspan="2" class="text-muted small fst-italic lane-empty">— drop a boat here —</td><td></td>';
    wireRow(row);
  }

  function makePoolItem(d) {
    var el = document.createElement('div');
    el.className = 'border rounded px-2 py-1 rg-pool-item d-flex align-items-center gap-2';
    el.setAttribute('draggable', 'true');
    el.dataset.registration = d.registration;
    el.dataset.boat = d.boat; el.dataset.club = d.club; el.dataset.code = d.code || '';
    el.innerHTML =
      (d.code ? '<code class="small">' + esc(d.code) + '</code>' : '') +
      '<span class="small fw-medium text-truncate">' + esc(d.boat) + '</span>' +
      '<span class="small text-muted text-truncate ms-auto">' + esc(d.club) + '</span>';
    wirePoolItem(el);
    return el;
  }

  // ── Server calls ───────────────────────────────────────────────────────
  async function assign(row, item) {
    var res = await window.rgPost('/event-user/lane-allocation/assign', {
      round: ROUND, heat: row.dataset.heat, lane: row.dataset.lane,
      registration: item.dataset.registration
    });
    if (!res.success) { window.rgToast(res.message || 'Could not allocate.', false); return; }
    fillRow(row, {
      allocation: res.allocation, registration: item.dataset.registration,
      boat: item.dataset.boat, club: item.dataset.club, code: item.dataset.code
    });
    item.remove();
    recount();
  }

  async function unassign(row) {
    var res = await window.rgPost('/event-user/lane-allocation/unassign', {
      round: ROUND, allocation: row.dataset.allocation
    });
    if (!res.success) { window.rgToast(res.message || 'Could not clear the lane.', false); return; }
    var d = {
      registration: row.dataset.registration, boat: row.dataset.boat,
      club: row.dataset.club, code: row.dataset.code
    };
    emptyRow(row);
    document.getElementById('poolList').appendChild(makePoolItem(d));
    recount();
  }

  // Move a drawn boat to another lane — the server swaps when occupied, so
  // the simplest correct response is to re-read the board.
  async function move(row, sourceRow) {
    var res = await window.rgPost('/event-user/lane-allocation/move', {
      round: ROUND, allocation: sourceRow.dataset.allocation,
      heat: row.dataset.heat, lane: row.dataset.lane
    });
    window.rgToast(res.message || 'Could not move the boat.', res.success);
    if (res.success) window.location.reload();
  }

  // ── Drag & drop + click-to-place ───────────────────────────────────────
  var dragged = null;     // the element being dragged (pool item or lane row)
  var picked  = null;     // click-to-place selection

  function pick(el) {
    if (picked) picked.classList.remove('rg-picked');
    if (picked === el) { picked = null; return; }
    picked = el;
    el.classList.add('rg-picked');
  }

  function wirePoolItem(el) {
    el.addEventListener('dragstart', function (e) {
      dragged = el;
      el.classList.add('opacity-50');
      // Setting drag data is required for drop to fire in most browsers.
      try {
        e.dataTransfer.setData('text/plain', el.dataset.registration || '');
        e.dataTransfer.effectAllowed = 'move';
      } catch (err) { /* older browsers */ }
    });
    el.addEventListener('dragend', function () { el.classList.remove('opacity-50'); dragged = null; });
    el.addEventListener('click', function () { pick(el); });
  }

  function wireRow(row) {
    row.addEventListener('dragstart', function (e) {
      if (!isFilled(row)) return;
      dragged = row;
      try {
        e.dataTransfer.setData('text/plain', row.dataset.allocation || '');
        e.dataTransfer.effectAllowed = 'move';
      } catch (err) { /* older browsers */ }
    });
    row.addEventListener('dragover', function (e) {
      e.preventDefault();
      if (e.dataTransfer) e.dataTransfer.dropEffect = 'move';
      row.classList.add('rg-drop-target');
    });
    row.addEventListener('dragleave', function () { row.classList.remove('rg-drop-target'); });
    row.addEventListener('drop', function (e) {
      e.preventDefault();
      row.classList.remove('rg-drop-target');
      if (!dragged || dragged === row) return;
      if (dragged.classList.contains('rg-pool-item')) {
        if (isFilled(row)) { window.rgToast('That lane is taken — clear it first, or drag the boat that is in it.', false); return; }
        assign(row, dragged);
      } else {
        move(row, dragged);
      }
    });
    row.addEventListener('click', function (e) {
      if (e.target.closest('.lane-clear')) { unassign(row); return; }
      if (picked && !isFilled(row)) {
        var el = picked; picked = null; el.classList.remove('rg-picked');
        assign(row, el);
      }
    });
    // A filled row can itself be dragged to another lane.
    row.setAttribute('draggable', isFilled(row) ? 'true' : 'false');
  }

  document.querySelectorAll('#poolList .rg-pool-item').forEach(wirePoolItem);
  document.querySelectorAll('.rg-lane-row').forEach(wireRow);

  // ── Pool filter ────────────────────────────────────────────────────────
  document.getElementById('poolSearch').addEventListener('input', function () {
    var q = this.value.trim().toLowerCase();
    document.querySelectorAll('#poolList .rg-pool-item').forEach(function (el) {
      var hay = (el.dataset.boat + ' ' + el.dataset.club + ' ' + (el.dataset.code || '')).toLowerCase();
      el.classList.toggle('d-none', q !== '' && hay.indexOf(q) === -1);
    });
  });

  // ── Bulk actions ───────────────────────────────────────────────────────
  async function bulk(url, payload, confirmText) {
    if (confirmText && !confirm(confirmText)) return;
    var res = await window.rgPost(url, payload);
    window.rgToast(res.message || 'Done.', res.success);
    if (res.success && res.reload) window.location.reload();
  }

  document.getElementById('btnAutoRandom').addEventListener('click', function () {
    bulk('/event-user/lane-allocation/auto-fill', { round: ROUND, order: 'random' },
         'Draw lots for every empty lane in this round?');
  });
  document.getElementById('btnAutoList').addEventListener('click', function () {
    bulk('/event-user/lane-allocation/auto-fill', { round: ROUND, order: 'list' }, null);
  });
  document.getElementById('btnClear').addEventListener('click', function () {
    bulk('/event-user/lane-allocation/clear', { round: ROUND },
         'Clear the entire lane draw for this round? Any results recorded on it are removed too.');
  });

  recount();
});
</script>
<?php endif; ?>
