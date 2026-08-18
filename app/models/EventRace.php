<?php
namespace Models;

use Core\Model;

/**
 * The programme — one row per race item in the Order of Events. Each carries
 * its serial number, scheduled date/time and the call-room status that walks
 * from Scheduled through to Medal Ceremony on race day.
 */
class EventRace extends Model
{
    public const GENDERS = [
        'open'  => 'Open',
        'men'   => 'Men',
        'women' => 'Women',
        'mixed' => 'Mixed',
    ];

    public const STATUSES = [
        'scheduled'        => 'Scheduled',
        'in_progress'      => 'In Progress',
        'finished'         => 'Finished',
        'result_published' => 'Result Published',
        'medal_ceremony'   => 'Medal Ceremony',
    ];

    public static function findById(int $id): ?array
    {
        return static::row("SELECT * FROM event_races WHERE id = ?", [$id]);
    }

    public static function findForEvent(int $eventId, int $id): ?array
    {
        return static::row("SELECT * FROM event_races WHERE id = ? AND event_id = ?", [$id, $eventId]);
    }

    /**
     * The full programme in running order — by date, then time, then serial.
     * Rows with no date sort last so an unscheduled item is visibly pending.
     */
    public static function forEvent(int $eventId, string $date = '', string $status = ''): array
    {
        $sql = "SELECT r.*,
                       (SELECT COUNT(*) FROM race_entries e WHERE e.event_race_id = r.id) AS entry_count,
                       (SELECT COUNT(*) FROM rounds ro      WHERE ro.event_race_id = r.id) AS round_count
                  FROM event_races r
                 WHERE r.event_id = ?";
        $params = [$eventId];
        if ($date !== '')   { $sql .= " AND r.race_date = ?";  $params[] = $date; }
        if ($status !== '') { $sql .= " AND r.status = ?";     $params[] = $status; }
        $sql .= " ORDER BY COALESCE(r.race_date, '9999-12-31'), COALESCE(r.race_time, '23:59:59'), r.sl_no, r.id";
        return static::rows($sql, $params);
    }

    /** Distinct scheduled dates, for the date filter and the date-wise PDF. */
    public static function datesForEvent(int $eventId): array
    {
        $rows = static::rows(
            "SELECT DISTINCT race_date FROM event_races
              WHERE event_id = ? AND race_date IS NOT NULL
              ORDER BY race_date",
            [$eventId]
        );
        return array_column($rows, 'race_date');
    }

    /** Next few races that haven't been run yet — the dashboard's "up next". */
    public static function upcoming(int $eventId, int $limit = 5): array
    {
        $limit = max(1, min(50, $limit));
        return static::rows(
            "SELECT * FROM event_races
              WHERE event_id = ? AND status IN ('scheduled','in_progress')
              ORDER BY COALESCE(race_date, '9999-12-31'), COALESCE(race_time, '23:59:59'), sl_no
              LIMIT {$limit}",
            [$eventId]
        );
    }

    /** Races that have at least one round, for the race-office pickers. */
    public static function withRounds(int $eventId): array
    {
        return static::rows(
            "SELECT r.*, (SELECT COUNT(*) FROM rounds ro WHERE ro.event_race_id = r.id) AS round_count
               FROM event_races r
              WHERE r.event_id = ?
              ORDER BY COALESCE(r.race_date,'9999-12-31'), COALESCE(r.race_time,'23:59:59'), r.sl_no, r.id",
            [$eventId]
        );
    }

    public static function create(array $data): int
    {
        return static::insert('event_races', $data);
    }

    public static function updateById(int $id, array $data): int
    {
        return static::update('event_races', $data, ['id' => $id]);
    }

    public static function deleteById(int $id): int
    {
        return static::delete('event_races', ['id' => $id]);
    }

    /** Next free serial number for a new programme item. */
    public static function nextSerial(int $eventId): int
    {
        return (int)static::value("SELECT COALESCE(MAX(sl_no), 0) + 1 FROM event_races WHERE event_id = ?", [$eventId], 1);
    }

    /** Renumber the programme 1..n in its current running order. */
    public static function resequence(int $eventId): void
    {
        $rows = static::rows(
            "SELECT id FROM event_races WHERE event_id = ?
              ORDER BY COALESCE(race_date,'9999-12-31'), COALESCE(race_time,'23:59:59'), sl_no, id",
            [$eventId]
        );
        $n = 1;
        foreach ($rows as $r) {
            static::update('event_races', ['sl_no' => $n++], ['id' => (int)$r['id']]);
        }
    }

    // ── Race entries (which boats contest which race) ────────────────────────

    /** Approved registrations entered in this race. */
    public static function entries(int $raceId): array
    {
        return static::rows(
            "SELECT e.id AS entry_id, tr.id AS registration_id, t.*
               FROM race_entries e
               JOIN team_registrations tr ON tr.id = e.team_registration_id
               JOIN teams t               ON t.id  = tr.team_id
              WHERE e.event_race_id = ?
              ORDER BY t.club_name, t.boat_name",
            [$raceId]
        );
    }

    /** Registration ids already entered in this race. */
    public static function entryRegistrationIds(int $raceId): array
    {
        $rows = static::rows("SELECT team_registration_id FROM race_entries WHERE event_race_id = ?", [$raceId]);
        return array_map('intval', array_column($rows, 'team_registration_id'));
    }

    /**
     * Replace a race's entry list with exactly $registrationIds. Only
     * approved registrations of the same event are accepted, so an unvetted
     * boat can never reach the lane board.
     */
    public static function setEntries(int $eventId, int $raceId, array $registrationIds): int
    {
        $ids = array_values(array_unique(array_map('intval', $registrationIds)));
        static::query("DELETE FROM race_entries WHERE event_race_id = ?", [$raceId]);
        if (!$ids) return 0;

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $valid = static::rows(
            "SELECT id FROM team_registrations
              WHERE event_id = ? AND status = 'approved' AND id IN ({$placeholders})",
            [$eventId, ...$ids]
        );
        foreach ($valid as $v) {
            static::query(
                "INSERT IGNORE INTO race_entries (event_id, event_race_id, team_registration_id) VALUES (?,?,?)",
                [$eventId, $raceId, (int)$v['id']]
            );
        }
        return count($valid);
    }

    /** "12 · Churulan Vallam — Men (1400 m)" for pickers and headings. */
    public static function label(array $race): string
    {
        $bits = [trim((string)($race['name'] ?? ''))];
        $g = (string)($race['gender'] ?? 'open');
        if ($g !== 'open') $bits[] = self::GENDERS[$g] ?? ucfirst($g);
        $label = implode(' — ', array_filter($bits));
        if (!empty($race['distance_m'])) $label .= ' (' . (int)$race['distance_m'] . ' m)';
        return $label;
    }
}
