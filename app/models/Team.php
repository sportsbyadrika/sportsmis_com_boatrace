<?php
namespace Models;

use Core\Model;

/**
 * A team is one competing boat crew: the club that enters it, the boat's own
 * name and its captain. Teams are event-scoped — the same club entering two
 * regattas has one row per event, so a boat can be renamed or re-crewed from
 * one year to the next without rewriting history.
 */
class Team extends Model
{
    public static function findById(int $id): ?array
    {
        return static::row("SELECT * FROM teams WHERE id = ?", [$id]);
    }

    /** A team scoped to its event — the guard every event controller uses. */
    public static function findForEvent(int $eventId, int $id): ?array
    {
        return static::row("SELECT * FROM teams WHERE id = ? AND event_id = ?", [$id, $eventId]);
    }

    /**
     * All teams of an event, each joined to its registration so the list can
     * show status without a second query.
     */
    public static function forEvent(int $eventId, string $search = '', string $status = ''): array
    {
        $sql = "SELECT t.*,
                       tr.id           AS registration_id,
                       tr.status       AS registration_status,
                       tr.remarks      AS registration_remarks,
                       tr.submitted_at AS registration_submitted_at
                  FROM teams t
             LEFT JOIN team_registrations tr ON tr.team_id = t.id AND tr.event_id = t.event_id
                 WHERE t.event_id = ?";
        $params = [$eventId];

        if ($search !== '') {
            $sql .= " AND (t.club_name LIKE ? OR t.boat_name LIKE ? OR t.captain_name LIKE ? OR t.short_code LIKE ?)";
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like);
        }
        if ($status !== '') {
            $sql .= " AND tr.status = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY t.club_name, t.boat_name";
        return static::rows($sql, $params);
    }

    /** Approved boats only — the pool the lane draw may allocate from. */
    public static function approvedForEvent(int $eventId): array
    {
        return static::rows(
            "SELECT t.*, tr.id AS registration_id
               FROM teams t
               JOIN team_registrations tr ON tr.team_id = t.id AND tr.event_id = t.event_id
              WHERE t.event_id = ? AND tr.status = 'approved' AND t.status = 'active'
              ORDER BY t.club_name, t.boat_name",
            [$eventId]
        );
    }

    public static function create(array $data): int
    {
        return static::insert('teams', $data);
    }

    public static function updateById(int $id, array $data): int
    {
        return static::update('teams', $data, ['id' => $id]);
    }

    public static function deleteById(int $id): int
    {
        return static::delete('teams', ['id' => $id]);
    }

    /** Is this boat already drawn into a lane anywhere in the event? */
    public static function isAllocated(int $teamId): bool
    {
        return (int)static::value(
            "SELECT COUNT(*)
               FROM lane_allocations la
               JOIN team_registrations tr ON tr.id = la.team_registration_id
              WHERE tr.team_id = ?",
            [$teamId], 0
        ) > 0;
    }

    /** "Boat Name (Club)" — the label used on lane cards and result rows. */
    public static function label(array $team): string
    {
        $boat = trim((string)($team['boat_name'] ?? ''));
        $club = trim((string)($team['club_name'] ?? ''));
        if ($boat === '') return $club;
        if ($club === '') return $boat;
        return "{$boat} ({$club})";
    }
}
