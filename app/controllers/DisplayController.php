<?php
namespace Controllers;

use Core\Controller;
use Models\{Schema, Event, Round, Result};

/**
 * The two public display screens. Both live on the chrome-free `public`
 * layout and are addressed by the event's hashed id, so a venue machine can
 * be pointed at a URL once and left alone:
 *
 *   /display/{hash}/wall    big TV / LED wall — auto-rotating result deck
 *   /display/{hash}/stream  chroma-key overlay for a live YouTube feed
 *
 * Neither needs an app session. When the event carries a display PIN, the
 * operator enters it once and the grant is remembered per event in
 * $_SESSION['display_pins']; without a PIN both screens are open, which is
 * what a public results board is meant to be.
 *
 * Only PUBLISHED rounds are ever read, so nothing provisional can reach a
 * screen the crowd is watching.
 */
class DisplayController extends Controller
{
    private function boot(): void
    {
        try { Schema::ensureAll(); } catch (\Throwable $e) {}
    }

    private function eventOr404(string $hash): array
    {
        $event = Event::findById(hid_event_decode($hash));
        if (!$event) $this->abort(404);
        $event['code'] = $event['code'] ?: ensureEventCode((int)$event['id']);
        return $event;
    }

    /** Has this browser cleared the event's PIN (or is there none)? */
    private function unlocked(array $event): bool
    {
        $pin = trim((string)($event['display_pin'] ?? ''));
        if ($pin === '') return true;
        return in_array((int)$event['id'], $_SESSION['display_pins'] ?? [], true);
    }

    // ── Operator sign-in ─────────────────────────────────────────────────────

    /** Landing page: pick a screen by Event Code, PIN if the event sets one. */
    public function entry(): void
    {
        $this->boot();
        $this->renderWith('public', 'displays/entry', [
            'pageTitle' => 'Display Screens — SportsMIS® Regatta',
            'bodyClass' => 'sms-body',
        ]);
    }

    public function open(): void
    {
        $this->boot();
        $this->verifyCsrf();

        $code   = strtoupper(trim((string)($_POST['event_code'] ?? '')));
        $pin    = trim((string)($_POST['display_pin'] ?? ''));
        $screen = (string)($_POST['screen'] ?? 'wall') === 'stream' ? 'stream' : 'wall';

        $event = Event::findByCode($code);
        if (!$event) $this->redirect('/display', 'No event has that Event Code.', 'error');

        $required = trim((string)($event['display_pin'] ?? ''));
        if ($required !== '' && !hash_equals($required, $pin)) {
            $this->redirect('/display', 'That PIN is not correct for this event.', 'error');
        }

        // Remember the grant per event, so a reload doesn't re-prompt.
        $_SESSION['display_pins'] = array_values(array_unique(
            array_merge($_SESSION['display_pins'] ?? [], [(int)$event['id']])
        ));

        $this->redirect('/display/' . hid_event((int)$event['id']) . '/' . $screen);
    }

    // ── Screen 1: big TV / LED wall ──────────────────────────────────────────

    public function wall(string $hash): void
    {
        $this->boot();
        $event = $this->eventOr404($hash);
        if (!$this->unlocked($event)) {
            $this->redirect('/display', 'Enter the operator PIN to open this event\'s screens.', 'warning');
        }

        $this->renderWith('public', 'displays/wall', [
            'pageTitle' => 'LED Wall — ' . $event['name'],
            'event'     => $event,
            'slides'    => $this->deck($event),
            'interval'  => max(3, min(60, (int)$event['slide_seconds'])),
            'refresh'   => (int)config('display.refresh_seconds', 60),
        ]);
    }

    /**
     * JSON feed for the wall so it can refresh its deck without reloading
     * the whole page (the page still hard-refreshes on a slow cadence as a
     * backstop against a stale tab left running all day).
     */
    public function wallFeed(string $hash): void
    {
        $this->boot();
        $event = $this->eventOr404($hash);
        if (!$this->unlocked($event)) $this->json(['success' => false, 'message' => 'Locked.'], 403);

        $this->json(['success' => true, 'slides' => $this->deck($event)]);
    }

    // ── Screen 2: YouTube chroma-key overlay ─────────────────────────────────

