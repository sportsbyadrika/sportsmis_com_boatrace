<?php
/**
 * Shared <head> + masthead for the Dompdf report bodies. Dompdf gets no
 * external stylesheet and no remote assets, so all CSS is inline and the
 * event image arrives as a data: URI ($logo) from Pdf::imageDataUri().
 * Expects $event, $logo and defines $h for escaping.
 */
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
  h2 { font-size: 10.5pt; margin: 12px 0 5px; }
  h3 { font-size: 9.5pt; margin: 10px 0 4px; }
  table.grid { width: 100%; border-collapse: collapse; }
  table.grid th, table.grid td { border: 1px solid #555; padding: 3px 5px; }
  table.grid thead th { background: #eef2f7; font-size: 8pt; }
  .c { text-align: center; }
  .b { font-weight: bold; }
  .mut { color: #666; font-size: 7.5pt; }
  .gold   { background: #fef9c3; }
  .silver { background: #f1f5f9; }
  .bronze { background: #ffedd5; }
  .fourth { background: #f8fafc; }
  .page-break { page-break-before: always; }
  .foot { margin-top: 14px; font-size: 7.5pt; color: #666; }
</style>
</head>
<body>

<table class="head" width="100%"><tr>
  <td width="55"><?php if (($logo ?? '') !== ''): ?><img class="logo" src="<?= $h($logo) ?>"><?php endif; ?></td>
  <td><div class="brand">SportsMIS&reg; <span class="sub">REGATTA</span></div></td>
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
