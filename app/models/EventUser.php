<?php
namespace Models;

use Core\Model;

/**
 * Per-event, privilege-gated race-office logins (+ their privilege rows).
 * Same shape as EventAdmin, but each account additionally holds a subset of
 * PRIVILEGES which gates both the nav and every controller action.
 */
class EventUser extends Model
{
    /** The privilege catalog. Keys are stored in event_user_privileges. */
    public const PRIVILEGES = [
        'rounds_heats'    => 'Rounds & Heats',
        'lane_allocation' => 'Lane Allocation',
        'result_entry'    => 'Result Entry',
        'reports'         => 'Reports',
        'displays'        => 'Display Screens',
    ];

    /** Icon + blurb for the dashboard cards, keyed by privilege. */
    public const PRIVILEGE_META = [
        'rounds_heats'    => ['bi-diagram-3', '/event-user/rounds',          'Define rounds per race and lay out the heats.'],
        'lane_allocation' => ['bi-water',     '/event-user/lane-allocation', 'Drag boats onto lanes, heat by heat.'],
        'result_entry'    => ['bi-stopwatch', '/event-user/results',         'Record times and positions, advance qualifiers.'],
        'reports'         => ['bi-trophy',    '/event-user/reports',         'Rank lists, heat sheets and printable PDFs.'],
        'displays'        => ['bi-tv',        '/event-user/displays',        'LED wall deck and the live-stream overlay.'],
    ];

    public static function findById(int $id): ?array
    {
        return static::row("SELECT * FROM event_users WHERE id = ?", [$id]);
    }

    public static function findByEventEmail(int $eventId, string $email): ?array
    {
        return static::row(
            "SELECT * FROM event_users WHERE event_id = ? AND email = ?",
            [$eventId, strtolower(trim($email))]
        );
    }

    /** All accounts for an event, each hydrated with its privilege list. */
    public static function forEvent(int $eventId): array
    {
        $rows = static::rows("SELECT * FROM event_users WHERE event_id = ? ORDER BY name", [$eventId]);
        foreach ($rows as &$r) {
            $r['privileges'] = static::privilegesFor((int)$r['id']);
        }
        unset($r);
        return $rows;
    }

    public static function privilegesFor(int $userId): array
    {
        $rows = static::rows(
            "SELECT privilege FROM event_user_privileges WHERE event_user_id = ? ORDER BY privilege",
            [$userId]
        );
        return array_column($rows, 'privilege');
    }

    /** Replace an account's privilege set with exactly $privileges. */
    public static function setPrivileges(int $userId, array $privileges): void
    {
        $valid = array_values(array_intersect($privileges, array_keys(self::PRIVILEGES)));
        static::query("DELETE FROM event_user_privileges WHERE event_user_id = ?", [$userId]);
        foreach ($valid as $p) {
            static::query(
                "INSERT IGNORE INTO event_user_privileges (event_user_id, privilege) VALUES (?, ?)",
                [$userId, $p]
            );
        }
    }

    public static function create(array $data): int
    {
        $data['email'] = strtolower(trim((string)$data['email']));
        return static::insert('event_users', $data);
    }

    public static function updateById(int $id, array $data): int
    {
        if (isset($data['email'])) $data['email'] = strtolower(trim((string)$data['email']));
        return static::update('event_users', $data, ['id' => $id]);
    }

    public static function deleteById(int $id): int
    {
        return static::delete('event_users', ['id' => $id]);
    }

    public static function updateLastLogin(int $id): void
    {
        static::update('event_users', ['last_login_at' => date('Y-m-d H:i:s')], ['id' => $id]);
    }

    /** Event Code + email + password. Returns the row or null. */
    public static function attempt(string $eventCode, string $email, string $password): ?array
    {
        $event = Event::findByCode($eventCode);
        if (!$event) return null;
        $user = static::findByEventEmail((int)$event['id'], $email);
        if (!$user || $user['status'] !== 'active') return null;
        if (!password_verify($password, (string)$user['password'])) return null;
        static::updateLastLogin((int)$user['id']);
        return $user;
    }
}
