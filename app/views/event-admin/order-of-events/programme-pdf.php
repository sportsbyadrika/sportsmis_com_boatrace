<?php
/**
 * Dompdf body for the programme. Self-contained: Dompdf gets no stylesheet
 * and no remote assets, so all CSS is inline here and the event logo arrives
 * as a data: URI from Pdf::imageDataUri().
 */
$genders = \Models\EventRace::GENDERS;
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$first = true;
?>
<!doctype html>
<html><head><meta charset="utf-8">
<style>
  @page { margin: 14mm 10mm 16mm 10mm; }
  body  { font-family: "DejaVu Sans", sans-serif; font-size: 8.5pt; color: #111; }
  .head { border-bottom: 2px solid #0b1f3a; padding-bottom: 6px; margin-bottom: 10px; }
  .head td { vertical-align: middle; border: 0; padding: 0; }
  .brand { font-size: 11pt; font-weight: bold; color: #0b1f3a; }
  .brand .sub { color: #0369a1; font-size: 7pt; letter-spacing: 1.2px; }
  .logo { height: 42px; }
  h1 { font-size: 13pt; margin: 0 0 2px; }
  .rname { font-size: 10pt; margin: 0 0 2px; }
  .meta { font-size: 8pt; color: #555; margin-bottom: 12px; }
  h2 { font-size: 10pt; margin: 12px 0 5px; }
  table.grid { width: 100%; border-collapse: collapse; }
  table.grid th, table.grid td { border: 1px solid #555; padding: 3px 5px; }
  table.grid thead th { background: #eef2f7; font-size: 8pt; }
  .c { text-align: center; }
  .b { font-weight: bold; }
  .mut { color: #666; font-size: 7.5pt; }
  .page-break { page-break-before: always; }
  .foot { margin-top: 14px; font-size: 7.5pt; color: #666; }
</style>
</head>
<body>

<table class="head" width="100%"><tr>
  <td width="55">
    <?php if ($logo !== ''): ?><img class="logo" src="<?= $h($logo) ?>"><?php endif; ?>
  </td>
  <td>
    <div class="brand">SportsMIS&reg; <span class="sub">REGATTA</span></div>
  </td>
  <td align="right" class="mut">
    Event Code <?= $h($event['code']) ?><br>
    Printed <?= $h(date('d M Y, g:i A')) ?>
  </td>
</tr></table>

<h1><?= $h($event['name']) ?></h1>
<?php if (!empty($event['name_regional'])): ?>
  <div class="rname"><?= $h($event['name_regional']) ?></div>
<?php endif; ?>
<div class="meta">
  <?= $h(formatDate($event['start_date'])) ?> &ndash; <?= $h(formatDate($event['end_date'])) ?>
  <?php if (!empty($event['venue'])): ?> &middot; <?= $h($event['venue']) ?><?php endif; ?>
  <?php if (!empty($event['organiser'])): ?> &middot; <?= $h($event['organiser']) ?><?php endif; ?>
</div>

<?php if (!$groups): ?>
  <p class="mut">No races have been added to the programme yet.</p>
<?php else: foreach ($groups as $date => $races): ?>
  <div class="<?= $first ? '' : 'page-break' ?>"><?php $first = false; ?>
    <h2><?= $date !== '' ? $h(formatDate($date, 'l, d F Y')) : 'Unscheduled' ?>
        <span class="mut">(<?= count($races) ?> race<?= count($races) === 1 ? '' : 's' ?>)</span></h2>
    <table class="grid">
      <thead>
        <tr>
          <th width="30">Sl.</th><th width="52">Time</th><th>Race</th>
          <th width="80">Class</th><th width="48">Gender</th>
          <th width="46">Dist.</th><th width="38">Lanes</th><th width="80">Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($races as $r): ?>
          <tr>
            <td class="c b"><?= (int)$r['sl_no'] ?></td>
            <td class="c"><?= $h(formatTime($r['race_time'])) ?></td>
            <td>
              <span class="b"><?= $h($r['name']) ?></span>
              <?php if (!empty($r['name_regional'])): ?><div class="mut"><?= $h($r['name_regional']) ?></div><?php endif; ?>
              <?php if (!empty($r['round_schedule'])): ?>
                <div style="font-size:7.5pt">Rounds: <?= $h($r['round_schedule']) ?></div>
              <?php endif; ?>
            </td>
            <td><?= $h($r['boat_class'] ?: '—') ?></td>
            <td class="c"><?= $h($genders[$r['gender']] ?? $r['gender']) ?></td>
            <td class="c"><?= $r['distance_m'] ? (int)$r['distance_m'] . ' m' : '—' ?></td>
            <td class="c"><?= (int)$r['lane_count'] ?></td>
            <td><?= $h(\Models\EventRace::STATUSES[$r['status']] ?? $r['status']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endforeach; endif; ?>

<div class="foot"><?= $h($footer) ?></div>
</body></html>
