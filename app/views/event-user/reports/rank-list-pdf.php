<?php
/** Dompdf body — event-wise rank list plus the club tally. */
require APP_ROOT . '/views/partials/pdf-head.php';
$medal = fn(int $p) => [1 => 'gold', 2 => 'silver', 3 => 'bronze'][$p] ?? 'fourth';
?>

<h2>Rank List</h2>

<?php if (!$rankList): ?>
  <p class="mut">No races in the programme yet.</p>
<?php else: ?>
  <table class="grid">
    <thead>
      <tr><th width="28">Sl.</th><th>Race</th><th width="46">Place</th>
          <th>Boat</th><th>Club</th><th width="66">Time</th></tr>
    </thead>
    <tbody>
      <?php foreach ($rankList as $entry): $race = $entry['race']; ?>
        <?php if (!$entry['places']): ?>
          <tr>
            <td class="c b"><?= (int)$race['sl_no'] ?></td>
            <td><?= $h($race['name']) ?></td>
            <td colspan="4" class="mut">
              <?= $entry['round'] ? 'Published, no placed boats recorded.' : 'No published result yet.' ?>
            </td>
          </tr>
        <?php else: foreach ($entry['places'] as $i => $p): ?>
          <tr class="<?= $medal((int)$p['position']) ?>">
            <?php if ($i === 0): ?>
              <td class="c b" rowspan="<?= count($entry['places']) ?>"><?= (int)$race['sl_no'] ?></td>
              <td rowspan="<?= count($entry['places']) ?>">
                <span class="b"><?= $h($race['name']) ?></span>
                <?php if (!empty($race['name_regional'])): ?><div class="mut"><?= $h($race['name_regional']) ?></div><?php endif; ?>
                <div class="mut"><?= $h($entry['round']['name'] ?? '') ?></div>
              </td>
            <?php endif; ?>
            <td class="c b"><?= $h(ordinal((int)$p['position'])) ?></td>
            <td><?= $h($p['boat_name']) ?></td>
            <td><?= $h($p['club_name']) ?></td>
            <td class="c"><?= $h($p['race_time'] ?: '—') ?></td>
          </tr>
        <?php endforeach; endif; ?>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php if ($tally): ?>
  <h2>Club Tally</h2>
  <table class="grid">
    <thead>
      <tr><th>Club</th><th width="40">1st</th><th width="40">2nd</th>
          <th width="40">3rd</th><th width="48">Points</th></tr>
    </thead>
    <tbody>
      <?php foreach ($tally as $t): ?>
        <tr>
          <td><?= $h($t['club_name']) ?></td>
          <td class="c gold"><?= (int)$t['gold'] ?></td>
          <td class="c silver"><?= (int)$t['silver'] ?></td>
          <td class="c bronze"><?= (int)$t['bronze'] ?></td>
          <td class="c b"><?= (int)$t['points'] ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<div class="foot"><?= $h($footer) ?></div>
</body></html>
