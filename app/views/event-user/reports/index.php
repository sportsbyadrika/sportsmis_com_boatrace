<?php /** Reports hub — rank list on screen, plus links to print/PDF and heat sheets. */ ?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <div>
    <h4 class="fw-bold mb-1">Reports</h4>
    <p class="text-muted mb-0 small">Built from published rounds only — unpublished results never appear here.</p>
  </div>
  <div class="btn-group">
    <a class="btn btn-outline-dark" target="_blank" rel="noopener" href="/event-user/reports/rank-list/print">
      <i class="bi bi-printer me-1"></i>Print Rank List
    </a>
    <a class="btn btn-outline-dark" target="_blank" rel="noopener" href="/event-user/reports/rank-list/pdf">
      <i class="bi bi-file-earmark-pdf me-1"></i>PDF
    </a>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="sms-card">
      <div class="sms-card-header">
        <strong><i class="bi bi-trophy me-2"></i>Event-wise Rank List</strong>
        <span class="small text-muted">1st &ndash; 4th per race</span>
      </div>

      <?php if (!$rankList): ?>
        <div class="p-4 text-center text-muted small">No races in the programme yet.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead class="table-light">
              <tr><th style="width:52px">Sl.</th><th>Race</th><th>Place</th><th>Boat</th><th>Club</th><th>Time</th></tr>
            </thead>
            <tbody>
              <?php foreach ($rankList as $entry): $race = $entry['race']; ?>
                <?php if (!$entry['places']): ?>
                  <tr>
                    <td class="fw-bold"><?= (int)$race['sl_no'] ?></td>
                    <td><?= e($race['name']) ?></td>
                    <td colspan="4" class="small text-muted fst-italic">
                      <?= $entry['round'] ? 'Published, but no placed boats recorded.' : 'No published result yet.' ?>
                    </td>
                  </tr>
                <?php else: foreach ($entry['places'] as $i => $p): ?>
                  <tr class="<?= e(positionClass((int)$p['position'])) ?>">
                    <?php if ($i === 0): ?>
                      <td class="fw-bold" rowspan="<?= count($entry['places']) ?>"><?= (int)$race['sl_no'] ?></td>
                      <td rowspan="<?= count($entry['places']) ?>">
                        <div class="fw-semibold"><?= e($race['name']) ?></div>
                        <?php if (!empty($race['name_regional'])): ?>
                          <div class="small text-muted"><?= e($race['name_regional']) ?></div>
                        <?php endif; ?>
                        <div class="small text-muted"><?= e($entry['round']['name'] ?? '') ?></div>
                      </td>
                    <?php endif; ?>
                    <td class="fw-bold"><?= e(ordinal((int)$p['position'])) ?></td>
                    <td>
                      <?= e($p['boat_name']) ?>
                      <?php if (!empty($p['short_code'])): ?><code class="small ms-1"><?= e($p['short_code']) ?></code><?php endif; ?>
                    </td>
                    <td class="small"><?= e($p['club_name']) ?></td>
                    <td class="small text-muted"><?= e($p['race_time'] ?: '—') ?></td>
                  </tr>
                <?php endforeach; endif; ?>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="sms-card mb-3">
      <div class="sms-card-header">
        <strong><i class="bi bi-award me-2"></i>Club Tally</strong>
        <span class="small text-muted">3&ndash;2&ndash;1 points</span>
      </div>
      <?php if (!$tally): ?>
        <div class="p-4 text-center text-muted small">Nothing published yet.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
              <tr><th>Club</th><th class="text-center">1st</th><th class="text-center">2nd</th>
                  <th class="text-center">3rd</th><th class="text-center">Pts</th></tr>
            </thead>
            <tbody>
              <?php foreach ($tally as $t): ?>
                <tr>
                  <td class="fw-semibold"><?= e($t['club_name']) ?></td>
                  <td class="text-center pos-gold"><?= (int)$t['gold'] ?></td>
                  <td class="text-center pos-silver"><?= (int)$t['silver'] ?></td>
                  <td class="text-center pos-bronze"><?= (int)$t['bronze'] ?></td>
                  <td class="text-center fw-bold"><?= (int)$t['points'] ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="sms-card">
      <div class="sms-card-header"><strong><i class="bi bi-grid-3x3 me-2"></i>Heat Sheets</strong></div>
      <?php if (!$rounds): ?>
        <div class="p-4 text-center text-muted small">No rounds yet.</div>
      <?php else: ?>
        <div class="list-group list-group-flush">
          <?php foreach ($rounds as $r): $rh = hid_round((int)$r['id']); ?>
            <div class="list-group-item d-flex align-items-center gap-2">
              <div class="min-w-0 flex-grow-1">
                <div class="small fw-semibold text-truncate">
                  <?= (int)$r['race_sl_no'] ?>. <?= e($r['race_name']) ?>
                </div>
                <div class="small text-muted"><?= e($r['name']) ?> &middot; <?= statusBadge($r['status']) ?></div>
              </div>
              <div class="btn-group btn-group-sm">
                <a class="btn btn-outline-secondary" target="_blank" rel="noopener"
                   href="/event-user/reports/heat-sheet/<?= e($rh) ?>/print" title="Print"><i class="bi bi-printer"></i></a>
                <a class="btn btn-outline-secondary" target="_blank" rel="noopener"
                   href="/event-user/reports/heat-sheet/<?= e($rh) ?>/pdf" title="PDF"><i class="bi bi-file-earmark-pdf"></i></a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
