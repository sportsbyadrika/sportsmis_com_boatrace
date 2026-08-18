<?php
/** Order of Events report — browser print view (A4). */
$genders = \Models\EventRace::GENDERS;
?>
<?php require APP_ROOT . '/views/partials/print-head.php'; ?>

<h2 style="font-size:13pt;margin:0 0 10px">Order of Events — Rounds &amp; Heats</h2>

<?php if (!$races): ?>
  <p class="text-muted">No races have been added to the programme yet.</p>
<?php else: foreach ($races as $race): ?>
  <div class="no-break" style="margin-bottom:14px">
    <h3 style="font-size:11pt;margin:12px 0 4px">
      <?= (int)$race['sl_no'] ?>. <?= e($race['name']) ?>
      <?php if (!empty($race['name_regional'])): ?>
        <span class="small text-muted">— <?= e($race['name_regional']) ?></span>
      <?php endif; ?>
    </h3>
    <div class="small text-muted" style="margin-bottom:4px">
      <?= e($genders[$race['gender']] ?? $race['gender']) ?>
      <?php if (!empty($race['boat_class'])): ?> &middot; <?= e($race['boat_class']) ?><?php endif; ?>
      <?php if (!empty($race['distance_m'])): ?> &middot; <?= (int)$race['distance_m'] ?> m<?php endif; ?>
      &middot; <?= (int)$race['lane_count'] ?> lanes
      &middot; <?= e(formatDateTime($race['race_date'], $race['race_time'])) ?>
      &middot; <?= e(\Models\EventRace::STATUSES[$race['status']] ?? $race['status']) ?>
    </div>

    <?php if (empty($race['rounds'])): ?>
      <p class="small text-muted">No rounds have been set up for this race.</p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th style="width:140px">Round</th><th style="width:96px">Runs at</th>
            <th style="width:44px">Lanes</th><th style="width:44px">Heats</th>
            <th style="width:60px">Qualifiers</th><th>Heat schedule</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($race['rounds'] as $round): $slot = roundSchedule($round, $race); ?>
            <tr>
              <td>
                <strong><?= e($round['name']) ?></strong>
                <div class="small text-muted">
                  <?= e(\Models\Round::STATUSES[$round['status']] ?? $round['status']) ?>
                </div>
              </td>
              <td class="text-center"><?= e(scheduleLabel($slot)) ?></td>
              <td class="text-center"><?= (int)$round['lane_count'] ?></td>
              <td class="text-center"><?= (int)$round['heat_count'] ?></td>
              <td class="text-center"><?= (int)$round['qualify_per_heat'] ?: '—' ?></td>
              <td class="small">
                <?php if (empty($round['heats'])): ?>
                  <span class="text-muted">No heats yet</span>
                <?php else: $bits = [];
                        foreach ($round['heats'] as $ht) {
                            $bits[] = \Models\Heat::label($ht) . ' — '
                                    . scheduleLabel(heatSchedule($ht, $round, $race))
                                    . ' (' . (int)$ht['allocated_count'] . '/' . (int)$round['lane_count'] . ')';
                        }
                        echo e(implode('  ·  ', $bits)); ?>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
<?php endforeach; endif; ?>

<p class="small text-muted" style="margin-top:18px"><?= e($footer) ?></p>
