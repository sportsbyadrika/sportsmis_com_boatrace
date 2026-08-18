<?php
/**
 * Bulk upload — step 2. Every row is shown with what it would do before
 * anything is written. Rows with errors are listed but never imported.
 */
$actionBadge = [
    'create' => ['Add',    'success',        'bi-plus-lg'],
    'update' => ['Update', 'info text-dark', 'bi-arrow-repeat'],
    'skip'   => ['Skip',   'secondary',      'bi-dash-lg'],
    'error'  => ['Error',  'danger',         'bi-exclamation-triangle'],
];
$writable = $counts['create'] + $counts['update'];
?>

<div class="d-flex align-items-center gap-2 mb-3">
  <a href="/event-admin/teams/import" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <div>
    <h4 class="fw-bold mb-0">Preview</h4>
    <p class="text-muted mb-0 small">Nothing has been saved yet. Check the rows below, then confirm.</p>
  </div>
</div>

<?php if (!empty($truncated)): ?>
  <div class="alert alert-warning d-flex gap-2 align-items-start">
    <i class="bi bi-exclamation-triangle mt-1"></i>
    <div class="small">
      Only the first <strong><?= (int)$maxRows ?></strong> rows were read — the rest of the file was
      ignored. Split it and upload the remainder as a second batch.
    </div>
  </div>
<?php endif; ?>

<div class="row g-3 mb-3">
  <?php
    $cards = [
      ['To add',    $counts['create'], 'bi-plus-circle',         '#f0fdf4', '#15803d'],
      ['To update', $counts['update'], 'bi-arrow-repeat',        '#eff6ff', '#1d4ed8'],
      ['Skipped',   $counts['skip'],   'bi-dash-circle',         '#f8fafc', '#64748b'],
      ['Errors',    $counts['error'],  'bi-exclamation-triangle','#fef2f2', '#b91c1c'],
    ];
    foreach ($cards as [$label, $value, $icon, $bg, $fg]):
  ?>
    <div class="col-6 col-lg-3">
      <div class="sms-stat-card">
        <div class="sms-stat-icon" style="background:<?= e($bg) ?>;color:<?= e($fg) ?>"><i class="bi <?= e($icon) ?>"></i></div>
        <div class="sms-stat-body">
          <div class="sms-stat-value"><?= (int)$value ?></div>
          <div class="sms-stat-label"><?= e($label) ?></div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="sms-card p-3 mb-3">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div class="small text-muted">
      Existing boats: <strong><?= $mode === 'update' ? 'updated from the file' : 'left alone' ?></strong>
      &middot; Registration state:
      <strong><?= e(ucfirst($status)) ?></strong>
      <?php if ($counts['error'] > 0): ?>
        <div class="text-danger mt-1">
          <i class="bi bi-info-circle me-1"></i>
          <?= (int)$counts['error'] ?> row<?= $counts['error'] === 1 ? '' : 's' ?> with errors will not be imported.
          Fix them in the file and upload again if you need them.
        </div>
      <?php endif; ?>
    </div>
    <div class="d-flex gap-2">
      <a href="/event-admin/teams/import" class="btn btn-outline-secondary">
        <i class="bi bi-x-lg me-1"></i>Cancel
      </a>
      <?php if ($writable > 0): ?>
        <form method="POST" action="/event-admin/teams/import">
          <?= csrf() ?>
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i>Import <?= (int)$writable ?> row<?= $writable === 1 ? '' : 's' ?>
          </button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if ($writable === 0): ?>
  <div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle me-1"></i>
    There is nothing to import from this file. Every row was either skipped or had an error.
  </div>
<?php endif; ?>

<div class="sms-card">
  <div class="sms-card-header">
    <strong><i class="bi bi-table me-2"></i><?= count($rows) ?> row<?= count($rows) === 1 ? '' : 's' ?> read</strong>
    <input type="search" class="form-control form-control-sm w-auto" placeholder="Filter rows…"
           data-filter-for="importRows">
  </div>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0" data-filter-table="importRows">
      <thead class="table-light">
        <tr>
          <th style="width:60px">Row</th>
          <th style="width:96px">Action</th>
          <th>Boat</th>
          <th>Club</th>
          <th>Captain</th>
          <th>Code</th>
          <th>Contact</th>
          <th>Notes</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r):
                $d = $r['data'];
                [$label, $colour, $icon] = $actionBadge[$r['action']] ?? $actionBadge['error']; ?>
          <tr class="<?= $r['action'] === 'error' ? 'table-danger' : '' ?>">
            <td class="text-muted small"><?= (int)$r['line'] ?></td>
            <td><span class="badge bg-<?= e($colour) ?>"><i class="bi <?= e($icon) ?> me-1"></i><?= e($label) ?></span></td>
            <td class="fw-semibold"><?= e($d['boat_name'] ?: '—') ?></td>
            <td class="small"><?= e($d['club_name'] ?: '—') ?></td>
            <td class="small text-muted"><?= e($d['captain_name'] ?: '—') ?></td>
            <td><?= $d['short_code'] !== '' ? '<code class="small">' . e($d['short_code']) . '</code>' : '<span class="text-muted small">—</span>' ?></td>
            <td class="small text-muted">
              <?= e($d['contact_phone'] ?: '') ?>
              <?php if ($d['contact_email'] !== ''): ?>
                <div class="text-truncate" style="max-width:190px"><?= e($d['contact_email']) ?></div>
              <?php endif; ?>
              <?php if ($d['contact_phone'] === '' && $d['contact_email'] === ''): ?>—<?php endif; ?>
            </td>
            <td class="small">
              <?php if ($r['errors']): ?>
                <span class="text-danger"><?= e(implode('; ', $r['errors'])) ?></span>
              <?php elseif ($r['action'] === 'skip'): ?>
                <span class="text-muted">Already on file as <?= e($r['existing']) ?></span>
              <?php elseif ($r['action'] === 'update'): ?>
                <span class="text-muted">Replaces <?= e($r['existing']) ?></span>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="px-3 py-2 small text-muted border-top" data-filter-count="importRows"></div>
</div>
