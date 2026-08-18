<?php /** Super Admin — the Event Admin accounts of one event. */ ?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/admin/events/<?= e(hid_event((int)$event['id'])) ?>" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i>
  </a>
  <div class="flex-grow-1 min-w-0">
    <h4 class="fw-bold mb-0 text-truncate">Event Admins</h4>
    <div class="small text-muted">
      <?= e($event['name']) ?> &middot;
      <span class="badge bg-primary-subtle text-primary-emphasis"><i class="bi bi-hash"></i><?= e($event['code']) ?></span>
    </div>
  </div>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddAdmin">
    <i class="bi bi-person-plus me-1"></i>Add Event Admin
  </button>
</div>

<div class="alert alert-info d-flex gap-2 align-items-start">
  <i class="bi bi-info-circle mt-1"></i>
  <div class="small">
    An event admin signs in at <a href="/login" target="_blank" rel="noopener">/login</a>
    with their email and the password shown once when the account is created or reset.
    There is one sign-in page for everyone — the workspace they land in follows from the account,
    so the Event Code (<strong><?= e($event['code']) ?></strong>) is not needed to sign in.
  </div>
</div>

<div class="sms-card">
  <div class="sms-card-header">
    <strong><i class="bi bi-person-badge me-2"></i><?= count($accounts) ?> account<?= count($accounts) === 1 ? '' : 's' ?></strong>
  </div>

  <?php if (!$accounts): ?>
    <div class="p-5 text-center">
      <i class="bi bi-person-x d-block mb-2" style="font-size:2.6rem;color:#cbd5e1"></i>
      <h6 class="fw-semibold">No admin accounts yet</h6>
      <p class="text-muted small mb-3">Create one so the organiser can start configuring the event.</p>
      <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalAddAdmin">
        <i class="bi bi-person-plus me-1"></i>Add Event Admin
      </button>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr><th>Name</th><th>Email</th><th>Phone</th><th>Status</th><th>Last login</th><th class="text-end">Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($accounts as $a): $h = hid_admin((int)$a['id']); ?>
            <tr>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <div class="sms-avatar sms-avatar-sm"><?= e(avatarInitials($a['name'])) ?></div>
                  <span class="fw-semibold"><?= e($a['name']) ?></span>
                </div>
              </td>
              <td class="small text-muted"><?= e($a['email']) ?></td>
              <td class="small text-muted"><?= e($a['phone'] ?: '—') ?></td>
              <td><?= statusBadge($a['status']) ?></td>
              <td class="small text-muted">
                <?= $a['last_login_at'] ? e(formatDate($a['last_login_at'], 'd M Y, g:i A')) : 'Never' ?>
              </td>
              <td class="text-end">
                <div class="btn-group btn-group-sm">
                  <button class="btn btn-outline-secondary" data-bs-toggle="modal"
                          data-bs-target="#modalEdit<?= e($h) ?>" title="Edit"><i class="bi bi-pencil"></i></button>
                  <form method="POST" action="/admin/admins/<?= e($h) ?>/reset" class="d-inline">
                    <?= csrf() ?>
                    <button class="btn btn-outline-warning" title="Reset password"
                            data-confirm="Reset the password for <?= e($a['email']) ?>? The current one stops working immediately.">
                      <i class="bi bi-key"></i>
                    </button>
                  </form>
                  <form method="POST" action="/admin/admins/<?= e($h) ?>/delete" class="d-inline">
                    <?= csrf() ?>
                    <button class="btn btn-outline-danger" title="Remove"
                            data-confirm="Remove <?= e($a['email']) ?> from this event?">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Add -->
<div class="modal fade" id="modalAddAdmin" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="/admin/events/<?= e(hid_event((int)$event['id'])) ?>/admins">
        <?= csrf() ?>
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add Event Admin</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Full name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Email <span class="text-danger">*</span></label>
            <input type="email" name="email" class="form-control" required>
            <div class="form-text">Must be unique within this event.</div>
          </div>
          <div class="mb-0">
            <label class="form-label">Phone</label>
            <input type="tel" name="phone" class="form-control">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Create Account</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit (one per row) -->
<?php foreach ($accounts as $a): $h = hid_admin((int)$a['id']); ?>
  <div class="modal fade" id="modalEdit<?= e($h) ?>" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form method="POST" action="/admin/admins/<?= e($h) ?>/update">
          <?= csrf() ?>
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Account</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Email</label>
              <input type="email" class="form-control" value="<?= e($a['email']) ?>" disabled>
              <div class="form-text">The sign-in email can&rsquo;t be changed — remove and re-add instead.</div>
            </div>
            <div class="mb-3">
              <label class="form-label">Full name <span class="text-danger">*</span></label>
              <input type="text" name="name" class="form-control" value="<?= e($a['name']) ?>" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Phone</label>
              <input type="tel" name="phone" class="form-control" value="<?= e($a['phone']) ?>">
            </div>
            <div class="mb-0">
              <label class="form-label">Status</label>
              <select name="status" class="form-select">
                <option value="active"   <?= $a['status'] === 'active'   ? 'selected' : '' ?>>Active</option>
                <option value="disabled" <?= $a['status'] === 'disabled' ? 'selected' : '' ?>>Disabled</option>
              </select>
              <div class="form-text">A disabled account can&rsquo;t sign in, but its history is kept.</div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>
<?php endforeach; ?>
