<?php
namespace Models;

use Core\Model;

/**
 * A round is one stage of a race — Preliminary Heats, Semi-Finals, Final,
 * or anything else the race office adds. Rounds are ordered by sort_order,
 * and each sets its own lane count (a final may run fewer lanes than the
 * heats it came from).
 *
 * Lifecycle: draft -> open (draw + results being worked) -> locked ->
 * published (visible to reports and the display screens).
 */
class Round extends Model
{
    public const TYPES = [
        'preliminary'   => 'Preliminary Heats',
        'quarter_final' => 'Quarter-Finals',
        'semi_final'    => 'Semi-Finals',
        'final'         => 'Final',
        'other'         => 'Other',
    ];

    public const STATUSES = [
        'draft'     => 'Draft',
        'open'      => 'Open',
        'locked'    => 'Locked',
        'published' => 'Published',
    ];

    /** The default ladder offered when a race has no rounds yet. */
    public const DEFAULT_LADDER = [
        ['name' => 'Preliminary Heats', 'round_type' => 'preliminary', 'qualify_per_heat' => 2],
        ['name' => 'Semi-Finals',       'round_type' => 'semi_final',  'qualify_per_heat' => 2],
        ['name' => 'Final',             'round_type' => 'final',       'qualify_per_heat' => 0],
    ];

    public static function findById(int $id): ?array
    {
        return static::row("SELECT * FROM rounds WHERE id = ?", [$id]);
    }

    public static function findForEvent(int $eventId, int $id): ?array
    {
        return static::row("SELECT * FROM rounds WHERE id = ? AND event_id = ?", [$id, $eventId]);
    }

    /** A round joined to its race — what every race-office screen needs. */
    public static function findWithRace(int $eventId, int $id): ?array
    {
        return static::row(
            "SELECT ro.*, r.name AS race_name, r.name_regional AS race_name_regional,
                    r.gender AS race_gender, r.distance_m, r.sl_no AS race_sl_no,
                    r.race_date, r.race_time, r.status AS race_status
               FROM rounds ro
               JOIN event_races r ON r.id = ro.event_race_id
              WHERE ro.id = ? AND ro.event_id = ?",
            [$id, $eventId]
        );
    }

    public static function forRace(int $raceId): array
    {
        return static::rows(
            "SELECT ro.*,
                    (SELECT COUNT(*) FROM heats h WHERE h.round_id = ro.id) AS heat_count,
                    (SELECT COUNT(*) FROM lane_allocations la WHERE la.round_id = ro.id) AS allocated_count
               FROM rounds ro
              WHERE ro.event_race_id = ?
              ORDER BY ro.sort_order, ro.id",
            [$raceId]
        );
    }

    public static function countForRace(int $raceId): int
    {
        return (int)static::value("SELECT COUNT(*) FROM rounds WHERE event_race_id = ?", [$raceId], 0);
    }

    /** Every round of an event, joined to its race — the round picker. */
    public static function forEvent(int $eventId): array
    {
        return static::rows(
            "SELECT ro.*, r.name AS race_name, r.sl_no AS race_sl_no, r.gender AS race_gender,
                    r.race_date, r.race_time,
                    (SELECT COUNT(*) FROM heats h WHERE h.round_id = ro.id) AS heat_count,
                    (SELECT COUNT(*) FROM lane_allocations la WHERE la.round_id = ro.id) AS allocated_count
               FROM rounds ro
               JOIN event_races r ON r.id = ro.event_race_id
              WHERE ro.event_id = ?
              ORDER BY COALESCE(r.race_date,'9999-12-31'), COALESCE(r.race_time,'23:59:59'),
                       r.sl_no, r.id, ro.sort_order",
            [$eventId]
        );
    }

    public static function create(array $data): int
    {
        return static::insert('rounds', $data);
    }

    public static function updateById(int $id, array $data): int
    {
        return static::update('rounds', $data, ['id' => $id]);
    }

    public static function deleteById(int $id): int
    {
        return static::delete('rounds', ['id' => $id]);
    }

    public static function nextSortOrder(int $raceId): int
    {
        return (int)static::value(
            "SELECT COALESCE(MAX(sort_order), 0) + 1 FROM rounds WHERE event_race_id = ?", [$raceId], 1);
    }

    /** The round immediately before this one in the same race, if any. */
    public static function previous(array $round): ?array
    {
        return static::row(
            "SELECT * FROM rounds
              WHERE event_race_id = ? AND sort_order < ?
              ORDER BY sort_order DESC LIMIT 1",
            [(int)$round['event_race_id'], (int)$round['sort_order']]
        );
    }

    /** The round immediately after this one, if any. */
    public static function next(array $round): ?array
    {
        return static::row(
            "SELECT * FROM rounds
              WHERE event_race_id = ? AND sort_order > ?
              ORDER BY sort_order ASC LIMIT 1",
            [(int)$round['event_race_id'], (int)$round['sort_order']]
        );
    }

    /** True once the round is locked or published — the draw must not move. */
    public static function isFrozen(array $round): bool
    {
        return in_array($round['status'] ?? 'draft', ['locked', 'published'], true);
    }
}
