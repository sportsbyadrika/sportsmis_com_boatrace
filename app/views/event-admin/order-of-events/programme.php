<?php
/**
 * Printable programme (browser print view, A4). $groups is date => races[];
 * an unscheduled race is grouped under ''. Each date starts a fresh sheet
 * after the first.
 */
$genders = \Models\EventRace::GENDERS;
$first   = true;
?>
<div class="print-head d-flex align-items-center justify-content-between">
  <div>
    <div class="brand">
      SportsMIS<sup>&reg;</sup>
      <span class="sub">Regatta</span>
    </div>
  </div>
  <div class="text-end small text-muted">
    Event Code <?= e($event['code']) ?><br>
    Printed <?= e(date('d M Y, g:i A')) ?>
  </div>
</div>

<h1 style="font-size:16pt;margin:0 0 2px"><?= e($event['name']) ?></h1>
<?php if (!empty($event['name_regional'])): ?>
  <div style="font-size:12pt;margin-bottom:2px"><?= e($event['name_regional']) ?></div>
<?php endif; ?>
<div class="small text-muted" style="margin-bottom:14px">
  <?= e(formatDate($event['start_date'])) ?> &ndash; <?= e(formatDate($event['end_date'])) ?>
  <?php if (!empty($event['venue'])): ?> &middot; <?= e($event['venue']) ?><?php endif; ?>
  <?php if (!empty($event['organiser'])): ?> &middot; <?= e($event['organiser']) ?><?php endif; ?>
</div>

<h2 style="font-size:13pt;margin:0 0 10px">Order of Events</h2>

<?php if (!$groups): ?>
  <p class="text-muted">No races have been added to the programme yet.</p>
<?php else: foreach ($groups as $date => $races): ?>
  <div class="<?= $first ? '' : 'page-break' ?>"><?php $first = false; ?>
    <h3 style="font-size:12pt;margin:14px 0 6px">
      <?= $date !== '' ? e(formatDate($date, 'l, d F Y')) : 'Unscheduled' ?>
      <span class="small text-muted">(<?= count($races) ?> race<?= count($races) === 1 ? '' : 's' ?>)</span>
    </h3>
    <table>
      <thead>
        <tr>
          <th style="width:38px">Sl.</th>
          <th style="width:62px">Time</th>
          <th>Race</th>
          <th style="width:96px">Class</th>
          <th style="width:56px">Gender</th>
          <th style="width:56px">Dist.</th>
          <th style="width:44px">Lanes</th>
          <th style="width:96px">Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($races as $r): ?>
          <tr>
            <td class="text-center fw-bold"><?= (int)$r['sl_no'] ?></td>
            <td class="text-center"><?= e(formatTime($r['race_time'])) ?></td>
            <td>
              <strong><?= e($r['name']) ?></strong>
              <?php if (!empty($r['name_regional'])): ?>
                <div class="small text-muted"><?= e($r['name_regional']) ?></div>
              <?php endif; ?>
              <?php if (!empty($r['code'])): ?>
                <div class="small text-muted">Code <?= e($r['code']) ?></div>
              <?php endif; ?>
            </td>
            <td class="small"><?= e($r['boat_class'] ?: '—') ?></td>
            <td class="small text-center"><?= e($genders[$r['gender']] ?? $r['gender']) ?></td>
            <td class="small text-center"><?= $r['distance_m'] ? (int)$r['distance_m'] . ' m' : '—' ?></td>
            <td class="text-center"><?= (int)$r['lane_count'] ?></td>
            <td class="small"><?= e(\Models\EventRace::STATUSES[$r['status']] ?? $r['status']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endforeach; endif; ?>

<p class="small text-muted" style="margin-top:18px"><?= e($footer) ?></p>
