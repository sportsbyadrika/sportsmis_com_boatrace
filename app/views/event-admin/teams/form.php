<?php
/** Add / edit a boat. $team is null when adding. */
$isEdit = !empty($team);
$action = $isEdit ? '/event-admin/teams/' . hid_team((int)$team['id']) : '/event-admin/teams';
$val = function (string $key, $fallback = '') use ($team, $isEdit) {
    if ($isEdit && array_key_exists($key, $team) && $team[$key] !== null) return $team[$key];
    return old($key, $fallback);
};
?>

<div class="d-flex align-items-center gap-2 mb-3">
  <a href="/event-admin/teams" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <div>
    <h4 class="fw-bold mb-0"><?= $isEdit ? 'Edit Team' : 'Add Team' ?></h4>
    <p class="text-muted mb-0 small">A team is one competing boat crew: the club, the boat and its captain.</p>
  </div>
</div>

<form method="POST" action="<?= e($action) ?>" enctype="multipart/form-data" novalidate>
  <?= csrf() ?>
  <div class="row g-3">
    <div class="col-lg-8">
      <div class="sms-card p-4 mb-3">
        <h6 class="fw-bold mb-3"><i class="bi bi-water me-2"></i>Boat &amp; Club</h6>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="club_name">Club name <span class="text-danger">*</span></label>
            <input type="text" class="form-control <?= hasError('club_name') ?>" id="club_name" name="club_name"
                   value="<?= e($val('club_name')) ?>" required>
            <?= fieldError('club_name') ?>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="boat_name">Boat name <span class="text-danger">*</span></label>
            <input type="text" class="form-control <?= hasError('boat_name') ?>" id="boat_name" name="boat_name"
                   value="<?= e($val('boat_name')) ?>" required>
            <?= fieldError('boat_name') ?>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="captain_name">Captain <span class="text-danger">*</span></label>
            <input type="text" class="form-control <?= hasError('captain_name') ?>" id="captain_name"
                   name="captain_name" value="<?= e($val('captain_name')) ?>" required>
            <?= fieldError('captain_name') ?>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="boat_class">Boat class / type</label>
            <input type="text" class="form-control" id="boat_class" name="boat_class"
                   value="<?= e($val('boat_class')) ?>" placeholder="e.g. Chundan Vallam">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="home_place">Home place</label>
            <input type="text" class="form-control" id="home_place" name="home_place" value="<?= e($val('home_place')) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="short_code">Short code</label>
            <input type="text" class="form-control text-uppercase" id="short_code" name="short_code"
                   value="<?= e($val('short_code')) ?>" maxlength="20" placeholder="e.g. NCB">
            <div class="form-text">Shown on the lane board and the display screens where space is tight.</div>
          </div>
        </div>
      </div>

      <div class="sms-card p-4">
        <h6 class="fw-bold mb-3"><i class="bi bi-telephone me-2"></i>Contact (optional)</h6>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label" for="contact_name">Contact person</label>
            <input type="text" class="form-control" id="contact_name" name="contact_name" value="<?= e($val('contact_name')) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label" for="contact_phone">Phone</label>
            <input type="tel" class="form-control" id="contact_phone" name="contact_phone" value="<?= e($val('contact_phone')) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label" for="contact_email">Email</label>
            <input type="email" class="form-control <?= hasError('contact_email') ?>" id="contact_email"
                   name="contact_email" value="<?= e($val('contact_email')) ?>">
            <?= fieldError('contact_email') ?>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="sms-card p-4 mb-3">
        <h6 class="fw-bold mb-3"><i class="bi bi-image me-2"></i>Club Logo</h6>
        <div class="text-center mb-3">
          <img id="logoPreview" src="<?= e($val('logo')) ?>"
               class="rounded border <?= $val('logo') ? '' : 'd-none' ?>"
               style="max-width:100%;max-height:160px;object-fit:contain" alt="">
        </div>
        <input type="file" class="form-control" name="logo" accept="image/*" data-preview="logoPreview" data-max-mb="7">
        <div class="form-text">Shown on the lane board and both display screens.</div>
      </div>

      <div class="sms-card p-4 mb-3">
        <h6 class="fw-bold mb-3"><i class="bi bi-toggles me-2"></i>Team Status</h6>
        <select class="form-select" name="status">
          <option value="active"   <?= $val('status', 'active') === 'active'   ? 'selected' : '' ?>>Active</option>
          <option value="inactive" <?= $val('status', 'active') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
        <div class="form-text mt-2">An inactive boat stays on record but is never offered to the lane draw.</div>
      </div>

      <div class="d-grid gap-2">
        <button type="submit" class="btn btn-primary py-2">
          <i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Save Changes' : 'Add Team' ?>
        </button>
        <a href="/event-admin/teams" class="btn btn-outline-secondary">Cancel</a>
      </div>
    </div>
  </div>
</form>
