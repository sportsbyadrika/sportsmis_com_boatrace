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
        $code = (string)$this->event['code'];
        $dir  = \Services\ResultSnapshot::directory($code);

        $manifest = null;
        if (is_file($dir . '/manifest.json')) {
            $manifest = json_decode((string)@file_get_contents($dir . '/manifest.json'), true);
        }

        $this->view('event-user/displays/index', [
            'pageTitle' => 'Display Screens',
            'wallUrl'   => '/display/' . $hash . '/wall',
            'streamUrl' => '/display/' . $hash . '/stream',
            'publicUrl' => \Services\ResultSnapshot::urlPath($code) . '/',
            'manifest'  => is_array($manifest) ? $manifest : null,
            'published' => \Models\Result::publishedRounds($this->eventId(), 20),
        ]);
    }

    /**
     * Rebuild the public results snapshot by hand.
     *
     * It is also rebuilt automatically whenever a round is published, so this
     * is for the cases automation cannot see — a race image swapped, a team
     * renamed, or a snapshot that needs forcing after a failure.
     */
    public function publishSnapshot(): void
    {
        $this->boot();
        $this->requirePrivilege('displays');
        $this->verifyCsrf();

        try {
            $result = \Services\ResultSnapshot::publish($this->eventId());
        } catch (\Throwable $e) {
            $this->redirect('/event-user/displays',
                'Could not publish the public results: ' . $e->getMessage(), 'error');
        }

        $this->redirect('/event-user/displays', sprintf(
            'Public results published — version %d, %d race%s, %s.',
            $result['version'], $result['races'], $result['races'] === 1 ? '' : 's',
            $result['bytes'] > 1024 ? round($result['bytes'] / 1024) . ' KB' : $result['bytes'] . ' bytes'
        ));
    }
}
