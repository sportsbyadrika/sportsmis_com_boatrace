<?php
namespace Controllers;

use Core\Pdf;
use Models\{Result, Round, Heat, LaneAllocation, AppSetting};

/**
 * Race office -> Reports (privilege: reports).
 *
 * Two reports, each available on screen, as a browser print view and as a
 * Dompdf download:
 *   - the event-wise rank list (1st–4th per race, plus the club tally);
 *   - a per-round heat sheet (every lane, with times and positions).
 *
 * Both read published rounds only, so nothing provisional can be printed.
 */
class EventUserReportController extends EventUserBase
{
    private function roundOr404(string $hash): array
    {
        $round = Round::findWithRace($this->eventId(), hid_round_decode($hash));
        if (!$round) $this->abort(404);
        return $round;
    }

    // ── On screen ────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->boot();
        $this->requirePrivilege('reports');

        $this->view('event-user/reports/index', [
            'pageTitle' => 'Reports',
            'rankList'  => Result::rankListForEvent($this->eventId()),
            'tally'     => Result::medalTally($this->eventId()),
            'rounds'    => Round::forEvent($this->eventId()),
        ]);
    }

    // ── Rank list ────────────────────────────────────────────────────────────

    public function rankListPrint(): void
    {
        $this->boot();
        $this->requirePrivilege('reports');

        $this->renderWith('print', 'event-user/reports/rank-list', [
            'pageTitle' => 'Rank List — ' . $this->event['name'],
            'event'     => $this->event,
            'rankList'  => Result::rankListForEvent($this->eventId()),
            'tally'     => Result::medalTally($this->eventId()),
            'footer'    => AppSetting::get('programme_footer', 'Powered by SportsMIS® Regatta'),
        ]);
    }

    public function rankListPdf(): void
    {
        $this->boot();
        $this->requirePrivilege('reports');

        $html = $this->renderToString('event-user/reports/rank-list-pdf', [
            'event'    => $this->event,
            'rankList' => Result::rankListForEvent($this->eventId()),
            'tally'    => Result::medalTally($this->eventId()),
            'logo'     => Pdf::imageDataUri($this->event['image'] ?? ''),
            'footer'   => AppSetting::get('programme_footer', 'Powered by SportsMIS® Regatta'),
        ]);
        Pdf::stream($html, 'rank-list.pdf', 'A4', 'portrait', true);
    }

    // ── Heat sheet ───────────────────────────────────────────────────────────

    public function heatSheetPrint(string $hash): void
    {
        $this->boot();
        $this->requirePrivilege('reports');
        $round = $this->roundOr404($hash);

        $this->renderWith('print', 'event-user/reports/heat-sheet', [
            'pageTitle' => 'Heat Sheet — ' . $round['race_name'],
            'event'     => $this->event,
            'round'     => $round,
            'heats'     => Result::heatSheet((int)$round['id']),
            'footer'    => AppSetting::get('programme_footer', 'Powered by SportsMIS® Regatta'),
        ]);
    }

    public function heatSheetPdf(string $hash): void
    {
        $this->boot();
        $this->requirePrivilege('reports');
        $round = $this->roundOr404($hash);

        $html = $this->renderToString('event-user/reports/heat-sheet-pdf', [
            'event'  => $this->event,
            'round'  => $round,
            'heats'  => Result::heatSheet((int)$round['id']),
            'logo'   => Pdf::imageDataUri($this->event['image'] ?? ''),
            'footer' => AppSetting::get('programme_footer', 'Powered by SportsMIS® Regatta'),
        ]);
        Pdf::stream($html, 'heat-sheet.pdf', 'A4', 'portrait', true);
    }

    // ── Helper ───────────────────────────────────────────────────────────────

    /** Capture a view's output instead of echoing it (for the PDF body). */
    private function renderToString(string $view, array $data): string
    {
        extract($data);
        ob_start();
        require APP_ROOT . "/views/{$view}.php";
        return (string)ob_get_clean();
    }
}
