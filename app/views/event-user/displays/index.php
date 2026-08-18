<?php /** Launcher for the two public screens. */ ?>

<div class="mb-3">
  <h4 class="fw-bold mb-1">Display Screens</h4>
  <p class="text-muted mb-0 small">
    Both screens read <strong>published rounds only</strong> — publish a round from Result Entry to put it on air.
  </p>
</div>

<?php if (!$published): ?>
  <div class="alert alert-warning d-flex gap-2 align-items-start">
    <i class="bi bi-exclamation-triangle mt-1"></i>
    <div class="small">
      Nothing is published yet, so both screens will show a waiting message.
      Publish a round from <strong>Result Entry</strong> first.
    </div>
  </div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="sms-card h-100 p-4">
      <div class="d-flex align-items-center gap-3 mb-3">
        <div class="sms-action-icon text-water fs-4"><i class="bi bi-tv"></i></div>
        <div>
          <h6 class="fw-bold mb-0">Big TV / LED Wall</h6>
          <div class="small text-muted">Auto-rotating deck for the venue screen.</div>
        </div>
      </div>
      <ul class="small text-muted ps-3 mb-3">
        <li>Rotates every <strong><?= (int)$event['slide_seconds'] ?>s</strong> through the event card,
            each published round, the rank list and the club tally.</li>
        <li>Pulls fresh results in the background, so it stays current without being touched.</li>
        <li>Operator keys: <kbd>Space</kbd> pause &middot; <kbd>&larr;</kbd> <kbd>&rarr;</kbd> step
            &middot; <kbd>F</kbd> full screen.</li>
      </ul>
      <div class="input-group input-group-sm mb-2">
        <span class="input-group-text">URL</span>
        <input type="text" class="form-control" readonly value="<?= e(url($wallUrl)) ?>"
               onfocus="this.select()">
      </div>
      <a href="<?= e($wallUrl) ?>" target="_blank" rel="noopener" class="btn btn-primary w-100">
        <i class="bi bi-play-fill me-1"></i>Open LED Wall
      </a>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="sms-card h-100 p-4">
      <div class="d-flex align-items-center gap-3 mb-3">
        <div class="sms-action-icon fs-4" style="color:<?= e($event['chroma_color']) ?>">
          <i class="bi bi-camera-reels"></i>
        </div>
        <div>
          <h6 class="fw-bold mb-0">YouTube Stream Overlay</h6>
          <div class="small text-muted">Chroma-key cards for the live broadcast.</div>
        </div>
      </div>
      <ul class="small text-muted ps-3 mb-3">
        <li>Paints a solid
          <span class="d-inline-block rounded border align-middle"
                style="width:12px;height:12px;background:<?= e($event['chroma_color']) ?>"></span>
          <code><?= e($event['chroma_color']) ?></code> background for your vision mixer to key out.</li>
        <li>Pick the race, round and heat that goes on air from the strip at the bottom.</li>
        <li>Hide that strip before capturing, so nothing but the cards is in shot.</li>
        <li>Change the key colour under <strong>Event Details &rarr; Display Screens</strong>,
            or per session with <code>?chroma=#rrggbb</code>.</li>
      </ul>
      <div class="input-group input-group-sm mb-2">
        <span class="input-group-text">URL</span>
        <input type="text" class="form-control" readonly value="<?= e(url($streamUrl)) ?>"
               onfocus="this.select()">
      </div>
      <a href="<?= e($streamUrl) ?>" target="_blank" rel="noopener" class="btn btn-water w-100">
        <i class="bi bi-play-fill me-1"></i>Open Stream Overlay
      </a>
    </div>
  </div>
</div>

<div class="sms-card p-4 mt-3">
  <h6 class="fw-bold mb-2"><i class="bi bi-key me-2"></i>Opening a screen on a venue machine</h6>
  <p class="small text-muted mb-2">
    Neither screen needs an app login. On the venue PC, open
    <a href="/display" target="_blank" rel="noopener"><?= e(url('/display')) ?></a>,
    enter Event Code <strong><?= e($event['code']) ?></strong>
    <?php if (!empty($event['display_pin'])): ?>
      and the operator PIN, then pick a screen.
    <?php else: ?>
      and pick a screen — this event has no PIN set, so the screens are open to anyone with the code.
    <?php endif; ?>
  </p>
  <?php if (empty($event['display_pin'])): ?>
    <p class="small text-muted mb-0">
      <i class="bi bi-info-circle me-1"></i>
      Ask your event administrator to set an operator PIN under
      <strong>Event Details &rarr; Display Screens</strong> if you would rather the screens weren&rsquo;t open.
    </p>
  <?php endif; ?>
</div>
