<?php
/**
 * Public / display layout. Deliberately chrome-free: the LED wall and the
 * chroma-key overlay own the whole viewport and bring their own styling, so
 * this layout ships only the document shell, the fonts and Bootstrap Icons.
 *
 * $bodyClass, $bodyStyle and $extraHead let a display view paint its own
 * background without a second layout.
 */
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e(csrfToken()) ?>">
  <title><?= e($pageTitle ?? 'SportsMIS® Regatta') ?></title>
  <link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="/assets/css/app.css?v=<?= @filemtime(PUBLIC_ROOT . '/assets/css/app.css') ?: time() ?>" rel="stylesheet">
  <?= $extraHead ?? '' ?>
</head>
<body class="<?= e($bodyClass ?? '') ?>" style="<?= e($bodyStyle ?? '') ?>">
  <?php require $content; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="/assets/js/app.js?v=<?= @filemtime(PUBLIC_ROOT . '/assets/js/app.js') ?: time() ?>"></script>
</body>
</html>
