<?php
namespace Controllers;

/**
 * Race office -> Displays (privilege: displays).
 *
 * A launcher, not a screen: it hands the operator the two public URLs, the
 * PIN they will be asked for, and a quick way to open each in a new window.
 * The screens themselves live in DisplayController and need no app session,
 * so a venue machine can be pointed at a URL once and left alone.
 */
class EventUserDisplayController extends EventUserBase
{
    public function index(): void
    {
        $this->boot();
        $this->requirePrivilege('displays');

        $hash = hid_event($this->eventId());

        $this->view('event-user/displays/index', [
            'pageTitle' => 'Display Screens',
            'wallUrl'   => '/display/' . $hash . '/wall',
            'streamUrl' => '/display/' . $hash . '/stream',
            'published' => \Models\Result::publishedRounds($this->eventId(), 20),
        ]);
    }
}
