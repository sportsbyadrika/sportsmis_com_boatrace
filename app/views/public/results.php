<?php /** The Results card a spectator arrives from — links into the static pages. */ ?>
<div class="container py-5">
  <div class="text-center mb-4">
    <span class="sms-mark d-inline-flex mb-3" style="width:56px;height:56px;font-size:1.6rem">
      <i class="bi bi-trophy"></i>
    </span>
    <h3 class="fw-bold mb-1">Live Results</h3>
    <p class="text-muted mb-0">Choose an event to follow the racing.</p>
  </div>

  <?php if (!$events): ?>
    <div class="row justify-content-center">
      <div class="col-md-7">
        <div class="sms-empty-state">
          <i class="bi bi-hourglass"></i>
          <h5>Nothing published yet</h5>
          <p>Results appear here as soon as an event publishes them.</p>
        </div>
      </div>
    </div>
  <?php else: ?>
    <div class="row g-3 justify-content-center">
      <?php foreach ($events as $ev): ?>
        <div class="col-md-6 col-xl-4">
          <a class="sms-event-card sms-hover-lift h-100 d-block text-decoration-none text-body"
             href="<?= e($ev['url']) ?>">
            <?php if ($ev['image'] !== ''): ?>
              <div style="height:150px;background:#e2e8f0 center/cover no-repeat url('<?= e($ev['image']) ?>')"></div>
            <?php endif; ?>
            <div class="sms-event-card-body">
              <h6 class="fw-bold mb-1"><?= e($ev['name']) ?></h6>
              <?php if ($ev['regional'] !== ''): ?>
                <div class="small text-muted mb-1"><?= e($ev['regional']) ?></div>
              <?php endif; ?>
              <div class="small text-muted">
                <?= e($ev['dates']) ?><?php if ($ev['venue'] !== ''): ?> &middot; <?= e($ev['venue']) ?><?php endif; ?>
              </div>
              <div class="d-flex align-items-center justify-content-between mt-3">
                <span class="badge bg-success"><i class="bi bi-broadcast me-1"></i>Results live</span>
                <span class="small text-water fw-semibold">Open <i class="bi bi-chevron-right"></i></span>
              </div>
            </div>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