    public function stream(string $hash): void
    {
        $this->boot();
        $event = $this->eventOr404($hash);
        if (!$this->unlocked($event)) {
            $this->redirect('/display', 'Enter the operator PIN to open this event\'s screens.', 'warning');
        }

        // The operator picks what is on air; ?chroma= overrides the stored
        // colour for a session where the mixer keys a different green.
        $chroma = trim((string)($_GET['chroma'] ?? ''));
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $chroma)) $chroma = (string)$event['chroma_color'];

        $published = Result::publishedRounds((int)$event['id'], 50);
        $selected  = null;
        $roundHash = trim((string)($_GET['round'] ?? ''));
        if ($roundHash !== '') {
            $selected = Round::findWithRace((int)$event['id'], hid_round_decode($roundHash));
            if ($selected && $selected['status'] !== 'published') $selected = null;
        }
        if (!$selected && $published) {
            $selected = Round::findWithRace((int)$event['id'], (int)$published[0]['id']);
        }

        $heatHash = trim((string)($_GET['heat'] ?? ''));
        $heats    = $selected ? Result::heatSheet((int)$selected['id']) : [];
        $heat     = null;
        if ($heatHash !== '') {
            $wanted = hid_heat_decode($heatHash);
            foreach ($heats as $h) { if ((int)$h['id'] === $wanted) { $heat = $h; break; } }
        }

        $this->renderWith('public', 'displays/stream', [
            'pageTitle' => 'Stream Overlay — ' . $event['name'],
            'bodyStyle' => 'background:' . $chroma . ';margin:0',
            'event'     => $event,
            'chroma'    => $chroma,
            'published' => $published,
            'round'     => $selected,
            'heats'     => $heats,
            'heat'      => $heat,
            'rankList'  => Result::rankListForEvent((int)$event['id']),
            'showChrome'=> ($_GET['chrome'] ?? '1') !== '0',
        ]);
    }

    // ── Deck building ────────────────────────────────────────────────────────

    /**
     * The LED wall's slide deck, in the order it rotates:
     *   1. an event title card
     *   2. one card per published round (its heats, lanes and times)
     *   3. the event rank list, in pages of six races
     *   4. the club tally, when anything has been published
     *
     * Every slide is a plain array so the same structure serves the initial
     * render and the JSON refresh feed.
     */
    private function deck(array $event): array
    {
        $eventId = (int)$event['id'];
        $slides  = [[
            'type'     => 'title',
            'title'    => $event['name'],
            'subtitle' => (string)($event['name_regional'] ?? ''),
            'meta'     => trim(implode(' · ', array_filter([
                formatDate($event['start_date']) . ' – ' . formatDate($event['end_date']),
                (string)($event['venue'] ?? ''),
            ]))),
            'image'    => (string)($event['image'] ?? ''),
        ]];

        foreach (Result::publishedRounds($eventId, 12) as $round) {
            $heats = [];
            foreach (Result::heatSheet((int)$round['id']) as $h) {
                $lanes = array_values(array_filter($h['lanes'], fn($l) => $l['position'] !== null));
                usort($lanes, fn($a, $b) => (int)$a['position'] <=> (int)$b['position']);
                if (!$lanes) continue;
                $heats[] = [
                    'name'  => \Models\Heat::label($h),
                    'lanes' => array_map(fn($l) => [
                        'position' => (int)$l['position'],
                        'lane'     => (int)$l['lane_no'],
                        'boat'     => (string)$l['boat_name'],
                        'club'     => (string)$l['club_name'],
                        'time'     => (string)($l['race_time'] ?? ''),
                    ], array_slice($lanes, 0, 6)),
                ];
            }
            if (!$heats) continue;

            $slides[] = [
                'type'     => 'round',
                'title'    => (int)$round['race_sl_no'] . '. ' . $round['race_name'],
                'subtitle' => (string)($round['race_name_regional'] ?? ''),
                'meta'     => $round['name']
                            . ($round['distance_m'] ? ' · ' . (int)$round['distance_m'] . ' m' : ''),
                'heats'    => $heats,
            ];
        }

        $ranked = array_values(array_filter(Result::rankListForEvent($eventId), fn($r) => !empty($r['places'])));
        foreach (array_chunk($ranked, 6) as $page => $chunk) {
            $slides[] = [
                'type'  => 'ranklist',
                'title' => 'Rank List',
                'meta'  => count($ranked) > 6 ? 'Page ' . ($page + 1) : '',
                'races' => array_map(fn($r) => [
                    'sl'     => (int)$r['race']['sl_no'],
                    'name'   => (string)$r['race']['name'],
                    'places' => array_map(fn($p) => [
                        'position' => (int)$p['position'],
                        'boat'     => (string)$p['boat_name'],
                        'club'     => (string)$p['club_name'],
                        'time'     => (string)($p['race_time'] ?? ''),
                    ], $r['places']),
                ], $chunk),
            ];
        }

        $tally = Result::medalTally($eventId);
        if ($tally) {
            $slides[] = [
                'type'  => 'tally',
                'title' => 'Club Tally',
                'meta'  => '3 – 2 – 1 points',
                'clubs' => array_map(fn($t) => [
                    'club'   => (string)$t['club_name'],
                    'gold'   => (int)$t['gold'],
                    'silver' => (int)$t['silver'],
                    'bronze' => (int)$t['bronze'],
                    'points' => (int)$t['points'],
                ], array_slice($tally, 0, 12)),
            ];
        }

        return $slides;
    }
}
