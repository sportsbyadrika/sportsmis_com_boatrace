<?php
namespace Controllers;

use Core\{Auth, FileUpload};
use Models\{Team, TeamRegistration};

/**
 * Event Admin -> Teams and their registrations.
 *
 * Creating a team also opens its registration row, so a boat always has a
 * reviewable state. Only an APPROVED registration can be drawn into a lane.
 */
class EventAdminTeamController extends EventAdminBase
{
    private function teamOr404(string $hash): array
    {
        $team = Team::findForEvent($this->eventId(), hid_team_decode($hash));
        if (!$team) $this->abort(404);
        return $team;
    }

    private function registrationOr404(string $hash): array
    {
        $reg = TeamRegistration::findForEvent($this->eventId(), hid_reg_decode($hash));
        if (!$reg) $this->abort(404);
        return $reg;
    }

    // ── Teams ────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->boot();
        $search = trim((string)($_GET['q'] ?? ''));
        $status = trim((string)($_GET['status'] ?? ''));

        $this->view('event-admin/teams/index', [
            'pageTitle' => 'Teams',
            'teams'     => Team::forEvent($this->eventId(), $search, $status),
            'search'    => $search,
            'status'    => $status,
        ]);
    }

    public function createForm(): void
    {
        $this->boot();
        $this->view('event-admin/teams/form', ['pageTitle' => 'Add Team', 'team' => null]);
    }

    public function store(): void
    {
        $this->boot();
        $this->verifyCsrf();

        $errors = $this->validate([
            'club_name'    => 'required|max:200',
            'boat_name'    => 'required|max:200',
            'captain_name' => 'required|max:150',
            'contact_email'=> 'email|max:190',
        ]);
        if ($errors) $this->redirect('/event-admin/teams/create', 'Please correct the highlighted fields.', 'error');

        $data = $this->collect();
        $data['event_id'] = $this->eventId();

        try {
            $logo = $this->handleLogoUpload();
            if ($logo !== null) $data['logo'] = $logo;
        } catch (\RuntimeException $e) {
            $_SESSION['old'] = $_POST;
            $this->redirect('/event-admin/teams/create', $e->getMessage(), 'error');
        }

        $teamId = Team::create($data);
        TeamRegistration::ensureFor($this->eventId(), $teamId, 'draft');

        $this->redirect('/event-admin/teams', 'Team added. Submit its registration when the entry is complete.');
    }

    public function editForm(string $hash): void
    {
        $this->boot();
        $this->view('event-admin/teams/form', [
            'pageTitle' => 'Edit Team',
            'team'      => $this->teamOr404($hash),
        ]);
    }

    public function update(string $hash): void
    {
        $this->boot();
        $this->verifyCsrf();
        $team = $this->teamOr404($hash);

        $errors = $this->validate([
            'club_name'    => 'required|max:200',
            'boat_name'    => 'required|max:200',
            'captain_name' => 'required|max:150',
            'contact_email'=> 'email|max:190',
        ]);
        if ($errors) {
            $this->redirect('/event-admin/teams/' . hid_team((int)$team['id']) . '/edit',
                'Please correct the highlighted fields.', 'error');
        }

        $data = $this->collect();
        try {
            $logo = $this->handleLogoUpload();
            if ($logo !== null) $data['logo'] = $logo;
        } catch (\RuntimeException $e) {
            $this->redirect('/event-admin/teams/' . hid_team((int)$team['id']) . '/edit', $e->getMessage(), 'error');
        }

        Team::updateById((int)$team['id'], $data);
        TeamRegistration::ensureFor($this->eventId(), (int)$team['id'], 'draft');

        $this->redirect('/event-admin/teams', 'Team updated.');
    }

    public function destroy(string $hash): void
    {
        $this->boot();
        $this->verifyCsrf();
        $team = $this->teamOr404($hash);

        // Deleting a drawn boat would silently empty a lane and orphan its
        // recorded result — make the race office undo the draw first.
        if (Team::isAllocated((int)$team['id'])) {
            $this->redirect('/event-admin/teams',
                'This boat is already drawn into a lane. Remove it from the lane draw before deleting it.', 'error');
        }

        Team::deleteById((int)$team['id']);
        $this->redirect('/event-admin/teams', 'Team removed.');
    }

    // ── Registrations ────────────────────────────────────────────────────────

    public function registrations(): void
    {
        $this->boot();
        $status = trim((string)($_GET['status'] ?? ''));

        $this->view('event-admin/registrations/index', [
            'pageTitle'     => 'Team Registrations',
            'registrations' => TeamRegistration::forEvent($this->eventId(), $status),
            'counts'        => TeamRegistration::countsForEvent($this->eventId()),
            'status'        => $status,
        ]);
    }

    /**
     * One endpoint for the whole workflow — submit / approve / return —
     * so the review screen posts the same shape whichever button was used.
     */
    public function decide(string $hash): void
    {
        $this->boot();
        $this->verifyCsrf();
        $reg    = $this->registrationOr404($hash);
        $action = (string)($_POST['action'] ?? '');
        $by     = (string)($this->admin['name'] ?? $this->admin['email'] ?? 'Event Admin');

        switch ($action) {
            case 'submit':
                TeamRegistration::submit((int)$reg['id']);
                $message = 'Registration submitted for review.';
                break;

            case 'approve':
                TeamRegistration::approve((int)$reg['id'], $by);
                $message = 'Registration approved — this boat can now be entered into races.';
                break;

            case 'return':
                $remarks = trim((string)($_POST['remarks'] ?? ''));
                if ($remarks === '') {
                    $this->respond(false, 'Say what needs changing before returning the registration.');
                }
                TeamRegistration::returnForChanges((int)$reg['id'], $by, $remarks);
                $message = 'Registration returned for changes.';
                break;

            case 'reopen':
                TeamRegistration::updateById((int)$reg['id'], ['status' => 'draft', 'remarks' => null]);
                $message = 'Registration reopened as a draft.';
                break;

            default:
                $this->respond(false, 'Unknown action.');
                return;
        }

        $this->respond(true, $message);
    }

    /** Approve every submitted registration in one go. */
    public function approveAll(): void
    {
        $this->boot();
        $this->verifyCsrf();

        $by  = (string)($this->admin['name'] ?? $this->admin['email'] ?? 'Event Admin');
        $n   = 0;
        foreach (TeamRegistration::forEvent($this->eventId(), 'submitted') as $reg) {
            TeamRegistration::approve((int)$reg['id'], $by);
            $n++;
        }
        $this->redirect('/event-admin/registrations',
            $n ? "Approved {$n} registration" . ($n === 1 ? '.' : 's.') : 'Nothing was awaiting review.');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Answer JSON to fetch() callers, a flash redirect to plain form posts. */
    private function respond(bool $ok, string $message): void
    {
        if ($this->isAjax()) {
            $this->json(['success' => $ok, 'message' => $message], $ok ? 200 : 422);
        }
        $this->redirect('/event-admin/registrations', $message, $ok ? 'success' : 'error');
    }

    private function collect(): array
    {
        return [
            'club_name'     => trim((string)($_POST['club_name'] ?? '')),
            'boat_name'     => trim((string)($_POST['boat_name'] ?? '')),
            'captain_name'  => trim((string)($_POST['captain_name'] ?? '')),
            'boat_class'    => trim((string)($_POST['boat_class'] ?? '')) ?: null,
            'home_place'    => trim((string)($_POST['home_place'] ?? '')) ?: null,
            'short_code'    => strtoupper(trim((string)($_POST['short_code'] ?? ''))) ?: null,
            'contact_name'  => trim((string)($_POST['contact_name'] ?? '')) ?: null,
            'contact_phone' => trim((string)($_POST['contact_phone'] ?? '')) ?: null,
            'contact_email' => strtolower(trim((string)($_POST['contact_email'] ?? ''))) ?: null,
            'status'        => ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
        ];
    }

    private function handleLogoUpload(): ?string
    {
        if (empty($_FILES['logo']['name'])) return null;
        return (new FileUpload())->upload($_FILES['logo'], 'teams', true);
    }
}
