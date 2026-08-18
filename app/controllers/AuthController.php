<?php
namespace Controllers;

use Core\{Controller, Auth};
use Models\{Schema, User, EventAdmin, EventUser};

/**
 * One sign-in page for everybody.
 *
 * The three roles used to have a page each, which meant the login screen
 * advertised the whole role structure to anyone who loaded it. Instead there
 * is a single email + password form, and the server works out which account
 * the credentials belong to by checking all three tables:
 *
 *   users        -> platform account   -> $_SESSION['user']
 *   event_admins -> event administrator -> $_SESSION['event_admin']
 *   event_users  -> race office         -> $_SESSION['event_user']
 *
 * The Event Code is no longer typed at sign-in. It is still the tenant's
 * public handle — it identifies the event in the portal chrome and opens the
 * display screens — but it is not a credential.
 *
 * One address can hold accounts on several regattas, since uniqueness is only
 * per (event_id, email). When the password matches more than one account the
 * user picks from a short list. That step runs strictly AFTER the password has
 * been verified, so it tells an unauthenticated visitor nothing.
 */
class AuthController extends Controller
{
    /** How long a verified but unresolved sign-in may sit at the chooser. */
    private const CHOICE_TTL = 300;   // seconds

    private function boot(): void
    {
        try {
            Schema::ensureUsers();
            Schema::ensureEvents();
            Schema::ensureEventAdmins();
            Schema::ensureEventUsers();
        } catch (\Throwable $e) {
            // A missing database is reported by the exception handler; the
            // login page itself must still render.
        }
    }

    // ── Sign in ──────────────────────────────────────────────────────────────

    public function loginForm(): void
    {
        $this->boot();
        if (Auth::check())          $this->redirect(Auth::homeUrl());
        if (Auth::eventAdminCheck()) $this->redirect('/event-admin/dashboard');
        if (Auth::eventUserCheck())  $this->redirect('/event-user/dashboard');

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

        $candidates = $this->resolveCandidates($email, $password);

        // One message for every failure, so nothing distinguishes "no such
        // address" from "wrong password" from "account disabled".
        if (!$candidates) {
            $_SESSION['old'] = ['email' => $email];
            $this->redirect('/login', 'Invalid email or password.', 'error');
        }

        if (count($candidates) === 1) {
            $this->signIn($candidates[0]);
            return;
        }

        $_SESSION['login_choices'] = ['at' => time(), 'items' => $candidates];
        $this->redirect('/login/choose');
    }

    /** The chooser, shown only when one password opens several accounts. */
    public function chooseForm(): void
    {
        $this->boot();
        $choices = $this->pendingChoices();
        if (!$choices) {
            $this->redirect('/login', 'That sign-in has expired. Please sign in again.', 'warning');
        }
        $this->renderWith('auth', 'auth/choose', [
            'pageTitle' => 'Choose an event',
            'choices'   => $choices,
        ]);
    }

    public function choose(): void
    {
        $this->boot();
        $this->verifyCsrf();

        $choices = $this->pendingChoices();
        if (!$choices) {
            $this->redirect('/login', 'That sign-in has expired. Please sign in again.', 'warning');
        }

        // Only an index into the list this session already verified is
        // accepted — the POST cannot name an arbitrary account.
        $index = (int)($_POST['choice'] ?? -1);
        if (!isset($choices[$index])) {
            $this->redirect('/login/choose', 'Choose where to sign in.', 'error');
        }

        unset($_SESSION['login_choices']);
        $this->signIn($choices[$index]);
    }

    // ── Resolution ───────────────────────────────────────────────────────────

