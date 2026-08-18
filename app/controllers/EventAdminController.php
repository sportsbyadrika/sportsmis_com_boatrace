<?php
namespace Controllers;

use Core\{Auth, FileUpload};
use Models\{Event, EventUser, TeamRegistration, EventRace};

/**
 * Event Admin home + event configuration.
 *
 * The details screen saves section by section over AJAX (the SportsMIS event
 * config pattern): each panel posts only its own fields, so a long form never
 * has to be completed in one go.
 */
class EventAdminController extends EventAdminBase
{
    /** Fields each details panel is allowed to write. */
    private const PANELS = [
        'identity' => ['name', 'name_regional', 'description'],
        'schedule' => ['start_date', 'end_date', 'venue', 'district', 'organiser'],
        'racing'   => ['default_lanes'],
        'display'  => ['chroma_color', 'slide_seconds', 'display_pin'],
    ];

    public function dashboard(): void
    {
        $this->boot();

        $this->view('event-admin/dashboard', [
            'pageTitle' => 'Dashboard',
            'stats'     => Event::stats($this->eventId()),
            'pending'   => TeamRegistration::forEvent($this->eventId(), 'submitted'),
            'upcoming'  => EventRace::upcoming($this->eventId(), 5),
            'users'     => EventUser::forEvent($this->eventId()),
        ]);
    }

    public function details(): void
    {
        $this->boot();

        $this->view('event-admin/details', [
            'pageTitle' => 'Event Details',
        ]);
    }

    /** AJAX section save. Only the named panel's fields are written. */
    public function saveSection(string $panel): void
    {
        $this->boot();
        $this->verifyCsrf();

        if (!isset(self::PANELS[$panel])) {
            $this->json(['success' => false, 'message' => 'Unknown section.'], 400);
        }

        $data = [];
        foreach (self::PANELS[$panel] as $field) {
            if (!array_key_exists($field, $_POST)) continue;
            $data[$field] = $this->normalise($field, (string)$_POST[$field]);
        }

        if ($panel === 'identity' && ($data['name'] ?? '') === '') {
            $this->json(['success' => false, 'message' => 'Event name is required.'], 422);
        }
        if ($panel === 'schedule'
            && !empty($data['start_date']) && !empty($data['end_date'])
            && $data['end_date'] < $data['start_date']) {
            $this->json(['success' => false, 'message' => 'The end date cannot fall before the start date.'], 422);
        }

        if ($data) Event::updateById($this->eventId(), $data);
        $this->json(['success' => true, 'message' => 'Saved.']);
    }

    /** Event image upload — a normal POST because it carries a file. */
    public function saveImage(): void
    {
        $this->boot();
        $this->verifyCsrf();

        if (empty($_FILES['image']['name'])) {
            $this->redirect('/event-admin/details', 'Choose an image first.', 'error');
        }
        try {
            $url = (new FileUpload())->upload($_FILES['image'], 'events', true);
        } catch (\RuntimeException $e) {
            $this->redirect('/event-admin/details', $e->getMessage(), 'error');
        }
        Event::updateById($this->eventId(), ['image' => $url]);
        $this->redirect('/event-admin/details', 'Event image updated.');
    }

    /** Per-field normalisation, keeping the stored values well-formed. */
    private function normalise(string $field, string $raw): mixed
    {
        $v = trim($raw);
        return match ($field) {
            'default_lanes' => max(2, min(20, (int)$v)),
            'slide_seconds' => max(3, min(60, (int)$v)),
            'chroma_color'  => preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? $v : '#00b140',
            'display_pin'   => $v !== '' ? substr($v, 0, 12) : null,
            'name'          => $v,
            default         => $v !== '' ? $v : null,
        };
    }
}
