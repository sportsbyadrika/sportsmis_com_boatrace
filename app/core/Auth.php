<?php
namespace Core;

/**
 * Session-based auth with three independent buckets, so a platform owner, an
 * event administrator and an event user can each be signed in without
 * clobbering one another:
 *
 *   $_SESSION['user']         -> platform accounts (super_admin) — users table
 *   $_SESSION['event_admin']  -> per-event administrator — event_admins table
 *   $_SESSION['event_user']   -> per-event privileged user — event_users table
 *
 * Event admins and event users sign in with an Event Code + email + password;
 * their credentials never touch the main users table. This mirrors the
 * SportsMIS `event_staff` bucket.
 */
class Auth
{
    // ── Platform accounts (super admin) ──────────────────────────────────────

    public static function check(): bool
    {
        return !empty($_SESSION['user']);
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function id(): ?int
    {
        return isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
    }

    public static function role(): ?string
    {
        return $_SESSION['user']['role'] ?? null;
    }

    public static function is(string ...$roles): bool
    {
        return in_array(static::role(), $roles, true);
    }

    /**
     * Capability set held by the signed-in platform account. The platform has
     * a single role today (super_admin); capabilities keep the door open for
     * additional platform hats without reworking every gate.
     */
    public static function capabilities(): array
    {
        $u = static::user();
        if (!$u) return [];
        if (isset($u['capabilities']) && is_array($u['capabilities'])) return $u['capabilities'];
        return ($u['role'] ?? '') === 'super_admin' ? ['admin'] : [];
    }

    public static function can(string $capability): bool
    {
        return in_array($capability, static::capabilities(), true);
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);
        unset($user['password']);
        $user['capabilities'] = ($user['role'] ?? '') === 'super_admin' ? ['admin'] : [];
        $_SESSION['user'] = $user;
        \Models\User::updateLastLogin((int)$user['id']);
    }

    public static function attempt(string $email, string $password): bool
    {
        $user = \Models\User::findByEmail($email);
        if (!$user || !password_verify($password, (string)$user['password'])) return false;
        if (($user['status'] ?? '') !== 'active') return false;
        static::login($user);
        return true;
    }

    public static function logout(): void
    {
        unset($_SESSION['user']);
    }

    /** Destroy every bucket and the session cookie. */
    public static function logoutAll(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function homeUrl(): string
    {
        return match (static::role()) {
            'super_admin' => '/admin/dashboard',
            default       => '/login',
        };
    }

    // ── Event Admin session (per event) ──────────────────────────────────────

    public static function eventAdminCheck(): bool
    {
        return !empty($_SESSION['event_admin']);
    }

    public static function eventAdmin(): ?array
    {
        return $_SESSION['event_admin'] ?? null;
    }

    public static function eventAdminLogin(array $admin): void
    {
        session_regenerate_id(true);
        $_SESSION['event_admin'] = [
            'id'       => (int)$admin['id'],
            'name'     => $admin['name'],
            'email'    => $admin['email'],
            'event_id' => (int)$admin['event_id'],
        ];
    }

    public static function eventAdminLogout(): void
    {
        unset($_SESSION['event_admin']);
    }

    // ── Event User session (per event, privilege-gated) ──────────────────────

    public static function eventUserCheck(): bool
    {
        return !empty($_SESSION['event_user']);
    }

    public static function eventUser(): ?array
    {
        return $_SESSION['event_user'] ?? null;
    }

    public static function eventUserLogin(array $user, array $privileges): void
    {
        session_regenerate_id(true);
        $_SESSION['event_user'] = [
            'id'         => (int)$user['id'],
            'name'       => $user['name'],
            'email'      => $user['email'],
            'event_id'   => (int)$user['event_id'],
            'privileges' => array_values($privileges),
        ];
    }

    public static function eventUserLogout(): void
    {
        unset($_SESSION['event_user']);
    }

    /** Does the signed-in event user hold the given privilege? */
    public static function eventUserCan(string $privilege): bool
    {
        $u = static::eventUser();
        return $u && in_array($privilege, $u['privileges'] ?? [], true);
    }

    // ── Password helpers ─────────────────────────────────────────────────────

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /** Readable one-time password for a freshly created account. */
    public static function generatePassword(int $length = 10): string
    {
        $chars = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789@#';
        $out = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < $length; $i++) $out .= $chars[random_int(0, $max)];
        return $out;
    }
}
