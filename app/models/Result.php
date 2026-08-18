<?php
namespace Models;

use Core\Model;

/**
 * One row per drawn lane, holding what happened in the water: the recorded
 * time, the finishing position, whether the boat advances, and any DNS/DNF/
 * DSQ outcome.
 *
 * Positions may be typed by the judge or derived from the times — see
 * saveHeat(). A boat that did not finish never receives a position, so it
 * can never surface on a rank list.
 */
class Result extends Model
{
    public const OUTCOMES = [
        'ok'  => 'Finished',
        'dns' => 'Did Not Start',
        'dnf' => 'Did Not Finish',
        'dsq' => 'Disqualified',
    ];

    public static function forHeat(int $heatId): array
    {
        return static::rows("SELECT * FROM results WHERE heat_id = ?", [$heatId]);
    }

    /** Results of a heat keyed by lane allocation id, for form prefill. */
    public static function byAllocation(int $heatId): array
    {
        $out = [];
        foreach (static::forHeat($heatId) as $r) {
            $out[(int)$r['lane_allocation_id']] = $r;
        }
        return $out;
    }

    /**
     * Write one heat's results in a single transaction.
     *
     * $rows is keyed by lane_allocation_id with keys: time, position,
     * qualified, outcome, remarks. Positions left blank are derived from the
     * recorded times (fastest first); a boat with a non-'ok' outcome is
     * cleared of both time and position.
     *
     * Returns the number of lanes written.
     */
    public static function saveHeat(int $eventId, array $round, array $heat, array $rows): int
    {
        $allocations = LaneAllocation::forHeat((int)$heat['id']);
        $valid = [];
        foreach ($allocations as $a) $valid[(int)$a['id']] = $a;

        $prepared = [];
        foreach ($rows as $allocationId => $input) {
            $allocationId = (int)$allocationId;
            if (!isset($valid[$allocationId])) continue;   // not a lane of this heat

            $outcome = (string)($input['outcome'] ?? 'ok');
            if (!array_key_exists($outcome, self::OUTCOMES)) $outcome = 'ok';

            $raceTime = $outcome === 'ok' ? normaliseRaceTime((string)($input['time'] ?? '')) : '';
            $centis   = $raceTime !== '' ? raceTimeToCentis($raceTime) : null;
            $position = $outcome === 'ok' ? (int)($input['position'] ?? 0) : 0;

            $prepared[$allocationId] = [
                'event_id'             => $eventId,
                'round_id'             => (int)$round['id'],
                'heat_id'              => (int)$heat['id'],
                'lane_allocation_id'   => $allocationId,
                'team_registration_id' => (int)$valid[$allocationId]['team_registration_id'],
                'race_time'            => $raceTime !== '' ? $raceTime : null,
                'time_centis'          => $centis,
                'position'             => $position > 0 ? $position : null,
                'qualified'            => !empty($input['qualified']) && $outcome === 'ok' ? 1 : 0,
                'outcome'              => $outcome,
                'remarks'              => trim((string)($input['remarks'] ?? '')) ?: null,
            ];
        }

        self::derivePositions($prepared);

        return static::transaction(function () use ($prepared) {
            foreach ($prepared as $row) {
                static::query(
                    "INSERT INTO results
                        (event_id, round_id, heat_id, lane_allocation_id, team_registration_id,
                         race_time, time_centis, position, qualified, outcome, remarks)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE
                        race_time   = VALUES(race_time),
                        time_centis = VALUES(time_centis),
                        position    = VALUES(position),
                        qualified   = VALUES(qualified),
                        outcome     = VALUES(outcome),
                        remarks     = VALUES(remarks)",
                    array_values($row)
                );
            }
            return count($prepared);
        });
    }

    /**
     * Fill in any missing positions from the recorded times, without
     * disturbing the ones the judge typed. Ranks are assigned over the
     * finishers only, skipping position numbers already claimed by hand.
     */
    private static function derivePositions(array &$rows): void
    {
        $taken = [];
        foreach ($rows as $r) {
            if ($r['position'] !== null) $taken[(int)$r['position']] = true;
        }

        $needing = [];
        foreach ($rows as $id => $r) {
            if ($r['position'] === null && $r['outcome'] === 'ok' && $r['time_centis'] !== null) {
                $needing[$id] = (int)$r['time_centis'];
            }
        }
        if (!$needing) return;

        asort($needing);                      // fastest first
        $next = 1;
        foreach (array_keys($needing) as $id) {
            while (!empty($taken[$next])) $next++;
            $rows[$id]['position'] = $next;
            $taken[$next] = true;
        }
    }

    /**
     * Tick the top N finishers of every heat in a round as qualified, where
     * N is the round's qualify_per_heat. Returns how many were marked.
     */
    public static function autoQualify(array $round): int
    {
        $perHeat = (int)($round['qualify_per_heat'] ?? 0);
        static::query("UPDATE results SET qualified = 0 WHERE round_id = ?", [(int)$round['id']]);
        if ($perHeat <= 0) return 0;

        $marked = 0;
        foreach (Heat::forRound((int)$round['id']) as $heat) {
            $rows = static::rows(
                "SELECT id FROM results
                  WHERE heat_id = ? AND outcome = 'ok' AND position IS NOT NULL
                  ORDER BY position ASC
                  LIMIT {$perHeat}",
                [(int)$heat['id']]
            );
            foreach ($rows as $r) {
                static::update('results', ['qualified' => 1], ['id' => (int)$r['id']]);
                $marked++;
            }
        }
        return $marked;
    }

