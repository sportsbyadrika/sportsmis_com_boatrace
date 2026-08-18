<?php
namespace Controllers;

use Models\{Event, EventUser, Round};

/**
 * Race-office home. The dashboard cards are built from the account's held
 * privileges, so it shows exactly what this user can actually open.
 */
class EventUserController extends EventUserBase
{
    public function dashboard(): void
    {
        $this->boot();

        $this->view('event-user/dashboard', [
            'pageTitle' => 'Race Office',
            'stats'     => Event::stats($this->eventId()),
            'rounds'    => array_slice(Round::forEvent($this->eventId()), 0, 8),
            'cards'     => $this->cards(),
        ]);
    }

    /** [title, blurb, icon, href] for each privilege this account holds. */
    private function cards(): array
    {
        $out = [];
        foreach (EventUser::PRIVILEGES as $key => $label) {
            if (!$this->can($key)) continue;
            [$icon, $href, $blurb] = EventUser::PRIVILEGE_META[$key];
            $out[] = ['title' => $label, 'blurb' => $blurb, 'icon' => $icon, 'href' => $href];
        }
        return $out;
    }
}
