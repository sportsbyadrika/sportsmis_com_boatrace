<?php
namespace Controllers;

use Core\{Pdf, FileUpload};
use Models\{EventRace, TeamRegistration, AppSetting, Round};

/**
 * Event Admin -> Order of Events (the programme).
 *
 * Each item carries a serial number, its scheduled date and time, and the
 * call-room status that walks Scheduled -> In Progress -> Finished ->
 * Result Published -> Medal Ceremony on race day. The list is sortable and
 * filterable client-side; the printable programme is rendered both as a
 * browser print view and as a Dompdf download, "all" or date-wise.
 */
class EventAdminRaceController extends EventAdminBase
{
    private function raceOr404(string $hash): array
    {
        $race = EventRace::findForEvent($this->eventId(), hid_race_decode($hash));
        if (!$race) $this->abort(404);
        return $race;
    }

    // ── Programme ────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->boot();
        $date   = trim((string)($_GET['date'] ?? ''));
        $status = trim((string)($_GET['status'] ?? ''));

        $this->view('event-admin/order-of-events/index', [
            'pageTitle' => 'Order of Events',
            'races'     => EventRace::forEvent($this->eventId(), $date, $status),
            'dates'     => EventRace::datesForEvent($this->eventId()),
            'date'      => $date,
            'status'    => $status,
            'nextSerial'=> EventRace::nextSerial($this->eventId()),
        ]);
    }

    public function store(): void
    {
        $this->boot();
        $this->verifyCsrf();

        $errors = $this->validate([
            'name'      => 'required|max:200',
            'race_date' => 'date',
            'race_time' => 'time',
        ]);
        if ($errors) $this->redirect('/event-admin/order-of-events', 'Please correct the highlighted fields.', 'error');

        $data = $this->collect();
        $data['event_id'] = $this->eventId();
        if ((int)$data['sl_no'] <= 0) $data['sl_no'] = EventRace::nextSerial($this->eventId());

        EventRace::create($data);
        $this->redirect('/event-admin/order-of-events', 'Race added to the programme.');
    }

    public function update(string $hash): void
    {
        $this->boot();
        $this->verifyCsrf();
        $race = $this->raceOr404($hash);

        if (trim((string)($_POST['name'] ?? '')) === '') {
            $this->redirect('/event-admin/order-of-events', 'Race name is required.', 'error');
        }

        EventRace::updateById((int)$race['id'], $this->collect());
        $this->redirect('/event-admin/order-of-events', 'Race updated.');
    }

    public function destroy(string $hash): void
    {
        $this->boot();
        $this->verifyCsrf();
        $race = $this->raceOr404($hash);

        // A race with rounds owns heats, lane draws and results underneath —
        // insist those are cleared first rather than cascading silently.
        if (\Models\Round::countForRace((int)$race['id']) > 0) {
            $this->redirect('/event-admin/order-of-events',
                'This race already has rounds. Delete its rounds in the race office before removing it.', 'error');
        }

        EventRace::deleteById((int)$race['id']);
        $this->redirect('/event-admin/order-of-events', 'Race removed from the programme.');
    }

    /** Inline status change from the programme list — AJAX, no page reload. */
    public function setStatus(string $hash): void
    {
        $this->boot();
        $this->verifyCsrf();
        $race   = $this->raceOr404($hash);
        $status = (string)($_POST['status'] ?? '');

        if (!array_key_exists($status, EventRace::STATUSES)) {
            $this->json(['success' => false, 'message' => 'Unknown status.'], 422);
        }
        EventRace::updateById((int)$race['id'], ['status' => $status]);
        $this->json([
            'success' => true,
            'message' => 'Status set to ' . EventRace::STATUSES[$status] . '.',
            'badge'   => raceStatusBadge($status),
        ]);
    }

    /** Renumber 1..n in the current running order (date, time, serial). */
    public function resequence(): void
    {
        $this->boot();
        $this->verifyCsrf();

        EventRace::resequence($this->eventId());
        $this->redirect('/event-admin/order-of-events', 'Serial numbers renumbered in running order.');
    }

    // ── Race entries ─────────────────────────────────────────────────────────

    public function entries(string $hash): void
    {
        $this->boot();
        $race = $this->raceOr404($hash);

        $this->view('event-admin/order-of-events/entries', [
            'pageTitle' => 'Entries — ' . $race['name'],
            'race'      => $race,
            'approved'  => TeamRegistration::forEvent($this->eventId(), 'approved'),
            'entered'   => EventRace::entryRegistrationIds((int)$race['id']),
            'entryMap'  => EventRace::entryMap((int)$race['id']),
        ]);
    }

    public function saveEntries(string $hash): void
    {
        $this->boot();
        $this->verifyCsrf();
        $race = $this->raceOr404($hash);

        $n = EventRace::setEntries($this->eventId(), (int)$race['id'], (array)($_POST['registrations'] ?? []));
        $this->redirect('/event-admin/order-of-events/' . hid_race((int)$race['id']) . '/entries',
            "Saved — {$n} boat" . ($n === 1 ? '' : 's') . ' entered in this race.');
    }

    /**
     * Upload the photo of one boat AS IT RACES HERE. Kept on the race entry
     * rather than the team, because the same club may field a different boat
     * in each race — teams.logo remains the club crest.
     */
    public function entryImage(string $hash, string $entryHash): void
    {
        $this->boot();
        $this->verifyCsrf();
        $race = $this->raceOr404($hash);
        $back = '/event-admin/order-of-events/' . hid_race((int)$race['id']) . '/entries';

        $entry = EventRace::findEntry((int)$race['id'], hid_entry_decode($entryHash));
        if (!$entry) $this->abort(404);

        if (empty($_FILES['image']['name'])) {
            $this->redirect($back, 'Choose an image first.', 'error');
        }
        try {
            $url = (new FileUpload())->upload($_FILES['image'], 'boats', true);
        } catch (\RuntimeException $e) {
            $this->redirect($back, $e->getMessage(), 'error');
        }

        EventRace::setEntryImage((int)$entry['id'], $url);
        $this->redirect($back, 'Boat photo updated.');
    }

    public function entryImageDelete(string $hash, string $entryHash): void
    {
        $this->boot();
        $this->verifyCsrf();
        $race = $this->raceOr404($hash);
        $back = '/event-admin/order-of-events/' . hid_race((int)$race['id']) . '/entries';

        $entry = EventRace::findEntry((int)$race['id'], hid_entry_decode($entryHash));
        if (!$entry) $this->abort(404);

        EventRace::setEntryImage((int)$entry['id'], null);
        $this->redirect($back, 'Boat photo removed.');
    }

    /** The picture shown on this race's public card. */
    public function raceImage(string $hash): void
    {
        $this->boot();
        $this->verifyCsrf();
        $race = $this->raceOr404($hash);
        $back = '/event-admin/order-of-events';

        if (empty($_FILES['image']['name'])) {
            $this->redirect($back, 'Choose an image first.', 'error');
        }
        try {
            $url = (new FileUpload())->upload($_FILES['image'], 'races', true);
        } catch (\RuntimeException $e) {
            $this->redirect($back, $e->getMessage(), 'error');
        }

        EventRace::updateById((int)$race['id'], ['image' => $url]);
        $this->redirect($back, 'Race image updated.');
    }

    public function raceImageDelete(string $hash): void
    {
        $this->boot();
        $this->verifyCsrf();
        $race = $this->raceOr404($hash);

        EventRace::updateById((int)$race['id'], ['image' => null]);
        $this->redirect('/event-admin/order-of-events', 'Race image removed.');
    }

    // ── Round schedule ───────────────────────────────────────────────────────
    // A race carries one slot; its rounds may each take their own, so the
    // preliminary heats, the semi-finals and the final can run at different
    // times or on different days. Blank inherits the race.

    public function schedule(string $hash): void
    {
        $this->boot();
        $race = $this->raceOr404($hash);

        $this->view('event-admin/order-of-events/schedule', [
            'pageTitle'     => 'Schedule — ' . $race['name'],
            'race'          => $race,
            'rounds'        => Round::forRace((int)$race['id']),
            'standard'      => Round::STANDARD_ROUNDS,
            'existingTypes' => Round::existingTypes((int)$race['id']),
        ]);
    }

    public function saveSchedule(string $hash): void
    {
        $this->boot();
        $this->verifyCsrf();
        $race = $this->raceOr404($hash);
        $back = '/event-admin/order-of-events/' . hid_race((int)$race['id']) . '/schedule';

        $dates = (array)($_POST['date'] ?? []);
        $times = (array)($_POST['time'] ?? []);

        // The form posts hashed round ids; resolve them back before writing.
        $schedules = [];
        foreach ($dates as $roundHash => $date) {
            $id = hid_round_decode((string)$roundHash);
            if ($id <= 0) continue;
            $schedules[$id] = ['date' => (string)$date, 'time' => (string)($times[$roundHash] ?? '')];
        }

        Round::updateSchedules((int)$race['id'], $schedules);
        $this->redirect($back, 'Round schedule saved.');
    }

    /**
     * Give a race exactly the rounds it runs. Not every race has the full
     * ladder — many are preliminary heats plus a final, and some are a final
     * on its own — so the rounds are ticked rather than seeded wholesale.
     *
     * Lane counts and heats remain the race office's business.
     */
    public function addRounds(string $hash): void
    {
        $this->boot();
        $this->verifyCsrf();
        $race = $this->raceOr404($hash);
        $back = '/event-admin/order-of-events/' . hid_race((int)$race['id']) . '/schedule';

        $types = array_values(array_intersect(
            (array)($_POST['types'] ?? []),
            array_keys(Round::STANDARD_ROUNDS)
        ));
        if (!$types) {
            $this->redirect($back, 'Tick at least one round to add.', 'error');
        }

        $added = Round::addStandard($this->eventId(), $race, $types);
        $this->redirect($back, $added
            ? "Added {$added} round" . ($added === 1 ? '' : 's') . ' — give each one its date and time below.'
            : 'Those rounds are already on this race.');
    }

    /**
     * Remove a round from a race, with everything underneath it.
     *
     * A locked or published round is refused outright: its results are
     * already out, and unlocking is a race-office decision. Anything else may
     * go, but the confirmation on the button spells out what goes with it.
     */
    public function destroyRound(string $hash, string $roundHash): void
    {
        $this->boot();
        $this->verifyCsrf();
        $race = $this->raceOr404($hash);
        $back = '/event-admin/order-of-events/' . hid_race((int)$race['id']) . '/schedule';

        $round = Round::findForEvent($this->eventId(), hid_round_decode($roundHash));
        if (!$round || (int)$round['event_race_id'] !== (int)$race['id']) $this->abort(404);

        if (Round::isFrozen($round)) {
            $this->redirect($back,
                'This round is ' . $round['status'] . '. The race office must unlock it before it can be removed.',
                'error');
        }

        $impact = Round::deletionImpact((int)$round['id']);
        Round::deleteById((int)$round['id']);
        Round::resequenceByLadder((int)$race['id']);

        $lost = [];
        if ($impact['heats'])   $lost[] = $impact['heats'] . ' heat' . ($impact['heats'] === 1 ? '' : 's');
        if ($impact['lanes'])   $lost[] = $impact['lanes'] . ' lane allocation' . ($impact['lanes'] === 1 ? '' : 's');
        if ($impact['results']) $lost[] = $impact['results'] . ' result' . ($impact['results'] === 1 ? '' : 's');

        $this->redirect($back, 'Removed ' . $round['name']
            . ($lost ? ', along with ' . implode(', ', $lost) . '.' : '.'));
    }

    // ── Printable programme ──────────────────────────────────────────────────

    /** Browser print view (A4). ?date=YYYY-MM-DD for one day, else all. */
    public function programmePrint(): void
    {
        $this->boot();
        $date = trim((string)($_GET['date'] ?? ''));

        $this->renderWith('print', 'event-admin/order-of-events/programme', [
            'pageTitle' => 'Order of Events — ' . $this->event['name'],
            'event'     => $this->event,
            'groups'    => $this->programmeGroups($date),
            'footer'    => AppSetting::get('programme_footer', 'Powered by SportsMIS® Regatta'),
        ]);
    }

    /** The same programme as a downloadable PDF. */
    public function programmePdf(): void
    {
        $this->boot();
        $date = trim((string)($_GET['date'] ?? ''));

        $html = $this->renderToString('event-admin/order-of-events/programme-pdf', [
            'event'  => $this->event,
            'groups' => $this->programmeGroups($date),
            'logo'   => Pdf::imageDataUri($this->event['image'] ?? ''),
            'footer' => AppSetting::get('programme_footer', 'Powered by SportsMIS® Regatta'),
        ]);

        $name = 'order-of-events-' . ($date !== '' ? $date : 'all') . '.pdf';
        Pdf::stream($html, $name, 'A4', 'portrait', true);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Programme rows grouped by race date, so both printed forms can render
     * a heading per day. An unscheduled race is grouped under ''.
     */
    private function programmeGroups(string $date): array
    {
        $groups = [];
        foreach (EventRace::forEvent($this->eventId(), $date) as $race) {
            // Printed under the race when its rounds run at their own times.
            $race['round_schedule'] = Round::scheduleSummary((int)$race['id'], $race);
            $key = (string)($race['race_date'] ?? '');
            $groups[$key][] = $race;
        }
        ksort($groups);
        return $groups;
    }

    /** Capture a view's output instead of echoing it (for the PDF body). */
    private function renderToString(string $view, array $data): string
    {
        extract($data);
        ob_start();
        require APP_ROOT . "/views/{$view}.php";
        return (string)ob_get_clean();
    }

    private function collect(): array
    {
        $gender = (string)($_POST['gender'] ?? 'open');
        $status = (string)($_POST['status'] ?? 'scheduled');
        $lanes  = (int)($_POST['lane_count'] ?? $this->event['default_lanes']);

        return [
            'sl_no'         => max(0, (int)($_POST['sl_no'] ?? 0)),
            'code'          => strtoupper(trim((string)($_POST['code'] ?? ''))) ?: null,
            'name'          => trim((string)($_POST['name'] ?? '')),
            'name_regional' => trim((string)($_POST['name_regional'] ?? '')) ?: null,
            'boat_class'    => trim((string)($_POST['boat_class'] ?? '')) ?: null,
            'category'      => trim((string)($_POST['category'] ?? '')) ?: null,
            'gender'        => array_key_exists($gender, EventRace::GENDERS) ? $gender : 'open',
            'distance_m'    => ($d = (int)($_POST['distance_m'] ?? 0)) > 0 ? $d : null,
            'lane_count'    => max(2, min(20, $lanes)),
            'race_date'     => trim((string)($_POST['race_date'] ?? '')) ?: null,
            'race_time'     => trim((string)($_POST['race_time'] ?? '')) ?: null,
            'status'        => array_key_exists($status, EventRace::STATUSES) ? $status : 'scheduled',
            'remarks'       => trim((string)($_POST['remarks'] ?? '')) ?: null,
        ];
    }
}
