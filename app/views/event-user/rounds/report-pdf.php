<?php
/**
 * Order of Events report (Dompdf body) — the programme expanded down to its
 * rounds and heats.
 *
 * The masthead is `position: fixed`, which Dompdf paints on EVERY page, so the
 * event name and headings survive a page break. The body is pushed down by
 * the same height. "Page N of M" is stamped through the canvas by
 * Core\Pdf::render(), because CSS counter(pages) reports 0 under Dompdf.
 */
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$genders = \Models\EventRace::GENDERS;
?>
<!doctype html>
<html><head><meta charset="utf-8">
<style>
  @page { margin: 34mm 10mm 16mm 10mm; }   /* top margin clears the fixed header */
  body  { font-family: "DejaVu Sans", sans-serif; font-size: 8.5pt; color: #111; margin: 0; }

  /* Repeated on every page by Dompdf. */
  #masthead {
    position: fixed; top: -26mm; left: 0; right: 0; height: 24mm;
    border-bottom: 2px solid #0b1f3a;
  }
  #masthead table { width: 100%; border-collapse: collapse; }
  #masthead td { border: 0; padding: 0; vertical-align: middle; }
  .logo  { height: 17mm; }
  .brand { font-size: 10pt; font-weight: bold; color: #0b1f3a; }
  .brand .sub { color: #0369a1; font-size: 6.5pt; letter-spacing: 1.2px; }
  .evname { font-size: 12pt; font-weight: bold; margin: 0; }
  .evreg  { font-size: 9pt; margin: 0; }
  .evmeta { font-size: 7.5pt; color: #555; }
  .doctitle { font-size: 9pt; font-weight: bold; color: #0b1f3a; text-transform: uppercase;
              letter-spacing: .8px; text-align: right; }

  table.grid { width: 100%; border-collapse: collapse; margin-bottom: 3mm; }
  table.grid th, table.grid td { border: 1px solid #666; padding: 2.5px 5px; }
  table.grid thead th { background: #eef2f7; font-size: 7.5pt; }
  .racehead { background: #0b1f3a; color: #fff; font-size: 9pt; font-weight: bold;
              padding: 3px 6px; }
  .racesub  { background: #e8eef6; font-size: 7.5pt; padding: 2px 6px; color: #23324a; }
  .roundrow td { background: #f6f8fb; font-weight: bold; }
  .c { text-align: center; }
  .mut { color: #666; font-size: 7.5pt; }
  .no-break { page-break-inside: avoid; }
  .foot { margin-top: 6mm; font-size: 7pt; color: #666; }
</style>
</head>
<body>

<div id="masthead">
  <table><tr>
    <td width="60">
      <?php if ($logo !== ''): ?><img class="logo" src="<?= $h($logo) ?>"><?php endif; ?>
    </td>
    <td>
      <div class="brand">SportsMIS&reg; <span class="sub">REGATTA</span></div>
      <p class="evname"><?= $h($event['name']) ?></p>
      <?php if (!empty($event['name_regional'])): ?>
        <p class="evreg"><?= $h($event['name_regional']) ?></p>
      <?php endif; ?>
      <div class="evmeta">
        <?= $h(formatDate($event['start_date'])) ?> &ndash; <?= $h(formatDate($event['end_date'])) ?>
        <?php if (!empty($event['venue'])): ?> &middot; <?= $h($event['venue']) ?><?php endif; ?>
      </div>
    </td>
    <td class="doctitle" width="150">
      Order of Events<br>
      <span class="mut" style="font-weight:normal;text-transform:none;letter-spacing:0">
        Rounds &amp; Heats<br>Printed <?= $h(date('d M Y, g:i A')) ?>
      </span>
    </td>
  </tr></table>
</div>

<?php if (!$races): ?>
  <p class="mut">No races have been added to the programme yet.</p>
<?php else: foreach ($races as $race): ?>
  <div class="no-break">
    <table class="grid">
      <tr><td colspan="6" class="racehead">
        <?= (int)$race['sl_no'] ?>. <?= $h($race['name']) ?>
        <?php if (!empty($race['name_regional'])): ?> &mdash; <?= $h($race['name_regional']) ?><?php endif; ?>
      </td></tr>
      <tr><td colspan="6" class="racesub">
        <?= $h($genders[$race['gender']] ?? $race['gender']) ?>
        <?php if (!empty($race['boat_class'])): ?> &middot; <?= $h($race['boat_class']) ?><?php endif; ?>
        <?php if (!empty($race['distance_m'])): ?> &middot; <?= (int)$race['distance_m'] ?> m<?php endif; ?>
        &middot; <?= (int)$race['lane_count'] ?> lanes
        &middot; <?= $h(formatDateTime($race['race_date'], $race['race_time'])) ?>
        &middot; <?= $h(\Models\EventRace::STATUSES[$race['status']] ?? $race['status']) ?>
      </td></tr>

      <?php if (empty($race['rounds'])): ?>
        <tr><td colspan="6" class="mut">No rounds have been set up for this race.</td></tr>
      <?php else: ?>
        <thead>
          <tr>
            <th width="150">Round</th><th width="90">Runs at</th>
            <th width="42">Lanes</th><th width="42">Heats</th>
            <th width="60">Qualifiers</th><th>Heat schedule</th>
          </tr>
        </thead>
        <?php foreach ($race['rounds'] as $round): $slot = roundSchedule($round, $race); ?>
          <tr class="roundrow">
            <td><?= $h($round['name']) ?>
              <div class="mut" style="font-weight:normal">
                <?= $h(\Models\Round::TYPES[$round['round_type']] ?? $round['round_type']) ?>
                &middot; <?= $h(\Models\Round::STATUSES[$round['status']] ?? $round['status']) ?>
              </div>
            </td>
            <td class="c"><?= $h(scheduleLabel($slot)) ?></td>
            <td class="c"><?= (int)$round['lane_count'] ?></td>
            <td class="c"><?= (int)$round['heat_count'] ?></td>
            <td class="c"><?= (int)$round['qualify_per_heat'] ?: '&mdash;' ?></td>
            <td style="font-weight:normal">
              <?php if (empty($round['heats'])): ?>
                <span class="mut">No heats yet</span>
              <?php else: $bits = [];
                      foreach ($round['heats'] as $ht) {
                          $bits[] = \Models\Heat::label($ht) . ' — '
                                  . scheduleLabel(heatSchedule($ht, $round, $race))
                                  . ' (' . (int)$ht['allocated_count'] . '/' . (int)$round['lane_count'] . ')';
                      }
                      echo $h(implode('  ·  ', $bits)); ?>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </table>
  </div>
<?php endforeach; endif; ?>

<div class="foot"><?= $h($footer) ?></div>
</body></html>
