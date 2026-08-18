<?php /** Operator landing page — open a screen by Event Code (+ PIN if set). */ ?>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
      <div class="text-center mb-4">
        <span class="sms-mark d-inline-flex mb-3" style="width:56px;height:56px;font-size:1.6rem">
          <i class="bi bi-water"></i>
        </span>
        <h4 class="fw-bold mb-1">Display Screens</h4>
        <p class="text-muted mb-0">Open the LED wall or the live-stream overlay for an event.</p>
      </div>

      <?= flashBag() ?>

      <div class="sms-card p-4">
        <form method="POST" action="/display/open" novalidate>
          <?= csrf() ?>
          <div class="mb-3">
            <label class="form-label" for="event_code">Event Code</label>
            <div class="input-group">
              <span class="input-group-text bg-white"><i class="bi bi-hash"></i></span>
              <input type="text" class="form-control text-uppercase" id="event_code" name="event_code"
                     value="<?= e(old('event_code')) ?>" placeholder="RG1A2B3C" required autofocus>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label" for="display_pin">Operator PIN</label>
            <div class="input-group">
              <span class="input-group-text bg-white"><i class="bi bi-key"></i></span>
              <input type="text" class="form-control" id="display_pin" name="display_pin" maxlength="12"
                     placeholder="Leave blank if the event has no PIN" autocomplete="off">
            </div>
          </div>

          <label class="form-label">Screen</label>
          <div class="row g-2 mb-3">
            <div class="col-6">
              <input type="radio" class="btn-check" name="screen" id="scrWall" value="wall" checked>
              <label class="btn btn-outline-primary w-100 py-3" for="scrWall">
                <i class="bi bi-tv d-block fs-4 mb-1"></i>LED Wall
                <div class="small text-muted">Auto-rotating deck</div>
              </label>
            </div>
            <div class="col-6">
              <input type="radio" class="btn-check" name="screen" id="scrStream" value="stream">
              <label class="btn btn-outline-primary w-100 py-3" for="scrStream">
                <i class="bi bi-camera-reels d-block fs-4 mb-1"></i>Stream Overlay
                <div class="small text-muted">Chroma-key green</div>
              </label>
            </div>
          </div>

          <button type="submit" class="btn btn-primary w-100 py-2">
            <i class="bi bi-play-fill me-1"></i>Open Screen
          </button>
        </form>
      </div>

      <p class="text-center text-muted small mt-3 mb-0">
        Only published results are shown. Powered by
        <strong>SportsMIS<sup style="font-size:.7em">&reg;</sup></strong> &middot; Regatta
      </p>
    </div>
  </div>
</div>
