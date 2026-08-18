<?php
namespace Services;

use Models\{Event, EventRace, Round, Result, Heat, LaneAllocation};

/**
 * Builds the public results payload and writes it out as STATIC files.
 *
 * Why static: on race day tens of thousands of people refresh at once. A PHP
 * request per visitor — and a database query behind it — is what takes a
 * shared host down. Apache serving a file it already has costs orders of
 * magnitude less, and puts a CDN in play, so the public page never executes
 * PHP or touches MySQL at all.
 *
 * Layout, per event, under app/public/live/<CODE>/:
 *
 *   manifest.json      tiny; carries the current version. Polled often.
 *   results-<v>.json   the payload. Immutable — its name changes with the
 *                      version — so a browser or CDN caches it forever and
 *                      only ever revalidates the manifest.
 *
 * Every file is written to a temporary name in the same directory and then
 * renamed over the target. rename() is atomic within a filesystem, so a
 * visitor never sees a half-written response — which is exactly the failure
 * a naive fopen/fwrite would produce under load.
 */
class ResultSnapshot
{
    /** Older payloads kept so requests already in flight still resolve. */
    private const KEEP_VERSIONS = 3;

    /**
     * The public filenames this service serves. Declared rather than inferred,
     * because the directory is generated at runtime: a rule elsewhere that
     * denies one of these 403s the results for everybody, and the page just
     * shows nothing. tools/selftest.php checks these against the .htaccess
     * deny patterns.
     */
    public const SERVED_FILES = ['manifest.json', 'results-1.json', 'index.html'];

    /** Per-race publication state, in the order the public page shows it. */
    public const STATE_FINAL = 'final';   // the deciding round is published
    public const STATE_ROUND = 'round';   // some earlier round is published
    public const STATE_NONE  = 'none';    // nothing published yet

    /** Absolute path of the directory serving one event. */
    public static function directory(string $eventCode): string
    {
        return PUBLIC_ROOT . '/live/' . self::safeCode($eventCode);
    }

    /** Public URL path of the same directory. */
    public static function urlPath(string $eventCode): string
    {
        return '/live/' . self::safeCode($eventCode);
    }

    /**
     * Rebuild and publish an event's snapshot.
     * Returns ['version' => int, 'races' => int, 'bytes' => int, 'path' => string].
     */
    public static function publish(int $eventId): array
    {
        $event = Event::findById($eventId);
        if (!$event) throw new \RuntimeException('That event no longer exists.');

        $code = (string)($event['code'] ?? '');
        if ($code === '') $code = ensureEventCode($eventId);

        $dir = self::directory($code);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Could not create the public results directory.');
        }

        $version = self::nextVersion($dir);
        $payload = self::build($event);
        $payload['version'] = $version;

        $json = json_encode($payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            throw new \RuntimeException('Could not encode the results payload.');
        }

        $payloadName = "results-{$version}.json";
        self::atomicWrite($dir . '/' . $payloadName, $json);

        // The manifest is written LAST: until it points at the new payload,
        // readers keep using the old one, so the swap is never half-applied.
        self::atomicWrite($dir . '/manifest.json', (string)json_encode([
            'version' => $version,
            'file'    => $payloadName,
            'updated' => date('c'),
            'event'   => $code,
        ], JSON_UNESCAPED_SLASHES));

        self::writeStaticAssets($dir, $code);
        self::pruneOldVersions($dir, $version);

