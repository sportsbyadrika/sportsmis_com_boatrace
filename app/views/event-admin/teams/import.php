<?php /** Bulk upload — step 1: pick the file and say how to treat what's already on file. */ ?>

<div class="d-flex align-items-center gap-2 mb-3">
  <a href="/event-admin/teams" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <div>
    <h4 class="fw-bold mb-0">Bulk Upload Teams</h4>
    <p class="text-muted mb-0 small">
      Add or update many boats at once from a spreadsheet. Nothing is saved until you review the preview.
    </p>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <form class="sms-card p-4" method="POST" action="/event-admin/teams/import/preview"
          enctype="multipart/form-data">
      <?= csrf() ?>

      <h6 class="fw-bold mb-3"><i class="bi bi-file-earmark-arrow-up me-2"></i>Choose your file</h6>

      <div class="mb-3">
        <label class="form-label" for="file">CSV file <span class="text-danger">*</span></label>
        <input type="file" class="form-control" id="file" name="file" accept=".csv,text/csv,text/plain"
               data-max-mb="2" required>
        <div class="form-text">
          Up to <?= (int)$maxRows ?> rows, 2&nbsp;MB. In Excel or Google Sheets use
          <strong>File → Save as / Download → CSV</strong>.
        </div>
      </div>

      <hr>

      <div class="mb-3">
        <label class="form-label">If a boat is already on file</label>
        <div class="form-check">
          <input class="form-check-input" type="radio" name="mode" id="modeSkip" value="skip" checked>
          <label class="form-check-label" for="modeSkip">
            <strong>Leave it alone</strong>
            <div class="small text-muted">Only boats not already entered are added.</div>
          </label>
        </div>
        <div class="form-check mt-2">
          <input class="form-check-input" type="radio" name="mode" id="modeUpdate" value="update">
          <label class="form-check-label" for="modeUpdate">
            <strong>Update it from the file</strong>
            <div class="small text-muted">
              Overwrites the details of matching boats. Club logos are never touched.
            </div>
          </label>
        </div>
      </div>

      <div class="mb-4">
        <label class="form-label" for="registration_status">Registration state for imported boats</label>
        <select class="form-select" id="registration_status" name="registration_status">
          <option value="draft" selected>Draft — review and approve later</option>
          <option value="submitted">Submitted — put them in the review queue</option>
          <option value="approved">Approved — ready to enter races straight away</option>
        </select>
        <div class="form-text">
          A registration is only ever moved forward, so re-uploading can&rsquo;t un-approve a boat
          that has already been vetted.
        </div>
      </div>

      <button type="submit" class="btn btn-primary w-100 py-2">
        <i class="bi bi-eye me-1"></i>Upload &amp; Preview
      </button>
    </form>
  </div>

  <div class="col-lg-5">
    <div class="sms-card p-4 mb-3">
      <h6 class="fw-bold mb-2"><i class="bi bi-download me-2"></i>Start from the template</h6>
      <p class="small text-muted">
        The template has the exact column headings and two example rows. Replace the examples
        with your own boats and upload it.
      </p>
      <a href="/event-admin/teams/import/template" class="btn btn-outline-primary w-100">
        <i class="bi bi-file-earmark-spreadsheet me-1"></i>Download CSV Template
      </a>
    </div>

    <div class="sms-card p-4">
      <h6 class="fw-bold mb-3"><i class="bi bi-list-columns me-2"></i>Columns</h6>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light"><tr><th>Heading</th><th>Required</th><th class="text-end">Max</th></tr></thead>
          <tbody>
            <?php foreach ($columns as [$label, $required, $max]): ?>
              <tr>
                <td class="small fw-semibold"><?= e($label) ?></td>
                <td>
                  <?php if ($required): ?>
                    <span class="badge bg-danger-subtle text-danger-emphasis">Required</span>
                  <?php else: ?>
                    <span class="small text-muted">Optional</span>
                  <?php endif; ?>
                </td>
                <td class="text-end small text-muted"><?= (int)$max ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <hr>
      <ul class="small text-muted mb-0 ps-3">
        <li>Column order doesn&rsquo;t matter, and extra columns are ignored.</li>
        <li>Headings are matched loosely — <code>Club Name</code>, <code>club_name</code>
            and <code>CLUBNAME</code> all work.</li>
        <li><strong>Status</strong> takes <code>active</code> or <code>inactive</code>;
            blank means active.</li>
        <li><strong>Short Code</strong> identifies a boat on re-upload. Without one,
            club&nbsp;+&nbsp;boat name is used.</li>
        <li><strong>Club logos are not part of the upload</strong> — a spreadsheet can&rsquo;t carry an
            image. Add them from each team&rsquo;s edit form afterwards.</li>
      </ul>
    </div>
  </div>
</div>
