<?php
namespace Controllers;

use Models\{EventRace, Round, Heat};

/**
 * Race office -> Rounds & Heats (privilege: rounds_heats).
 *
 * A race's rounds are an ordered ladder — Preliminary Heats, Semi-Finals,
 * Final by default, but any of them can be added, renamed or removed. Each
 * round owns its own lane count, and its heats are kept in step with the
 * requested heat count by Heat::syncCount().
 */
class EventUserRoundController extends EventUserBase
{
    private function raceOr404(string $hash): array
    {
        $race = EventRace::findForEvent($this->eventId(), hid_race_decode($hash));
        if (!$race) $this->abort(404);
        return $race;
    }

    private function roundOr404(string $hash): array
    {
        $round = Round::findForEvent($this->eventId(), hid_round_decode($hash));
        if (!$round) $this->abort(404);
        return $round;
    }

    // ── Screens ──────────────────────────────────────────────────────────────

    /** Race picker: every programme item with its round/heat counts. */
    public function index(): void
    {
        $this->boot();
        $this->requirePrivilege('rounds_heats');

        $this->view('event-user/rounds/index', [
            'pageTitle' => 'Rounds & Heats',
            'races'     => EventRace::withRounds($this->eventId()),
        ]);
    }

    /** One race: its ladder of rounds, each with its heats. */
    public function show(string $hash): void
    {
        $this->boot();
        $this->requirePrivilege('rounds_heats');
        $race = $this->raceOr404($hash);

        $rounds = Round::forRace((int)$race['id']);
        foreach ($rounds as &$r) {
            $r['heats'] = Heat::forRound((int)$r['id']);
        }
        unset($r);

        $this->view('event-user/rounds/show', [
            'pageTitle'   => 'Rounds — ' . $race['name'],
            'race'        => $race,
            'rounds'      => $rounds,
            'entryCount'  => count(EventRace::entryRegistrationIds((int)$race['id'])),
        ]);
    }

    // ── Rounds ───────────────────────────────────────────────────────────────

    /** Create the default Prelim / Semi / Final ladder in one click. */
    public function seedLadder(string $hash): void
    {
        $this->boot();
        $this->requirePrivilege('rounds_heats');
        $this->verifyCsrf();
        $race = $this->raceOr404($hash);
        $back = '/event-user/rounds/' . hid_race((int)$race['id']);

        if (Round::countForRace((int)$race['id']) > 0) {
            $this->redirect($back, 'This race already has rounds.', 'error');
        }

        $order = 1;
        foreach (Round::DEFAULT_LADDER as $spec) {
            Round::create([
                'event_id'         => $this->eventId(),
                'event_race_id'    => (int)$race['id'],
                'name'             => $spec['name'],
                'round_type'       => $spec['round_type'],
                'sort_order'       => $order++,
                'lane_count'       => (int)$race['lane_count'],
                'qualify_per_heat' => $spec['qualify_per_heat'],
                'status'           => 'draft',
            ]);
        }
        $this->redirect($back, 'Default ladder created — Preliminary Heats, Semi-Finals and Final.');
    }

    public function storeRound(string $hash): void
    {
        $this->boot();
        $this->requirePrivilege('rounds_heats');
        $this->verifyCsrf();
        $race = $this->raceOr404($hash);
        $back = '/event-user/rounds/' . hid_race((int)$race['id']);

        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') $this->redirect($back, 'Round name is required.', 'error');

        $roundId = Round::create([
            'event_id'         => $this->eventId(),
            'event_race_id'    => (int)$race['id'],
            'name'             => $name,
            'round_type'       => $this->roundType((string)($_POST['round_type'] ?? '')),
            'sort_order'       => Round::nextSortOrder((int)$race['id']),
            'lane_count'       => $this->lanes((int)($_POST['lane_count'] ?? $race['lane_count'])),
            'qualify_per_heat' => max(0, min(20, (int)($_POST['qualify_per_heat'] ?? 2))),
            'scheduled_date'   => trim((string)($_POST['scheduled_date'] ?? '')) ?: null,
            'scheduled_time'   => trim((string)($_POST['scheduled_time'] ?? '')) ?: null,
            'status'           => 'draft',
        ]);

        $heats = max(0, (int)($_POST['heat_count'] ?? 0));
        if ($heats > 0) Heat::syncCount($this->eventId(), $roundId, $heats);

        $this->redirect($back, 'Round added.');
    }

