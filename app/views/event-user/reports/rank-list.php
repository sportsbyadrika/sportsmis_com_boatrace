<?php /** Printable event-wise rank list (1st–4th per race) + club tally. */ ?>
<?php require APP_ROOT . '/views/partials/print-head.php'; ?>

<h2 style="font-size:13pt;margin:0 0 10px">Rank List</h2>

<?php if (!$rankList): ?>
  <p class="text-muted">No races in the programme yet.</p>
<?php else: ?>
  <table>
    <thead>
      <tr>
        <th style="width:34px">Sl.</th><th>Race</th><th style="width:56px">Place</th>
        <th>Boat</th><th>Club</th><th style="width:78px">Time</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rankList as $entry): $race = $entry['race']; ?>
        <?php if (!$entry['places']): ?>
          <tr>
            <td class="text-center fw-bold"><?= (int)$race['sl_no'] ?></td>
            <td><?= e($race['name']) ?></td>
            <td colspan="4" class="small text-muted">
              <?= $entry['round'] ? 'Published, no placed boats recorded.' : 'No published result yet.' ?>
            </td>
          </tr>
        <?php else: foreach ($entry['places'] as $i => $p): ?>
          <tr class="<?= e(positionClass((int)$p['position'])) ?>">
            <?php if ($i === 0): ?>
              <td class="text-center fw-bold" rowspan="<?= count($entry['places']) ?>"><?= (int)$race['sl_no'] ?></td>
              <td rowspan="<?= count($entry['places']) ?>">
                <strong><?= e($race['name']) ?></strong>
                <?php if (!empty($race['name_regional'])): ?>
                  <div class="small text-muted"><?= e($race['name_regional']) ?></div>
                <?php endif; ?>
                <div class="small text-muted"><?= e($entry['round']['name'] ?? '') ?></div>
              </td>
            <?php endif; ?>
            <td class="text-center fw-bold"><?= e(ordinal((int)$p['position'])) ?></td>
            <td><?= e($p['boat_name']) ?></td>
            <td><?= e($p['club_name']) ?></td>
            <td class="text-center"><?= e($p['race_time'] ?: '—') ?></td>
          </tr>
        <?php endforeach; endif; ?>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php if ($tally): ?>
  <div class="no-break" style="margin-top:20px">
    <h2 style="font-size:13pt;margin:0 0 8px">Club Tally</h2>
    <table>
      <thead>
        <tr><th>Club</th><th style="width:48px">1st</th><th style="width:48px">2nd</th>
            <th style="width:48px">3rd</th><th style="width:56px">Points</th></tr>
      </thead>
      <tbody>
        <?php foreach ($tally as $t): ?>
          <tr>
            <td><?= e($t['club_name']) ?></td>
            <td class="text-center pos-gold"><?= (int)$t['gold'] ?></td>
            <td class="text-center pos-silver"><?= (int)$t['silver'] ?></td>
            <td class="text-center pos-bronze"><?= (int)$t['bronze'] ?></td>
            <td class="text-center fw-bold"><?= (int)$t['points'] ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<p class="small text-muted" style="margin-top:18px"><?= e($footer) ?></p>
