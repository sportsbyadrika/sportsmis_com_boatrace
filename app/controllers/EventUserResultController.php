<?php
namespace Controllers;

use Models\{Round, Heat, LaneAllocation, Result, EventRace};

/**
 * Race office -> Result Entry (privilege: result_entry).
 *
 * One heat at a time: a row per lane with the boat's time, position, outcome
 * and a qualifier tick. Positions left blank are derived from the times when
 * the heat is saved, so a judge can record times only and still get a ranked
 * heat. Publishing a round is what makes its results visible to the reports
 * and the display screens.
 */
class EventUserResultController extends EventUserBase
{
    private function roundOr404(string $hash): array
    {
        $round = Round::findWithRace($this->eventId(), hid_round_decode($hash));
        if (!$round) $this->abort(404);
        return $round;
    }

    // ── Screens ──────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->boot();
        $this->requirePrivilege('result_entry');

        $roundHash = trim((string)($_GET['round'] ?? ''));
        $round     = $roundHash !== '' ? Round::findWithRace($this->eventId(), hid_round_decode($roundHash)) : null;

        // No round chosen: the picker, not an empty grid.
        if (!$round) {
            $this->view('event-user/results/pick', [
                'pageTitle' => 'Result Entry',
                'rounds'    => Round::forEvent($this->eventId()),
            ]);
            return;
        }

        $heats = [];
        if ($round) {
            foreach (Heat::forRound((int)$round['id']) as $h) {
                $h['lanes'] = LaneAllocation::forHeat((int)$h['id']);
                $heats[] = $h;
            }
        }

        $this->view('event-user/results/index', [
            'pageTitle' => 'Result Entry',
            'round'     => $round,
            'heats'     => $heats,
            'nextRound' => $round ? Round::next($round) : null,
        ]);
    }

    // ── Mutations ────────────────────────────────────────────────────────────

    /** Save one heat's grid. AJAX — the page holds several heat forms. */
    public function saveHeat(): void
    {
        $this->boot();
        $this->requirePrivilege('result_entry');
        $this->verifyCsrf();

        $round = $this->roundOr404((string)($_POST['round'] ?? ''));
        if ($round['status'] === 'published') {
            $this->json(['success' => false,
                         'message' => 'This round is published — unpublish it before editing results.'], 422);
        }

        $heat = Heat::findForEvent($this->eventId(), hid_heat_decode((string)($_POST['heat'] ?? '')));
        if (!$heat || (int)$heat['round_id'] !== (int)$round['id']) {
            $this->json(['success' => false, 'message' => 'That heat is not part of this round.'], 422);
        }

        // rows[<lane_allocation_id>][time|position|qualified|outcome|remarks]
        $rows = (array)($_POST['rows'] ?? []);
        foreach ($rows as $allocationId => $row) {
            $raw = trim((string)($row['time'] ?? ''));
            if ($raw !== '' && normaliseRaceTime($raw) === '') {
                $this->json(['success' => false,
                             'message' => "“{$raw}” isn't a time we can read — use m:ss.mmm, for example 4:12.35."], 422);
            }
        }

        $saved = Result::saveHeat($this->eventId(), $round, $heat, $rows);

        // A round being worked on moves out of draft on its own, so the
        // lane board correctly reports it as in progress.
        if ($round['status'] === 'draft') {
            Round::updateById((int)$round['id'], ['status' => 'open']);
        }
        Heat::updateById((int)$heat['id'], ['status' => 'finished']);

        $this->json(['success' => true, 'reload' => true,
                     'message' => "Saved {$saved} lane" . ($saved === 1 ? '.' : 's.')]);
    }

    /** Tick the top N of every heat, per the round's qualify_per_heat. */
    public function autoQualify(): void
    {
        $this->boot();
        $this->requirePrivilege('result_entry');
        $this->verifyCsrf();

        $round = $this->roundOr404((string)($_POST['round'] ?? ''));
        if (Round::isFrozen($round)) {
            $this->json(['success' => false, 'message' => 'This round is locked.'], 422);
        }

        $n = Result::autoQualify($round);
        $this->json(['success' => true, 'reload' => true, 'message' => $n
            ? "Marked {$n} qualifier" . ($n === 1 ? '.' : 's.')
            : 'No qualifiers marked — set “qualifiers per heat” on the round, or record positions first.']);
    }

    /** Clear every recorded result of one heat. */
    public function clearHeat(): void
    {
        $this->boot();
        $this->requirePrivilege('result_entry');
        $this->verifyCsrf();

        $round = $this->roundOr404((string)($_POST['round'] ?? ''));
        if (Round::isFrozen($round)) {
            $this->json(['success' => false, 'message' => 'This round is locked.'], 422);
        }

        $heat = Heat::findForEvent($this->eventId(), hid_heat_decode((string)($_POST['heat'] ?? '')));
        if (!$heat || (int)$heat['round_id'] !== (int)$round['id']) {
            $this->json(['success' => false, 'message' => 'That heat is not part of this round.'], 422);
        }

        $n = Result::clearHeat((int)$heat['id']);
        Heat::updateById((int)$heat['id'], ['status' => 'scheduled']);
        $this->json(['success' => true, 'reload' => true,
                     'message' => "Cleared {$n} recorded result" . ($n === 1 ? '.' : 's.')]);
    }

    /**
     * Move a round through locked / published / back to open. Publishing is
     * the gate: only a published round reaches the reports, the rank list
     * and both display screens.
     */
    public function setStatus(): void
    {
        $this->boot();
        $this->requirePrivilege('result_entry');
        $this->verifyCsrf();

        $round  = $this->roundOr404((string)($_POST['round'] ?? ''));
        $status = (string)($_POST['status'] ?? '');
        if (!array_key_exists($status, Round::STATUSES)) {
            $this->json(['success' => false, 'message' => 'Unknown status.'], 422);
        }

        $data = ['status' => $status];
        $data['published_at'] = $status === 'published' ? date('Y-m-d H:i:s') : null;
        Round::updateById((int)$round['id'], $data);

        // Publishing the last round of a race is what "result published"
        // means on the programme, so keep the two in step.
        if ($status === 'published' && !Round::next($round)) {
            EventRace::updateById((int)$round['event_race_id'], ['status' => 'result_published']);
        }

        $this->json(['success' => true, 'reload' => true,
                     'message' => 'Round ' . Round::STATUSES[$status] . '.']);
    }
}
