<?php
namespace Models;

use Core\Model;

/**
 * Events are the tenant boundary: every team, race, round, heat, lane and
 * result hangs off one. `code` is the Event Code that event admins and event
 * users type at sign-in, and that the display screens are addressed by.
 */
class Event extends Model
{
    public const STATUSES = [
        'draft'     => 'Draft',
        'active'    => 'Active',
        'completed' => 'Completed',
        'archived'  => 'Archived',
    ];

    public static function findById(int $id): ?array
    {
        return static::row("SELECT * FROM events WHERE id = ?", [$id]);
    }

    public static function findByCode(string $code): ?array
    {
        $code = strtoupper(trim($code));
        if ($code === '') return null;
        return static::row("SELECT * FROM events WHERE code = ?", [$code]);
    }

    /** All events, newest first, with the counts the admin list shows. */
    public static function allWithCounts(string $search = '', string $status = ''): array
    {
        $where  = [];
        $params = [];
        if ($search !== '') {
            $where[] = "(e.name LIKE ? OR e.name_regional LIKE ? OR e.code LIKE ? OR e.venue LIKE ?)";
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like);
        }
        if ($status !== '') {
            $where[] = "e.status = ?";
            $params[] = $status;
        }
        $sql = "SELECT e.*,
                       (SELECT COUNT(*) FROM event_admins ea WHERE ea.event_id = e.id) AS admin_count,
                       (SELECT COUNT(*) FROM event_users  eu WHERE eu.event_id = e.id) AS user_count,
                       (SELECT COUNT(*) FROM teams t        WHERE t.event_id  = e.id) AS team_count,
                       (SELECT COUNT(*) FROM event_races r  WHERE r.event_id  = e.id) AS race_count
                  FROM events e";
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= " ORDER BY COALESCE(e.start_date, '9999-12-31') DESC, e.id DESC";
        return static::rows($sql, $params);
    }

    public static function create(array $data): int
    {
        return static::insert('events', $data);
    }

    public static function updateById(int $id, array $data): int
    {
        if (!$data) return 0;
        return static::update('events', $data, ['id' => $id]);
    }

    public static function deleteById(int $id): int
    {
        return static::delete('events', ['id' => $id]);
    }

    /** Current event code, '' when not yet minted. */
    public static function codeFor(int $id): string
    {
        return (string)static::value("SELECT code FROM events WHERE id = ?", [$id], '');
    }

    /** Throws on the unique-key collision so ensureEventCode() can retry. */
    public static function setCode(int $id, string $code): void
    {
        static::update('events', ['code' => $code], ['id' => $id]);
    }

    /** Headline counts for the super admin dashboard. */
    public static function platformStats(): array
    {
        return [
            'events'    => (int)static::value("SELECT COUNT(*) FROM events", [], 0),
            'active'    => (int)static::value("SELECT COUNT(*) FROM events WHERE status = 'active'", [], 0),
            'admins'    => (int)static::value("SELECT COUNT(*) FROM event_admins", [], 0),
            'users'     => (int)static::value("SELECT COUNT(*) FROM event_users", [], 0),
        ];
    }

    /** Per-event counters shown on both event dashboards. */
    public static function stats(int $eventId): array
    {
        return [
            'teams'      => (int)static::value("SELECT COUNT(*) FROM teams WHERE event_id = ?", [$eventId], 0),
            'approved'   => (int)static::value(
                "SELECT COUNT(*) FROM team_registrations WHERE event_id = ? AND status = 'approved'", [$eventId], 0),
            'pending'    => (int)static::value(
                "SELECT COUNT(*) FROM team_registrations WHERE event_id = ? AND status = 'submitted'", [$eventId], 0),
            'races'      => (int)static::value("SELECT COUNT(*) FROM event_races WHERE event_id = ?", [$eventId], 0),
            'rounds'     => (int)static::value("SELECT COUNT(*) FROM rounds WHERE event_id = ?", [$eventId], 0),
            'heats'      => (int)static::value("SELECT COUNT(*) FROM heats WHERE event_id = ?", [$eventId], 0),
            'allocated'  => (int)static::value("SELECT COUNT(*) FROM lane_allocations WHERE event_id = ?", [$eventId], 0),
            'published'  => (int)static::value(
                "SELECT COUNT(*) FROM rounds WHERE event_id = ? AND status = 'published'", [$eventId], 0),
            'event_users'=> (int)static::value("SELECT COUNT(*) FROM event_users WHERE event_id = ?", [$eventId], 0),
        ];
    }
}