    public function updateRound(string $hash): void
    {
        $this->boot();
        $this->requirePrivilege('rounds_heats');
        $this->verifyCsrf();
        $round = $this->roundOr404($hash);
        $back  = '/event-user/rounds/' . hid_race((int)$round['event_race_id']);

        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') $this->redirect($back, 'Round name is required.', 'error');

        // Narrowing the lane count would strand boats already drawn into the
        // lanes being removed — refuse rather than silently dropping them.
        $newLanes = $this->lanes((int)($_POST['lane_count'] ?? $round['lane_count']));
        $maxLane  = \Models\LaneAllocation::maxLaneUsed((int)$round['id']);
        if ($newLanes < $maxLane) {
            $this->redirect($back,
                "Lane {$maxLane} is already allocated in this round — clear it before reducing the lane count to {$newLanes}.",
                'error');
        }

        Round::updateById((int)$round['id'], [
            'name'             => $name,
            'round_type'       => $this->roundType((string)($_POST['round_type'] ?? $round['round_type'])),
            'lane_count'       => $newLanes,
            'qualify_per_heat' => max(0, min(20, (int)($_POST['qualify_per_heat'] ?? $round['qualify_per_heat']))),
            'sort_order'       => max(1, min(50, (int)($_POST['sort_order'] ?? $round['sort_order']))),
            // Blank clears the override, so the round inherits its race again.
            'scheduled_date'   => trim((string)($_POST['scheduled_date'] ?? '')) ?: null,
            'scheduled_time'   => trim((string)($_POST['scheduled_time'] ?? '')) ?: null,
        ]);
        $this->redirect($back, 'Round updated.');
    }

    public function destroyRound(string $hash): void
    {
        $this->boot();
        $this->requirePrivilege('rounds_heats');
        $this->verifyCsrf();
        $round = $this->roundOr404($hash);
        $back  = '/event-user/rounds/' . hid_race((int)$round['event_race_id']);

        if (Round::isFrozen($round)) {
            $this->redirect($back, 'A locked or published round cannot be deleted. Unlock it first.', 'error');
        }
        Round::deleteById((int)$round['id']);
        $this->redirect($back, 'Round deleted, along with its heats and lane draw.');
    }

    // ── Heats ────────────────────────────────────────────────────────────────

    /** Set how many heats this round runs. Drawn heats are never removed. */
    public function setHeats(string $hash): void
    {
        $this->boot();
        $this->requirePrivilege('rounds_heats');
        $this->verifyCsrf();
        $round = $this->roundOr404($hash);
        $back  = '/event-user/rounds/' . hid_race((int)$round['event_race_id']);

        if (Round::isFrozen($round)) {
            $this->redirect($back, 'This round is locked — unlock it before changing its heats.', 'error');
        }

        $requested = max(1, min(40, (int)($_POST['heat_count'] ?? 1)));
        $actual    = Heat::syncCount($this->eventId(), (int)$round['id'], $requested);

        $message = $actual === $requested
            ? "Round now runs {$actual} heat" . ($actual === 1 ? '.' : 's.')
            : "Kept {$actual} heats — the extra ones already hold a lane draw and were not removed.";
        $this->redirect($back, $message, $actual === $requested ? 'success' : 'warning');
    }

    /** Rename a heat or give it its own date/time. */
    public function updateHeat(string $hash): void
    {
        $this->boot();
        $this->requirePrivilege('rounds_heats');
        $this->verifyCsrf();

        $heat = Heat::findForEvent($this->eventId(), hid_heat_decode($hash));
        if (!$heat) $this->abort(404);
        $round = Round::findForEvent($this->eventId(), (int)$heat['round_id']);

        Heat::updateById((int)$heat['id'], [
            'name'           => trim((string)($_POST['name'] ?? '')) ?: ('Heat ' . (int)$heat['heat_no']),
            'scheduled_date' => trim((string)($_POST['scheduled_date'] ?? '')) ?: null,
            'scheduled_time' => trim((string)($_POST['scheduled_time'] ?? '')) ?: null,
        ]);

        $this->redirect('/event-user/rounds/' . hid_race((int)($round['event_race_id'] ?? 0)), 'Heat updated.');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function roundType(string $type): string
    {
        return array_key_exists($type, Round::TYPES) ? $type : 'other';
    }

    private function lanes(int $n): int
    {
        return max(2, min(20, $n));
    }
}
