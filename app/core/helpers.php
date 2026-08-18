<?php
/**
 * Global helper functions. Loaded once from app/public/index.php before the
 * router runs, so every view and controller can call them unqualified.
 */

// ── Output escaping / form plumbing ──────────────────────────────────────────

function e(mixed $val): string
{
    return htmlspecialchars((string)$val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function old(string $key, mixed $default = ''): mixed
{
    return $_SESSION['old'][$key] ?? $default;
}

function flash(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function _getViewErrors(): array
{
    return $GLOBALS['_sms_errors'] ?? $_SESSION['errors'] ?? [];
}

function fieldError(string $field): string
{
    $errors = _getViewErrors();
    if (!isset($errors[$field])) return '';
    return '<div class="invalid-feedback d-block">' . e(implode(' ', (array)$errors[$field])) . '</div>';
}

function hasError(string $field): string
{
    return isset(_getViewErrors()[$field]) ? 'is-invalid' : '';
}

/** Hidden CSRF field — drop into every mutating form. */
function csrf(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return '<input type="hidden" name="_token" value="' . e($_SESSION['csrf_token']) . '">';
}

/** Raw CSRF token, for FormData built in JavaScript. */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function asset(string $path): string
{
    return '/assets/' . ltrim($path, '/');
}

function url(string $path = ''): string
{
    $cfg = require CONFIG_ROOT . '/app.php';
    return rtrim($cfg['url'], '/') . '/' . ltrim($path, '/');
}

function config(string $key, mixed $default = null): mixed
{
    static $cfg = null;
    if ($cfg === null) $cfg = require CONFIG_ROOT . '/app.php';
    $node = $cfg;
    foreach (explode('.', $key) as $seg) {
        if (!is_array($node) || !array_key_exists($seg, $node)) return $default;
        $node = $node[$seg];
    }
    return $node;
}

function activeNav(string $prefix): string
{
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    return str_starts_with($uri, $prefix) ? 'active' : '';
}

// ── Hidden (obfuscated) URL ids ──────────────────────────────────────────────
// Integer ids never appear in a URL: each is wrapped in an HMAC-signed token
// scoped to its entity type, so a token minted for an event can't be replayed
// as a team id.

function hid_event(int $id): string            { return \Core\Hash::encode($id, 'event'); }
function hid_event_decode($v): int             { return \Core\Hash::decodeOrInt($v, 'event'); }
function hid_admin(int $id): string            { return \Core\Hash::encode($id, 'event_admin'); }
function hid_admin_decode($v): int             { return \Core\Hash::decodeOrInt($v, 'event_admin'); }
function hid_user(int $id): string             { return \Core\Hash::encode($id, 'event_user'); }
function hid_user_decode($v): int              { return \Core\Hash::decodeOrInt($v, 'event_user'); }
function hid_team(int $id): string             { return \Core\Hash::encode($id, 'team'); }
function hid_team_decode($v): int              { return \Core\Hash::decodeOrInt($v, 'team'); }
function hid_reg(int $id): string              { return \Core\Hash::encode($id, 'registration'); }
function hid_reg_decode($v): int               { return \Core\Hash::decodeOrInt($v, 'registration'); }
function hid_race(int $id): string             { return \Core\Hash::encode($id, 'race'); }
function hid_race_decode($v): int              { return \Core\Hash::decodeOrInt($v, 'race'); }
function hid_round(int $id): string            { return \Core\Hash::encode($id, 'round'); }
function hid_round_decode($v): int             { return \Core\Hash::decodeOrInt($v, 'round'); }
function hid_heat(int $id): string             { return \Core\Hash::encode($id, 'heat'); }
function hid_heat_decode($v): int              { return \Core\Hash::decodeOrInt($v, 'heat'); }
function hid_entry(int $id): string            { return \Core\Hash::encode($id, 'race_entry'); }
function hid_entry_decode($v): int             { return \Core\Hash::decodeOrInt($v, 'race_entry'); }
function hid_alloc(int $id): string            { return \Core\Hash::encode($id, 'allocation'); }
function hid_alloc_decode($v): int             { return \Core\Hash::decodeOrInt($v, 'allocation'); }

// ── Dates, times, formatting ─────────────────────────────────────────────────

function formatDate(?string $date, string $format = 'd M Y'): string
{
    if (!$date || $date === '0000-00-00') return '—';
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : '—';
}

function formatTime(?string $time, string $format = 'g:i A'): string
{
    if (!$time || $time === '00:00:00') return '—';
    $ts = strtotime($time);
    return $ts ? date($format, $ts) : '—';
}

/** "12 Apr 2026 · 9:30 AM" — the programme's standard date+time stamp. */
function formatDateTime(?string $date, ?string $time): string
{
    $d = formatDate($date);
    $t = formatTime($time);
    if ($d === '—' && $t === '—') return '—';
    if ($t === '—') return $d;
    if ($d === '—') return $t;
    return "{$d} · {$t}";
}

/**
 * Normalise a race time typed by a judge into MM:SS.mmm for storage and
 * sorting. Accepts "1:23.45", "83.45", "1:23", "01:23.450". Returns '' when
 * the input can't be parsed so the caller can reject it.
 */
function normaliseRaceTime(?string $raw): string
{
    $raw = trim((string)$raw);
    if ($raw === '') return '';
    if (!preg_match('/^(?:(\d{1,2}):)?(\d{1,2})(?:[.,](\d{1,3}))?$/', $raw, $m)) return '';
    $min = (int)($m[1] ?? 0);
    $sec = (int)$m[2];
    if ($sec > 59 && ($m[1] ?? '') !== '') return '';
    // Bare "83.45" means 83 seconds — roll the overflow into minutes.
    if (($m[1] ?? '') === '' && $sec > 59) { $min = intdiv($sec, 60); $sec %= 60; }
    $ms  = str_pad((string)($m[3] ?? '0'), 3, '0');
    return sprintf('%02d:%02d.%s', $min, $sec, $ms);
}

/** Race time as hundredths of a second, for ordering. 0 when unparseable. */
function raceTimeToCentis(?string $t): int
{
    $t = normaliseRaceTime($t);
    if ($t === '') return 0;
    [$mm, $rest] = explode(':', $t, 2);
    [$ss, $ms]   = explode('.', $rest, 2);
    return ((int)$mm * 60 + (int)$ss) * 100 + (int)round(((int)$ms) / 10);
}

/**
 * Resolve a scheduled slot against the level above it.
 *
 * A regatta is scheduled at three levels and each one is optional:
 *
 *     race  ──▶  round  ──▶  heat
 *
 * A race always carries a date and time. A round may override either — semis
 * at 14:00 on the same day, or the final on the following morning — and an
 * individual heat may override again. Anything left blank inherits, so an
 * event that runs a race straight through needs no extra data entry at all.
 *
 * Date and time inherit independently: a round on the same day at a different
 * time only has to set the time.
 *
 * Returns ['date','time','own_date','own_time','inherited'].
 */
function effectiveSchedule(?string $date, ?string $time, ?string $fallbackDate = null, ?string $fallbackTime = null): array
{
    $date = trim((string)$date);
    $time = trim((string)$time);
    $ownDate = $date !== '' && $date !== '0000-00-00';
    $ownTime = $time !== '' && $time !== '00:00:00';

    return [
        'date'      => $ownDate ? $date : (string)$fallbackDate,
        'time'      => $ownTime ? $time : (string)$fallbackTime,
        'own_date'  => $ownDate,
        'own_time'  => $ownTime,
        'inherited' => !$ownDate || !$ownTime,
    ];
}

/** A round's slot, falling back to its race. Pass the joined row or both rows. */
function roundSchedule(array $round, ?array $race = null): array
{
    return effectiveSchedule(
        $round['scheduled_date'] ?? null,
        $round['scheduled_time'] ?? null,
        $race['race_date'] ?? $round['race_date'] ?? null,
        $race['race_time'] ?? $round['race_time'] ?? null
    );
}

/** A heat's slot, falling back to its round and then to the race. */
function heatSchedule(array $heat, array $round, ?array $race = null): array
{
    $parent = roundSchedule($round, $race);
    return effectiveSchedule(
        $heat['scheduled_date'] ?? null,
        $heat['scheduled_time'] ?? null,
        $parent['date'],
        $parent['time']
    );
}

/** "09 Aug 2026 · 3:00 PM" from a resolved slot. */
function scheduleLabel(array $slot): string
{
    return formatDateTime($slot['date'] ?: null, $slot['time'] ?: null);
}

function avatarInitials(string $name): string
{
    $words = preg_split('/\s+/', trim($name)) ?: [];
    $initials = strtoupper(mb_substr((string)($words[0] ?? ''), 0, 1));
    if (count($words) > 1) $initials .= strtoupper(mb_substr((string)end($words), 0, 1));
    return $initials !== '' ? $initials : 'U';
}

function ordinal(int $n): string
{
    if ($n <= 0) return (string)$n;
    $suffix = ['th', 'st', 'nd', 'rd'];
    $v = $n % 100;
    return $n . ($suffix[($v - 20) % 10] ?? $suffix[$v] ?? $suffix[0]);
}

// ── Badges and flash rendering ───────────────────────────────────────────────

function flashBag(): string
{
    $f = flash();
    if (!$f) return '';
    $type = match ($f['type'] ?? 'info') {
        'success' => 'success',
        'error'   => 'danger',
        'warning' => 'warning',
        default   => 'info',
    };
    return '<div class="alert alert-' . $type . ' alert-dismissible fade show mb-3" role="alert">'
        . e($f['message'])
        . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>'
        . '</div>';
}

function statusBadge(?string $status): string
{
    $key = strtolower(trim((string)$status));
    $map = [
        'active'    => 'success',
        'published' => 'success',
        'approved'  => 'success',
        'completed' => 'info',
        'submitted' => 'info',
        'pending'   => 'warning',
        'returned'  => 'warning',
        'draft'     => 'secondary',
        'inactive'  => 'secondary',
        'archived'  => 'secondary',
        'disabled'  => 'danger',
        'suspended' => 'danger',
        'rejected'  => 'danger',
    ];
    $color = $map[$key] ?? 'secondary';
    $label = ucwords(str_replace('_', ' ', $key ?: 'unknown'));
    return "<span class='badge bg-{$color}'>" . e($label) . "</span>";
}

/**
 * Call-room / progress badge for a programme item. The vocabulary is the
 * race-day flow: Scheduled -> In Progress -> Finished -> Result Published ->
 * Medal Ceremony.
 */
function raceStatusBadge(?string $status): string
{
    $map = [
        'scheduled'        => ['Scheduled',        'secondary',      'bi-clock'],
        'in_progress'      => ['In Progress',      'warning text-dark', 'bi-play-circle'],
        'finished'         => ['Finished',         'info text-dark', 'bi-flag'],
        'result_published' => ['Result Published', 'success',        'bi-megaphone'],
        'medal_ceremony'   => ['Medal Ceremony',   'primary',        'bi-award'],
    ];
    [$label, $color, $icon] = $map[strtolower((string)$status)] ?? $map['scheduled'];
    return "<span class='badge bg-{$color}'><i class='bi {$icon} me-1'></i>" . e($label) . "</span>";
}

/** The five call-room statuses in race-day order — used to build selects. */
function raceStatuses(): array
{
    return [
        'scheduled'        => 'Scheduled',
        'in_progress'      => 'In Progress',
        'finished'         => 'Finished',
        'result_published' => 'Result Published',
        'medal_ceremony'   => 'Medal Ceremony',
    ];
}

/** Medal colour class for positions 1–4 (4th gets a neutral tint). */
function positionClass(int $pos): string
{
    return match ($pos) {
        1 => 'pos-gold',
        2 => 'pos-silver',
        3 => 'pos-bronze',
        default => 'pos-fourth',
    };
}

/**
 * Ensure an event has a short, unique Event Code — the tenant's public
 * handle, printed on the programme and typed by a venue operator to open a
 * display screen. Generated on first use and never changes. Idempotent.
 */
function ensureEventCode(int $eventId): string
{
    $current = \Models\Event::codeFor($eventId);
    if ($current !== '') return $current;

    for ($i = 0; $i < 8; $i++) {
        $code = 'RG' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        try {
            \Models\Event::setCode($eventId, $code);
            return $code;
        } catch (\Throwable $e) {
            continue;   // collision on uq_events_code — draw again
        }
    }
    return '';
}
