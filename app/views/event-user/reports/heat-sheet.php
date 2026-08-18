<?php /** Printable heat sheet for one round — every lane with its result. */ ?>
<?php require APP_ROOT . '/views/partials/print-head.php'; ?>

<h2 style="font-size:13pt;margin:0 0 4px">
  <?= (int)$round['race_sl_no'] ?>. <?= e($round['race_name']) ?> &mdash; <?= e($round['name']) ?>
</h2>
<div class="small text-muted" style="margin-bottom:12px">
  <?= e(formatDateTime($round['race_date'], $round['race_time'])) ?>
  &middot; <?= (int)$round['lane_count'] ?> lanes
  &middot; <?= e(\Models\Round::STATUSES[$round['status']] ?? $round['status']) ?>
</div>

<?php if (!$heats): ?>
  <p class="text-muted">This round has no heats.</p>
<?php else: foreach ($heats as $i => $h): ?>
  <div class="no-break" style="margin-bottom:16px">
    <h3 style="font-size:11pt;margin:10px 0 5px">
      <?= e(\Models\Heat::label($h)) ?>
      <?php if ($h['scheduled_date'] || $h['scheduled_time']): ?>
        <span class="small text-muted">— <?= e(formatDateTime($h['scheduled_date'], $h['scheduled_time'])) ?></span>
      <?php endif; ?>
    </h3>
    <table>
      <thead>
        <tr>
          <th style="width:44px">Lane</th><th>Boat</th><th>Club</th><th>Captain</th>
          <th style="width:74px">Time</th><th style="width:50px">Pos.</th>
          <th style="width:56px">Qual.</th><th style="width:60px">Outcome</th>
        </tr>
      </thead>
      <tbody>
        <?php for ($lane = 1; $lane <= (int)$round['lane_count']; $lane++):
                $row = null;
                foreach ($h['lanes'] as $l) { if ((int)$l['lane_no'] === $lane) { $row = $l; break; } } ?>
          <tr class="<?= $row && $row['position'] ? e(positionClass((int)$row['position'])) : '' ?>">
            <td class="text-center fw-bold"><?= $lane ?></td>
            <?php if (!$row): ?>
              <td colspan="7" class="small text-muted">— empty —</td>
            <?php else: ?>
              <td><?= e($row['boat_name']) ?></td>
              <td><?= e($row['club_name']) ?></td>
              <td class="small"><?= e($row['captain_name']) ?></td>
              <td class="text-center"><?= e($row['race_time'] ?: '') ?></td>
              <td class="text-center fw-bold"><?= $row['position'] ? (int)$row['position'] : '' ?></td>
              <td class="text-center"><?= !empty($row['qualified']) ? 'Yes' : '' ?></td>
              <td class="text-center small">
                <?= ($row['outcome'] ?? 'ok') === 'ok' ? '' : strtoupper(e($row['outcome'])) ?>
              </td>
            <?php endif; ?>
          </tr>
        <?php endfor; ?>
      </tbody>
    </table>
  </div>
<?php endforeach; endif; ?>

<p class="small text-muted" style="margin-top:18px"><?= e($footer) ?></p>