    /**
     * Every account this email + password opens, across all three tables.
     *
     * Each candidate is stored by id only — no password material reaches the
     * session — and is re-read from the database before the actual sign-in.
     */
    private function resolveCandidates(string $email, string $password): array
    {
        $out = [];

        $user = User::findByEmail($email);
        if ($user
            && ($user['status'] ?? '') === 'active'
            && password_verify($password, (string)$user['password'])) {
            $out[] = [
                'kind'  => 'admin',
                'id'    => (int)$user['id'],
                'title' => 'Platform administration',
                'sub'   => 'All events on this installation',
                'icon'  => 'bi-shield-lock',
                'code'  => '',
            ];
        }

        foreach (EventAdmin::activeForEmail($email) as $row) {
            if (!password_verify($password, (string)$row['password'])) continue;
            $out[] = [
                'kind'  => 'event_admin',
                'id'    => (int)$row['id'],
                'title' => (string)$row['event_name'],
                'sub'   => 'Event administration',
                'icon'  => 'bi-person-badge',
                'code'  => (string)($row['event_code'] ?? ''),
            ];
        }

        foreach (EventUser::activeForEmail($email) as $row) {
            if (!password_verify($password, (string)$row['password'])) continue;
            $out[] = [
                'kind'  => 'event_user',
                'id'    => (int)$row['id'],
                'title' => (string)$row['event_name'],
                'sub'   => 'Race office',
                'icon'  => 'bi-people',
                'code'  => (string)($row['event_code'] ?? ''),
            ];
        }

        return $out;
    }

    /** Open the session bucket the candidate belongs to and go to its home. */
    private function signIn(array $candidate): void
    {
        $id = (int)($candidate['id'] ?? 0);

        switch ($candidate['kind'] ?? '') {
            case 'admin':
                $user = User::findById($id);
                if (!$user || ($user['status'] ?? '') !== 'active') break;
                Auth::login($user);
                $this->redirect(Auth::homeUrl());
                return;

            case 'event_admin':
                $admin = EventAdmin::findById($id);
                if (!$admin || $admin['status'] !== 'active') break;
                EventAdmin::updateLastLogin($id);
                Auth::eventAdminLogin($admin);
                $this->redirect('/event-admin/dashboard');
                return;

            case 'event_user':
                $eventUser = EventUser::findById($id);
                if (!$eventUser || $eventUser['status'] !== 'active') break;
                EventUser::updateLastLogin($id);
                Auth::eventUserLogin($eventUser, EventUser::privilegesFor($id));
                $this->redirect('/event-user/dashboard');
                return;
        }

        // The account changed between verifying the password and landing here.
        $this->redirect('/login', 'That account is no longer available.', 'error');
    }

    /** Verified-but-unresolved sign-in, if one is still fresh. */
    private function pendingChoices(): array
    {
        $pending = $_SESSION['login_choices'] ?? null;
        if (!is_array($pending)) return [];

        if (time() - (int)($pending['at'] ?? 0) > self::CHOICE_TTL) {
            unset($_SESSION['login_choices']);
            return [];
        }
        return is_array($pending['items'] ?? null) ? $pending['items'] : [];
    }

    // ── Sign out ─────────────────────────────────────────────────────────────

    /** Ends the platform session; per-event buckets keep their own. */
    public function logout(): void
    {
        Auth::logout();
        $this->redirect('/login', 'You have been signed out.');
    }

    public function eventAdminLogout(): void
    {
        Auth::eventAdminLogout();
        $this->redirect('/login', 'You have been signed out.');
    }

    public function eventUserLogout(): void
    {
        Auth::eventUserLogout();
        $this->redirect('/login', 'You have been signed out.');
    }

    // ── Password changes ─────────────────────────────────────────────────────

    /** Super admin, posted from the admin layout's modal. */
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

    public function eventAdminPassword(): void
    {
        $this->boot();
        $this->verifyCsrf();
        if (!Auth::eventAdminCheck()) $this->redirect('/login');

        $id    = (int)Auth::eventAdmin()['id'];
        $admin = EventAdmin::findById($id);
        [$ok, $message] = $this->validatePasswordChange($admin['password'] ?? '');
        if (!$ok) $this->redirect('/event-admin/dashboard', $message, 'error');

        EventAdmin::updateById($id, ['password' => Auth::hashPassword((string)$_POST['password'])]);
        $this->redirect('/event-admin/dashboard', 'Your password has been updated.');
    }

    public function eventUserPassword(): void
    {
        $this->boot();
        $this->verifyCsrf();
        if (!Auth::eventUserCheck()) $this->redirect('/login');

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
