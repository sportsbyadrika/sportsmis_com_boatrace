<?php /** Race picker for Rounds & Heats. */ ?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <div>
    <h4 class="fw-bold mb-1">Rounds &amp; Heats</h4>
    <p class="text-muted mb-0 small">Pick a race to lay out its rounds and heats.</p>
  </div>
  <div class="d-flex gap-2 flex-wrap align-items-center">
    <input type="search" class="form-control w-auto" placeholder="Filter races…" data-filter-for="raceList">
    <div class="btn-group">
      <a class="btn btn-outline-dark" target="_blank" rel="noopener" href="/event-user/rounds/report/print">
        <i class="bi bi-printer me-1"></i>Print
      </a>
      <a class="btn btn-outline-dark" target="_blank" rel="noopener" href="/event-user/rounds/report/pdf">
        <i class="bi bi-file-earmark-pdf me-1"></i>PDF
      </a>
    </div>
  </div>
</div>

<?php if (!$races): ?>
  <div class="sms-empty-state">
    <i class="bi bi-list-ol"></i>
    <h5>The programme is empty</h5>
    <p>Your event administrator needs to add races to the Order of Events first.</p>
  </div>
<?php else: ?>
  <div class="sms-card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0" data-filter-table="raceList">
        <thead class="table-light">
          <tr>
            <th style="width:60px">Sl.</th><th>Race</th><th>Date &amp; Time</th>
            <th class="text-center">Lanes</th><th class="text-center">Rounds</th>
            <th>Status</th><th class="text-end"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($races as $r): ?>
            <tr>
              <td class="fw-bold"><?= (int)$r['sl_no'] ?></td>
              <td>
                <div class="fw-semibold"><?= e($r['name']) ?></div>
                <?php if (!empty($r['name_regional'])): ?>
                  <div class="small text-muted"><?= e($r['name_regional']) ?></div>
                <?php endif; ?>
                <div class="small text-muted">
                  <?php if (!empty($r['boat_class'])): ?><?= e($r['boat_class']) ?> &middot; <?php endif; ?>
                  <?= e(\Models\EventRace::GENDERS[$r['gender']] ?? $r['gender']) ?>
                  <?php if (!empty($r['distance_m'])): ?> &middot; <?= (int)$r['distance_m'] ?> m<?php endif; ?>
                </div>
              </td>
              <td class="small"><?= e(formatDateTime($r['race_date'], $r['race_time'])) ?></td>
              <td class="text-center"><?= (int)$r['lane_count'] ?></td>
              <td class="text-center">
                <?php if ((int)$r['round_count'] === 0): ?>
                  <span class="badge bg-warning text-dark">None</span>
                <?php else: ?>
                  <span class="badge bg-primary-subtle text-primary-emphasis"><?= (int)$r['round_count'] ?></span>
                <?php endif; ?>
              </td>
              <td><?= raceStatusBadge($r['status']) ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-primary" href="/event-user/rounds/<?= e(hid_race((int)$r['id'])) ?>">
                  Open <i class="bi bi-chevron-right"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="px-3 py-2 small text-muted border-top" data-filter-count="raceList"></div>
  </div>
<?php endif; ?>
