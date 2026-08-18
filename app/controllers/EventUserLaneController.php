<?php
namespace Controllers;

use Models\{EventRace, Round, Heat, LaneAllocation, Result, TeamRegistration};

/**
 * Race office -> Lane Allocation (privilege: lane_allocation).
 *
 * The board shows one round at a time: its heats down the left, the lanes of
 * the selected heat in the middle, and the pool of boats still to be drawn on
 * the right. Drag (or click-then-click) writes through the AJAX endpoints
 * below; every one of them re-checks the round is not frozen and that the
 * boat is eligible, so the UI can never talk the server into an invalid draw.
 *
 * The pool for the FIRST round of a race is its approved race entries. For
 * every later round it is the qualifiers carried forward from the round
 * before, which is what makes the ladder work.
 */
class EventUserLaneController extends EventUserBase
{
    private function roundOr404(string $hash): array
    {
        $round = Round::findWithRace($this->eventId(), hid_round_decode($hash));
        if (!$round) $this->abort(404);
        return $round;
    }

    /** JSON guard shared by the mutating endpoints. */
    private function assertEditable(array $round): void
    {
        if (Round::isFrozen($round)) {
            $this->json([
                'success' => false,
                'message' => 'This round is ' . $round['status'] . ' — unlock it before changing the draw.',
            ], 422);
        }
    }

    // ── Board ────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->boot();
        $this->requirePrivilege('lane_allocation');

        $roundHash = trim((string)($_GET['round'] ?? ''));
        $round     = $roundHash !== '' ? Round::findWithRace($this->eventId(), hid_round_decode($roundHash)) : null;

        $data = [
            'pageTitle' => 'Lane Allocation',
            'rounds'    => Round::forEvent($this->eventId()),
            'round'     => $round,
            'heats'     => [],
            'lanes'     => [],
            'pool'      => [],
            'poolNote'  => '',
        ];

        if ($round) {
            $heats = Heat::forRound((int)$round['id']);

            // Lane grid per heat: lane_no => allocation|null, so the view can
            // draw every lane whether or not it is filled.
            $byHeat = [];
            foreach ($heats as $h) $byHeat[(int)$h['id']] = [];
            foreach (LaneAllocation::forRound((int)$round['id']) as $la) {
                $byHeat[(int)$la['heat_id']][(int)$la['lane_no']] = $la;
            }

            [$pool, $note] = $this->poolFor($round);

            $data['heats']    = $heats;
            $data['lanes']    = $byHeat;
            $data['pool']     = $pool;
            $data['poolNote'] = $note;
        }