        return [
            'version' => $version,
            'races'   => count($payload['races']),
            'bytes'   => strlen($json),
            'path'    => self::urlPath($code) . '/',
        ];
    }

    // ── Payload ──────────────────────────────────────────────────────────────

    /** The whole public payload for one event. */
    private static function build(array $event): array
    {
        $eventId = (int)$event['id'];

        $races = [];
        foreach (EventRace::forEvent($eventId) as $race) {
            $races[] = self::raceCard($race);
        }

        return [
            'generated' => date('c'),
            'event'     => [
                'code'     => (string)($event['code'] ?? ''),
                'name'     => (string)$event['name'],
                'regional' => (string)($event['name_regional'] ?? ''),
                'venue'    => (string)($event['venue'] ?? ''),
                'dates'    => trim(formatDate($event['start_date']) . ' – ' . formatDate($event['end_date']), ' –'),
                'image'    => (string)($event['image'] ?? ''),
            ],
            'races' => $races,
            'tally' => array_map(fn($t) => [
                'club'   => (string)$t['club_name'],
                'gold'   => (int)$t['gold'],
                'silver' => (int)$t['silver'],
                'bronze' => (int)$t['bronze'],
                'points' => (int)$t['points'],
            ], Result::medalTally($eventId)),
        ];
    }

    /**
     * One race card. Which of the three shapes it takes is decided here, once,
     * so the public page never has to work it out.
     */
    private static function raceCard(array $race): array
    {
        $raceId   = (int)$race['id'];
        $deciding = Result::decidingRound($raceId);

        $card = [
            'sl'       => (int)$race['sl_no'],
            'name'     => (string)$race['name'],
            'regional' => (string)($race['name_regional'] ?? ''),
            'class'    => (string)($race['boat_class'] ?? ''),
            'gender'   => EventRace::GENDERS[$race['gender']] ?? (string)$race['gender'],
            'distance' => $race['distance_m'] ? (int)$race['distance_m'] . ' m' : '',
            'when'     => formatDateTime($race['race_date'] ?? null, $race['race_time'] ?? null),
            'image'    => (string)($race['image'] ?? ''),
        ];

        // The deciding round is published: this race is settled.
        if ($deciding && $deciding['status'] === 'published') {
            $card['state']  = self::STATE_FINAL;
            $card['label']  = 'Final Result';
            $card['round']  = (string)$deciding['name'];
            $card['places'] = self::places((int)$deciding['id']);
            return $card;
        }

        // An earlier round is out: show it and who went through.
        $latest = self::latestPublishedRound($raceId);
        if ($latest) {
            $card['state']     = self::STATE_ROUND;
            $card['label']     = 'Round Result';
            $card['round']     = (string)$latest['name'];
            $card['qualified'] = self::qualified((int)$latest['id']);
            return $card;
        }

        // Nothing published: the entry list is all that can be shown.
        $card['state'] = self::STATE_NONE;
        $card['label'] = 'Not Published';
        $card['teams'] = array_map(fn($t) => [
            'boat'  => (string)$t['boat_name'],
            'club'  => (string)$t['club_name'],
            'code'  => (string)($t['short_code'] ?? ''),
            'image' => (string)($t['entry_image'] ?? ''),
        ], EventRace::entries($raceId));
        return $card;
    }

    /** Latest published round of a race, whichever it is. */
    private static function latestPublishedRound(int $raceId): ?array
    {
        foreach (array_reverse(Round::forRace($raceId)) as $round) {
            if ($round['status'] === 'published') return $round;
        }
        return null;
    }

    /** Placed boats of a published round, 1st onward. */
    private static function places(int $roundId): array
    {
        $out = [];
        foreach (Heat::forRound($roundId) as $heat) {
            foreach (LaneAllocation::forHeat((int)$heat['id']) as $lane) {
                if ($lane['position'] === null || ($lane['outcome'] ?? 'ok') !== 'ok') continue;
                $out[] = [
                    'pos'   => (int)$lane['position'],
                    'boat'  => (string)$lane['boat_name'],
                    'club'  => (string)$lane['club_name'],
                    'time'  => (string)($lane['race_time'] ?? ''),
                    'lane'  => (int)$lane['lane_no'],
                ];
            }
        }
        usort($out, fn($a, $b) => $a['pos'] <=> $b['pos']);
        return $out;
    }

    /** Boats carried forward from a published round. */
    private static function qualified(int $roundId): array
    {
        return array_map(fn($q) => [
            'boat' => (string)$q['boat_name'],
            'club' => (string)$q['club_name'],
            'pos'  => (int)($q['position'] ?? 0),
            'time' => (string)($q['race_time'] ?? ''),
            'heat' => (int)($q['heat_no'] ?? 0),
        ], Result::qualifiersForRound($roundId));
    }

    // ── Writing ──────────────────────────────────────────────────────────────

    /**
     * Write via a temporary file in the SAME directory, then rename over the
     * target. rename() is atomic within a filesystem, so a reader gets either
     * the whole old file or the whole new one — never a partial response.
     */
    private static function atomicWrite(string $path, string $contents): void
    {
        $tmp = dirname($path) . '/.tmp-' . bin2hex(random_bytes(6));

        $handle = @fopen($tmp, 'wb');
        if ($handle === false) throw new \RuntimeException('Could not open a temporary file for writing.');

        try {
            if (@fwrite($handle, $contents) !== strlen($contents)) {
                throw new \RuntimeException('Could not write the snapshot in full.');
            }
            @fflush($handle);
            // Durable on disk before it becomes visible under its real name.
            if (function_exists('fsync')) @fsync($handle);
        } finally {
            @fclose($handle);
        }

        // Permissions must be right BEFORE the rename, or a reader can catch
        // the file in the instant between appearing and becoming readable.
        @chmod($tmp, 0644);

        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException('Could not publish the snapshot.');
        }
    }

    /** One past the highest version already on disk. */
    private static function nextVersion(string $dir): int
    {
        $manifest = $dir . '/manifest.json';
        if (is_file($manifest)) {
            $current = json_decode((string)@file_get_contents($manifest), true);
            if (is_array($current) && isset($current['version'])) {
                return (int)$current['version'] + 1;
            }
        }
        // No manifest: start above anything already lying about, so a stale
        // cached payload name can never be reused for different content.
        $highest = 0;
        foreach (glob($dir . '/results-*.json') ?: [] as $file) {
            if (preg_match('/results-(\d+)\.json$/', $file, $m)) {
                $highest = max($highest, (int)$m[1]);
            }
        }
        return $highest + 1;
    }

    /** Keep a few old payloads so in-flight requests still resolve. */
    private static function pruneOldVersions(string $dir, int $current): void
    {
        foreach (glob($dir . '/results-*.json') ?: [] as $file) {
            if (!preg_match('/results-(\d+)\.json$/', $file, $m)) continue;
            if ((int)$m[1] <= $current - self::KEEP_VERSIONS) @unlink($file);
        }
        // Clear any temp file left behind by an interrupted publish.
        foreach (glob($dir . '/.tmp-*') ?: [] as $stale) {
            if (@filemtime($stale) < time() - 300) @unlink($stale);
        }
    }

    /** The page itself and its caching rules — static, refreshed each publish. */
    private static function writeStaticAssets(string $dir, string $code): void
    {
        $page = (string)@file_get_contents(APP_ROOT . '/views/public/live-template.html');
        if ($page !== '') {
            self::atomicWrite($dir . '/index.html', str_replace('__EVENT_CODE__', $code, $page));
        }

        // Immutable payloads may be cached forever; the manifest is the only
        // thing a client revalidates, and it is tiny.
        self::atomicWrite($dir . '/.htaccess', <<<HTA
            # Written by Services\ResultSnapshot — edits here are overwritten.
            Options -Indexes

            # Grant these explicitly. A deny rule in a parent directory —
            # "never serve .json", say — would otherwise 403 the payload and
            # leave the page loading but empty, with nothing to show why.
            <FilesMatch "^(manifest\.json|results-\d+\.json|index\.html)$">
              Require all granted
            </FilesMatch>

            <IfModule mod_headers.c>
              # Versioned payloads never change under their own name.
              <FilesMatch "^results-\d+\.json$">
                Header set Cache-Control "public, max-age=31536000, immutable"
              </FilesMatch>
              # The manifest is the poll target: cheap to revalidate, short-lived.
              <FilesMatch "^manifest\.json$">
                Header set Cache-Control "public, max-age=5, stale-while-revalidate=30"
              </FilesMatch>
              <FilesMatch "^index\.html$">
                Header set Cache-Control "public, max-age=60"
              </FilesMatch>
            </IfModule>

            <IfModule mod_deflate.c>
              AddOutputFilterByType DEFLATE application/json text/html
            </IfModule>

            <IfModule mod_mime.c>
              AddType application/json .json
            </IfModule>
            HTA);
    }

    /** Event codes are our own, but this path is written to disk — be strict. */
    private static function safeCode(string $code): string
    {
        $clean = preg_replace('/[^A-Za-z0-9_-]/', '', strtoupper(trim($code)));
        if ($clean === '' || $clean === null) {
            throw new \RuntimeException('That event has no usable Event Code.');
        }
        return $clean;
    }
}
