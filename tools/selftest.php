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
