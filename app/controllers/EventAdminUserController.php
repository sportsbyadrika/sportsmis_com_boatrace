<?php
namespace Controllers;

use Core\Auth;
use Models\EventUser;

/**
 * Event Admin -> Event User accounts.
 *
 * Each account holds a subset of EventUser::PRIVILEGES; both the portal nav
 * and every race-office controller gate on that set, so an account with only
 * `result_entry` sees nothing else and cannot reach it by URL either.
 *
 * Accounts sign in on the single /login form with email and password; the
 * Event Code identifies the regatta but is not part of signing in.
 */
class EventAdminUserController extends EventAdminBase
{
    private function userOr404(string $hash): array
    {
        $user = EventUser::findById(hid_user_decode($hash));
        if (!$user || (int)$user['event_id'] !== $this->eventId()) $this->abort(404);
        return $user;
    }

    public function index(): void
    {
        $this->boot();

        $this->view('event-admin/users/index', [
            'pageTitle'  => 'Event Users',
            'users'      => EventUser::forEvent($this->eventId()),
            'privileges' => EventUser::PRIVILEGES,
        ]);
    }

    public function store(): void
    {
        $this->boot();
        $this->verifyCsrf();

        $errors = $this->validate([
            'name'  => 'required|max:150',
            'email' => 'required|email|max:190',
        ]);
        if ($errors) $this->redirect('/event-admin/users', 'Please correct the highlighted fields.', 'error');

        $email = strtolower(trim((string)$_POST['email']));
        if (EventUser::findByEventEmail($this->eventId(), $email)) {
            $this->redirect('/event-admin/users', 'That email already has an account on this event.', 'error');
        }

        $password = Auth::generatePassword(10);
        $id = EventUser::create([
            'event_id'    => $this->eventId(),
            'name'        => trim((string)$_POST['name']),
            'email'       => $email,
            'phone'       => trim((string)($_POST['phone'] ?? '')) ?: null,
            'designation' => trim((string)($_POST['designation'] ?? '')) ?: null,
            'password'    => Auth::hashPassword($password),
            'status'      => 'active',
        ]);
        EventUser::setPrivileges($id, (array)($_POST['privileges'] ?? []));

        // Shown once — the hash is all that is stored.
        $this->redirect('/event-admin/users', sprintf(
            'Account created. Sign in at %s · Email: %s · Temporary password: %s — share these now, they are not shown again.',
            url('/login'), $email, $password
        ));
    }

    public function update(string $hash): void
    {
        $this->boot();
        $this->verifyCsrf();
        $user = $this->userOr404($hash);

        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') $this->redirect('/event-admin/users', 'Name is required.', 'error');

        $status = (string)($_POST['status'] ?? 'active');
        EventUser::updateById((int)$user['id'], [
            'name'        => $name,
            'phone'       => trim((string)($_POST['phone'] ?? '')) ?: null,
            'designation' => trim((string)($_POST['designation'] ?? '')) ?: null,
            'status'      => in_array($status, ['active', 'disabled'], true) ? $status : 'active',
        ]);
        EventUser::setPrivileges((int)$user['id'], (array)($_POST['privileges'] ?? []));

        $this->redirect('/event-admin/users', 'Account updated.');
    }

    public function resetPassword(string $hash): void
    {
        $this->boot();
        $this->verifyCsrf();
        $user = $this->userOr404($hash);

        $password = Auth::generatePassword(10);
        EventUser::updateById((int)$user['id'], ['password' => Auth::hashPassword($password)]);

        $this->redirect('/event-admin/users', sprintf(
            'Password reset for %s. New temporary password: %s — share it now, it is not shown again.',
            $user['email'], $password
        ));
    }

    public function destroy(string $hash): void
    {
        $this->boot();
        $this->verifyCsrf();
        $user = $this->userOr404($hash);

        EventUser::deleteById((int)$user['id']);
        $this->redirect('/event-admin/users', 'Account removed.');
    }
}
