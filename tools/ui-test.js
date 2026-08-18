/**
 * Browser test for the public results page.
 *
 * The page is a static file that nobody logs in to and that tens of thousands
 * of people read on phones, so its layout IS the feature: the boat name, the
 * club and the finishing time have to sit in a card without printing over one
 * another. That cannot be checked by reading PHP, so this drives a real
 * browser over a fixture payload and measures the boxes.
 *
 * Optional — it needs Node, playwright-core and a Chromium build, none of
 * which the hosting account has. It exits 0 with a note when they are absent,
 * so it is safe to run anywhere:
 *
 *     npm i playwright-core && node tools/ui-test.js
 *
 * Chromium is found via CHROME_PATH, PLAYWRIGHT_BROWSERS_PATH or the usual
 * system locations. Pass --shots <dir> to also write screenshots.
 */
'use strict';

const path = require('path'), http = require('http'), fs = require('fs'), os = require('os');

let chromium;
try { ({ chromium } = require('playwright-core')); }
catch (e) {
  console.log('playwright-core is not installed — skipping the browser test.');
  console.log('  npm i playwright-core && node tools/ui-test.js');
  process.exit(0);
}

// ── Where is Chromium? ──────────────────────────────────────────────────────
function findChrome() {
  if (process.env.CHROME_PATH && fs.existsSync(process.env.CHROME_PATH)) return process.env.CHROME_PATH;
  const roots = [process.env.PLAYWRIGHT_BROWSERS_PATH, '/opt/pw-browsers',
                 path.join(os.homedir(), '.cache/ms-playwright')].filter(Boolean);
  for (const root of roots) {
    if (!fs.existsSync(root)) continue;
    for (const dir of fs.readdirSync(root)) {
      for (const rel of ['chrome-linux/chrome', 'chrome-mac/Chromium.app/Contents/MacOS/Chromium']) {
        const p = path.join(root, dir, rel);
        if (fs.existsSync(p)) return p;
      }
    }
  }
  for (const p of ['/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome']) {
    if (fs.existsSync(p)) return p;
  }
  return null;
}

const chromePath = findChrome();
if (!chromePath) {
  console.log('No Chromium build found — skipping the browser test.');
  console.log('  set CHROME_PATH, or run: npx playwright install chromium');
  process.exit(0);
}

