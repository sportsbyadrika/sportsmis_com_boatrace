<?php /** Event Admin dashboard — the event at a glance. */ ?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <div class="min-w-0">
    <h4 class="fw-bold mb-1 text-truncate"><?= e($event['name']) ?></h4>
    <p class="text-muted mb-0 small">
      <?php if (!empty($event['name_regional'])): ?><?= e($event['name_regional']) ?> &middot; <?php endif; ?>
      <?= e(formatDate($event['start_date'])) ?> &ndash; <?= e(formatDate($event['end_date'])) ?>
      <?php if (!empty($event['venue'])): ?> &middot; <?= e($event['venue']) ?><?php endif; ?>
    </p>
  </div>
  <div class="d-flex gap-2">
    <?= statusBadge($event['status']) ?>
  </div>
</div>

<div class="row g-3 mb-4">
  <?php
    $cards = [
      ['Teams',            $stats['teams'],     'bi-people',        '#eff6ff', '#1d4ed8'],
      ['Approved',         $stats['approved'],  'bi-check2-circle', '#f0fdf4', '#15803d'],
      ['Awaiting Review',  $stats['pending'],   'bi-hourglass',     '#fffbeb', '#b45309'],
      ['Races',            $stats['races'],     'bi-list-ol',       '#f5f3ff', '#6d28d9'],
      ['Heats',            $stats['heats'],     'bi-grid-3x3',      '#ecfeff', '#0e7490'],
      ['Event Users',      $stats['event_users'],'bi-person-badge', '#fef2f2', '#b91c1c'],
    ];
    foreach ($cards as [$label, $value, $icon, $bg, $fg]):
  ?>
    <div class="col-6 col-lg-2">
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

<div class="row g-3 mb-4">
  <?php
    $actions = [
      ['Event Details',    'Name, dates, venue and display defaults.',      'bi-sliders',       '/event-admin/details'],
      ['Teams',            'Clubs, boats and captains.',                    'bi-people',        '/event-admin/teams'],
      ['Registrations',    'Review and approve entries.',                   'bi-clipboard-check','/event-admin/registrations'],
      ['Order of Events',  'The programme, with a printable version.',      'bi-list-ol',       '/event-admin/order-of-events'],
      ['Event Users',      'Race-office accounts and their privileges.',    'bi-person-badge',  '/event-admin/users'],
    ];
    foreach ($actions as [$title, $blurb, $icon, $href]):
  ?>
    <div class="col-md-6 col-xl-4">
      <a class="sms-action-card h-100" href="<?= e($href) ?>">
        <div class="sms-action-icon text-water"><i class="bi <?= e($icon) ?>"></i></div>
        <div class="min-w-0">
          <div class="fw-semibold"><?= e($title) ?></div>
          <div class="small text-muted"><?= e($blurb) ?></div>
        </div>
        <i class="bi bi-chevron-right ms-auto text-muted"></i>
      </a>
    </div>
  <?php endforeach; ?>
</div>

<div class="sms-card p-4 mb-4">
  <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-3">
    <div>
      <h6 class="fw-bold mb-1"><i class="bi bi-broadcast me-2"></i>Race Office</h6>
      <p class="text-muted mb-0 small">
        Run this event yourself — heats and the lane draw, results, reports, and the screens —
        without creating an Event User account just for the job. You keep your administrator
        session and can step back at any time.
      </p>
    </div>
    <a href="/event-admin/race-office" class="btn btn-water">
      <i class="bi bi-box-arrow-in-right me-1"></i>Open Race Office
    </a>
  </div>

  <div class="row g-2">
    <?php
      $office = [
        ['Rounds &amp; Heats', 'bi-diagram-3', 'rounds'],
        ['Lane Allocation',    'bi-water',     'lane-allocation'],
        ['Result Entry',       'bi-stopwatch', 'results'],
        ['Reports',            'bi-trophy',    'reports'],
        ['TV &amp; Broadcast', 'bi-tv',        'displays'],
      ];
      foreach ($office as [$title, $icon, $to]):
    ?>
      <div class="col-6 col-lg">
        <a class="sms-action-card h-100 text-center d-block py-3"
           href="/event-admin/race-office?to=<?= e($to) ?>">
          <div class="sms-action-icon text-water mx-auto mb-2"><i class="bi <?= e($icon) ?>"></i></div>
          <div class="small fw-semibold"><?= $title ?></div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="sms-card h-100">
      <div class="sms-card-header">
        <strong><i class="bi bi-hourglass-split me-2"></i>Awaiting Review
          <span class="badge bg-warning text-dark ms-1"><?= count($pending) ?></span></strong>
        <a href="/event-admin/registrations?status=submitted" class="btn btn-sm btn-outline-primary">Review</a>
      </div>
      <?php if (!$pending): ?>
        <div class="p-4 text-center text-muted small">Nothing is waiting for review.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th>Boat</th><th>Club</th><th>Submitted</th></tr></thead>
            <tbody>
              <?php foreach (array_slice($pending, 0, 6) as $p): ?>
                <tr>
                  <td class="fw-semibold"><?= e($p['boat_name']) ?></td>
                  <td class="small text-muted"><?= e($p['club_name']) ?></td>
                  <td class="small text-muted"><?= e(formatDate($p['submitted_at'], 'd M, g:i A')) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="sms-card h-100">
      <div class="sms-card-header">
        <strong><i class="bi bi-calendar3 me-2"></i>Up Next</strong>
        <a href="/event-admin/order-of-events" class="btn btn-sm btn-outline-primary">Programme</a>
      </div>
      <?php if (!$upcoming): ?>
        <div class="p-4 text-center text-muted small">
          No races scheduled yet. <a href="/event-admin/order-of-events">Build the programme</a>.
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th style="width:50px">#</th><th>Race</th><th>When</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach ($upcoming as $r): ?>
                <tr>
                  <td class="fw-bold"><?= (int)$r['sl_no'] ?></td>
                  <td>
                    <div class="fw-semibold"><?= e($r['name']) ?></div>
                    <?php if (!empty($r['name_regional'])): ?>
                      <div class="small text-muted"><?= e($r['name_regional']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="small text-muted"><?= e(formatDateTime($r['race_date'], $r['race_time'])) ?></td>
                  <td><?= raceStatusBadge($r['status']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
