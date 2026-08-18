<?php
/**
 * Registration review queue. Submit / approve / return / reopen all post to
 * the same endpoint with an `action` field, so the row buttons stay uniform.
 */
$tabs = ['' => 'All'] + \Models\TeamRegistration::STATUSES;
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <div>
    <h4 class="fw-bold mb-1">Team Registrations</h4>
    <p class="text-muted mb-0 small">Only an approved registration can be entered into a race and drawn into a lane.</p>
  </div>
  <?php if (($counts['submitted'] ?? 0) > 0): ?>
    <form method="POST" action="/event-admin/registrations/approve-all">
      <?= csrf() ?>
      <button class="btn btn-success"
              data-confirm="Approve all <?= (int)$counts['submitted'] ?> submitted registration(s)?">
        <i class="bi bi-check2-all me-1"></i>Approve All Submitted
      </button>
    </form>
  <?php endif; ?>
</div>

<ul class="nav nav-pills mb-3 flex-wrap gap-1">
  <?php foreach ($tabs as $key => $label): ?>
    <li class="nav-item">
      <a class="nav-link <?= $status === (string)$key ? 'active' : '' ?>"
         href="/event-admin/registrations<?= $key === '' ? '' : '?status=' . e((string)$key) ?>">
        <?= e($label) ?>
        <span class="badge bg-light text-dark ms-1">
          <?= $key === '' ? array_sum($counts) : (int)($counts[$key] ?? 0) ?>
        </span>
      </a>
    </li>
  <?php endforeach; ?>
</ul>

<?php if (!$registrations): ?>
  <div class="sms-empty-state">
    <i class="bi bi-clipboard-check"></i>
    <h5>Nothing here</h5>
    <p>No registrations match this filter. Add teams first — each new team opens a draft registration.</p>
    <a href="/event-admin/teams" class="btn btn-primary">Go to Teams</a>
  </div>
<?php else: ?>
  <div class="sms-card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr><th>Boat</th><th>Club</th><th>Captain</th><th>Status</th><th>Remarks</th><th class="text-end">Action</th></tr>
        </thead>
        <tbody>
          <?php foreach ($registrations as $r): $h = hid_reg((int)$r['id']); ?>
            <tr>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <?php if (!empty($r['logo'])): ?>
                    <img src="<?= e($r['logo']) ?>" class="rounded border" width="32" height="32"
                         style="object-fit:cover" alt="">
                  <?php else: ?>
                    <div class="sms-avatar sms-avatar-sm"><?= e(avatarInitials($r['boat_name'])) ?></div>
                  <?php endif; ?>
                  <div>
                    <div class="fw-semibold"><?= e($r['boat_name']) ?></div>
                    <?php if (!empty($r['boat_class'])): ?>
                      <div class="small text-muted"><?= e($r['boat_class']) ?></div>
                    <?php endif; ?>
                  </div>
                </div>
              </td>
              <td class="small"><?= e($r['club_name']) ?></td>
              <td class="small text-muted"><?= e($r['captain_name']) ?></td>
              <td>
                <?= statusBadge($r['status']) ?>
                <?php if (!empty($r['submitted_at'])): ?>
                  <div class="small text-muted mt-1"><?= e(formatDate($r['submitted_at'], 'd M, g:i A')) ?></div>
                <?php endif; ?>
              </td>
              <td class="small text-muted" style="max-width:260px"><?= e($r['remarks'] ?: '—') ?></td>
              <td class="text-end">
                <div class="btn-group btn-group-sm">
                  <?php if (in_array($r['status'], ['draft', 'returned'], true)): ?>
                    <form method="POST" action="/event-admin/registrations/<?= e($h) ?>/decide" class="d-inline">
                      <?= csrf() ?><input type="hidden" name="action" value="submit">
                      <button class="btn btn-outline-primary"><i class="bi bi-send me-1"></i>Submit</button>
                    </form>
                  <?php endif; ?>

                  <?php if ($r['status'] === 'submitted'): ?>
                    <form method="POST" action="/event-admin/registrations/<?= e($h) ?>/decide" class="d-inline">
                      <?= csrf() ?><input type="hidden" name="action" value="approve">
                      <button class="btn btn-outline-success"><i class="bi bi-check-lg me-1"></i>Approve</button>
                    </form>
                    <button class="btn btn-outline-warning" data-bs-toggle="modal"
                            data-bs-target="#modalReturn<?= e($h) ?>">
                      <i class="bi bi-arrow-counterclockwise me-1"></i>Return
                    </button>
                  <?php endif; ?>

                  <?php if ($r['status'] === 'approved'): ?>
                    <form method="POST" action="/event-admin/registrations/<?= e($h) ?>/decide" class="d-inline">
                      <?= csrf() ?><input type="hidden" name="action" value="reopen">
                      <button class="btn btn-outline-secondary"
                              data-confirm="Reopen this approved registration as a draft? It will be withdrawn from any race it hasn't been drawn into.">
                        <i class="bi bi-unlock me-1"></i>Reopen
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Return-for-changes modals -->
  <?php foreach ($registrations as $r): if ($r['status'] !== 'submitted') continue; $h = hid_reg((int)$r['id']); ?>
    <div class="modal fade" id="modalReturn<?= e($h) ?>" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <form method="POST" action="/event-admin/registrations/<?= e($h) ?>/decide">
            <?= csrf() ?><input type="hidden" name="action" value="return">
            <div class="modal-header">
              <h5 class="modal-title"><i class="bi bi-arrow-counterclockwise me-2"></i>Return for Changes</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <p class="small text-muted">
                Returning <strong><?= e($r['boat_name']) ?></strong> (<?= e($r['club_name']) ?>).
              </p>
              <label class="form-label">What needs changing? <span class="text-danger">*</span></label>
              <textarea class="form-control" name="remarks" rows="3" maxlength="500" required></textarea>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-warning">Return Registration</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
