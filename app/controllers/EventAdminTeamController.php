<?php
namespace Controllers;

use Core\FileUpload;
use Models\{Team, TeamRegistration};
use Services\TeamImport;

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

    // ── Bulk upload ──────────────────────────────────────────────────────────
    // Two steps on purpose: a spreadsheet is never written straight to the
    // database. The upload is parsed and shown back row by row, and only the
    // confirm step writes — in one transaction.

    /** How long a parsed-but-unconfirmed upload stays available. */
    private const IMPORT_TTL = 900;   // 15 minutes

    public function importForm(): void
    {
        $this->boot();
        $this->view('event-admin/teams/import', [
            'pageTitle' => 'Bulk Upload Teams',
            'columns'   => TeamImport::COLUMNS,
            'maxRows'   => TeamImport::MAX_ROWS,
        ]);
    }

    /** The starter spreadsheet, so nobody has to guess the column names. */
    public function importTemplate(): void
    {
        $this->boot();

        $csv = TeamImport::templateCsv();
        while (ob_get_level() > 0) { ob_end_clean(); }
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="regatta-teams-template.csv"');
        header('Content-Length: ' . strlen($csv));
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        echo $csv;
        exit;
    }

    /** Parse, validate against what is already on file, and show the result. */
    public function importPreview(): void
    {
        $this->boot();
        $this->verifyCsrf();

        $mode   = ($_POST['mode'] ?? 'skip') === 'update' ? 'update' : 'skip';
        $status = (string)($_POST['registration_status'] ?? 'draft');
        if (!in_array($status, ['draft', 'submitted', 'approved'], true)) $status = 'draft';

        $content = $this->readUploadedCsv();          // redirects on any problem
        $parsed  = TeamImport::parse($content);

        if ($parsed['fatal'] !== '') {
            $this->redirect('/event-admin/teams/import', $parsed['fatal'], 'error');
        }
        if ($parsed['missing']) {
            $this->redirect('/event-admin/teams/import',
                'The file is missing these required columns: ' . implode(', ', $parsed['missing']) . '.', 'error');
        }
        if (!$parsed['rows']) {
            $this->redirect('/event-admin/teams/import', 'That file has a header but no data rows.', 'error');
        }

        // Decide what each row would do, against the teams already on file.
        $ready = [];
        foreach ($parsed['rows'] as $row) {
            $data = $row['data'];
            $existing = $row['errors'] ? null : Team::matchExisting(
                $this->eventId(), (string)$data['short_code'], $data['club_name'], $data['boat_name']
            );

            if ($row['errors'])          $row['action'] = 'error';
            elseif (!$existing)          $row['action'] = 'create';
            elseif ($mode === 'update')  $row['action'] = 'update';
            else                         $row['action'] = 'skip';

            $row['existing'] = $existing ? Team::label($existing) : '';
            $ready[] = $row;
        }

        $_SESSION['team_import'] = [
            'at'     => time(),
            'mode'   => $mode,
            'status' => $status,
            'rows'   => $ready,
        ];

        $this->view('event-admin/teams/import-preview', [
            'pageTitle' => 'Bulk Upload — Preview',
            'rows'      => $ready,
            'mode'      => $mode,
            'status'    => $status,
            'truncated' => $parsed['truncated'],
            'maxRows'   => TeamImport::MAX_ROWS,
            'counts'    => $this->tally($ready),
        ]);
    }

    /** Write the previewed rows. Rows that failed validation are never written. */
    public function importCommit(): void
    {
        $this->boot();
        $this->verifyCsrf();

        $pending = $_SESSION['team_import'] ?? null;
        if (!is_array($pending) || time() - (int)($pending['at'] ?? 0) > self::IMPORT_TTL) {
            unset($_SESSION['team_import']);
            $this->redirect('/event-admin/teams/import',
                'That upload has expired. Please choose the file again.', 'warning');
        }

        $writable = array_values(array_filter(
            $pending['rows'] ?? [],
            fn($r) => ($r['action'] ?? '') === 'create' || ($r['action'] ?? '') === 'update'
        ));
        unset($_SESSION['team_import']);

        if (!$writable) {
            $this->redirect('/event-admin/teams/import', 'Nothing in that file could be imported.', 'error');
        }

        $tally = Team::importRows(
            $this->eventId(), $writable,
            (string)$pending['mode'], (string)$pending['status']
        );

        $parts = [];
        if ($tally['created']) $parts[] = $tally['created'] . ' added';
        if ($tally['updated']) $parts[] = $tally['updated'] . ' updated';
        if ($tally['skipped']) $parts[] = $tally['skipped'] . ' skipped';

        $this->redirect('/event-admin/teams',
            'Bulk upload complete — ' . ($parts ? implode(', ', $parts) : 'nothing to do') . '.');
    }

    /** Read the uploaded CSV, or redirect back with a plain explanation. */
    private function readUploadedCsv(): string
    {
        $back = '/event-admin/teams/import';
        $file = $_FILES['file'] ?? null;

        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $this->redirect($back, 'Choose a CSV file to upload.', 'error');
        }
        if (($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
            $this->redirect($back, 'That upload did not complete. Please try again.', 'error');
        }
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $this->redirect($back, 'That upload could not be read. Please try again.', 'error');
        }
        if ((int)$file['size'] > 2 * 1024 * 1024) {
            $this->redirect($back, 'That file is larger than 2 MB. Split it and upload in batches.', 'error');
        }

        $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'txt'], true)) {
            $this->redirect($back,
                'Upload a .csv file. In Excel or Google Sheets choose File → Save as / Download → CSV.', 'error');
        }

        $content = @file_get_contents($file['tmp_name']);
        if ($content === false || trim($content) === '') {
            $this->redirect($back, 'That file is empty.', 'error');
        }
        return $content;
    }

    /** Row counts per action, for the preview summary. */
    private function tally(array $rows): array
    {
        $counts = ['create' => 0, 'update' => 0, 'skip' => 0, 'error' => 0];
        foreach ($rows as $r) {
            $action = $r['action'] ?? 'error';
            if (isset($counts[$action])) $counts[$action]++;
        }
        return $counts;
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
