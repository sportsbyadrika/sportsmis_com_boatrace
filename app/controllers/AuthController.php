<?php
namespace Controllers;

use Core\{Controller, Auth};
use Models\{Schema, User, EventAdmin, EventUser};

/**
 * Sign-in for all three roles. Each area writes to its own session bucket
 * (see Core\Auth), so a platform owner testing an event portal never loses
 * their admin session.
 *
 *   /login              -> super admin  (email + password)
 *   /event-admin/login  -> event admin  (event code + email + password)
 *   /event-user/login   -> event user   (event code + email + password)
 */
class AuthController extends Controller
{
    private function boot(): void
    {
        try {
            Schema::ensureUsers();
            Schema::ensureEvents();
            Schema::ensureEventAdmins();
            Schema::ensureEventUsers();
        } catch (\Throwable $e) {
            // A missing database is reported by the exception handler; the
            // login page itself must still render for the diagnostics link.
        }
    }

    // ── Super admin ──────────────────────────────────────────────────────────

    public function loginForm(): void
    {
        $this->boot();
        if (Auth::check()) $this->redirect(Auth::homeUrl());
        $this->renderWith('auth', 'auth/login', ['pageTitle' => 'Sign in']);
    }

    public function login(): void
    {
        $this->boot();
        $this->verifyCsrf();

        $email    = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            $_SESSION['old'] = ['email' => $email];
            $this->redirect('/login', 'Enter your email and password.', 'error');
        }
        if (!Auth::attempt($email, $password)) {
            $_SESSION['old'] = ['email' => $email];
            $this->redirect('/login', 'Invalid email or password.', 'error');
        }
        $this->redirect(Auth::homeUrl());
    }

    // ── Event admin ──────────────────────────────────────────────────────────

    public function eventAdminLoginForm(): void
    {
        $this->boot();
        if (Auth::eventAdminCheck()) $this->redirect('/event-admin/dashboard');
        $this->renderWith('auth', 'auth/event-admin-login', ['pageTitle' => 'Event Admin sign in']);
    }

    public function eventAdminLogin(): void
    {
        $this->boot();
        $this->verifyCsrf();

        $code     = strtoupper(trim((string)($_POST['event_code'] ?? '')));
        $email    = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');

        $admin = EventAdmin::attempt($code, $email, $password);
        if (!$admin) {
            $_SESSION['old'] = ['event_code' => $code, 'email' => $email];
            $this->redirect('/event-admin/login', 'Invalid Event Code, email or password.', 'error');
        }
        Auth::eventAdminLogin($admin);
        $this->redirect('/event-admin/dashboard');
    }

    public function eventAdminLogout(): void
    {
        Auth::eventAdminLogout();
        $this->redirect('/event-admin/login', 'You have been signed out.');
    }

    // ── Event user ───────────────────────────────────────────────────────────

    public function eventUserLoginForm(): void
    {
        $this->boot();
        if (Auth::eventUserCheck()) $this->redirect('/event-user/dashboard');
        $this->renderWith('auth', 'auth/event-user-login', ['pageTitle' => 'Event User sign in']);
    }

    public function eventUserLogin(): void
    {
        $this->boot();
        $this->verifyCsrf();

        $code     = strtoupper(trim((string)($_POST['event_code'] ?? '')));
        $email    = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');

        $user = EventUser::attempt($code, $email, $password);
        if (!$user) {
            $_SESSION['old'] = ['event_code' => $code, 'email' => $email];
            $this->redirect('/event-user/login', 'Invalid Event Code, email or password.', 'error');
        }
        Auth::eventUserLogin($user, EventUser::privilegesFor((int)$user['id']));
        $this->redirect('/event-user/dashboard');
    }

    public function eventUserLogout(): void
    {
        Auth::eventUserLogout();
        $this->redirect('/event-user/login', 'You have been signed out.');
    }

    // ── Shared ───────────────────────────────────────────────────────────────

    /** Signs the platform account out; the per-event buckets keep their own. */
    public function logout(): void
    {
        Auth::logout();
        $this->redirect('/login', 'You have been signed out.');
    }

    /** Super admin password change (posted from the admin layout's modal). */
    public function changePassword(): void
    {
        $this->boot();
        $this->verifyCsrf();
        if (!Auth::check()) $this->redirect('/login');

        $user = User::findById((int)Auth::id());
        [$ok, $message] = $this->validatePasswordChange($user['password'] ?? '');
        if (!$ok) $this->redirect('/admin/dashboard', $message, 'error');

        User::updatePassword((int)$user['id'], Auth::hashPassword((string)$_POST['password']));
        $this->redirect('/admin/dashboard', 'Your password has been updated.');
    }

    /** Event admin password change. */
    public function eventAdminPassword(): void
    {
        $this->boot();
        $this->verifyCsrf();
        if (!Auth::eventAdminCheck()) $this->redirect('/event-admin/login');

        $id    = (int)Auth::eventAdmin()['id'];
        $admin = EventAdmin::findById($id);
        [$ok, $message] = $this->validatePasswordChange($admin['password'] ?? '');
        if (!$ok) $this->redirect('/event-admin/dashboard', $message, 'error');

        EventAdmin::updateById($id, ['password' => Auth::hashPassword((string)$_POST['password'])]);
        $this->redirect('/event-admin/dashboard', 'Your password has been updated.');
    }

    /** Event user password change. */
    public function eventUserPassword(): void
    {
        $this->boot();
        $this->verifyCsrf();
        if (!Auth::eventUserCheck()) $this->redirect('/event-user/login');

        $id   = (int)Auth::eventUser()['id'];
        $user = EventUser::findById($id);
        [$ok, $message] = $this->validatePasswordChange($user['password'] ?? '');
        if (!$ok) $this->redirect('/event-user/dashboard', $message, 'error');

        EventUser::updateById($id, ['password' => Auth::hashPassword((string)$_POST['password'])]);
        $this->redirect('/event-user/dashboard', 'Your password has been updated.');
    }

    /** Shared rules for the three change-password modals. */
    private function validatePasswordChange(string $currentHash): array
    {
        $current = (string)($_POST['current_password'] ?? '');
        $new     = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['password_confirmation'] ?? '');

        if (!password_verify($current, $currentHash)) return [false, 'Your current password is incorrect.'];
        if (strlen($new) < 8)                          return [false, 'The new password must be at least 8 characters.'];
        if ($new !== $confirm)                         return [false, 'The new passwords do not match.'];
        return [true, ''];
    }
}
