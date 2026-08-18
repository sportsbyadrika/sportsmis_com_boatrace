<?php
/**
 * YouTube / OBS chroma-key overlay.
 *
 * The page paints a solid keyable colour (the event's chroma_color, or a
 * ?chroma=#rrggbb override) and puts nothing on it but the result cards, so a
 * vision mixer can key the background out and drop the cards over the live
 * feed. Type is large and high-contrast because it will be scaled, encoded
 * and watched on a phone.
 *
 * The operator strip lets someone pick what is on air; ?chrome=0 hides it so
 * the captured window is nothing but keyable colour and cards.
 */
$eventHash = hid_event((int)$event['id']);
$base      = '/display/' . $eventHash . '/stream';
$qs = function (array $overrides) use ($base, $round, $heat, $chroma, $showChrome) {
    $params = array_filter([
        'round'  => $round ? hid_round((int)$round['id']) : null,
        'heat'   => $heat  ? hid_heat((int)$heat['id'])   : null,
        'chroma' => $chroma,
        'chrome' => $showChrome ? null : '0',
    ], fn($v) => $v !== null && $v !== '');
    foreach ($overrides as $k => $v) {
        if ($v === null) unset($params[$k]); else $params[$k] = $v;
    }
    return $base . ($params ? '?' . http_build_query($params) : '');
};
?>
<style>
  html, body { height:100%; margin:0; font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; }
  .ov { position:fixed; inset:0; display:flex; flex-direction:column; padding:4vh 5vw; }

  /* The cards themselves — deliberately opaque and dark so they survive
     keying, compression and a small screen. */
  .card-deck { display:flex; flex-direction:column; gap:2vh; max-width:78vw; }
  .ov-card { background:#0b1f3a; color:#fff; border-radius:1.6vh; overflow:hidden;
             box-shadow:0 1.2vh 3vh rgba(0,0,0,.35); }
  .ov-head { padding:1.6vh 2vw; background:linear-gradient(90deg,#0369a1,#0ea5e9); }
  .ov-head h1 { margin:0; font-size:3.4vh; font-weight:800; line-height:1.1; }
  .ov-head .sub { font-size:2.2vh; color:#e0f2fe; margin-top:.3vh; }
  .ov-head .meta { font-size:1.9vh; color:#bae6fd; margin-top:.3vh; }

  .ov-rows { padding:.6vh 0 1vh; }
  .row-l { display:flex; align-items:center; gap:1.4vw; padding:1.1vh 2vw;
           border-top:1px solid rgba(255,255,255,.10); }
  .row-l:first-child { border-top:0; }
  .row-l .pos { width:5vh; height:5vh; border-radius:1vh; flex:0 0 auto;
                display:flex; align-items:center; justify-content:center;
                font-size:2.6vh; font-weight:800; background:rgba(255,255,255,.14); }
  .row-l.p1 .pos { background:#facc15; color:#0b1f3a; }
  .row-l.p2 .pos { background:#e5e7eb; color:#0b1f3a; }
  .row-l.p3 .pos { background:#e8b485; color:#0b1f3a; }
  .row-l .who  { min-width:0; flex:1; }
  .row-l .boat { font-size:3vh; font-weight:800; line-height:1.12;
                 white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .row-l .club { font-size:2vh; color:#cbd5e1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .row-l .lane { font-size:1.8vh; color:#7dd3fc; flex:0 0 auto; }
  .row-l .time { font-size:2.8vh; font-weight:800; font-variant-numeric:tabular-nums;
                 color:#7dd3fc; flex:0 0 auto; }

  .ov-brand { margin-top:2vh; display:inline-flex; align-items:center; gap:.8vw;
              background:#0b1f3a; color:#fff; border-radius:1vh; padding:.9vh 1.4vw;
              font-size:1.9vh; font-weight:600; align-self:flex-start; }
  .ov-brand .sb { color:#7dd3fc; letter-spacing:.12em; text-transform:uppercase; font-size:1.4vh; }

  .ov-empty { background:#0b1f3a; color:#cbd5e1; border-radius:1.6vh;
              padding:3vh 3vw; font-size:2.6vh; align-self:flex-start; }

  /* Operator strip — never part of the keyed picture in practice, because
     the operator hides it with ?chrome=0 before going on air. */
  .ops { position:fixed; left:0; right:0; bottom:0; background:rgba(2,6,23,.92); color:#fff;
         padding:10px 14px; font-size:13px; display:flex; gap:10px; align-items:center;
         flex-wrap:wrap; z-index:20; }
  .ops select, .ops input { background:#0f172a; color:#fff; border:1px solid #334155;
                            border-radius:7px; padding:5px 8px; font-size:13px; max-width:44vw; }
  .ops a { color:#7dd3fc; text-decoration:none; }
  .ops .sep { opacity:.4; }
  @media print { .ops { display:none; } }
</style>

<div class="ov">
  <?php if (!$round): ?>
    <div class="ov-empty">
      <strong>Nothing published yet.</strong><br>
      Results appear here as soon as a round is published in the race office.
    </div>

  <?php else: ?>
    <?php
      // On air: one heat if the operator picked one, otherwise every heat of
      // the round, each as its own card.
      $cards = $heat ? [$heat] : $heats;
    ?>
    <div class="card-deck">
      <?php foreach ($cards as $h):
              $lanes = array_values(array_filter($h['lanes'], fn($l) => $l['position'] !== null));
              usort($lanes, fn($a, $b) => (int)$a['position'] <=> (int)$b['position']);
              if (!$lanes) continue; ?>
        <div class="ov-card">
          <div class="ov-head">
            <h1><?= (int)$round['race_sl_no'] ?>. <?= e($round['race_name']) ?></h1>
            <?php if (!empty($round['race_name_regional'])): ?>
              <div class="sub"><?= e($round['race_name_regional']) ?></div>
            <?php endif; ?>
            <div class="meta">
              <?= e($round['name']) ?> &middot; <?= e(\Models\Heat::label($h)) ?>
              <?php if (!empty($round['distance_m'])): ?> &middot; <?= (int)$round['distance_m'] ?> m<?php endif; ?>
            </div>
          </div>
          <div class="ov-rows">
            <?php foreach (array_slice($lanes, 0, 6) as $l): $p = (int)$l['position']; ?>
              <div class="row-l <?= $p <= 3 ? 'p' . $p : '' ?>">
                <div class="pos"><?= $p ?></div>
                <div class="who">
                  <div class="boat"><?= e($l['boat_name']) ?></div>
                  <div class="club"><?= e($l['club_name']) ?></div>
                </div>
                <div class="lane">Lane <?= (int)$l['lane_no'] ?></div>
                <?php if (!empty($l['race_time'])): ?>
                  <div class="time"><?= e($l['race_time']) ?></div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="ov-brand">
      <i class="bi bi-water"></i>
      <span>SportsMIS<sup style="font-size:.6em">&reg;</sup></span>
      <span class="sb">Regatta</span>
    </div>
  <?php endif; ?>
</div>

<?php if ($showChrome): ?>
  <div class="ops">
    <strong><i class="bi bi-camera-reels me-1"></i>On air</strong>
    <span class="sep">|</span>

    <label>Round
      <select onchange="location.href=this.value">
        <?php if (!$published): ?>
          <option>No published rounds</option>
        <?php else: foreach ($published as $p): ?>
          <option value="<?= e($qs(['round' => hid_round((int)$p['id']), 'heat' => null])) ?>"
                  <?= $round && (int)$round['id'] === (int)$p['id'] ? 'selected' : '' ?>>
            <?= e((int)$p['race_sl_no'] . '. ' . $p['race_name'] . ' — ' . $p['name']) ?>
          </option>
        <?php endforeach; endif; ?>
      </select>
    </label>

    <label>Heat
      <select onchange="location.href=this.value">
        <option value="<?= e($qs(['heat' => null])) ?>" <?= $heat ? '' : 'selected' ?>>All heats</option>
        <?php foreach ($heats as $h): ?>
          <option value="<?= e($qs(['heat' => hid_heat((int)$h['id'])])) ?>"
                  <?= $heat && (int)$heat['id'] === (int)$h['id'] ? 'selected' : '' ?>>
            <?= e(\Models\Heat::label($h)) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>

    <label>Key colour
      <input type="color" value="<?= e($chroma) ?>"
             onchange="location.href='<?= e($base) ?>?chroma=' + encodeURIComponent(this.value)
                       + '<?= $round ? '&round=' . e(hid_round((int)$round['id'])) : '' ?>'
                       + '<?= $heat ? '&heat=' . e(hid_heat((int)$heat['id'])) : '' ?>'">
    </label>
    <code><?= e($chroma) ?></code>

    <span class="sep">|</span>
    <a href="<?= e($qs(['chrome' => '0'])) ?>"><i class="bi bi-eye-slash"></i> Hide this bar (go on air)</a>
    <span class="sep">|</span>
    <a href="/display"><i class="bi bi-box-arrow-left"></i> Switch event</a>
  </div>
<?php endif; ?>
