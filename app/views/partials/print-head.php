<?php
/** Shared masthead for the browser print views (A4 `print` layout). */
?>
<div class="print-head d-flex align-items-center justify-content-between">
  <div class="brand">SportsMIS<sup>&reg;</sup> <span class="sub">Regatta</span></div>
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
