<?php /** Event Admin — race-office accounts and their privileges. */ ?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <div>
    <h4 class="fw-bold mb-1">Event Users</h4>
    <p class="text-muted mb-0 small">Race-office accounts for this event. Each sees only what its privileges allow.</p>
  </div>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddUser">
    <i class="bi bi-person-plus me-1"></i>Add Event User
  </button>
</div>

<div class="alert alert-info d-flex gap-2 align-items-start">
  <i class="bi bi-info-circle mt-1"></i>
  <div class="small">
    Event users sign in at <a href="/event-user/login" target="_blank" rel="noopener">/event-user/login</a>
    with Event Code <strong><?= e($event['code']) ?></strong>, their email and the password shown once when
    the account is created or reset.
  </div>
</div>

<?php if (!$users): ?>
  <div class="sms-empty-state">
    <i class="bi bi-person-badge"></i>
    <h5>No event users yet</h5>
    <p>Create accounts for the people running heats, the lane draw, results and the screens.</p>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddUser">
      <i class="bi bi-person-plus me-1"></i>Add Event User
    </button>
  </div>
<?php else: ?>
  <div class="sms-card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr><th>Name</th><th>Email</th><th>Privileges</th><th>Status</th><th>Last login</th><th class="text-end">Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): $h = hid_user((int)$u['id']); ?>
            <tr>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <div class="sms-avatar sms-avatar-sm"><?= e(avatarInitials($u['name'])) ?></div>
                  <div>
                    <div class="fw-semibold"><?= e($u['name']) ?></div>
                    <?php if (!empty($u['designation'])): ?>
                      <div class="small text-muted"><?= e($u['designation']) ?></div>
                    <?php endif; ?>
                  </div>
                </div>
              </td>
              <td class="small text-muted"><?= e($u['email']) ?></td>
              <td>
                <?php if (!$u['privileges']): ?>
                  <span class="badge bg-danger-subtle text-danger-emphasis">No privileges — can't do anything</span>
                <?php else: foreach ($u['privileges'] as $p): ?>
                  <span class="badge bg-light text-dark border me-1 mb-1"><?= e($privileges[$p] ?? $p) ?></span>
                <?php endforeach; endif; ?>
              </td>
              <td><?= statusBadge($u['status']) ?></td>
              <td class="small text-muted">
                <?= $u['last_login_at'] ? e(formatDate($u['last_login_at'], 'd M Y, g:i A')) : 'Never' ?>
              </td>
              <td class="text-end">
                <div class="btn-group btn-group-sm">
                  <button class="btn btn-outline-secondary" data-bs-toggle="modal"
                          data-bs-target="#modalEditUser<?= e($h) ?>" title="Edit"><i class="bi bi-pencil"></i></button>
                  <form method="POST" action="/event-admin/users/<?= e($h) ?>/reset" class="d-inline">
                    <?= csrf() ?>
                    <button class="btn btn-outline-warning" title="Reset password"
                            data-confirm="Reset the password for <?= e($u['email']) ?>?"><i class="bi bi-key"></i></button>
                  </form>
                  <form method="POST" action="/event-admin/users/<?= e($h) ?>/delete" class="d-inline">
                    <?= csrf() ?>
                    <button class="btn btn-outline-danger" title="Remove"
                            data-confirm="Remove <?= e($u['email']) ?> from this event?"><i class="bi bi-trash"></i></button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<!-- Add -->
<div class="modal fade" id="modalAddUser" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form method="POST" action="/event-admin/users">
        <?= csrf() ?>
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add Event User</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Full name <span class="text-danger">*</span></label>
              <input type="text" name="name" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Email <span class="text-danger">*</span></label>
              <input type="email" name="email" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Phone</label>
              <input type="tel" name="phone" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Designation</label>
              <input type="text" name="designation" class="form-control" placeholder="e.g. Chief Judge">
            </div>
          </div>
          <hr>
          <label class="form-label fw-semibold">Privileges</label>
          <div class="row g-2">
            <?php foreach ($privileges as $key => $label): ?>
              <div class="col-md-6">
                <div class="form-check border rounded p-2 ps-4">
                  <input class="form-check-input" type="checkbox" name="privileges[]"
                         value="<?= e($key) ?>" id="add_<?= e($key) ?>">
                  <label class="form-check-label small" for="add_<?= e($key) ?>">
                    <strong><?= e($label) ?></strong>
                    <div class="text-muted"><?= e(\Models\EventUser::PRIVILEGE_META[$key][2] ?? '') ?></div>
                  </label>
                </div>
              </div>
            <?php endforeach; ?>
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

<!-- Edit -->
<?php foreach ($users as $u): $h = hid_user((int)$u['id']); ?>
  <div class="modal fade" id="modalEditUser<?= e($h) ?>" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <form method="POST" action="/event-admin/users/<?= e($h) ?>/update">
          <?= csrf() ?>
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit <?= e($u['name']) ?></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" class="form-control" value="<?= e($u['email']) ?>" disabled>
                <div class="form-text">The sign-in email can&rsquo;t be changed.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Full name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" value="<?= e($u['name']) ?>" required>
              </div>
              <div class="col-md-4">
                <label class="form-label">Phone</label>
                <input type="tel" name="phone" class="form-control" value="<?= e($u['phone']) ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label">Designation</label>
                <input type="text" name="designation" class="form-control" value="<?= e($u['designation']) ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                  <option value="active"   <?= $u['status'] === 'active'   ? 'selected' : '' ?>>Active</option>
                  <option value="disabled" <?= $u['status'] === 'disabled' ? 'selected' : '' ?>>Disabled</option>
                </select>
              </div>
            </div>
            <hr>
            <label class="form-label fw-semibold">Privileges</label>
            <div class="row g-2">
              <?php foreach ($privileges as $key => $label): ?>
                <div class="col-md-6">
                  <div class="form-check border rounded p-2 ps-4">
                    <input class="form-check-input" type="checkbox" name="privileges[]"
                           value="<?= e($key) ?>" id="ed_<?= e($h) ?>_<?= e($key) ?>"
                           <?= in_array($key, $u['privileges'], true) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="ed_<?= e($h) ?>_<?= e($key) ?>">
                      <strong><?= e($label) ?></strong>
                      <div class="text-muted"><?= e(\Models\EventUser::PRIVILEGE_META[$key][2] ?? '') ?></div>
                    </label>
                  </div>
                </div>
              <?php endforeach; ?>
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
