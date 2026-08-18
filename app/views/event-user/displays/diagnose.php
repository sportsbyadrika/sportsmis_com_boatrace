<?php
/**
 * Public results diagnostics.
 *
 * From a browser, "no results" looks identical whether the page was never
 * republished, a file is missing, or a server rule denies the payload. This
 * separates them.
 */
$stale = $pageVersion !== null && $pageVersion < $wantVersion;
?>

<div class="d-flex align-items-center gap-2 mb-3">
  <a href="/event-user/displays" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <div>
    <h4 class="fw-bold mb-0">Public Results — Diagnostics</h4>
    <p class="text-muted mb-0 small">What is on disk, and what the web server actually returns for it.</p>
  </div>
</div>

<?php
  // Lead with the verdict — the detail below is for confirming it.
  $verdict = null;
  $probeManifest = $probes['manifest.json'] ?? [];
  $status = $probeManifest['status'] ?? null;

  if (!$dirExists) {
      $verdict = ['danger', 'The results directory does not exist. Publish once from Displays.'];
  } elseif (empty($files['manifest.json']['exists'])) {
      $verdict = ['danger', 'manifest.json is missing. Publish again from Displays.'];
  } elseif ($status === 403) {
      $verdict = ['danger', 'The server is refusing manifest.json (HTTP 403) — a rule is denying it. '
                          . 'Deploy the latest code, which removes .json from the deny list in the root .htaccess, then publish again.'];
  } elseif ($status === 404) {
      $verdict = ['danger', 'The server cannot find manifest.json (HTTP 404) — the document root or the '
                          . 'rewrite is not resolving /live/. Check the domain\'s document root.'];
  } elseif ($status === 500) {
      $verdict = ['danger', 'The server returned 500 for this directory — usually an .htaccess directive '
                          . 'the host does not permit. Publish again with the latest code, which guards those directives.'];
  } elseif ($stale) {
      $verdict = ['warning', 'The published page is older than the code on this server (v' . (int)$pageVersion
                          . ' vs v' . (int)$wantVersion . '). Press "Publish Now" to regenerate it.'];
  } elseif ($status !== null && $status >= 200 && $status < 300) {
      $verdict = ['success', 'Everything checks out — the server is serving the results data correctly.'];
  } else {
      $verdict = ['secondary', 'Could not reach the public URL from the server itself. '
                             . 'Open it in a browser to see what it returns.'];
  }
?>

<div class="alert alert-<?= e($verdict[0]) ?> d-flex gap-2 align-items-start">
  <i class="bi bi-<?= $verdict[0] === 'success' ? 'check-circle' : 'exclamation-triangle' ?> mt-1"></i>
  <div><?= e($verdict[1]) ?></div>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="sms-card h-100">
      <div class="sms-card-header"><strong><i class="bi bi-folder me-2"></i>On disk</strong></div>
      <div class="p-3">
        <div class="small text-muted mb-1">Directory</div>
        <code class="small d-block mb-2" style="word-break:break-all"><?= e($dir) ?></code>
        <div class="mb-3">
          <?= $dirExists ? '<span class="badge bg-success">Exists</span>' : '<span class="badge bg-danger">Missing</span>' ?>
          <?= $dirWritable ? '<span class="badge bg-success ms-1">Writable</span>' : '<span class="badge bg-warning text-dark ms-1">Not writable</span>' ?>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light"><tr><th>File</th><th class="text-end">Size</th><th>Perms</th><th>Written</th></tr></thead>
          <tbody>
            <?php foreach ($files as $name => $f): ?>
              <tr>
                <td><code class="small"><?= e($name) ?></code></td>
                <td class="text-end small"><?= !empty($f['exists']) ? number_format((int)$f['size']) . ' B' : '—' ?></td>
                <td class="small text-muted"><?= e($f['perms'] ?? '—') ?></td>
                <td class="small text-muted">
                  <?= !empty($f['exists']) ? e(date('d M, g:i:s A', (int)$f['mtime'])) : '<span class="text-danger">missing</span>' ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="p-3 border-top small">
        <div class="text-muted mb-1">Published page version</div>
        <?php if ($pageVersion === null): ?>
          <span class="badge bg-danger">index.html missing</span>
        <?php elseif ($stale): ?>
          <span class="badge bg-warning text-dark">v<?= (int)$pageVersion ?> — code here is v<?= (int)$wantVersion ?></span>
          <div class="text-muted mt-1">Publish again to regenerate the page from the deployed template.</div>
        <?php else: ?>
          <span class="badge bg-success">v<?= (int)$pageVersion ?> — current</span>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="sms-card h-100">
      <div class="sms-card-header"><strong><i class="bi bi-globe me-2"></i>What the server returns</strong></div>
      <div class="p-3">
        <?php foreach ($probes as $name => $p): ?>
          <div class="mb-3">
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <code class="small"><?= e($name) ?></code>
              <?php $st = $p['status']; ?>
              <?php if ($st === null): ?>
                <span class="badge bg-secondary">no response</span>
              <?php elseif ($st >= 200 && $st < 300): ?>
                <span class="badge bg-success">HTTP <?= (int)$st ?></span>
              <?php else: ?>
                <span class="badge bg-danger">HTTP <?= (int)$st ?></span>
              <?php endif; ?>
            </div>
            <div class="small text-muted mt-1" style="word-break:break-all"><?= e($p['url']) ?></div>
            <?php if ($p['error'] !== ''): ?>
              <div class="small text-danger mt-1"><?= e($p['error']) ?></div>
            <?php endif; ?>
            <?php if ($p['snippet'] !== ''): ?>
              <pre class="small bg-light border rounded p-2 mt-1 mb-0"
                   style="max-height:110px;overflow:auto"><?= e($p['snippet']) ?></pre>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>

        <?php if ($manifest): ?>
          <hr>
          <div class="small text-muted mb-1">Manifest on disk</div>
          <div class="small">
            version <strong><?= (int)($manifest['version'] ?? 0) ?></strong>
            &middot; points at <code><?= e((string)($manifest['file'] ?? '')) ?></code>
            <?php $named = $dir . '/' . (string)($manifest['file'] ?? ''); ?>
            <?= is_file($named) ? '<span class="badge bg-success ms-1">present</span>'
                                : '<span class="badge bg-danger ms-1">MISSING</span>' ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="sms-card p-3 mt-3">
  <div class="d-flex gap-2 flex-wrap">
    <form method="POST" action="/event-user/displays/publish">
      <?= csrf() ?>
      <button class="btn btn-primary"><i class="bi bi-cloud-arrow-up me-1"></i>Publish Now</button>
    </form>
    <a class="btn btn-outline-secondary" href="/event-user/displays/diagnose">
      <i class="bi bi-arrow-clockwise me-1"></i>Re-check
    </a>
    <a class="btn btn-outline-primary" target="_blank" rel="noopener" href="<?= e($baseUrl) ?>">
      <i class="bi bi-box-arrow-up-right me-1"></i>Open public page
    </a>
    <a class="btn btn-outline-primary" target="_blank" rel="noopener" href="<?= e($baseUrl) ?>manifest.json">
      <i class="bi bi-filetype-json me-1"></i>Open manifest.json
    </a>
  </div>
</div>
