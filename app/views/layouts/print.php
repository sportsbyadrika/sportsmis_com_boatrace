<?php
/**
 * Bare A4 layout for browser "Save as PDF". No app chrome, a running
 * "Page N of M" footer (Chromium/Edge honour the @page counter; Firefox
 * supplies its own), and a small toolbar that is hidden when printing.
 *
 * Views using this layout should set $pageTitle and mark any section that
 * must start on a fresh sheet with class="page-break".
 */
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle ?? 'Print') ?> – SportsMIS® Regatta</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    @page {
      size: <?= e($paperSize ?? 'A4 portrait') ?>;
      margin: 16mm 12mm 20mm 12mm;
      @bottom-right { content: "Page " counter(page) " of " counter(pages); font-size: 9pt; color: #666; }
    }
    html, body { background: #fff; color: #111; }
    body { font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 10.5pt; }
    h1, h2, h3, h4, h5, h6 { page-break-after: avoid; }
    table { width: 100%; border-collapse: collapse; }
    table thead { display: table-header-group; }
    table tr, table td, table th { page-break-inside: avoid; }
    table th, table td { padding: 4px 7px; border: 1px solid #444; vertical-align: middle; }
    table thead th { background: #f1f3f5; font-weight: 600; }
    .page-break { page-break-before: always; }
    .no-break   { page-break-inside: avoid; }
    .print-head { border-bottom: 2px solid #0b1f3a; padding-bottom: 8px; margin-bottom: 14px; }
    .print-head .brand { font-weight: 700; color: #0b1f3a; }
    .print-head .brand .sub { color: #0369a1; letter-spacing: .12em; text-transform: uppercase; font-size: 8pt; }
    .pos-gold   { background: #fef9c3; }
    .pos-silver { background: #f1f5f9; }
    .pos-bronze { background: #ffedd5; }
    .pos-fourth { background: #f8fafc; }
    @media screen {
      body { padding: 22px; max-width: 210mm; margin: 16px auto 32px; box-shadow: 0 0 12px rgba(0,0,0,.1); }
    }
    @media print { .no-print { display: none !important; } }
  </style>
</head>
<body>
  <div class="no-print d-flex gap-2 align-items-center mb-3">
    <button type="button" class="btn btn-sm btn-primary" onclick="window.print()">
      <i class="bi bi-printer me-1"></i>Print
    </button>
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.close()">
      <i class="bi bi-x-lg me-1"></i>Close
    </button>
    <span class="text-muted small ms-2">Use your browser's Print dialog to save as PDF.</span>
  </div>
  <?php require $content; ?>
</body>
</html>
