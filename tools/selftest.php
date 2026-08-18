<?php
/**
 * Runtime self-test — exercises the pure logic and the PDF pipeline with
 * fixture data, so both can be checked without a database.
 *
 *   php tools/selftest.php
 *
 * Requires `composer install` for the PDF section; without it, the PDF
 * checks are skipped rather than failed.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
define('APP_ROOT',    $root . '/app');
define('CONFIG_ROOT', APP_ROOT . '/config');
define('PUBLIC_ROOT', APP_ROOT . '/public');

spl_autoload_register(function (string $class) {
    foreach ([
        'Core\\'        => APP_ROOT . '/core/',
        'Models\\'      => APP_ROOT . '/models/',
        'Controllers\\' => APP_ROOT . '/controllers/',
        'Services\\'    => APP_ROOT . '/services/',
    ] as $prefix => $base) {
        if (!str_starts_with($class, $prefix)) continue;
        $file = $base . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (file_exists($file)) { require $file; return; }
    }
});
$vendor = $root . '/vendor/autoload.php';
if (file_exists($vendor)) require_once $vendor;
require APP_ROOT . '/core/helpers.php';
if (session_status() !== PHP_SESSION_ACTIVE) @session_start();

$failures = [];
$passed   = 0;

function check(string $label, mixed $got, mixed $want): void
{
    global $failures, $passed;
    if ($got === $want) { $passed++; return; }
    $failures[] = sprintf('%s: got %s, want %s', $label, var_export($got, true), var_export($want, true));
}

function ok(string $label, bool $cond): void
{
    global $failures, $passed;
    if ($cond) { $passed++; return; }
    $failures[] = $label;
}

// ── Race-time normalisation ─────────────────────────────────────────────────
// Judges type times in several shapes; all must land on MM:SS.mmm or be
// rejected outright so the controller can complain rather than store junk.
foreach ([
    '4:12.35'  => '04:12.350',
    '1:23'     => '01:23.000',
    '83.45'    => '01:23.450',   // bare seconds roll into minutes
    '00:59.9'  => '00:59.900',
    '4:12,35'  => '04:12.350',   // comma decimal
    '12:00.000'=> '12:00.000',
    'abc'      => '',
    ''         => '',
    '9:99.0'   => '',            // 99 seconds inside an explicit minute field
] as $in => $want) {
    check("normaliseRaceTime('{$in}')", normaliseRaceTime((string)$in), $want);
}

// ── Time ordering ───────────────────────────────────────────────────────────
check('raceTimeToCentis(1:00.00)', raceTimeToCentis('1:00.00'), 6000);
check('raceTimeToCentis(0:00.10)', raceTimeToCentis('0:00.10'), 10);
ok('faster time sorts first', raceTimeToCentis('4:12.35') < raceTimeToCentis('4:12.36'));
check('raceTimeToCentis(garbage)', raceTimeToCentis('nope'), 0);

// ── Ordinals ────────────────────────────────────────────────────────────────
foreach ([1 => '1st', 2 => '2nd', 3 => '3rd', 4 => '4th', 11 => '11th', 12 => '12th',
          13 => '13th', 21 => '21st', 22 => '22nd', 23 => '23rd', 101 => '101st'] as $n => $want) {
    check("ordinal({$n})", ordinal($n), $want);
}

// ── Hidden URL ids ──────────────────────────────────────────────────────────
// A token must round-trip within its own context and be rejected in another,
// otherwise an event token could be replayed as a team id.
$token = hid_event(4242);
check('hid_event round-trip', hid_event_decode($token), 4242);
ok('token is not the raw id', $token !== '4242');
check('cross-context token is rejected', hid_team_decode($token), 0);
check('garbage token is rejected', hid_event_decode('!!!not-a-token!!!'), 0);
check('plain integer still decodes', hid_event_decode('17'), 17);

// ── Formatting ──────────────────────────────────────────────────────────────
check('formatDate(null)', formatDate(null), '—');
check('formatDate', formatDate('2026-08-09'), '09 Aug 2026');
check('formatTime', formatTime('15:00:00'), '3:00 PM');
check('formatDateTime', formatDateTime('2026-08-09', '15:00:00'), '09 Aug 2026 · 3:00 PM');
check('formatDateTime date only', formatDateTime('2026-08-09', null), '09 Aug 2026');
check('e() escapes', e('<b>"x"</b>'), '&lt;b&gt;&quot;x&quot;&lt;/b&gt;');
check('avatarInitials', avatarInitials('Nadubhagom Boat Club'), 'NC');
check('positionClass(1)', positionClass(1), 'pos-gold');
check('positionClass(9)', positionClass(9), 'pos-fourth');

// ── Seed data ───────────────────────────────────────────────────────────────
// database/seeds.sql documents a bootstrap password in a comment and stores
// its bcrypt hash. If the two ever drift, a fresh install cannot sign in —
// so verify every hash literal in the file against the documented password.
$seedFile = $root . '/database/seeds.sql';
if (is_file($seedFile)) {
    $sql = (string)file_get_contents($seedFile);
    preg_match_all('/\$2y\$\d{2}\$[A-Za-z0-9.\/]{53}/', $sql, $m);
    $hashes = array_unique($m[0]);
    ok('seeds.sql contains a password hash', count($hashes) > 0);
    foreach ($hashes as $hash) {
        ok('seed hash matches the documented bootstrap password',
            password_verify('ChangeMe@123', $hash));
    }
}

// ── Environment sample ──────────────────────────────────────────────────────
// app/.env.example is what an operator copies to app/.env. If it drifts from
// what the config actually reads, a deploy silently falls back to defaults —
// so parse it with the same loop as app/public/index.php and compare the key
// set against every getenv() the config uses.
$envSample = APP_ROOT . '/.env.example';
if (is_file($envSample)) {
    $parsed = [];
    foreach (file($envSample, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        $v = trim($v, "\"'");
        if ($k !== '') $parsed[$k] = $v;
    }
    ok('env sample parses to some keys', count($parsed) > 0);

    $used = [];
    foreach ([CONFIG_ROOT . '/app.php', CONFIG_ROOT . '/database.php', APP_ROOT . '/core/helpers.php'] as $f) {
        if (!is_file($f)) continue;
        // \x22 and \x27 are the double and single quote, written as hex so
        // the pattern carries no quote character of its own.
        preg_match_all('/getenv\\(\\s*[\\x22\\x27]([A-Z0-9_]+)[\\x22\\x27]\\s*\\)/',
            (string)file_get_contents($f), $m);
        foreach ($m[1] as $k) $used[$k] = true;
    }
    foreach (array_keys($used) as $key) {
        ok("env sample documents {$key}", array_key_exists($key, $parsed));
    }
    // The chroma default must survive the parser — it starts with '#', which
    // is also the comment marker, so this is a genuine edge case.
    if (isset($parsed['DISPLAY_CHROMA_COLOR'])) {
        ok('chroma default parses as a colour',
            (bool)preg_match('/^#[0-9a-fA-F]{6}$/', $parsed['DISPLAY_CHROMA_COLOR']));
    }
}

// ── Bulk team upload: CSV parsing ───────────────────────────────────────────
// Services\TeamImport turns a spreadsheet into validated rows. Everything a
// real organiser's export throws at it — locale delimiters, Excel's BOM,
// loose headings, blank lines, duplicates — is handled here rather than at
// the database, so it is worth exercising directly.
$imp = \Services\TeamImport::class;

$csv = "Club Name,Boat Name,Captain Name,Boat Class,Home Place,Short Code,Contact Name,Contact Phone,Contact Email,Status\n"
     . "Nadubhagom BC,Nadubhagom Chundan,K. Menon,Chundan,Nadubhagom,NBC,R. Kumar,9876543210,nbc@example.com,active\n"
     . "Karichal BC,Karichal Chundan,S. Pillai,Chundan,Karichal,KBC,,,,\n";
$r = $imp::parse($csv);
check('clean file has no fatal error', $r['fatal'], '');
check('clean file is missing nothing', $r['missing'], []);
check('clean file yields both rows', count($r['rows']), 2);
check('no row errors on a clean file',
    array_merge(...array_column($r['rows'], 'errors')) ?: [], []);
check('first row keeps its boat name', $r['rows'][0]['data']['boat_name'], 'Nadubhagom Chundan');
check('short code is upper-cased', $r['rows'][0]['data']['short_code'], 'NBC');
check('blank status defaults to active', $r['rows'][1]['data']['status'], 'active');
check('line numbers point at the spreadsheet row', $r['rows'][1]['line'], 3);

// The template we hand people must itself import without complaint.
$t = $imp::parse($imp::templateCsv());
check('template parses with no fatal error', $t['fatal'], '');
check('template has every required column', $t['missing'], []);
check('template rows are all valid',
    array_merge(...array_column($t['rows'], 'errors')) ?: [], []);

// Loose headings — people rename columns.
$r = $imp::parse("Club,Boat,Captain,Code,Email,Phone\nA Club,A Boat,A Captain,ABC,a@b.com,9876543210\n");
check('loose headings are accepted', $r['missing'], []);
check('aliased code column maps through', $r['rows'][0]['data']['short_code'], 'ABC');
check('aliased email column maps through', $r['rows'][0]['data']['contact_email'], 'a@b.com');

// A missing required column must be reported, not silently imported.
$r = $imp::parse("Club Name,Boat Name\nA,B\n");
check('missing required column is reported', $r['missing'], ['Captain Name']);
check('nothing is parsed when a column is missing', $r['rows'], []);

// Locale delimiters and Excel's BOM.
$r = $imp::parse("Club Name;Boat Name;Captain Name\nA Club;A Boat;A Captain\n");
check('semicolon-delimited file parses', count($r['rows']), 1);
$r = $imp::parse("Club Name\tBoat Name\tCaptain Name\nA Club\tA Boat\tA Captain\n");
check('tab-delimited file parses', count($r['rows']), 1);
$r = $imp::parse("\xEF\xBB\xBFClub Name,Boat Name,Captain Name\nA Club,A Boat,A Captain\n");
check('BOM does not break the first heading', $r['missing'], []);
check('BOM does not leak into the first value', $r['rows'][0]['data']['club_name'], 'A Club');

// Blank lines in the middle of a sheet are common and must be ignored.
$r = $imp::parse("Club Name,Boat Name,Captain Name\nA,B,C\n\n,,\nD,E,F\n");
check('blank lines are skipped', count($r['rows']), 2);

// Quoted commas — a club with a comma in its name.
$r = $imp::parse("Club Name,Boat Name,Captain Name\n\"Alpha, Beta Club\",A Boat,A Captain\n");
check('quoted comma stays inside the field', $r['rows'][0]['data']['club_name'], 'Alpha, Beta Club');

// Duplicates inside one file, by short code and by club+boat.
$r = $imp::parse("Club Name,Boat Name,Captain Name,Short Code\nA,B,C,XX\nD,E,F,XX\n");
check('duplicate short code is flagged', count($r['rows'][1]['errors']), 1);
ok('duplicate names the earlier row', str_contains($r['rows'][1]['errors'][0], 'row 2'));
$r = $imp::parse("Club Name,Boat Name,Captain Name\nA Club,A Boat,C\na club,a boat,D\n");
check('duplicate club+boat is flagged case-insensitively', count($r['rows'][1]['errors']), 1);
check('the first of a duplicate pair stays clean', $r['rows'][0]['errors'], []);

// Field-level validation.
$r = $imp::parse("Club Name,Boat Name,Captain Name,Contact Email\nA,B,C,not-an-email\n");
ok('invalid email is rejected', str_contains(implode(' ', $r['rows'][0]['errors']), 'Contact Email'));
$r = $imp::parse("Club Name,Boat Name,Captain Name,Contact Phone\nA,B,C,=CMD|calc\n");
ok('junk in the phone column is rejected', str_contains(implode(' ', $r['rows'][0]['errors']), 'Contact Phone'));
$r = $imp::parse("Club Name,Boat Name,Captain Name,Status\nA,B,C,retired\n");
ok('unknown status is rejected', str_contains(implode(' ', $r['rows'][0]['errors']), 'Status'));
check('rejected status still falls back to active', $r['rows'][0]['data']['status'], 'active');
$r = $imp::parse("Club Name,Boat Name,Captain Name\n,B,C\n");
ok('missing required value is rejected', str_contains(implode(' ', $r['rows'][0]['errors']), 'Club Name'));

// Over-length values are reported AND truncated to the column width.
$long = str_repeat('x', 260);
$r = $imp::parse("Club Name,Boat Name,Captain Name\n{$long},B,C\n");
ok('over-length value is reported', str_contains(implode(' ', $r['rows'][0]['errors']), 'longer than'));
check('over-length value is cut to the column width',
    mb_strlen($r['rows'][0]['data']['club_name']), 200);

// Empty and header-only files.
check('empty file is a fatal error', $imp::parse("")['fatal'] !== '', true);
$r = $imp::parse("Club Name,Boat Name,Captain Name\n");
check('header-only file yields no rows', $r['rows'], []);
check('header-only file is not fatal', $r['fatal'], '');

// The row cap bounds the preview page and the session holding it.
$big = "Club Name,Boat Name,Captain Name\n";
for ($i = 0; $i < $imp::MAX_ROWS + 25; $i++) $big .= "Club {$i},Boat {$i},Cap {$i}\n";
$r = $imp::parse($big);
check('row cap is enforced', count($r['rows']), $imp::MAX_ROWS);
check('exceeding the cap is reported', $r['truncated'], true);

// ── Bulk team upload: the write path ────────────────────────────────────────
// Team::importRows() decides what a previewed spreadsheet actually does to
// the event. Driven against SQLite with a PDO injected into Core\Model, so
// create / update / skip and the forward-only registration rule are exercised
// for real. Portable SQL only — this tests the logic, not MySQL.
if (extension_loaded('pdo_sqlite')) {
    $pdo = new PDO('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('CREATE TABLE teams (id INTEGER PRIMARY KEY, event_id INT, club_name TEXT,
        boat_name TEXT, captain_name TEXT, boat_class TEXT, home_place TEXT, contact_name TEXT,
        contact_phone TEXT, contact_email TEXT, logo TEXT, short_code TEXT, status TEXT)');
    $pdo->exec('CREATE TABLE team_registrations (id INTEGER PRIMARY KEY, event_id INT, team_id INT,
        status TEXT, remarks TEXT, submitted_at TEXT, decided_at TEXT, decided_by TEXT)');

    $prop = new ReflectionProperty(\Core\Model::class, 'pdo');
    $prop->setAccessible(true);
    $prop->setValue(null, $pdo);

    $EV = 7;
    $rowsOf = fn(string $csv) => \Services\TeamImport::parse($csv)['rows'];
    $countTeams = fn() => (int)$pdo->query("SELECT COUNT(*) FROM teams")->fetchColumn();
    $teamBy = function (string $code) use ($pdo) {
        $st = $pdo->prepare("SELECT * FROM teams WHERE short_code = ?");
        $st->execute([$code]);
        return $st->fetch() ?: [];
    };
    $regOf = function (int $teamId) use ($pdo) {
        $st = $pdo->prepare("SELECT * FROM team_registrations WHERE team_id = ?");
        $st->execute([$teamId]);
        return $st->fetch() ?: [];
    };

    $sheet = "Club Name,Boat Name,Captain Name,Short Code\n"
           . "Nadubhagom BC,Nadubhagom Chundan,K. Menon,NBC\n"
           . "Karichal BC,Karichal Chundan,S. Pillai,KBC\n";

    $t = \Models\Team::importRows($EV, $rowsOf($sheet), 'skip', 'draft');
    check('import creates both boats', $t, ['created' => 2, 'updated' => 0, 'skipped' => 0]);
    check('both boats are on file', $countTeams(), 2);
    check('each import opens a registration',
        (int)$pdo->query("SELECT COUNT(*) FROM team_registrations")->fetchColumn(), 2);
    check('registration opens as draft', $regOf((int)$teamBy('NBC')['id'])['status'], 'draft');

    // A logo can only ever be added by hand; re-importing must not wipe it.
    $pdo->exec("UPDATE teams SET logo = '/assets/uploads/teams/nbc.png' WHERE short_code = 'NBC'");

    // Re-uploading the same sheet in skip mode changes nothing.
    $t = \Models\Team::importRows($EV, $rowsOf($sheet), 'skip', 'draft');
    check('re-upload in skip mode skips both', $t, ['created' => 0, 'updated' => 0, 'skipped' => 2]);
    check('re-upload in skip mode adds nothing', $countTeams(), 2);

    // Update mode rewrites the details of a matching boat.
    $fixed = "Club Name,Boat Name,Captain Name,Short Code,Home Place\n"
           . "Nadubhagom BC,Nadubhagom Chundan,P. Varghese,NBC,Nadubhagom\n";
    $t = \Models\Team::importRows($EV, $rowsOf($fixed), 'update', 'draft');
    check('update mode updates the match', $t, ['created' => 0, 'updated' => 1, 'skipped' => 0]);
    check('the captain is rewritten', $teamBy('NBC')['captain_name'], 'P. Varghese');
    check('a blank column is filled in', $teamBy('NBC')['home_place'], 'Nadubhagom');
    check('the uploaded logo survives an update',
        $teamBy('NBC')['logo'], '/assets/uploads/teams/nbc.png');

    // A boat matches on club + boat name even without a short code.
    $noCode = "Club Name,Boat Name,Captain Name\nKarichal BC,Karichal Chundan,New Captain\n";
    $t = \Models\Team::importRows($EV, $rowsOf($noCode), 'update', 'draft');
    check('match falls back to club + boat name', $t['updated'], 1);
    check('no duplicate was created', $countTeams(), 2);

    // Importing as approved moves the registration forward.
    $t = \Models\Team::importRows($EV, $rowsOf($sheet), 'update', 'approved');
    $reg = $regOf((int)$teamBy('NBC')['id']);
    check('importing as approved approves the registration', $reg['status'], 'approved');
    ok('approval is stamped', !empty($reg['decided_at']) && !empty($reg['decided_by']));

    // ...and no later import may walk an already-vetted boat backwards.
    // Two separate guards cover this, so both need their own case:
    //   'draft'     returns before the rank check ever runs;
    //   'submitted' is what the rank check itself exists to stop.
    \Models\Team::importRows($EV, $rowsOf($sheet), 'update', 'draft');
    check('a later draft import cannot un-approve',
        $regOf((int)$teamBy('NBC')['id'])['status'], 'approved');
    \Models\Team::importRows($EV, $rowsOf($sheet), 'update', 'submitted');
    check('a later submitted import cannot demote an approved boat',
        $regOf((int)$teamBy('NBC')['id'])['status'], 'approved');

    // The guard must not block legitimate progress: a fresh boat walks
    // draft -> submitted -> approved across successive uploads.
    $third = "Club Name,Boat Name,Captain Name,Short Code\nVeeyapuram BC,Veeyapuram Chundan,R. Nair,VBC\n";
    \Models\Team::importRows($EV, $rowsOf($third), 'skip', 'draft');
    $vbc = (int)$teamBy('VBC')['id'];
    check('a new boat starts as draft', $regOf($vbc)['status'], 'draft');
    \Models\Team::importRows($EV, $rowsOf($third), 'update', 'submitted');
    check('draft moves forward to submitted', $regOf($vbc)['status'], 'submitted');
    ok('submission is stamped', !empty($regOf($vbc)['submitted_at']));
    \Models\Team::importRows($EV, $rowsOf($third), 'update', 'approved');
    check('submitted moves forward to approved', $regOf($vbc)['status'], 'approved');

    // Matching is case-insensitive on both routes.
    ok('short-code match ignores case',
        \Models\Team::matchExisting($EV, 'nbc', 'x', 'y') !== null);
    ok('name match ignores case',
        \Models\Team::matchExisting($EV, '', 'karichal bc', 'KARICHAL CHUNDAN') !== null);
    ok('an unrelated boat does not match',
        \Models\Team::matchExisting($EV, '', 'Other Club', 'Other Boat') === null);
    ok('another event\'s boats are never matched',
        \Models\Team::matchExisting($EV + 1, 'NBC', 'Nadubhagom BC', 'Nadubhagom Chundan') === null);

    $prop->setValue(null, null);
}

// ── Schedule cascade: race → round → heat ───────────────────────────────────
// Each level may override the one above it, and date and time inherit
// independently, so "semis at 14:00 on the same day" needs only a time.
$RACE  = ['race_date' => '2026-08-08', 'race_time' => '09:30:00'];
$plain = ['scheduled_date' => null, 'scheduled_time' => null];

$s0 = roundSchedule($plain, $RACE);
check('a round with no slot takes the race date', $s0['date'], '2026-08-08');
check('a round with no slot takes the race time', $s0['time'], '09:30:00');
check('and is marked inherited', $s0['inherited'], true);
check('and owns neither field', [$s0['own_date'], $s0['own_time']], [false, false]);

// Time only — the classic "semis later the same day".
$timeOnly = ['scheduled_date' => null, 'scheduled_time' => '14:00:00'];
$s1 = roundSchedule($timeOnly, $RACE);
check('a time-only round keeps the race date', $s1['date'], '2026-08-08');
check('a time-only round uses its own time',   $s1['time'], '14:00:00');
check('a time-only round owns just the time',  [$s1['own_date'], $s1['own_time']], [false, true]);
check('a partly-set round still reads as inherited', $s1['inherited'], true);

// Both — the final on the following morning.
$both = ['scheduled_date' => '2026-08-09', 'scheduled_time' => '10:15:00'];
$s2 = roundSchedule($both, $RACE);
check('a fully-set round uses its own date', $s2['date'], '2026-08-09');
check('a fully-set round uses its own time', $s2['time'], '10:15:00');
check('a fully-set round is not inherited',  $s2['inherited'], false);

// A heat resolves through its round, not straight to the race.
$heatPlain = ['scheduled_date' => null, 'scheduled_time' => null];
$h1 = heatSchedule($heatPlain, $timeOnly, $RACE);
check('a heat inherits its round rather than the race', $h1['time'], '14:00:00');
check('and still picks up the race date through it',    $h1['date'], '2026-08-08');

$heatOwn = ['scheduled_date' => null, 'scheduled_time' => '14:45:00'];
$h2 = heatSchedule($heatOwn, $timeOnly, $RACE);
check('a heat may override its round', $h2['time'], '14:45:00');
check('while still inheriting the date', $h2['date'], '2026-08-08');

$h3 = heatSchedule($heatPlain, $both, $RACE);
check('a heat follows a round moved to another day', $h3['date'], '2026-08-09');
check('and that round\'s time', $h3['time'], '10:15:00');

// Placeholder values must count as "not set", or a zero date would win over
// a real one from the level above.
$zero = ['scheduled_date' => '0000-00-00', 'scheduled_time' => '00:00:00'];
$s3 = roundSchedule($zero, $RACE);
check('a zero date does not override', $s3['date'], '2026-08-08');
check('a zero time does not override', $s3['time'], '09:30:00');

// Nothing anywhere stays genuinely empty rather than inventing a date.
$s4 = roundSchedule($plain, ['race_date' => null, 'race_time' => null]);
check('an unscheduled race leaves the round unscheduled', [$s4['date'], $s4['time']], ['', '']);
check('and its label says so', scheduleLabel($s4), '—');

check('a resolved slot formats as date and time',
    scheduleLabel($s2), '09 Aug 2026 · 10:15 AM');

// ── Race entries: saving the list must not destroy boat photos ──────────────
// Each entry carries the boat's photo for that race. setEntries() used to
// delete every row and re-insert, which would have thrown those photos away
// each time somebody ticked one more box — so it is written as a diff, and
// that is what this pins down.
if (extension_loaded('pdo_sqlite')) {
    $pdo = new PDO('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('CREATE TABLE team_registrations (id INTEGER PRIMARY KEY, event_id INT, team_id INT, status TEXT)');
    $pdo->exec('CREATE TABLE race_entries (id INTEGER PRIMARY KEY, event_id INT, event_race_id INT,
        team_registration_id INT, image TEXT, created_at TEXT)');

    $prop = new ReflectionProperty(\Core\Model::class, 'pdo');
    $prop->setAccessible(true);
    $prop->setValue(null, $pdo);

    $EV = 3; $RACE = 11;
    // Four approved boats, plus one that is only submitted.
    $pdo->exec("INSERT INTO team_registrations (id, event_id, team_id, status) VALUES
        (1,3,101,'approved'), (2,3,102,'approved'), (3,3,103,'approved'),
        (4,3,104,'approved'), (5,3,105,'submitted')");

    // Distinguishes "no entry row at all" from "entry row with no photo" —
    // conflating the two would let a dropped boat pass as an un-photographed
    // one, which is exactly the bug this section is here to catch.
    $imageOf = function (int $reg) use ($pdo, $RACE) {
        $st = $pdo->prepare("SELECT image FROM race_entries WHERE event_race_id = ? AND team_registration_id = ?");
        $st->execute([$RACE, $reg]);
        $row = $st->fetch();
        return $row === false ? '__no_entry__' : $row['image'];
    };

    check('entering three boats', \Models\EventRace::setEntries($EV, $RACE, [1, 2, 3]), 3);
    check('the entry list reads back',
        \Models\EventRace::entryRegistrationIds($RACE), [1, 2, 3]);

    // Photos are uploaded against two of them.
    $map = \Models\EventRace::entryMap($RACE);
    \Models\EventRace::setEntryImage($map[1]['entry_id'], '/assets/uploads/boats/one.jpg');
    \Models\EventRace::setEntryImage($map[2]['entry_id'], '/assets/uploads/boats/two.jpg');
    check('a photo is stored against the entry', $imageOf(1), '/assets/uploads/boats/one.jpg');

    // Saving the list again with one boat added and one removed.
    check('re-saving the list', \Models\EventRace::setEntries($EV, $RACE, [1, 2, 4]), 3);
    check('photos survive a re-save', $imageOf(1), '/assets/uploads/boats/one.jpg');
    check('every kept photo survives',  $imageOf(2), '/assets/uploads/boats/two.jpg');
    check('a dropped boat is gone',     $imageOf(3), '__no_entry__');
    check('a newly added boat has no photo yet', $imageOf(4), null);
    check('the list is exactly what was asked for',
        \Models\EventRace::entryRegistrationIds($RACE), [1, 2, 4]);

    // Re-saving an identical list must be a complete no-op for the rows.
    $before = $pdo->query("SELECT id FROM race_entries ORDER BY id")->fetchAll();
    \Models\EventRace::setEntries($EV, $RACE, [1, 2, 4]);
    check('an unchanged save does not churn the rows',
        $pdo->query("SELECT id FROM race_entries ORDER BY id")->fetchAll(), $before);

    // Only approved registrations may be entered.
    check('a non-approved boat is refused', \Models\EventRace::setEntries($EV, $RACE, [1, 5]), 1);
    check('and it never reaches the entry list',
        \Models\EventRace::entryRegistrationIds($RACE), [1]);
    check('the approved boat kept its photo', $imageOf(1), '/assets/uploads/boats/one.jpg');

    // A boat belonging to another event can never be entered here.
    check('another event\'s registration is refused',
        \Models\EventRace::setEntries($EV + 1, $RACE, [1]), 0);

    // Clearing the list empties it.
    check('clearing the list', \Models\EventRace::setEntries($EV, $RACE, []), 0);
    check('nothing is left', \Models\EventRace::entryRegistrationIds($RACE), []);

    // findEntry is scoped to its race.
    \Models\EventRace::setEntries($EV, $RACE, [1]);
    $entryId = \Models\EventRace::entryMap($RACE)[1]['entry_id'];
    ok('findEntry finds its own entry', \Models\EventRace::findEntry($RACE, $entryId) !== null);
    ok('findEntry refuses another race\'s entry',
        \Models\EventRace::findEntry($RACE + 1, $entryId) === null);

    $prop->setValue(null, null);
}

// ── Sign-in resolution ──────────────────────────────────────────────────────
// One /login form resolves the role by checking all three account tables, so
// the page never advertises the role structure. That resolution is the piece
// most worth testing: it decides who gets in and as what.
//
// Driven against SQLite with a PDO injected into Core\Model. The queries
// involved are plain portable SQL, so this exercises the LOGIC — it is not a
// substitute for running the real schema on MySQL.
if (extension_loaded('pdo_sqlite')) {
    $pdo = new PDO('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT, password TEXT,
                                    role TEXT, status TEXT, last_login_at TEXT)');
    $pdo->exec('CREATE TABLE events (id INTEGER PRIMARY KEY, code TEXT, name TEXT, name_regional TEXT,
                                     image TEXT, start_date TEXT, end_date TEXT, status TEXT)');
    $pdo->exec('CREATE TABLE event_admins (id INTEGER PRIMARY KEY, event_id INT, name TEXT, email TEXT,
                                           password TEXT, status TEXT, last_login_at TEXT)');
    $pdo->exec('CREATE TABLE event_users (id INTEGER PRIMARY KEY, event_id INT, name TEXT, email TEXT,
                                          password TEXT, status TEXT, last_login_at TEXT)');
    $pdo->exec('CREATE TABLE event_user_privileges (id INTEGER PRIMARY KEY, event_user_id INT, privilege TEXT)');

    $hash = fn(string $p) => password_hash($p, PASSWORD_BCRYPT, ['cost' => 4]);   // cheap for tests

    $pdo->exec("INSERT INTO events (id, code, name, status) VALUES
        (1, 'RGAAA111', 'Alpha Regatta', 'active'),
        (2, 'RGBBB222', 'Beta Regatta',  'active')");

    $ins = fn(string $sql, array $a) => $pdo->prepare($sql)->execute($a);
    $ins("INSERT INTO users (name,email,password,role,status) VALUES (?,?,?,?,?)",
         ['Root', 'root@example.com', $hash('rootpass'), 'super_admin', 'active']);
    $ins("INSERT INTO users (name,email,password,role,status) VALUES (?,?,?,?,?)",
         ['Old', 'retired@example.com', $hash('rootpass'), 'super_admin', 'inactive']);

    $ins("INSERT INTO event_admins (event_id,name,email,password,status) VALUES (?,?,?,?,?)",
         [1, 'Ann', 'ann@example.com', $hash('annpass'), 'active']);
    // Same address, two regattas, DIFFERENT passwords — only the matching one counts.
    $ins("INSERT INTO event_admins (event_id,name,email,password,status) VALUES (?,?,?,?,?)",
         [1, 'Cara', 'cara@example.com', $hash('shared'), 'active']);
    $ins("INSERT INTO event_admins (event_id,name,email,password,status) VALUES (?,?,?,?,?)",
         [2, 'Cara', 'cara@example.com', $hash('different'), 'active']);
    // Disabled account must never appear.
    $ins("INSERT INTO event_admins (event_id,name,email,password,status) VALUES (?,?,?,?,?)",
         [2, 'Gone', 'gone@example.com', $hash('gonepass'), 'disabled']);

    $ins("INSERT INTO event_users (event_id,name,email,password,status) VALUES (?,?,?,?,?)",
         [2, 'Cara', 'cara@example.com', $hash('shared'), 'active']);
    $pdo->exec("INSERT INTO event_user_privileges (event_user_id, privilege)
                VALUES (1, 'result_entry'), (1, 'reports')");

    // Inject the connection Core\Model would otherwise open itself.
    $prop = new ReflectionProperty(\Core\Model::class, 'pdo');
    $prop->setAccessible(true);
    $prop->setValue(null, $pdo);

    $resolve = new ReflectionMethod(\Controllers\AuthController::class, 'resolveCandidates');
    $resolve->setAccessible(true);
    $auth = new \Controllers\AuthController();
    $find = fn(string $email, string $password) => $resolve->invoke($auth, $email, $password);

    $kinds = fn(array $c) => implode(',', array_column($c, 'kind'));

    $r = $find('root@example.com', 'rootpass');
    check('platform account resolves', $kinds($r), 'admin');

    check('wrong password resolves to nothing', $find('root@example.com', 'nope'), []);
    check('unknown address resolves to nothing', $find('nobody@example.com', 'rootpass'), []);
    check('inactive platform account is excluded', $find('retired@example.com', 'rootpass'), []);
    check('disabled event account is excluded', $find('gone@example.com', 'gonepass'), []);

    $r = $find('ann@example.com', 'annpass');
    check('single event-admin resolves', $kinds($r), 'event_admin');
    check('candidate carries its event name', $r[0]['title'] ?? '', 'Alpha Regatta');

    // Cara holds three accounts; only the two sharing this password may appear.
    $r = $find('cara@example.com', 'shared');
    check('one password opens exactly its own accounts', $kinds($r), 'event_admin,event_user');
    check('the other regatta is not offered',
        in_array('Beta Regatta', array_column($r, 'title'), true)
            && count(array_filter($r, fn($c) => $c['title'] === 'Beta Regatta')) === 1, true);

    $r = $find('cara@example.com', 'different');
    check('the other password opens only its own account', $kinds($r), 'event_admin');
    check('and it is the other regatta', $r[0]['title'] ?? '', 'Beta Regatta');

    // Candidates must never carry password material into the session.
    $leak = [];
    foreach ($find('cara@example.com', 'shared') as $c) {
        foreach ($c as $k => $v) {
            if (is_string($v) && str_starts_with($v, '$2y$')) $leak[] = $k;
        }
    }
    check('no password hash reaches the candidate list', $leak, []);

    $prop->setValue(null, null);   // leave no connection behind
}

// ── Root .htaccess ──────────────────────────────────────────────────────────
// Guards the case where the domain's document root is the deployment path
// rather than app/public. The rule ORDER carries the weight: the loop guard
// must precede the catch-all, or the catch-all rewrites its own output until
// Apache gives up with a 500.
$rootHt = $root . '/.htaccess';
if (is_file($rootHt)) {
    $ht = (string)file_get_contents($rootHt);
    ok('root .htaccess disables directory listings', str_contains($ht, 'Options -Indexes'));

    $guard    = strpos($ht, 'RewriteRule ^app/public/ - [L]');
    $catchAll = strpos($ht, 'RewriteRule ^(.*)$ app/public/$1 [L]');
    ok('root .htaccess has the rewrite loop guard', $guard !== false);
    ok('root .htaccess has the catch-all rewrite', $catchAll !== false);
    ok('loop guard precedes the catch-all',
        $guard !== false && $catchAll !== false && $guard < $catchAll);
    ok('root .htaccess denies dotfiles', str_contains($ht, '<FilesMatch "^\\.">'));

    // Nothing served from app/public may carry a denied extension, or the
    // inherited rule would 403 a legitimate asset.
    if (preg_match('/FilesMatch "\\\\\\.\\(([a-z|]+)\\)\\$"/', $ht, $m)) {
        $denied = explode('|', $m[1]);
        $clash  = [];
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PUBLIC_ROOT));
        foreach ($rii as $f) {
            if ($f->isDir()) continue;
            if (str_starts_with($f->getFilename(), '.')) continue;   // covered by the dotfile rule
            if (in_array(strtolower($f->getExtension()), $denied, true)) {
                $clash[] = $f->getFilename();
            }
        }
        ok('no served asset matches a denied extension (' . implode(', ', $clash) . ')', $clash === []);
    }
}

// ── PDF pipeline ────────────────────────────────────────────────────────────
if (!class_exists(\Dompdf\Dompdf::class)) {
    echo "note: Dompdf not installed — skipping PDF checks (run `composer install`).\n";
} else {
    $event = [
        'id' => 1, 'code' => 'RG1A2B3C', 'name' => 'Nehru Trophy Boat Race 2026',
        'name_regional' => 'നെഹ്‌റു ട്രോഫി വള്ളംകളി',
        'start_date' => '2026-08-08', 'end_date' => '2026-08-09',
        'venue' => 'Punnamada Lake', 'organiser' => 'District Sports Council',
        'image' => null, 'default_lanes' => 4,
    ];
    $logo   = '';
    $footer = 'Powered by SportsMIS® Regatta';

    $render = function (string $view, array $vars): string {
        extract($vars);
        ob_start();
        require APP_ROOT . "/views/{$view}.php";
        return (string)ob_get_clean();
    };

    // Programme
    $race = fn(int $sl, string $name, string $d, string $t, string $st) => [
        'sl_no' => $sl, 'name' => $name, 'name_regional' => 'ചുണ്ടൻ വള്ളം', 'code' => "R{$sl}",
        'boat_class' => 'Chundan Vallam', 'gender' => 'men', 'distance_m' => 1400,
        'lane_count' => 4, 'race_date' => $d, 'race_time' => $t, 'status' => $st,
    ];
    $groups = [
        '2026-08-08' => [$race(1, 'Heat A', '2026-08-08', '09:30:00', 'result_published')],
        '2026-08-09' => [$race(2, 'Final', '2026-08-09', '15:00:00', 'scheduled')],
    ];
    $pdf = \Core\Pdf::render($render('event-admin/order-of-events/programme-pdf',
        compact('event', 'groups', 'logo', 'footer')), 'A4', 'portrait', true);
    ok('programme PDF renders', str_starts_with($pdf, '%PDF-') && strlen($pdf) > 1000);

    // Rank list
    $place = fn(int $p, string $boat, string $club, string $t) => [
        'position' => $p, 'boat_name' => $boat, 'club_name' => $club, 'race_time' => $t,
        'short_code' => 'NBC', 'logo' => null, 'outcome' => 'ok', 'captain_name' => 'K. Menon',
    ];
    $rankList = [
        ['race' => ['sl_no' => 1, 'name' => 'Chundan Vallam', 'name_regional' => 'ചുണ്ടൻ വള്ളം'],
         'round' => ['name' => 'Final'],
         'places' => [$place(1, 'Nadubhagom', 'NBC', '4:12.350'), $place(2, 'Karichal', 'KBC', '4:13.100'),
                      $place(3, 'Veeyapuram', 'VBC', '4:15.000'), $place(4, 'Payippad', 'PBC', '4:18.220')]],
        ['race' => ['sl_no' => 2, 'name' => 'Iruttukuthy', 'name_regional' => null],
         'round' => null, 'places' => []],
    ];
    $tally = [
        ['club_name' => 'NBC', 'gold' => 1, 'silver' => 0, 'bronze' => 0, 'points' => 3],
        ['club_name' => 'KBC', 'gold' => 0, 'silver' => 1, 'bronze' => 0, 'points' => 2],
    ];
    $pdf = \Core\Pdf::render($render('event-user/reports/rank-list-pdf',
        compact('event', 'rankList', 'tally', 'logo', 'footer')), 'A4', 'portrait', true);
    ok('rank-list PDF renders', str_starts_with($pdf, '%PDF-') && strlen($pdf) > 1000);

    // Heat sheet — one lane deliberately left empty to exercise that branch.
    $round = ['id' => 9, 'race_sl_no' => 1, 'race_name' => 'Chundan Vallam', 'name' => 'Final',
              'lane_count' => 4, 'status' => 'published', 'race_date' => '2026-08-09', 'race_time' => '15:00:00'];
    $lane = fn(int $n, string $boat, string $club, string $t, ?int $pos) => [
        'lane_no' => $n, 'boat_name' => $boat, 'club_name' => $club, 'captain_name' => 'K. Menon',
        'race_time' => $t, 'position' => $pos, 'qualified' => 0, 'outcome' => 'ok', 'remarks' => null,
    ];
    $heats = [[
        'id' => 1, 'heat_no' => 1, 'name' => 'Final',
        'scheduled_date' => '2026-08-09', 'scheduled_time' => '15:00:00',
        'lanes' => [$lane(1, 'Nadubhagom', 'NBC', '4:12.350', 1),
                    $lane(2, 'Karichal', 'KBC', '4:13.100', 2),
                    $lane(4, 'Veeyapuram', 'VBC', '4:15.000', 3)],
    ]];
    $pdf = \Core\Pdf::render($render('event-user/reports/heat-sheet-pdf',
        compact('event', 'round', 'heats', 'logo', 'footer')), 'A4', 'portrait', true);
    ok('heat-sheet PDF renders', str_starts_with($pdf, '%PDF-') && strlen($pdf) > 1000);
}

// ── Report ──────────────────────────────────────────────────────────────────
printf("%d check(s) passed\n", $passed);
if ($failures) {
    echo count($failures) . " failure(s):\n";
    foreach ($failures as $f) echo "  - {$f}\n";
    exit(1);
}
echo "Self-test OK.\n";
