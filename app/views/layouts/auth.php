<?php
/**
 * Sign-in chrome. Left panel carries the Regatta brand story, right panel
 * the form. Used by all three login areas (super admin, event admin,
 * event user) — each view supplies its own form and role tab state.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e(csrfToken()) ?>">
  <title><?= e($pageTitle ?? 'Sign in') ?> – SportsMIS® Regatta</title>
  <link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="/assets/css/app.css?v=<?= @filemtime(PUBLIC_ROOT . '/assets/css/app.css') ?: time() ?>" rel="stylesheet">
</head>
<body class="sms-auth-body">

<div class="sms-auth-wrapper">

  <!-- Brand panel -->
  <aside class="sms-auth-brand text-white">
    <div class="sms-auth-brand-inner">
      <div class="d-flex align-items-center gap-2 mb-4">
        <span class="sms-mark" style="width:44px;height:44px;font-size:1.4rem"><i class="bi bi-water"></i></span>
        <div>
          <div class="h4 fw-bold mb-0">SportsMIS<sup style="font-size:.55em">&reg;</sup></div>
          <div class="sms-subbrand" style="color:#7dd3fc">Regatta</div>
        </div>
      </div>

      <h2 class="fw-bold mb-3" style="line-height:1.2">Run your boat race,<br>lane by lane.</h2>
      <p class="text-white-75 mb-4">
        The SportsMIS platform, purpose-built for regattas — programme, heats,
        lane draw, results and the big screen, all in one place.
      </p>

      <div class="d-flex flex-column gap-3">
        <div class="d-flex align-items-start gap-3">
          <div class="sms-auth-feat-icon"><i class="bi bi-list-ol"></i></div>
          <div>
            <div class="fw-semibold">Order of Events</div>
            <div class="small text-white-75">Serial, date, time and call-room status — printable programme.</div>
          </div>
        </div>
        <div class="d-flex align-items-start gap-3">
          <div class="sms-auth-feat-icon"><i class="bi bi-grid-3x3-gap"></i></div>
          <div>
            <div class="fw-semibold">Heats &amp; Lane Draw</div>
            <div class="small text-white-75">Drag a boat onto a lane; qualifiers advance automatically.</div>
          </div>
        </div>
        <div class="d-flex align-items-start gap-3">
          <div class="sms-auth-feat-icon"><i class="bi bi-tv"></i></div>
          <div>
            <div class="fw-semibold">LED wall &amp; live stream</div>
            <div class="small text-white-75">Auto-rotating result deck plus a chroma-key overlay for YouTube.</div>
          </div>
        </div>
      </div>

      <div class="small text-white-75 mt-5">
        Powered by <strong class="text-white">SportsMIS<sup style="font-size:.7em">&reg;</sup></strong>
      </div>
    </div>
  </aside>

  <!-- Form panel -->
  <section class="sms-auth-form-panel">
    <div class="sms-auth-form-inner">
      <?= flashBag() ?>
      <?php require $content; ?>
      <div class="text-center text-muted small mt-4">
        &copy; <?= date('Y') ?> SportsByA Tech (OPC) Private Limited
      </div>
    </div>
  </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/app.js?v=<?= @filemtime(PUBLIC_ROOT . '/assets/js/app.js') ?: time() ?>"></script>
</body>
</html>