    /**
     * The boats carried forward from a round — shaped exactly like the race
     * entries the first-round pool uses, so the lane board treats them alike.
     */
    public static function qualifiersForRound(int $roundId): array
    {
        return static::rows(
            "SELECT tr.id AS registration_id, t.*, re.position, re.race_time,
                    h.heat_no
               FROM results re
               JOIN team_registrations tr ON tr.id = re.team_registration_id
               JOIN teams t               ON t.id  = tr.team_id
               JOIN heats h               ON h.id  = re.heat_id
              WHERE re.round_id = ? AND re.qualified = 1
              ORDER BY re.position, h.heat_no",
            [$roundId]
        );
    }

    /** Clear every result of one heat. */
    public static function clearHeat(int $heatId): int
    {
        return static::delete('results', ['heat_id' => $heatId]);
    }

    // ── Reporting ────────────────────────────────────────────────────────────

    /**
     * Event-wise rank list: for every race, the 1st–4th placed boats taken
     * from its LAST published round (the final, normally). Unpublished
     * rounds are ignored, so nothing provisional reaches a report or a
     * display screen.
     */
    public static function rankListForEvent(int $eventId, int $places = 4): array
    {
        $places = max(1, min(8, $places));

        $races = static::rows(
            "SELECT * FROM event_races WHERE event_id = ?
              ORDER BY COALESCE(race_date,'9999-12-31'), COALESCE(race_time,'23:59:59'), sl_no, id",
            [$eventId]
        );

        $out = [];
        foreach ($races as $race) {
            $round = static::row(
                "SELECT * FROM rounds
                  WHERE event_race_id = ? AND status = 'published'
                  ORDER BY sort_order DESC LIMIT 1",
                [(int)$race['id']]
            );
            if (!$round) {
                $out[] = ['race' => $race, 'round' => null, 'places' => []];
                continue;
            }

            $rows = static::rows(
                "SELECT re.position, re.race_time, re.outcome,
                        t.club_name, t.boat_name, t.captain_name, t.short_code, t.logo
                   FROM results re
                   JOIN team_registrations tr ON tr.id = re.team_registration_id
                   JOIN teams t               ON t.id  = tr.team_id
                  WHERE re.round_id = ? AND re.outcome = 'ok'
                        AND re.position IS NOT NULL AND re.position <= ?
                  ORDER BY re.position",
                [(int)$round['id'], $places]
            );
            $out[] = ['race' => $race, 'round' => $round, 'places' => $rows];
        }
        return $out;
    }

    /**
     * Club medal tally across every published final — 1st/2nd/3rd counts
     * plus a simple 3-2-1 points total, ordered as a standings table.
     */
    public static function medalTally(int $eventId): array
    {
        return static::rows(
            "SELECT t.club_name,
                    SUM(re.position = 1) AS gold,
                    SUM(re.position = 2) AS silver,
                    SUM(re.position = 3) AS bronze,
                    SUM(CASE re.position WHEN 1 THEN 3 WHEN 2 THEN 2 WHEN 3 THEN 1 ELSE 0 END) AS points
               FROM results re
               JOIN rounds ro             ON ro.id = re.round_id AND ro.status = 'published'
               JOIN team_registrations tr ON tr.id = re.team_registration_id
               JOIN teams t               ON t.id  = tr.team_id
              WHERE re.event_id = ? AND re.outcome = 'ok' AND re.position BETWEEN 1 AND 3
                AND ro.sort_order = (SELECT MAX(r2.sort_order) FROM rounds r2
                                      WHERE r2.event_race_id = ro.event_race_id AND r2.status = 'published')
              GROUP BY t.club_name
              ORDER BY points DESC, gold DESC, silver DESC, bronze DESC, t.club_name",
            [$eventId]
        );
    }

    /**
     * One round's full heat sheet — every lane with its boat and result.
     * Used by the printable sheets and by both display screens.
     */
    public static function heatSheet(int $roundId): array
    {
        $heats = Heat::forRound($roundId);
        foreach ($heats as &$h) {
            $h['lanes'] = LaneAllocation::forHeat((int)$h['id']);
        }
        unset($h);
        return $heats;
    }

    /** Published rounds of an event, newest first — the display feed. */
    public static function publishedRounds(int $eventId, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        return static::rows(
            "SELECT ro.*, r.name AS race_name, r.name_regional AS race_name_regional,
                    r.sl_no AS race_sl_no, r.gender AS race_gender, r.distance_m,
                    r.race_date, r.race_time
               FROM rounds ro
               JOIN event_races r ON r.id = ro.event_race_id
              WHERE ro.event_id = ? AND ro.status = 'published'
              ORDER BY ro.published_at DESC, ro.id DESC
              LIMIT {$limit}",
            [$eventId]
        );
    }
}
