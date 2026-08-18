<?php
/** Dompdf body — heat sheet for one round, every lane of every heat. */
require APP_ROOT . '/views/partials/pdf-head.php';
$medal = fn(int $p) => [1 => 'gold', 2 => 'silver', 3 => 'bronze'][$p] ?? 'fourth';
$lanes = (int)$round['lane_count'];
?>

<h2><?= (int)$round['race_sl_no'] ?>. <?= $h($round['race_name']) ?> &mdash; <?= $h($round['name']) ?></h2>
<div class="mut" style="margin-bottom:10px">
  <?= $h(scheduleLabel(roundSchedule($round))) ?>
  &middot; <?= $lanes ?> lanes
  &middot; <?= $h(\Models\Round::STATUSES[$round['status']] ?? $round['status']) ?>
</div>

<?php if (!$heats): ?>
  <p class="mut">This round has no heats.</p>
<?php else: foreach ($heats as $ht): ?>
  <h3><?= $h(\Models\Heat::label($ht)) ?>
    <?php $hs = heatSchedule($ht, $round); ?>
    <?php if ($hs['date'] !== '' || $hs['time'] !== ''): ?>
      <span class="mut">— <?= $h(scheduleLabel($hs)) ?></span>
    <?php endif; ?>
  </h3>
  <table class="grid">
    <thead>
      <tr><th width="34">Lane</th><th>Boat</th><th>Club</th><th>Captain</th>
          <th width="62">Time</th><th width="34">Pos.</th><th width="40">Qual.</th><th width="46">Outcome</th></tr>
    </thead>
    <tbody>
      <?php for ($lane = 1; $lane <= $lanes; $lane++):
              $row = null;
              foreach ($ht['lanes'] as $l) { if ((int)$l['lane_no'] === $lane) { $row = $l; break; } } ?>
        <tr class="<?= $row && $row['position'] ? $medal((int)$row['position']) : '' ?>">
          <td class="c b"><?= $lane ?></td>
          <?php if (!$row): ?>
            <td colspan="7" class="mut">— empty —</td>
          <?php else: ?>
            <td><?= $h($row['boat_name']) ?></td>
            <td><?= $h($row['club_name']) ?></td>
            <td><?= $h($row['captain_name']) ?></td>
            <td class="c"><?= $h($row['race_time'] ?: '') ?></td>
            <td class="c b"><?= $row['position'] ? (int)$row['position'] : '' ?></td>
            <td class="c"><?= !empty($row['qualified']) ? 'Yes' : '' ?></td>
            <td class="c"><?= ($row['outcome'] ?? 'ok') === 'ok' ? '' : strtoupper($h($row['outcome'])) ?></td>
          <?php endif; ?>
        </tr>
      <?php endfor; ?>
    </tbody>
  </table>
<?php endforeach; endif; ?>

<div class="foot"><?= $h($footer) ?></div>
</body></html>
