<?php
namespace Controllers;

use Core\{Controller, Auth};
use Models\{Schema, Event, EventUser};

/**
 * Shared gate for every race-office screen. boot() self-heals the schema,
 * checks the event_user session bucket, reloads the account's privileges
 * from the database (so a grant or revoke takes effect without signing out)
 * and loads the tenant event.
 *
 * Every action then calls requirePrivilege() — the nav hides what an account
 * can't do, and this stops it reaching the same page by URL.
 */
abstract class EventUserBase extends Controller
{
    protected array $user  = [];
    protected array $event = [];
    protected array $privileges = [];

    protected function boot(): void
    {
        try { Schema::ensureAll(); } catch (\Throwable $e) {}

        if (!Auth::eventUserCheck()) {
            $this->redirect('/login', 'Please sign in to continue.', 'warning');
        }

        $session = Auth::eventUser();

        // An event administrator may stand in for the race office. There is no
        // event_users row behind that, so the administrator's OWN session is
        // re-verified here on every request — the flag in the bucket is never
        // taken on trust, and the access dies with the session that granted it.
        if (!empty($session['via_admin'])) {
            $this->bootAsAdmin((int)$session['event_id']);
            return;
        }

        $user = EventUser::findById((int)$session['id']);
        if (!$user || $user['status'] !== 'active') {
            Auth::eventUserLogout();
            $this->redirect('/login', 'Your account is no longer active.', 'error');
        }

        $event = Event::findById((int)$user['event_id']);
        if (!$event) {
            Auth::eventUserLogout();
            $this->redirect('/login', 'That event no longer exists.', 'error');
        }
        $event['code'] = $event['code'] ?: ensureEventCode((int)$event['id']);

        // Re-read privileges rather than trusting the session copy, so a
        // change made by the event admin applies on the next request.
        $this->privileges = EventUser::privilegesFor((int)$user['id']);
        $_SESSION['event_user']['privileges'] = $this->privileges;

        $this->user  = $user;
        $this->event = $event;
    }

    /**
     * Race-office context for an event administrator standing in. They own the
     * event, so they hold every privilege while they are in here.
     */
    private function bootAsAdmin(int $eventId): void
    {
        if (!Auth::eventAdminCheck()) {
            Auth::eventUserLogout();
            $this->redirect('/login', 'Your administrator session has ended. Please sign in again.', 'warning');
        }

        $admin = \Models\EventAdmin::findById((int)Auth::eventAdmin()['id']);
        if (!$admin
            || $admin['status'] !== 'active'
            || (int)$admin['event_id'] !== $eventId) {
            Auth::eventUserLogout();
            $this->redirect('/event-admin/dashboard', 'That race-office access is no longer available.', 'error');
        }

        $event = Event::findById((int)$admin['event_id']);
        if (!$event) {
            Auth::eventUserLogout();
            $this->redirect('/login', 'That event no longer exists.', 'error');
        }
        $event['code'] = $event['code'] ?: ensureEventCode((int)$event['id']);

        $this->privileges = array_keys(EventUser::PRIVILEGES);
        $_SESSION['event_user']['privileges'] = $this->privileges;

        $this->user  = [
            'id'       => 0,
            'name'     => $admin['name'],
            'email'    => $admin['email'],
            'event_id' => (int)$admin['event_id'],
        ];
        $this->event = $event;
    }

    protected function requirePrivilege(string $privilege): void
    {
        if (in_array($privilege, $this->privileges, true)) return;
        if ($this->isAjax()) {
            $this->json(['success' => false, 'message' => 'You do not have permission to do that.'], 403);
        }
        $this->abort(403);
    }

    protected function can(string $privilege): bool
    {
        return in_array($privilege, $this->privileges, true);
    }

    protected function eventId(): int
    {
        return (int)$this->event['id'];
    }

    protected function view(string $view, array $data = []): void
    {
        $this->renderWith('event', $view, array_merge([
            'event'      => $this->event,
            'privileges' => $this->privileges,
        ], $data));
    }
}
