<?php
/**
 * Event details — each panel saves on its own over AJAX (data-ajax-form
 * posts to /event-admin/details/{panel}/save and reports through the shared
 * toast), so nothing has to be filled in one sitting.
 */
?>

<div class="mb-3">
  <h4 class="fw-bold mb-1">Event Details</h4>
  <p class="text-muted mb-0 small">Each section saves on its own — no need to complete the whole page.</p>
</div>

<div class="row g-3">
  <div class="col-lg-8">

    <!-- Identity -->
    <form class="sms-card p-4 mb-3" method="POST" action="/event-admin/details/identity/save" data-ajax-form>
      <?= csrf() ?>
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h6 class="fw-bold mb-0"><i class="bi bi-card-heading me-2"></i>Identity</h6>
        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-check-lg me-1"></i>Save</button>
      </div>
      <div class="mb-3">
        <label class="form-label" for="name">Event name <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="name" name="name" value="<?= e($event['name']) ?>" required>
      </div>
      <div class="mb-3">
        <label class="form-label" for="name_regional">Regional-language name</label>
        <input type="text" class="form-control" id="name_regional" name="name_regional"
               value="<?= e($event['name_regional']) ?>">
        <div class="form-text">Printed on the programme and shown on both display screens.</div>
      </div>
      <div class="mb-0">
        <label class="form-label" for="description">Description</label>
        <textarea class="form-control" id="description" name="description" rows="3"><?= e($event['description']) ?></textarea>
      </div>
    </form>

    <!-- Schedule & venue -->
    <form class="sms-card p-4 mb-3" method="POST" action="/event-admin/details/schedule/save" data-ajax-form>
      <?= csrf() ?>
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h6 class="fw-bold mb-0"><i class="bi bi-calendar3 me-2"></i>Schedule &amp; Venue</h6>
        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-check-lg me-1"></i>Save</button>
      </div>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label" for="start_date">Start date</label>
          <input type="date" class="form-control" id="start_date" name="start_date" value="<?= e($event['start_date']) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label" for="end_date">End date</label>
          <input type="date" class="form-control" id="end_date" name="end_date" value="<?= e($event['end_date']) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label" for="venue">Venue</label>
          <input type="text" class="form-control" id="venue" name="venue" value="<?= e($event['venue']) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label" for="district">District / Region</label>
          <input type="text" class="form-control" id="district" name="district" value="<?= e($event['district']) ?>">
        </div>
        <div class="col-12">
          <label class="form-label" for="organiser">Organising body</label>
          <input type="text" class="form-control" id="organiser" name="organiser" value="<?= e($event['organiser']) ?>">
        </div>
      </div>
    </form>

    <!-- Racing defaults -->
    <form class="sms-card p-4 mb-3" method="POST" action="/event-admin/details/racing/save" data-ajax-form>
      <?= csrf() ?>
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h6 class="fw-bold mb-0"><i class="bi bi-water me-2"></i>Racing Defaults</h6>
        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-check-lg me-1"></i>Save</button>
      </div>
      <div class="row g-3">
        <div class="col-md-5">
          <label class="form-label" for="default_lanes">Default lanes / tracks</label>
          <input type="number" class="form-control" id="default_lanes" name="default_lanes" min="2" max="20"
                 value="<?= (int)$event['default_lanes'] ?>">
          <div class="form-text">Applied to a new round; each round can still set its own lane count.</div>
        </div>
      </div>
    </form>

    <!-- Display -->
    <form class="sms-card p-4" method="POST" action="/event-admin/details/display/save" data-ajax-form>
      <?= csrf() ?>
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h6 class="fw-bold mb-0"><i class="bi bi-tv me-2"></i>Display Screens</h6>
        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-check-lg me-1"></i>Save</button>
      </div>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label" for="chroma_color">Chroma-key colour</label>
          <div class="input-group">
            <input type="color" class="form-control form-control-color" value="<?= e($event['chroma_color']) ?>"
                   oninput="document.getElementById('chroma_color').value = this.value">
            <input type="text" class="form-control" id="chroma_color" name="chroma_color"
                   value="<?= e($event['chroma_color']) ?>" pattern="#[0-9a-fA-F]{6}">
          </div>
          <div class="form-text">Background of the YouTube overlay.</div>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="slide_seconds">LED wall seconds / slide</label>
          <input type="number" class="form-control" id="slide_seconds" name="slide_seconds" min="3" max="60"
                 value="<?= (int)$event['slide_seconds'] ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label" for="display_pin">Operator PIN</label>
          <input type="text" class="form-control" id="display_pin" name="display_pin"
                 value="<?= e($event['display_pin']) ?>" maxlength="12" placeholder="Blank = no PIN">
          <div class="form-text">Asked once when the LED wall opens.</div>
        </div>
      </div>
    </form>
  </div>

  <div class="col-lg-4">
    <div class="sms-card p-4 mb-3">
      <h6 class="fw-bold mb-3"><i class="bi bi-image me-2"></i>Event Image</h6>
      <form method="POST" action="/event-admin/details/image" enctype="multipart/form-data">
        <?= csrf() ?>
        <div class="text-center mb-3">
          <img id="imagePreview" src="<?= e($event['image']) ?>"
               class="rounded border <?= $event['image'] ? '' : 'd-none' ?>"
               style="max-width:100%;max-height:180px;object-fit:cover" alt="">
        </div>
        <input type="file" class="form-control mb-2" name="image" accept="image/*"
               data-preview="imagePreview" data-max-mb="7" required>
        <button type="submit" class="btn btn-outline-primary w-100">
          <i class="bi bi-upload me-1"></i>Upload Image
        </button>
      </form>
    </div>

    <div class="sms-card p-4">
      <h6 class="fw-bold mb-2"><i class="bi bi-hash me-2"></i>Event Code</h6>
      <div class="fs-4"><code><?= e($event['code']) ?></code></div>
      <p class="small text-muted mb-0 mt-2">
        Identifies this regatta on the programme, the reports and the display screens.
        It is <strong>not</strong> needed to sign in — everyone uses
        <a href="/login" target="_blank" rel="noopener">/login</a> with their email and password —
        but a venue operator does need it to open a display screen.
      </p>
    </div>
  </div>
</div>