// ── Fixture ─────────────────────────────────────────────────────────────────
// One race of each state, with names long enough to force truncation — the
// overlap bug only showed itself on names that ran past the card.
const PAYLOAD = {
  generated: '2026-08-09T18:55:00+05:30',
  event: { code: 'NTBR', name: '72nd Nehru Trophy Boat Race', regional: 'നെഹ്‌റു ട്രോഫി വള്ളംകളി',
           venue: 'Punnamada Lake, Alappuzha', dates: '09 Aug 2026', image: '' },
  races: [
    { sl: 1, name: 'Chundan Vallam', regional: 'ചുണ്ടൻ വള്ളം', class: 'Chundan', gender: 'Men',
      distance: '1370 m', when: '09 Aug 2026, 04:30 PM', image: '',
      state: 'final', label: 'Final Result', round: 'Final', places: [
        { pos: 1, boat: 'Champakulam Chundan', club: 'Tropical Titans Boat Club Alappuzha', time: '09:15.867', lane: 3 },
        { pos: 2, boat: 'Nadubhagom Chundan', club: 'United Boat Club Kainakary', time: '09:18.240', lane: 1 },
        { pos: 3, boat: 'Veeyapuram Chundan', club: 'Veeyapuram Boat Club', time: '09:21.005', lane: 4 },
        { pos: 4, boat: 'Karuvatta Chundan', club: 'Karuvatta Boat Club', time: '09:24.500', lane: 2 },
        { pos: 5, boat: 'Payippadan Chundan', club: 'Payippad Boat Club', time: '09:29.910', lane: 5 }],
      rounds: [
        { name: 'Preliminary Heats', type: 'preliminary', when: '09 Aug 2026, 10:00 AM', heats: [
          { no: 1, name: '', when: '09 Aug 2026, 10:00 AM', lanes: [
            { lane: 3, boat: 'Champakulam Chundan', club: 'Tropical Titans Boat Club Alappuzha', pos: 1, time: '09:31.200', out: '', q: 1 },
            { lane: 1, boat: 'Nadubhagom Chundan', club: 'United Boat Club Kainakary', pos: 2, time: '09:33.010', out: '', q: 1 },
            { lane: 2, boat: 'Kainakary Chundan', club: 'Kainakary Boat Club', pos: 0, time: '', out: 'DNF', q: 0 }] },
          { no: 2, name: '', when: '09 Aug 2026, 10:20 AM', lanes: [
            { lane: 4, boat: 'Veeyapuram Chundan', club: 'Veeyapuram Boat Club', pos: 1, time: '09:35.400', out: '', q: 1 },
            { lane: 2, boat: 'Karuvatta Chundan', club: 'Karuvatta Boat Club', pos: 2, time: '09:36.900', out: '', q: 1 },
            { lane: 5, boat: 'Payippadan Chundan', club: 'Payippad Boat Club', pos: 3, time: '09:40.150', out: '', q: 1 }] }] },
        { name: 'Final', type: 'final', when: '09 Aug 2026, 04:30 PM', heats: [
          { no: 1, name: '', when: '09 Aug 2026, 04:30 PM', lanes: [
            { lane: 3, boat: 'Champakulam Chundan', club: 'Tropical Titans Boat Club Alappuzha', pos: 1, time: '09:15.867', out: '', q: 0 },
            { lane: 1, boat: 'Nadubhagom Chundan', club: 'United Boat Club Kainakary', pos: 2, time: '09:18.240', out: '', q: 0 },
            { lane: 4, boat: 'Veeyapuram Chundan', club: 'Veeyapuram Boat Club', pos: 3, time: '09:21.005', out: '', q: 0 },
            { lane: 2, boat: 'Karuvatta Chundan', club: 'Karuvatta Boat Club', pos: 4, time: '09:24.500', out: '', q: 0 },
            { lane: 5, boat: 'Payippadan Chundan', club: 'Payippad Boat Club', pos: 5, time: '09:29.910', out: '', q: 0 }] }] }] },
    { sl: 2, name: 'Iruttukuthy Vallam', regional: '', class: 'Iruttukuthy', gender: 'Men',
      distance: '1370 m', when: '09 Aug 2026, 03:00 PM', image: '',
      state: 'round', label: 'Round Result', round: 'Semi Final', qualified: [
        { pos: 1, boat: 'St. George Iruttukuthy', club: 'Nadubhagam Boat Club', time: '05:02.100', heat: 1 },
        { pos: 2, boat: 'Kavalam Iruttukuthy', club: 'Kavalam Boat Club', time: '05:04.660', heat: 1 },
        { pos: 1, boat: 'Thekkanattu Iruttukuthy', club: 'Thekkanattu Club', time: '05:03.220', heat: 2 },
        { pos: 2, boat: 'Chambakulam Iruttukuthy', club: 'Chambakulam Boat Club', time: '05:06.900', heat: 2 }],
      rounds: [
        { name: 'Semi Final', type: 'semi_final', when: '09 Aug 2026, 03:00 PM', heats: [
          { no: 1, name: '', when: '09 Aug 2026, 03:00 PM', lanes: [
            { lane: 1, boat: 'St. George Iruttukuthy', club: 'Nadubhagam Boat Club', pos: 1, time: '05:02.100', out: '', q: 1 },
            { lane: 2, boat: 'Kavalam Iruttukuthy', club: 'Kavalam Boat Club', pos: 2, time: '05:04.660', out: '', q: 1 }] },
          { no: 2, name: '', when: '09 Aug 2026, 03:20 PM', lanes: [
            { lane: 1, boat: 'Thekkanattu Iruttukuthy', club: 'Thekkanattu Club', pos: 1, time: '05:03.220', out: '', q: 1 },
            { lane: 3, boat: 'Chambakulam Iruttukuthy', club: 'Chambakulam Boat Club', pos: 2, time: '05:06.900', out: '', q: 1 }] }] }] },
    { sl: 3, name: 'Veppu Vallam', regional: '', class: 'Veppu', gender: 'Women',
      distance: '1000 m', when: '09 Aug 2026, 02:00 PM', image: '',
      state: 'none', label: 'Not Published', teams: [
        { boat: 'Mannar Veppu', club: 'Mannar Boat Club', code: 'MNR', image: '' },
        { boat: 'Pallathuruthy Veppu', club: 'Pallathuruthy Boat Club Alappuzha', code: 'PLT', image: '' },
        { boat: 'Kumarakom Veppu', club: 'Kumarakom Boat Club', code: 'KMK', image: '' },
        { boat: 'Neerettupuram Veppu', club: 'Neerettupuram Boat Club', code: 'NRT', image: '' }],
      rounds: [] }],
  tally: [{ club: 'Tropical Titans Boat Club Alappuzha', gold: 1, silver: 0, bronze: 0, points: 3 },
          { club: 'United Boat Club Kainakary', gold: 0, silver: 1, bronze: 0, points: 2 }]
};

