<?php
namespace Models;

use Core\Model;

/**
 * The lane draw: (round, heat, lane) -> team registration.
 *
 * Two unique keys keep the board honest — one boat per lane
 * (heat_id, lane_no) and one lane per boat within a round
 * (round_id, team_registration_id). Both are enforced in MySQL, so a
 * double-tap on the drag handler cannot produce a duplicate.
 */
class LaneAllocation extends Model
{
    public static function findById(int $id): ?array
    {
        return static::row("SELECT * FROM lane_allocations WHERE id = ?", [$id]);
    }

    /** Every allocation in a round, joined to the boat it holds. */
    public static function forRound(int $roundId): array
    {
        return static::rows(
            "SELECT la.*, t.id AS team_id, t.club_name, t.boat_name, t.captain_name,
                    t.short_code, t.boat_class, t.logo,
                    h.heat_no, h.name AS heat_name
               FROM lane_allocations la
               JOIN team_registrations tr ON tr.id = la.team_registration_id
               JOIN teams t               ON t.id  = tr.team_id
               JOIN heats h               ON h.id  = la.heat_id
              WHERE la.round_id = ?
              ORDER BY h.heat_no, la.lane_no",
            [$roundId]
        );
    }

    /** Allocations of one heat, with the boat and any recorded result. */
    public static function forHeat(int $heatId): array
    {
        return static::rows(
            "SELECT la.*, t.id AS team_id, t.club_name, t.boat_name, t.captain_name,
                    t.short_code, t.boat_class, t.logo,
                    re.race_time, re.time_centis, re.position, re.qualified, re.outcome, re.remarks
               FROM lane_allocations la
               JOIN team_registrations tr ON tr.id = la.team_registration_id
               JOIN teams t               ON t.id  = tr.team_id
          LEFT JOIN results re            ON re.lane_allocation_id = la.id
              WHERE la.heat_id = ?
              ORDER BY la.lane_no",
            [$heatId]
        );
    }

    /** Registration ids already drawn somewhere in this round. */
    public static function drawnRegistrationIds(int $roundId): array
    {
        $rows = static::rows("SELECT team_registration_id FROM lane_allocations WHERE round_id = ?", [$roundId]);
        return array_map('intval', array_column($rows, 'team_registration_id'));
    }

    public static function findByHeatLane(int $heatId, int $laneNo): ?array
    {
        return static::row("SELECT * FROM lane_allocations WHERE heat_id = ? AND lane_no = ?", [$heatId, $laneNo]);
    }

    public static function findByRoundTeam(int $roundId, int $registrationId): ?array
    {
        return static::row(
            "SELECT * FROM lane_allocations WHERE round_id = ? AND team_registration_id = ?",
            [$roundId, $registrationId]
        );
    }

    public static function create(array $data): int
    {
        return static::insert('lane_allocations', $data);
    }

    public static function updateById(int $id, array $data): int
    {
        return static::update('lane_allocations', $data, ['id' => $id]);
    }

    public static function deleteById(int $id): int
    {
        return static::delete('lane_allocations', ['id' => $id]);
    }

    /** Clear an entire round's draw (and, by cascade, its results). */
    public static function clearRound(int $roundId): int
    {
        return static::delete('lane_allocations', ['round_id' => $roundId]);
    }

    public static function countForRound(int $roundId): int
    {
        return (int)static::value("SELECT COUNT(*) FROM lane_allocations WHERE round_id = ?", [$roundId], 0);
    }

    /** Highest lane number in use — guards a shrink of the lane count. */
    public static function maxLaneUsed(int $roundId): int
    {
        return (int)static::value("SELECT COALESCE(MAX(lane_no), 0) FROM lane_allocations WHERE round_id = ?", [$roundId], 0);
    }

    /**
     * Move a boat to another (heat, lane). When the target lane is occupied
     * the two boats swap, which is what a race secretary means by "move" —
     * done in one transaction so the unique keys never see a half state.
     */
    public static function moveOrSwap(int $allocationId, int $targetHeatId, int $targetLaneNo): array
    {
        return static::transaction(function () use ($allocationId, $targetHeatId, $targetLaneNo) {
            $source = static::findById($allocationId);
            if (!$source) return ['ok' => false, 'message' => 'That lane no longer exists.'];

            $target = static::findByHeatLane($targetHeatId, $targetLaneNo);
            if ($target && (int)$target['id'] === (int)$source['id']) {
                return ['ok' => true, 'swapped' => false, 'message' => 'Already there.'];
            }

            if (!$target) {
                static::updateById($allocationId, ['heat_id' => $targetHeatId, 'lane_no' => $targetLaneNo]);
                return ['ok' => true, 'swapped' => false, 'message' => 'Boat moved.'];
            }

            // Park the source on a lane number no draw can occupy, so the
            // (heat_id, lane_no) unique key stays satisfied mid-swap.
            static::updateById((int)$source['id'], ['lane_no' => 0, 'heat_id' => (int)$source['heat_id']]);
            static::updateById((int)$target['id'], ['heat_id' => (int)$source['heat_id'], 'lane_no' => (int)$source['lane_no']]);
            static::updateById((int)$source['id'], ['heat_id' => $targetHeatId, 'lane_no' => $targetLaneNo]);

            return ['ok' => true, 'swapped' => true, 'message' => 'Boats swapped.'];
        });
    }
}
