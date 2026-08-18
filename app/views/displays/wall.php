<?php
/**
 * Big TV / LED wall. Full-viewport, high contrast, auto-rotating.
 *
 * The deck arrives as $slides (see DisplayController::deck()) and is handed
 * to the client as JSON; the same shape comes back from the feed endpoint,
 * so the wall refreshes its content in place as rounds are published and
 * only hard-reloads on a slow cadence as a backstop against a stale tab.
 *
 * Everything is sized in vh/vw so one build fills a 1080p panel, a 4K wall
 * or a projector without a per-venue tweak.
 */
$feedUrl = '/display/' . hid_event((int)$event['id']) . '/feed';
?>
<style>
  html, body { height:100%; margin:0; overflow:hidden; background:#04122a; color:#fff;
               font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; }
  .wall { position:fixed; inset:0; display:flex; flex-direction:column;
          background:radial-gradient(circle at 18% 8%, #1a3470, #04122a 62%); }

  .wall-top { display:flex; align-items:center; gap:1.2vw; padding:1.4vh 2vw;
              border-bottom:1px solid rgba(255,255,255,.12); flex:0 0 auto; }
  .wall-top .mark { width:5.2vh; height:5.2vh; border-radius:1vh; flex:0 0 auto;
                    background:linear-gradient(150deg,#0ea5e9,#0369a1); color:#fff;
                    display:flex; align-items:center; justify-content:center; font-size:2.6vh; }
  .wall-top .who { flex:1; min-width:0; }
  .wall-top .who h1 { margin:0; font-size:2.8vh; font-weight:800; white-space:nowrap;
                      overflow:hidden; text-overflow:ellipsis; }
  .wall-top .who .sub { font-size:1.8vh; color:#93c5fd; margin-top:.2vh;
                        white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .wall-top .clock { text-align:right; flex:0 0 auto; line-height:1.05; }
  .wall-top .clock .t { font-size:3vh; font-weight:800; font-variant-numeric:tabular-nums; }
  .wall-top .clock .d { font-size:1.6vh; color:#cbd5e1; }

  .stage { flex:1 1 auto; position:relative; overflow:hidden; padding:2vh 2.4vw; }
  .slide { position:absolute; inset:2vh 2.4vw; display:none; flex-direction:column; }
  .slide.on { display:flex; animation:fade .5s ease; }
  @keyframes fade { from { opacity:0; transform:translateY(1.2vh); } to { opacity:1; transform:none; } }

  .slide h2 { margin:0 0 .4vh; font-size:4vh; font-weight:800; line-height:1.1; }
  .slide .sub  { font-size:2.4vh; color:#93c5fd; margin-bottom:.4vh; }
  .slide .meta { font-size:2vh; color:#cbd5e1; margin-bottom:1.6vh; }

  /* Title card */
  .titlecard { align-items:center; justify-content:center; text-align:center; }
  .titlecard h2 { font-size:7vh; }
  .titlecard .sub { font-size:4vh; }
  .titlecard img { max-height:26vh; border-radius:1.4vh; margin-bottom:2.4vh; object-fit:cover; }

  /* Round card: heats side by side */
  .heats { display:flex; gap:1.4vw; flex:1; min-height:0; }
  .heat  { flex:1 1 0; background:rgba(255,255,255,.06); border-radius:1.2vh;
           padding:1.4vh 1.2vw; display:flex; flex-direction:column; min-width:0; }
  .heat .hname { font-size:2.4vh; font-weight:700; color:#facc15; margin-bottom:.9vh; }
  .lane { display:flex; align-items:center; gap:.9vw; padding:.75vh 0;
          border-top:1px solid rgba(255,255,255,.08); min-width:0; }
  .lane:first-of-type { border-top:0; }
  .lane .pos { width:4.2vh; height:4.2vh; border-radius:.8vh; flex:0 0 auto;
               display:flex; align-items:center; justify-content:center;
               font-size:2.2vh; font-weight:800; background:rgba(255,255,255,.12); }
  .lane.p1 .pos { background:#facc15; color:#0b1f3a; }
  .lane.p2 .pos { background:#e5e7eb; color:#0b1f3a; }
  .lane.p3 .pos { background:#e8b485; color:#0b1f3a; }
  .lane .who  { min-width:0; flex:1; }
  .lane .boat { font-size:2.4vh; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .lane .club { font-size:1.8vh; color:#cbd5e1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .lane .time { font-size:2.3vh; font-weight:700; font-variant-numeric:tabular-nums; color:#7dd3fc; flex:0 0 auto; }

  /* Rank list + tally tables */
  table.deck { width:100%; border-collapse:collapse; font-size:2.2vh; }
  table.deck th { text-align:left; font-size:1.7vh; text-transform:uppercase; letter-spacing:.08em;
                  color:#93c5fd; padding:.7vh .8vw; font-weight:700; }
  table.deck td { padding:.75vh .8vw; border-top:1px solid rgba(255,255,255,.08); }
  table.deck .rc { font-weight:800; }
  table.deck .g td { background:rgba(250,204,21,.14); }
  table.deck .s td { background:rgba(229,231,235,.10); }
  table.deck .b td { background:rgba(232,180,133,.12); }
  table.deck .num { font-variant-numeric:tabular-nums; text-align:center; }

  .wall-foot { flex:0 0 auto; display:flex; align-items:center; gap:1vw;
               padding:1vh 2vw; border-top:1px solid rgba(255,255,255,.12);
               font-size:1.7vh; color:#cbd5e1; }
  .dots { display:flex; gap:.5vw; margin-left:auto; }
  .dots i { width:1vh; height:1vh; border-radius:50%; background:rgba(255,255,255,.25); display:block; }
  .dots i.on { background:#0ea5e9; }

  .empty { flex:1; display:flex; align-items:center; justify-content:center;
           text-align:center; color:#94a3b8; font-size:3vh; }
</style>

<div class="wall">
  <div class="wall-top">
    <div class="mark"><i class="bi bi-water"></i></div>
    <div class="who">
      <h1><?= e($event['name']) ?></h1>
      <?php if (!empty($event['name_regional'])): ?>
        <div class="sub"><?= e($event['name_regional']) ?></div>
      <?php endif; ?>
    </div>
    <div class="clock"><div class="t" id="clockT">--:--</div><div class="d" id="clockD"></div></div>
  </div>

  <div class="stage" id="stage">
    <div class="empty" id="emptyMsg" hidden>
      Waiting for published results&hellip;
    </div>
  </div>

  <div class="wall-foot">
    <span>SportsMIS<sup>&reg;</sup> Regatta</span>
    <span>&middot;</span>
    <span><?= e($event['venue'] ?: $event['code']) ?></span>
    <div class="dots" id="dots"></div>
  </div>
</div>

<script id="deckData" type="application/json"><?= json_encode($slides, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var stage    = document.getElementById('stage');
  var dotsHost = document.getElementById('dots');
  var emptyMsg = document.getElementById('emptyMsg');
  var INTERVAL = <?= (int)$interval ?> * 1000;
  var REFRESH  = <?= (int)$refresh ?> * 1000;
  var FEED     = <?= json_encode($feedUrl) ?>;

  var slides = JSON.parse(document.getElementById('deckData').textContent || '[]');
  var index  = 0;
  var timer  = null;

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function medal(p) { return p === 1 ? 'p1' : (p === 2 ? 'p2' : (p === 3 ? 'p3' : '')); }
  function rowCls(p) { return p === 1 ? 'g' : (p === 2 ? 's' : (p === 3 ? 'b' : '')); }

  function head(s) {
    return '<h2>' + esc(s.title) + '</h2>' +
      (s.subtitle ? '<div class="sub">' + esc(s.subtitle) + '</div>' : '') +
      (s.meta ? '<div class="meta">' + esc(s.meta) + '</div>' : '');
  }

  function renderTitle(s) {
    return '<div class="slide titlecard">' +
      (s.image ? '<img src="' + esc(s.image) + '" alt="">' : '') +
      '<h2>' + esc(s.title) + '</h2>' +
      (s.subtitle ? '<div class="sub">' + esc(s.subtitle) + '</div>' : '') +
      (s.meta ? '<div class="meta">' + esc(s.meta) + '</div>' : '') +
      '</div>';
  }

  function renderRound(s) {
    var heats = s.heats.map(function (h) {
      var lanes = h.lanes.map(function (l) {
        return '<div class="lane ' + medal(l.position) + '">' +
          '<div class="pos">' + l.position + '</div>' +
          '<div class="who"><div class="boat">' + esc(l.boat) + '</div>' +
          '<div class="club">' + esc(l.club) + '</div></div>' +
          (l.time ? '<div class="time">' + esc(l.time) + '</div>' : '') +
          '</div>';
      }).join('');
      return '<div class="heat"><div class="hname">' + esc(h.name) + '</div>' + lanes + '</div>';
    }).join('');
    return '<div class="slide">' + head(s) + '<div class="heats">' + heats + '</div></div>';
  }

  function renderRankList(s) {
    var rows = s.races.map(function (r) {
      return r.places.map(function (p, i) {
        return '<tr class="' + rowCls(p.position) + '">' +
          (i === 0
            ? '<td class="num rc" rowspan="' + r.places.length + '">' + r.sl + '</td>' +
              '<td rowspan="' + r.places.length + '">' + esc(r.name) + '</td>'
            : '') +
          '<td class="num rc">' + p.position + '</td>' +
          '<td>' + esc(p.boat) + '</td>' +
          '<td>' + esc(p.club) + '</td>' +
          '<td class="num">' + esc(p.time || '') + '</td>' +
          '</tr>';
      }).join('');
    }).join('');
    return '<div class="slide">' + head(s) +
      '<table class="deck"><thead><tr><th>Sl.</th><th>Race</th><th>Pos.</th>' +
      '<th>Boat</th><th>Club</th><th>Time</th></tr></thead><tbody>' + rows + '</tbody></table></div>';
  }

  function renderTally(s) {
    var rows = s.clubs.map(function (c, i) {
      return '<tr class="' + rowCls(i + 1) + '">' +
        '<td class="num rc">' + (i + 1) + '</td>' +
        '<td>' + esc(c.club) + '</td>' +
        '<td class="num">' + c.gold + '</td>' +
        '<td class="num">' + c.silver + '</td>' +
        '<td class="num">' + c.bronze + '</td>' +
        '<td class="num rc">' + c.points + '</td></tr>';
    }).join('');
    return '<div class="slide">' + head(s) +
      '<table class="deck"><thead><tr><th>#</th><th>Club</th><th>1st</th>' +
      '<th>2nd</th><th>3rd</th><th>Points</th></tr></thead><tbody>' + rows + '</tbody></table></div>';
  }

  function build() {
    var html = slides.map(function (s) {
      if (s.type === 'title')    return renderTitle(s);
      if (s.type === 'round')    return renderRound(s);
      if (s.type === 'ranklist') return renderRankList(s);
      if (s.type === 'tally')    return renderTally(s);
      return '';
    }).join('');
    stage.innerHTML = html + emptyMsg.outerHTML;
    emptyMsg = document.getElementById('emptyMsg');
    emptyMsg.hidden = slides.length > 0;

    dotsHost.innerHTML = slides.map(function () { return '<i></i>'; }).join('');
    if (index >= slides.length) index = 0;
    show(index);
  }

  function show(i) {
    var panes = stage.querySelectorAll('.slide');
    if (!panes.length) return;
    panes.forEach(function (p, n) { p.classList.toggle('on', n === i); });
    dotsHost.querySelectorAll('i').forEach(function (d, n) { d.classList.toggle('on', n === i); });
  }

  function advance() {
    var panes = stage.querySelectorAll('.slide');
    if (!panes.length) return;
    index = (index + 1) % panes.length;
    show(index);
  }

  // Pull a fresh deck without disturbing the rotation; if the slide count
  // changed the current index is clamped by build().
  async function refresh() {
    try {
      var res  = await fetch(FEED, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      var data = await res.json();
      if (data && data.success && Array.isArray(data.slides)) {
        var changed = JSON.stringify(data.slides) !== JSON.stringify(slides);
        if (changed) { slides = data.slides; build(); }
      }
    } catch (err) { /* a dropped network must not stop the rotation */ }
  }

  function clock() {
    var now = new Date();
    document.getElementById('clockT').textContent =
      now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    document.getElementById('clockD').textContent =
      now.toLocaleDateString([], { weekday: 'short', day: '2-digit', month: 'short' });
  }

  build();
  clock();
  setInterval(clock, 10000);
  timer = setInterval(advance, INTERVAL);
  setInterval(refresh, REFRESH);

  // Operator keys: space pauses, arrows step, F goes full screen.
  document.addEventListener('keydown', function (e) {
    if (e.code === 'Space') {
      e.preventDefault();
      if (timer) { clearInterval(timer); timer = null; }
      else { timer = setInterval(advance, INTERVAL); }
    } else if (e.code === 'ArrowRight') {
      advance();
    } else if (e.code === 'ArrowLeft') {
      var n = stage.querySelectorAll('.slide').length;
      if (n) { index = (index - 1 + n) % n; show(index); }
    } else if (e.key === 'f' || e.key === 'F') {
      if (document.fullscreenElement) document.exitFullscreen();
      else document.documentElement.requestFullscreen();
    }
  });
});
</script>
