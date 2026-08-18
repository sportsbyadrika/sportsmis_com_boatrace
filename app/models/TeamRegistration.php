<?php
namespace Models;

use Core\Model;

/**
 * A team's entry into its event, carrying the review workflow:
 *
 *   draft ──submit──> submitted ──approve──> approved
 *                          └────return─────> returned ──submit──> submitted
 *
 * Only an APPROVED registration may be drawn into a lane, so the lane board
 * and the results downstream of it can never contain an unvetted boat.
 */
class TeamRegistration extends Model
{
    public const STATUSES = [
        'draft'     => 'Draft',
        'submitted' => 'Submitted',
        'approved'  => 'Approved',
        'returned'  => 'Returned',
    ];

    public static function findById(int $id): ?array
    {
        return static::row("SELECT * FROM team_registrations WHERE id = ?", [$id]);
    }

    public static function findForEvent(int $eventId, int $id): ?array
    {
        return static::row(
            "SELECT * FROM team_registrations WHERE id = ? AND event_id = ?",
            [$id, $eventId]
        );
    }

    public static function findByTeam(int $eventId, int $teamId): ?array
    {
        return static::row(
            "SELECT * FROM team_registrations WHERE event_id = ? AND team_id = ?",
            [$eventId, $teamId]
        );
    }

    /** Registrations with their team details, for the review queue. */
    public static function forEvent(int $eventId, string $status = ''): array
    {
        $sql = "SELECT tr.*, t.club_name, t.boat_name, t.captain_name, t.logo,
                       t.short_code, t.boat_class, t.contact_phone, t.contact_email
                  FROM team_registrations tr
                  JOIN teams t ON t.id = tr.team_id
                 WHERE tr.event_id = ?";
        $params = [$eventId];
        if ($status !== '') {
            $sql .= " AND tr.status = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY FIELD(tr.status,'submitted','returned','draft','approved'), t.club_name";
        return static::rows($sql, $params);
    }

    /** Count per status, so the review screen can show its tabs' badges. */
    public static function countsForEvent(int $eventId): array
    {
        $rows = static::rows(
            "SELECT status, COUNT(*) AS n FROM team_registrations WHERE event_id = ? GROUP BY status",
            [$eventId]
        );
        $out = array_fill_keys(array_keys(self::STATUSES), 0);
        foreach ($rows as $r) $out[$r['status']] = (int)$r['n'];
        return $out;
    }

    /** Create the registration row a team needs; idempotent. */
    public static function ensureFor(int $eventId, int $teamId, string $status = 'draft'): int
    {
        $existing = static::findByTeam($eventId, $teamId);
        if ($existing) return (int)$existing['id'];
        return static::insert('team_registrations', [
            'event_id' => $eventId,
            'team_id'  => $teamId,
            'status'   => $status,
        ]);
    }

    public static function updateById(int $id, array $data): int
    {
        return static::update('team_registrations', $data, ['id' => $id]);
    }

    public static function submit(int $id): void
    {
        static::updateById($id, [
            'status'       => 'submitted',
            'submitted_at' => date('Y-m-d H:i:s'),
            'remarks'      => null,
        ]);
    }

    public static function approve(int $id, string $by): void
    {
        static::updateById($id, [
            'status'     => 'approved',
            'decided_at' => date('Y-m-d H:i:s'),
            'decided_by' => $by,
            'remarks'    => null,
        ]);
    }

    /** Send it back with a reason the organiser can act on. */
    public static function returnForChanges(int $id, string $by, string $remarks): void
    {
        static::updateById($id, [
            'status'     => 'returned',
            'decided_at' => date('Y-m-d H:i:s'),
            'decided_by' => $by,
            'remarks'    => $remarks !== '' ? mb_substr($remarks, 0, 500) : null,
        ]);
    }
}
