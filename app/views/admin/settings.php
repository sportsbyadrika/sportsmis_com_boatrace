<?php /** Super Admin — platform-wide defaults. */ ?>

<div class="mb-3">
  <h4 class="fw-bold mb-1">Platform Settings</h4>
  <p class="text-muted mb-0 small">Defaults applied when a new event is created.</p>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <form class="sms-card p-4" method="POST" action="/admin/settings" data-ajax-form>
      <?= csrf() ?>
      <h6 class="fw-bold mb-3"><i class="bi bi-gear me-2"></i>General</h6>

      <div class="mb-3">
        <label class="form-label" for="platform_name">Platform display name</label>
        <input type="text" class="form-control" id="platform_name" name="platform_name"
               value="<?= e($settings['platform_name'] ?? 'SportsMIS® Regatta') ?>">
      </div>
      <div class="mb-3">
        <label class="form-label" for="support_email">Support email</label>
        <input type="email" class="form-control" id="support_email" name="support_email"
               value="<?= e($settings['support_email'] ?? '') ?>" placeholder="support@sportsmis.com">
        <div class="form-text">Shown to event organisers when they need help.</div>
      </div>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label" for="default_lanes">Default lane count</label>
          <input type="number" class="form-control" id="default_lanes" name="default_lanes" min="2" max="20"
                 value="<?= e($settings['default_lanes'] ?? '6') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label" for="default_chroma">Default chroma colour</label>
          <input type="text" class="form-control" id="default_chroma" name="default_chroma"
                 value="<?= e($settings['default_chroma'] ?? '#00b140') ?>">
        </div>
      </div>
      <div class="mt-3 mb-3">
        <label class="form-label" for="programme_footer">Programme / report footer</label>
        <input type="text" class="form-control" id="programme_footer" name="programme_footer"
               value="<?= e($settings['programme_footer'] ?? 'Powered by SportsMIS® Regatta') ?>">
      </div>

      <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Settings</button>
    </form>
  </div>

  <div class="col-lg-5">
    <div class="sms-card p-4">
      <h6 class="fw-bold mb-3"><i class="bi bi-diagram-2 me-2"></i>How access works</h6>
      <ol class="small text-muted ps-3 mb-0">
        <li class="mb-2"><strong>Super Admin</strong> (you) creates events and their Event Admin accounts.</li>
        <li class="mb-2"><strong>Event Admin</strong> configures the event, teams, registrations and the
            Order of Events, and creates Event User accounts with granular privileges.</li>
        <li class="mb-0"><strong>Event User</strong> runs the race office — rounds &amp; heats, lane draw,
            result entry, reports and the display screens — limited to the privileges granted.</li>
      </ol>
      <hr>
      <p class="small text-muted mb-0">
        All three sign in on separate pages, and the two event roles need the event&rsquo;s
        <strong>Event Code</strong> alongside their email.
      </p>
    </div>
  </div>
</div>
