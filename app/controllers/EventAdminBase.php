<?php
namespace Controllers;

use Core\{Controller, Auth};
use Models\{Schema, Event};

/**
 * Shared gate for every Event Admin screen. Each concrete controller calls
 * boot() first; it self-heals the schema, checks the event_admin session
 * bucket and loads the tenant event into $this->event.
 */
abstract class EventAdminBase extends Controller
{
    protected array $admin = [];
    protected array $event = [];

    protected function boot(): void
    {
        try { Schema::ensureAll(); } catch (\Throwable $e) {}

        if (!Auth::eventAdminCheck()) {
            $this->redirect('/event-admin/login', 'Please sign in to continue.', 'warning');
        }

        $session = Auth::eventAdmin();
        $admin   = \Models\EventAdmin::findById((int)$session['id']);
        if (!$admin || $admin['status'] !== 'active') {
            Auth::eventAdminLogout();
            $this->redirect('/event-admin/login', 'Your account is no longer active.', 'error');
        }

        $event = Event::findById((int)$admin['event_id']);
        if (!$event) {
            Auth::eventAdminLogout();
            $this->redirect('/event-admin/login', 'That event no longer exists.', 'error');
        }
        $event['code'] = $event['code'] ?: ensureEventCode((int)$event['id']);

        $this->admin = $admin;
        $this->event = $event;
    }

    /** Convenience for the many per-event queries below. */
    protected function eventId(): int
    {
        return (int)$this->event['id'];
    }

    /** Merge the layout's required data into every render. */
    protected function view(string $view, array $data = []): void
    {
        $this->renderWith('event', $view, array_merge(['event' => $this->event], $data));
    }
}
