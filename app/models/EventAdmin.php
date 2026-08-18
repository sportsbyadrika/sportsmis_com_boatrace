<?php
namespace Models;

use Core\Model;

/**
 * Per-event administrator logins. Auth is independent of the users table —
 * uniqueness is per (event_id, email), so one address can administer several
 * regattas. Sign-in is email + password on the single /login form.
 */
class EventAdmin extends Model
{
    public static function findById(int $id): ?array
    {
        return static::row("SELECT * FROM event_admins WHERE id = ?", [$id]);
    }

    public static function findByEventEmail(int $eventId, string $email): ?array
    {
        return static::row(
            "SELECT * FROM event_admins WHERE event_id = ? AND email = ?",
            [$eventId, strtolower(trim($email))]
        );
    }

    public static function forEvent(int $eventId): array
    {
        return static::rows(
            "SELECT * FROM event_admins WHERE event_id = ? ORDER BY name",
            [$eventId]
        );
    }

    /** Every admin account across the platform, for the super admin list. */
    public static function allWithEvent(string $search = ''): array
    {
        $params = [];
        $sql = "SELECT ea.*, e.name AS event_name, e.code AS event_code, e.status AS event_status
                  FROM event_admins ea
                  JOIN events e ON e.id = ea.event_id";
        if ($search !== '') {
            $sql .= " WHERE ea.name LIKE ? OR ea.email LIKE ? OR e.name LIKE ? OR e.code LIKE ?";
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like);
        }
        $sql .= " ORDER BY e.name, ea.name";
        return static::rows($sql, $params);
    }

    public static function create(array $data): int
    {
        $data['email'] = strtolower(trim((string)$data['email']));
        return static::insert('event_admins', $data);
    }

    public static function updateById(int $id, array $data): int
    {
        if (isset($data['email'])) $data['email'] = strtolower(trim((string)$data['email']));
        return static::update('event_admins', $data, ['id' => $id]);
    }

    public static function deleteById(int $id): int
    {
        return static::delete('event_admins', ['id' => $id]);
    }

    public static function updateLastLogin(int $id): void
    {
        static::update('event_admins', ['last_login_at' => date('Y-m-d H:i:s')], ['id' => $id]);
    }

    /**
     * Every ACTIVE admin account sharing this email address, across all
     * events, hydrated with the event details a sign-in chooser needs.
     *
     * Sign-in resolves the role from email + password alone, so the login
     * page never has to advertise that per-event roles exist. An address can
     * legitimately hold accounts on several regattas — uniqueness is only per
     * (event_id, email) — which is why this returns a list.
     */
    public static function activeForEmail(string $email): array
    {
        $email = strtolower(trim($email));
        if ($email === '') return [];
        return static::rows(
            "SELECT ea.*, e.name AS event_name, e.name_regional AS event_name_regional,
                    e.code AS event_code, e.status AS event_status,
                    e.start_date, e.end_date, e.image AS event_image
               FROM event_admins ea
               JOIN events e ON e.id = ea.event_id
              WHERE ea.email = ? AND ea.status = 'active'
              ORDER BY COALESCE(e.start_date, '9999-12-31') DESC, e.name",
            [$email]
        );
    }
}