        $this->view('event-user/lane-allocation/index', $data);
    }

    // ── Mutations (all AJAX) ─────────────────────────────────────────────────

    /** Place a boat on an empty lane. */
    public function assign(): void
    {
        $this->boot();
        $this->requirePrivilege('lane_allocation');
        $this->verifyCsrf();

        $round = $this->roundOr404((string)($_POST['round'] ?? ''));
        $this->assertEditable($round);

        $heat = Heat::findForEvent($this->eventId(), hid_heat_decode((string)($_POST['heat'] ?? '')));
        if (!$heat || (int)$heat['round_id'] !== (int)$round['id']) {
            $this->json(['success' => false, 'message' => 'That heat is not part of this round.'], 422);
        }

        $lane = (int)($_POST['lane'] ?? 0);
        if ($lane < 1 || $lane > (int)$round['lane_count']) {
            $this->json(['success' => false, 'message' => 'That lane number is outside this round.'], 422);
        }

        $regId = (int)($_POST['registration'] ?? 0);
        if (!$this->isEligible($round, $regId)) {
            $this->json(['success' => false, 'message' => 'That boat is not eligible for this round.'], 422);
        }
        if (LaneAllocation::findByHeatLane((int)$heat['id'], $lane)) {
            $this->json(['success' => false, 'message' => 'That lane is already taken.'], 409);
        }
        if (LaneAllocation::findByRoundTeam((int)$round['id'], $regId)) {
            $this->json(['success' => false, 'message' => 'That boat already has a lane in this round.'], 409);
        }

        $id = LaneAllocation::create([
            'event_id'             => $this->eventId(),
            'round_id'             => (int)$round['id'],
            'heat_id'              => (int)$heat['id'],
            'lane_no'              => $lane,
            'team_registration_id' => $regId,
        ]);

        $this->json(['success' => true, 'message' => 'Lane allocated.', 'allocation' => hid_alloc($id)]);
    }

    /** Take a boat off its lane and return it to the pool. */
    public function unassign(): void
    {
        $this->boot();
        $this->requirePrivilege('lane_allocation');
        $this->verifyCsrf();

        $round = $this->roundOr404((string)($_POST['round'] ?? ''));
        $this->assertEditable($round);

        $alloc = LaneAllocation::findById(hid_alloc_decode((string)($_POST['allocation'] ?? '')));
        if (!$alloc || (int)$alloc['round_id'] !== (int)$round['id']) {
            $this->json(['success' => false, 'message' => 'That lane is already empty.'], 422);
        }

        LaneAllocation::deleteById((int)$alloc['id']);
        $this->json(['success' => true, 'message' => 'Lane cleared.']);
    }

    /** Drag an already-drawn boat to another lane; swaps if occupied. */
    public function move(): void
    {
        $this->boot();
        $this->requirePrivilege('lane_allocation');
        $this->verifyCsrf();

        $round = $this->roundOr404((string)($_POST['round'] ?? ''));
        $this->assertEditable($round);

        $alloc = LaneAllocation::findById(hid_alloc_decode((string)($_POST['allocation'] ?? '')));
        if (!$alloc || (int)$alloc['round_id'] !== (int)$round['id']) {
            $this->json(['success' => false, 'message' => 'That boat is no longer on the board.'], 422);
        }

        $heat = Heat::findForEvent($this->eventId(), hid_heat_decode((string)($_POST['heat'] ?? '')));
        if (!$heat || (int)$heat['round_id'] !== (int)$round['id']) {
            $this->json(['success' => false, 'message' => 'That heat is not part of this round.'], 422);
        }

        $lane = (int)($_POST['lane'] ?? 0);
        if ($lane < 1 || $lane > (int)$round['lane_count']) {
            $this->json(['success' => false, 'message' => 'That lane number is outside this round.'], 422);
        }

        $res = LaneAllocation::moveOrSwap((int)$alloc['id'], (int)$heat['id'], $lane);
        $this->json(['success' => (bool)$res['ok'], 'message' => $res['message']], $res['ok'] ? 200 : 422);
    }

    /**
     * Fill every empty lane from the pool. `order=random` draws lots (the
     * usual way a regatta seeds a preliminary); `order=list` fills in pool
     * order, which is handy when the pool is already seeded by time.
     */
    public function autoFill(): void
    {
        $this->boot();
        $this->requirePrivilege('lane_allocation');
        $this->verifyCsrf();

        $round = $this->roundOr404((string)($_POST['round'] ?? ''));
        $this->assertEditable($round);

        [$pool] = $this->poolFor($round);
        $drawn  = LaneAllocation::drawnRegistrationIds((int)$round['id']);
        $queue  = array_values(array_filter($pool, fn($p) => !in_array((int)$p['registration_id'], $drawn, true)));

        if (($_POST['order'] ?? 'random') === 'random') shuffle($queue);

        $heats = Heat::forRound((int)$round['id']);
        if (!$heats) {
            $this->json(['success' => false, 'message' => 'This round has no heats yet.'], 422);
        }

        // Deal across the heats rather than filling one at a time, so the
        // boats spread evenly when the pool doesn't fill every lane.
        $placed = 0;
        $lanes  = (int)$round['lane_count'];
        for ($lane = 1; $lane <= $lanes && $queue; $lane++) {
            foreach ($heats as $heat) {
                if (!$queue) break;
                if (LaneAllocation::findByHeatLane((int)$heat['id'], $lane)) continue;
                $entry = array_shift($queue);
                LaneAllocation::create([
                    'event_id'             => $this->eventId(),
                    'round_id'             => (int)$round['id'],
                    'heat_id'              => (int)$heat['id'],
                    'lane_no'              => $lane,
                    'team_registration_id' => (int)$entry['registration_id'],
                ]);
                $placed++;
            }
        }

        $left = count($queue);
        $this->json([
            'success' => true,
            'reload'  => true,
            'message' => "Placed {$placed} boat" . ($placed === 1 ? '' : 's')
                       . ($left ? " — {$left} could not fit and stayed in the pool." : '.'),
        ]);
    }

    /** Wipe the round's draw (and, by cascade, any results recorded on it). */
    public function clear(): void
    {
        $this->boot();
        $this->requirePrivilege('lane_allocation');
        $this->verifyCsrf();

        $round = $this->roundOr404((string)($_POST['round'] ?? ''));
        $this->assertEditable($round);

        $n = LaneAllocation::clearRound((int)$round['id']);
        $this->json(['success' => true, 'reload' => true,
                     'message' => "Cleared {$n} lane" . ($n === 1 ? '.' : 's.')]);
    }

    // ── Pool resolution ──────────────────────────────────────────────────────

    /**
     * Who may start in this round.
     *   first round  -> the race's approved entries
     *   later rounds -> the boats flagged qualified in the previous round
     * Returns [pool, note] where note explains an empty pool to the operator.
     */
    private function poolFor(array $round): array
    {
        $previous = Round::previous($round);

        if (!$previous) {
            $entries = EventRace::entries((int)$round['event_race_id']);
            $note = $entries
                ? ''
                : 'No boats are entered in this race yet — ask your event administrator to set the entries.';
            return [$entries, $note];
        }

        $qualifiers = Result::qualifiersForRound((int)$previous['id']);
        $note = $qualifiers
            ? ''
            : 'No qualifiers yet from ' . $previous['name'] . ' — publish that round\'s results first.';
        return [$qualifiers, $note];
    }

    /** Is this registration allowed to take a lane in this round? */
    private function isEligible(array $round, int $registrationId): bool
    {
        if ($registrationId <= 0) return false;

        // Belt and braces: the pool queries already restrict to approved
        // boats of this event, but a hand-crafted POST must not slip past.
        $reg = TeamRegistration::findForEvent($this->eventId(), $registrationId);
        if (!$reg || $reg['status'] !== 'approved') return false;

        [$pool] = $this->poolFor($round);
        foreach ($pool as $p) {
            if ((int)$p['registration_id'] === $registrationId) return true;
        }
        return false;
    }
}
