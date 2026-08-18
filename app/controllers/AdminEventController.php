<?php
namespace Controllers;

use Core\{Controller, Auth, FileUpload};
use Models\{Schema, Event, EventAdmin, EventUser, AppSetting};

/**
 * Super Admin -> Events. Creating an event mints its Event Code, which is
 * what the event's own admins and users sign in with.
 */
class AdminEventController extends Controller
{
    private function boot(): void
    {
        try { Schema::ensureAll(); } catch (\Throwable $e) {}
        if (!Auth::check() || !Auth::is('super_admin')) {
            $this->redirect('/login', 'Please sign in to continue.', 'warning');
        }
    }

    /** Resolve a hashed URL id to an event row, 404ing on anything unknown. */
    private function eventOr404(string $hash): array
    {
        $event = Event::findById(hid_event_decode($hash));
        if (!$event) $this->abort(404);
        return $event;
    }

    // ── List / show ──────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->boot();

        $search = trim((string)($_GET['q'] ?? ''));
        $status = trim((string)($_GET['status'] ?? ''));

        $this->renderWith('app', 'admin/events/index', [
            'pageTitle' => 'Events',
            'events'    => Event::allWithCounts($search, $status),
            'search'    => $search,
            'status'    => $status,
        ]);
    }

    public function show(string $hash): void
    {
        $this->boot();
        $event = $this->eventOr404($hash);
        $event['code'] = $event['code'] ?: ensureEventCode((int)$event['id']);

        $this->renderWith('app', 'admin/events/show', [
            'pageTitle' => $event['name'],
            'event'     => $event,
            'admins'    => EventAdmin::forEvent((int)$event['id']),
            'users'     => EventUser::forEvent((int)$event['id']),
            'stats'     => Event::stats((int)$event['id']),
        ]);
    }

    // ── Create / update ──────────────────────────────────────────────────────

    public function createForm(): void
    {
        $this->boot();
        $this->renderWith('app', 'admin/events/form', [
            'pageTitle'    => 'Create Event',
            'event'        => null,
            'defaultLanes' => (int)(AppSetting::get('default_lanes', '6')),
        ]);
    }

    public function store(): void
    {
        $this->boot();
        $this->verifyCsrf();

        $errors = $this->validate([
            'name'       => 'required|max:200',
            'start_date' => 'date',
            'end_date'   => 'date',
        ]);
        if ($errors) $this->redirect('/admin/events/create', 'Please correct the highlighted fields.', 'error');

        $data = $this->collect();
        $data['created_by'] = (int)Auth::id();

        try {
            $image = $this->handleImageUpload();
            if ($image !== null) $data['image'] = $image;
        } catch (\RuntimeException $e) {
            $_SESSION['old'] = $_POST;
            $this->redirect('/admin/events/create', $e->getMessage(), 'error');
        }

        $id = Event::create($data);
        ensureEventCode($id);

        $this->redirect('/admin/events/' . hid_event($id),
            'Event created. Add an Event Admin account so the organiser can sign in.');
    }

    public function editForm(string $hash): void
    {
        $this->boot();
        $event = $this->eventOr404($hash);

        $this->renderWith('app', 'admin/events/form', [
            'pageTitle'    => 'Edit Event',
            'event'        => $event,
            'defaultLanes' => (int)$event['default_lanes'],
        ]);
    }

    public function update(string $hash): void
    {
        $this->boot();
        $this->verifyCsrf();
        $event = $this->eventOr404($hash);

        $errors = $this->validate([
            'name'       => 'required|max:200',
            'start_date' => 'date',
            'end_date'   => 'date',
        ]);
        if ($errors) {
            $this->redirect('/admin/events/' . hid_event((int)$event['id']) . '/edit',
                'Please correct the highlighted fields.', 'error');
        }

        $data = $this->collect();
        try {
            $image = $this->handleImageUpload();
            if ($image !== null) $data['image'] = $image;
        } catch (\RuntimeException $e) {
            $this->redirect('/admin/events/' . hid_event((int)$event['id']) . '/edit', $e->getMessage(), 'error');
        }

        Event::updateById((int)$event['id'], $data);
        $this->redirect('/admin/events/' . hid_event((int)$event['id']), 'Event updated.');
    }

    public function destroy(string $hash): void
    {
        $this->boot();
        $this->verifyCsrf();
        $event = $this->eventOr404($hash);

        // Deleting a live event would take its programme, draws and results
        // with it — insist the organiser archives it instead.
        if (($event['status'] ?? '') !== 'draft') {
            $this->redirect('/admin/events/' . hid_event((int)$event['id']),
                'Only a draft event can be deleted. Archive this event instead.', 'error');
        }

        Event::deleteById((int)$event['id']);
        $this->redirect('/admin/events', 'Event deleted.');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Whitelist of writable columns, normalised from $_POST. */
    private function collect(): array
    {
        $lanes  = (int)($_POST['default_lanes'] ?? 6);
        $slide  = (int)($_POST['slide_seconds'] ?? 9);
        $chroma = trim((string)($_POST['chroma_color'] ?? '#00b140'));
        $pin    = trim((string)($_POST['display_pin'] ?? ''));

        return [
            'name'          => trim((string)($_POST['name'] ?? '')),
            'name_regional' => trim((string)($_POST['name_regional'] ?? '')) ?: null,
            'venue'         => trim((string)($_POST['venue'] ?? '')) ?: null,
            'district'      => trim((string)($_POST['district'] ?? '')) ?: null,
            'organiser'     => trim((string)($_POST['organiser'] ?? '')) ?: null,
            'description'   => trim((string)($_POST['description'] ?? '')) ?: null,
            'start_date'    => trim((string)($_POST['start_date'] ?? '')) ?: null,
            'end_date'      => trim((string)($_POST['end_date'] ?? '')) ?: null,
            'status'        => array_key_exists((string)($_POST['status'] ?? ''), Event::STATUSES)
                                 ? (string)$_POST['status'] : 'draft',
            'default_lanes' => max(2, min(20, $lanes)),
            'chroma_color'  => preg_match('/^#[0-9a-fA-F]{6}$/', $chroma) ? $chroma : '#00b140',
            'display_pin'   => $pin !== '' ? substr($pin, 0, 12) : null,
            'slide_seconds' => max(3, min(60, $slide)),
        ];
    }

    /** Returns the stored URL, or null when no new file was chosen. */
    private function handleImageUpload(): ?string
    {
        if (empty($_FILES['image']['name'])) return null;
        return (new FileUpload())->upload($_FILES['image'], 'events', true);
    }
}