const root = fs.mkdtempSync(path.join(os.tmpdir(), 'rg-ui-'));
const template = fs.readFileSync(path.join(__dirname, '../app/views/public/live-template.html'), 'utf8');
fs.writeFileSync(path.join(root, 'index.html'),
  template.replace(/__EVENT_CODE__/g, 'NTBR').replace(/__TEMPLATE_VERSION__/g, '0'));
fs.writeFileSync(path.join(root, 'results-7.json'), JSON.stringify(PAYLOAD));
fs.writeFileSync(path.join(root, 'manifest.json'), JSON.stringify(
  { version: 7, file: 'results-7.json', updated: '2026-08-09T18:55:00+05:30', event: 'NTBR' }));

const shotDir = process.argv.includes('--shots')
  ? process.argv[process.argv.indexOf('--shots') + 1] : null;

// ── Server ──────────────────────────────────────────────────────────────────
const TYPES = { '.html': 'text/html', '.json': 'application/json' };
const server = http.createServer((req, res) => {
  const name = decodeURIComponent(req.url.split('?')[0]);
  const file = path.join(root, name === '/' ? 'index.html' : path.basename(name));
  if (!fs.existsSync(file)) { res.writeHead(404); return res.end('not found'); }
  res.writeHead(200, { 'content-type': TYPES[path.extname(file)] || 'text/plain' });
  res.end(fs.readFileSync(file));
});

// Lane and heat labels carry a non-breaking space so they never wrap.
const flat = s => s.replace(/\u00a0/g, ' ');

const fails = [];
function t(name, cond, extra) {
  console.log((cond ? 'ok   ' : 'FAIL ') + name + (extra && !cond ? ' — ' + extra : ''));
  if (!cond) fails.push(name);
}

// Three profiles: a phone, a desktop, and a browser too old for
// dialog.showModal() — the detail view has a fallback for it.
const PROFILES = [
  ['phone',   { width: 390, height: 844 }],
  ['desktop', { width: 1280, height: 900 }],
  ['legacy',  { width: 390, height: 844 }]
];

