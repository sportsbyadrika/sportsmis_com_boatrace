<?php
/** Create / edit an event. $event is null when creating. */
$isEdit  = !empty($event);
$action  = $isEdit ? '/admin/events/' . hid_event((int)$event['id']) : '/admin/events';
$val = function (string $key, $fallback = '') use ($event, $isEdit) {
    if ($isEdit && array_key_exists($key, $event) && $event[$key] !== null) return $event[$key];
    return old($key, $fallback);
};
?>

<div class="d-flex align-items-center gap-2 mb-3">
  <a href="<?= $isEdit ? '/admin/events/' . e(hid_event((int)$event['id'])) : '/admin/events' ?>"
     class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <div>
    <h4 class="fw-bold mb-0"><?= $isEdit ? 'Edit Event' : 'Create Event' ?></h4>
    <p class="text-muted mb-0 small">
      <?= $isEdit ? 'Update this regatta&rsquo;s details.' : 'Set up a new regatta. An Event Code is generated automatically.' ?>
    </p>
  </div>
</div>

<form method="POST" action="<?= e($action) ?>" enctype="multipart/form-data" novalidate>
  <?= csrf() ?>

  <div class="row g-3">
    <div class="col-lg-8">
      <div class="sms-card p-4 mb-3">
        <h6 class="fw-bold mb-3"><i class="bi bi-info-circle me-2"></i>Event Details</h6>

        <div class="mb-3">
          <label class="form-label" for="name">Event name <span class="text-danger">*</span></label>
          <input type="text" class="form-control <?= hasError('name') ?>" id="name" name="name"
                 value="<?= e($val('name')) ?>" placeholder="e.g. Nehru Trophy Boat Race 2026" required>
          <?= fieldError('name') ?>
        </div>

        <div class="mb-3">
          <label class="form-label" for="name_regional">Regional-language name</label>
          <input type="text" class="form-control" id="name_regional" name="name_regional"
                 value="<?= e($val('name_regional')) ?>" placeholder="Name in the local script">
          <div class="form-text">Shown alongside the English name on the programme and the display screens.</div>
        </div>

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="venue">Venue</label>
            <input type="text" class="form-control" id="venue" name="venue"
                   value="<?= e($val('venue')) ?>" placeholder="e.g. Punnamada Lake">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="district">District / Region</label>
            <input type="text" class="form-control" id="district" name="district" value="<?= e($val('district')) ?>">
          </div>
          <div class="col-md-12">
            <label class="form-label" for="organiser">Organising body</label>
            <input type="text" class="form-control" id="organiser" name="organiser" value="<?= e($val('organiser')) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="start_date">Start date</label>
            <input type="date" class="form-control" id="start_date" name="start_date" value="<?= e($val('start_date')) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="end_date">End date</label>
            <input type="date" class="form-control" id="end_date" name="end_date" value="<?= e($val('end_date')) ?>">
          </div>
          <div class="col-12">
            <label class="form-label" for="description">Description</label>
            <textarea class="form-control" id="description" name="description" rows="3"><?= e($val('description')) ?></textarea>
          </div>
        </div>
      </div>

      <div class="sms-card p-4">
        <h6 class="fw-bold mb-3"><i class="bi bi-display me-2"></i>Race &amp; Display Defaults</h6>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label" for="default_lanes">Default lanes / tracks</label>
            <input type="number" class="form-control" id="default_lanes" name="default_lanes" min="2" max="20"
                   value="<?= e($val('default_lanes', $defaultLanes ?? 6)) ?>">
            <div class="form-text">Starting point for each new round; every round can override it.</div>
          </div>
          <div class="col-md-4">
            <label class="form-label" for="chroma_color">Chroma-key colour</label>
            <div class="input-group">
              <input type="color" class="form-control form-control-color" id="chroma_color_picker"
                     value="<?= e($val('chroma_color', '#00b140')) ?>"
                     oninput="document.getElementById('chroma_color').value = this.value">
              <input type="text" class="form-control" id="chroma_color" name="chroma_color"
                     value="<?= e($val('chroma_color', '#00b140')) ?>" pattern="#[0-9a-fA-F]{6}">
            </div>
            <div class="form-text">Background of the YouTube overlay.</div>
          </div>
          <div class="col-md-4">
            <label class="form-label" for="slide_seconds">LED wall seconds / slide</label>
            <input type="number" class="form-control" id="slide_seconds" name="slide_seconds" min="3" max="60"
                   value="<?= e($val('slide_seconds', 9)) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="display_pin">Display operator PIN</label>
            <input type="text" class="form-control" id="display_pin" name="display_pin"
                   value="<?= e($val('display_pin')) ?>" maxlength="12" placeholder="Leave blank for no PIN">
            <div class="form-text">Asked for once when opening the LED wall on a venue machine.</div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="sms-card p-4 mb-3">
        <h6 class="fw-bold mb-3"><i class="bi bi-image me-2"></i>Event Image</h6>
        <div class="text-center mb-3">
          <img id="imagePreview"
               src="<?= e($val('image')) ?>"
               class="rounded border <?= $val('image') ? '' : 'd-none' ?>"
               style="max-width:100%;max-height:180px;object-fit:cover" alt="">
        </div>
        <input type="file" class="form-control" name="image" accept="image/*"
               data-preview="imagePreview" data-max-mb="7">
        <div class="form-text">JPG, PNG or WebP, up to 7&nbsp;MB.</div>
      </div>

      <div class="sms-card p-4 mb-3">
        <h6 class="fw-bold mb-3"><i class="bi bi-toggles me-2"></i>Status</h6>
        <select class="form-select" name="status">
          <?php foreach (\Models\Event::STATUSES as $key => $label): ?>
            <option value="<?= e($key) ?>" <?= $val('status', 'draft') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="form-text mt-2">Only a <strong>draft</strong> event can be deleted; archive anything that has run.</div>
      </div>

      <?php if ($isEdit): ?>
        <div class="sms-card p-4 mb-3">
          <h6 class="fw-bold mb-2"><i class="bi bi-hash me-2"></i>Event Code</h6>
          <div class="d-flex align-items-center gap-2">
            <code class="fs-5"><?= e($event['code'] ?? '—') ?></code>
          </div>
          <div class="form-text">Event admins and event users sign in with this code. It never changes.</div>
        </div>
      <?php endif; ?>

      <div class="d-grid gap-2">
        <button type="submit" class="btn btn-primary py-2">
          <i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Save Changes' : 'Create Event' ?>
        </button>
        <a href="<?= $isEdit ? '/admin/events/' . e(hid_event((int)$event['id'])) : '/admin/events' ?>"
           class="btn btn-outline-secondary">Cancel</a>
      </div>
    </div>
  </div>
</form>
