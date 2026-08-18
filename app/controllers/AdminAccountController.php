<?php
namespace Controllers;

use Core\{Controller, Auth};
use Models\{Schema, Event, EventAdmin};

/**
 * Super Admin -> Event Admin accounts. One event can carry several
 * administrators; each signs in with the Event Code + their email.
 *
 * Passwords are generated here and shown once, on the redirect — they are
 * never stored in clear and cannot be read back.
 */
class AdminAccountController extends Controller
{
    private function boot(): void
    {
        try { Schema::ensureAll(); } catch (\Throwable $e) {}
        if (!Auth::check() || !Auth::is('super_admin')) {
            $this->redirect('/login', 'Please sign in to continue.', 'warning');
        }
    }

    private function eventOr404(string $hash): array
    {
        $event = Event::findById(hid_event_decode($hash));
        if (!$event) $this->abort(404);
        return $event;
    }

    private function adminOr404(string $hash): array
    {
        $admin = EventAdmin::findById(hid_admin_decode($hash));
        if (!$admin) $this->abort(404);
        return $admin;
    }

    // ── Screens ──────────────────────────────────────────────────────────────

    /** Every event-admin account across the platform. */
    public function index(): void
    {
        $this->boot();
        $search = trim((string)($_GET['q'] ?? ''));

        $this->renderWith('app', 'admin/accounts/index', [
            'pageTitle' => 'Event Admin Accounts',
            'accounts'  => EventAdmin::allWithEvent($search),
            'events'    => Event::allWithCounts(),
            'search'    => $search,
        ]);
    }

    /** The accounts panel for one event (also linked from the event page). */
    public function forEvent(string $hash): void
    {
        $this->boot();
        $event = $this->eventOr404($hash);
        $event['code'] = $event['code'] ?: ensureEventCode((int)$event['id']);

        $this->renderWith('app', 'admin/accounts/event', [
            'pageTitle' => 'Event Admins — ' . $event['name'],
            'event'     => $event,
            'accounts'  => EventAdmin::forEvent((int)$event['id']),
        ]);
    }

    // ── Mutations ────────────────────────────────────────────────────────────

    public function store(string $hash): void
    {
        $this->boot();
        $this->verifyCsrf();
        $event  = $this->eventOr404($hash);
        $backTo = '/admin/events/' . hid_event((int)$event['id']) . '/admins';

        $errors = $this->validate([
            'name'  => 'required|max:150',
            'email' => 'required|email|max:190',
        ]);
        if ($errors) $this->redirect($backTo, 'Please correct the highlighted fields.', 'error');

        $email = strtolower(trim((string)$_POST['email']));
        if (EventAdmin::findByEventEmail((int)$event['id'], $email)) {
            $this->redirect($backTo, 'That email already has an admin account on this event.', 'error');
        }

        $password = Auth::generatePassword(10);
        EventAdmin::create([
            'event_id' => (int)$event['id'],
            'name'     => trim((string)$_POST['name']),
            'email'    => $email,
            'phone'    => trim((string)($_POST['phone'] ?? '')) ?: null,
            'password' => Auth::hashPassword($password),
            'status'   => 'active',
        ]);
        ensureEventCode((int)$event['id']);

        // Shown once, in the flash — there is no way to retrieve it later.
        $this->redirect($backTo, sprintf(
            'Account created. Event Code: %s · Email: %s · Temporary password: %s — share these now, they are not shown again.',
            $event['code'] ?: ensureEventCode((int)$event['id']), $email, $password
        ));
    }

    public function update(string $hash): void
    {
        $this->boot();
        $this->verifyCsrf();
        $admin  = $this->adminOr404($hash);
        $backTo = '/admin/events/' . hid_event((int)$admin['event_id']) . '/admins';

        $name   = trim((string)($_POST['name'] ?? ''));
        $phone  = trim((string)($_POST['phone'] ?? ''));
        $status = (string)($_POST['status'] ?? 'active');

        if ($name === '') $this->redirect($backTo, 'Name is required.', 'error');

        EventAdmin::updateById((int)$admin['id'], [
            'name'   => $name,
            'phone'  => $phone ?: null,
            'status' => in_array($status, ['active', 'disabled'], true) ? $status : 'active',
        ]);
        $this->redirect($backTo, 'Account updated.');
    }

    public function resetPassword(string $hash): void
    {
        $this->boot();
        $this->verifyCsrf();
        $admin  = $this->adminOr404($hash);
        $backTo = '/admin/events/' . hid_event((int)$admin['event_id']) . '/admins';

        $password = Auth::generatePassword(10);
        EventAdmin::updateById((int)$admin['id'], ['password' => Auth::hashPassword($password)]);

        $this->redirect($backTo, sprintf(
            'Password reset for %s. New temporary password: %s — share it now, it is not shown again.',
            $admin['email'], $password
        ));
    }

    public function destroy(string $hash): void
    {
        $this->boot();
        $this->verifyCsrf();
        $admin  = $this->adminOr404($hash);
        $backTo = '/admin/events/' . hid_event((int)$admin['event_id']) . '/admins';

        EventAdmin::deleteById((int)$admin['id']);
        $this->redirect($backTo, 'Account removed.');
    }
}