(async () => {
  await new Promise(r => server.listen(0, r));
  const base = 'http://127.0.0.1:' + server.address().port + '/';
  const browser = await chromium.launch({ executablePath: chromePath });

  for (const [label, viewport] of PROFILES) {
    const page = await browser.newPage({ viewport, deviceScaleFactor: 2 });
    if (label === 'legacy') {
      await page.addInitScript(() => { delete HTMLDialogElement.prototype.showModal; });
    }
    const errs = [];
    page.on('pageerror', e => errs.push(String(e)));
    await page.goto(base);
    await page.waitForSelector('.card');

    // ── The bug this file exists for ──────────────────────────────────────
    // As inline spans the name and the club ignored the truncation rules and
    // printed over the finishing time. Measure it, do not eyeball it.
    const rows = await page.$$eval('ol.places li', lis => lis.map(li => {
      const who = li.querySelector('.who').getBoundingClientRect();
      const time = li.querySelector('.time');
      const boat = li.querySelector('.boat'), club = li.querySelector('.club');
      const b = boat.getBoundingClientRect();
      return {
        boat: boat.textContent.slice(0, 24),
        horiz: time ? Math.max(0, who.right - time.getBoundingClientRect().left) : 0,
        vert: club ? Math.max(0, b.bottom - club.getBoundingClientRect().top - 0.6) : 0,
        fits: boat.scrollWidth <= boat.clientWidth + 1
      };
    }));
    t(label + ': the name block never overlaps the time', rows.every(r => r.horiz <= 0.5),
      JSON.stringify(rows.filter(r => r.horiz > 0.5).slice(0, 2)));
    t(label + ': the boat and club stack instead of overprinting', rows.every(r => r.vert <= 0),
      JSON.stringify(rows.filter(r => r.vert > 0).slice(0, 2)));
    t(label + ': long names truncate inside the row', rows.every(r => r.fits));

    const spill = await page.$$eval('.card', cards => cards.map(c => {
      const cr = c.getBoundingClientRect();
      let worst = 0;
      c.querySelectorAll('.cbody *').forEach(n => {
        const r = n.getBoundingClientRect();
        worst = Math.max(worst, r.right - cr.right, r.bottom - cr.bottom, cr.left - r.left);
      });
      return Math.round(worst);
    }));
    t(label + ': card contents stay inside the card', spill.every(s => s <= 1), JSON.stringify(spill));
    t(label + ': the page never scrolls sideways',
      await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1));
    t(label + ': the card previews the top three only',
      (await page.$$('.card[data-sl="1"] ol.places li')).length === 3);

    // ── Search and filter ─────────────────────────────────────────────────
    // A hidden card must actually disappear. .card carries display:flex, which
    // out-ranks the browser's own [hidden] rule unless we say otherwise.
    const visible = () => page.$$eval('.card', cards =>
      cards.filter(c => c.getBoundingClientRect().height > 0).map(c => c.dataset.sl));

    await page.fill('#q', 'kavalam');           // a club, in race 2 only
    t(label + ': searching a club shows only that race',
      JSON.stringify(await visible()) === '["2"]', JSON.stringify(await visible()));
    await page.fill('#q', 'chundan');           // a race name and a boat class
    t(label + ': searching a race name narrows the grid',
      JSON.stringify(await visible()) === '["1"]', JSON.stringify(await visible()));
    await page.fill('#q', 'Champakulam');       // a boat, mixed case
    t(label + ': search ignores case', JSON.stringify(await visible()) === '["1"]',
      JSON.stringify(await visible()));
    await page.fill('#q', 'zzz');
    t(label + ': a search with no match hides every card', (await visible()).length === 0);
    t(label + ': and says so', (await page.$('.empty.filtered')) !== null);
    await page.fill('#q', '');
    t(label + ': clearing the search brings them all back', (await visible()).length === 3);
    t(label + ': and takes the no-match message away', (await page.$('.empty.filtered')) === null);

    await page.selectOption('#st', 'none');
    t(label + ': the state filter narrows to unpublished races',
      JSON.stringify(await visible()) === '["3"]', JSON.stringify(await visible()));
    await page.fill('#q', 'chundan');
    t(label + ': search and filter apply together', (await visible()).length === 0);
    await page.selectOption('#st', '');
    await page.fill('#q', '');
    t(label + ': resetting both restores the grid', (await visible()).length === 3);

    if (shotDir) {
      fs.mkdirSync(shotDir, { recursive: true });
      await page.screenshot({ path: path.join(shotDir, label + '-grid.png'), fullPage: true });
    }

    // ── The detail view ───────────────────────────────────────────────────
    await page.click('.card[data-sl="1"]');
    await page.waitForSelector('#sheet[open]');
    t(label + ': opening a race deep-links it', page.url().endsWith('#race-1'), page.url());
    t(label + ': the detail view holds every placing', (await page.$$('#shBody ol.places li')).length === 5);
    t(label + ': it names the race', (await page.textContent('#shTitle')) === 'Chundan Vallam');
    t(label + ': it adds the lane', flat(await page.textContent('#shBody')).includes('Lane 3'));
    t(label + ': its rows do not overlap either',
      (await page.$$eval('#shBody ol.places li', lis => lis.map(li => {
        const w = li.querySelector('.who').getBoundingClientRect(), tm = li.querySelector('.time');
        return tm ? Math.max(0, w.right - tm.getBoundingClientRect().left) : 0;
      }))).every(v => v <= 0.5));
    t(label + ': it fits the viewport', await page.evaluate(() => {
      const s = document.getElementById('sheet').getBoundingClientRect();
      return s.top >= -1 && s.bottom <= window.innerHeight + 1 && s.width > 200;
    }));
    if (shotDir) await page.screenshot({ path: path.join(shotDir, label + '-detail.png') });

    // ── Every published round, heat by heat ───────────────────────────────
    t(label + ': a race with rounds offers them on the card',
      (await page.$$('.card[data-sl="1"] .more.rounds')).length === 1);
    t(label + ': a race with none does not',
      (await page.$$('.card[data-sl="3"] .more.rounds')).length === 0);

    await page.click('#tabRounds');
    t(label + ': the rounds tab is addressable', page.url().endsWith('#race-1-rounds'), page.url());
    const roundsText = flat(await page.textContent('#shBody'));
    t(label + ': it lists every published round',
      roundsText.includes('Preliminary Heats') && roundsText.includes('Final'));
    t(label + ': it names each heat of a multi-heat round',
      roundsText.includes('Heat 1') && roundsText.includes('Heat 2'));
    t(label + ': it does not label a single-heat round', !roundsText.includes('Heat 3'));
    t(label + ': it shows the lane each boat rowed in', roundsText.includes('Lane 3'));
    t(label + ': it shows a boat that did not finish', roundsText.includes('DNF'));
    t(label + ': it marks who qualified', (await page.$$('#shBody .qq')).length === 5);
    t(label + ': every lane of every heat is listed',
      (await page.$$('#shBody ol.places li')).length === 11);
    t(label + ': the rounds rows do not overlap either',
      (await page.$$eval('#shBody ol.places li', lis => lis.map(li => {
        const w = li.querySelector('.who').getBoundingClientRect(), tm = li.querySelector('.time');
        return tm ? Math.max(0, w.right - tm.getBoundingClientRect().left) : 0;
      }))).every(v => v <= 0.5));
    if (shotDir) await page.screenshot({ path: path.join(shotDir, label + '-rounds.png') });

    await page.click('#tabResult');
    t(label + ': switching back returns to the result',
      page.url().endsWith('#race-1') && (await page.$$('#shBody ol.places li')).length === 5, page.url());

    await page.goBack();
    await page.waitForTimeout(150);
    t(label + ': back closes it — tabs never stack up history',
      (await page.$('#sheet[open]')) === null);
    t(label + ': and takes the race out of the address bar', !page.url().includes('#race-'), page.url());

    await page.click('.card[data-sl="3"]');
    await page.waitForSelector('#sheet[open]');
    t(label + ': an unpublished race lists its boats', (await page.$$('#shBody ol.places li')).length === 4);
    await page.keyboard.press('Escape');
    await page.waitForTimeout(200);
    t(label + ': escape closes it', (await page.$('#sheet[open]')) === null);
    t(label + ': escape clears the hash too', !page.url().includes('#race-'), page.url());

    // The second card link opens straight into the rounds view.
    await page.click('.card[data-sl="2"] .more.rounds');
    await page.waitForSelector('#sheet[open]');
    t(label + ': the card link opens the rounds view',
      page.url().endsWith('#race-2-rounds') &&
      (await page.getAttribute('#tabRounds', 'aria-selected')) === 'true', page.url());
    t(label + ': showing that race\'s heats',
      (await page.textContent('#shBody')).includes('Semi Final'));
    await page.keyboard.press('Escape');
    await page.waitForTimeout(200);

    // A link someone shared has to open on that race, not on the grid.
    await page.goto(base + '#race-2');
    await page.waitForSelector('#sheet[open]', { timeout: 5000 }).catch(() => {});
    t(label + ': a shared link opens that race', (await page.$('#sheet[open]')) !== null);
    t(label + ': showing the qualifiers', (await page.$$('#shBody ol.places li')).length === 4);
    t(label + ': under the round that produced them',
      (await page.textContent('#shBody')).includes('Semi Final'));

    t(label + ': the page raised no script errors', errs.length === 0, errs.join(' | '));
    await page.close();
  }

  await browser.close();
  server.close();
  for (const f of fs.readdirSync(root)) fs.unlinkSync(path.join(root, f));
  fs.rmdirSync(root);

  console.log(fails.length ? '\n' + fails.length + ' UI check(s) FAILED' : '\nAll UI checks passed.');
  process.exit(fails.length ? 1 : 0);
})();
