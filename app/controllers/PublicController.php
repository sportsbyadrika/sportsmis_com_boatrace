<?php
namespace Controllers;

use Core\Controller;
use Models\{Schema, Event};

/**
 * Public-facing pages.
 *
 * EXTENSION POINT — deliberately a stub. The public experience (event
 * landing pages, live results for spectators, a results archive) is still to
 * be specified. Everything it will need is already in place:
 *
 *   - the `public` layout renders a chrome-free page;
 *   - Models\Result::rankListForEvent() / ::heatSheet() expose published
 *     results only, so nothing unpublished can leak;
 *   - Models\Event::findByCode() resolves the shareable Event Code.
 *
 * Add routes in app/public/index.php under the PUBLIC section and give each
 * one a method here.
 */
class PublicController extends Controller
{
    private function boot(): void
    {
        try { Schema::ensureEvents(); } catch (\Throwable $e) {}
    }

    public function index(): void
    {
        $this->boot();
        $this->renderWith('public', 'public/index', [
            'pageTitle' => 'SportsMIS® Regatta',
            'bodyClass' => 'sms-body',
        ]);
    }

    /**
     * The Results card a spectator arrives from: the events with a published
     * snapshot, each linking to its own STATIC page.
     *
     * This page runs PHP, the results pages themselves do not — so the only
     * dynamic request in the public path is this one, and it is the one nobody
     * refreshes during a race.
     */
    public function results(): void
    {
        $this->boot();

        $events = [];
        foreach (Event::publicListing() as $event) {
            $code = (string)($event['code'] ?? '');
            if ($code === '') continue;

            $manifest = \Services\ResultSnapshot::directory($code) . '/manifest.json';
            if (!is_file($manifest)) continue;      // nothing published yet

            $meta = json_decode((string)@file_get_contents($manifest), true);
            $events[] = [
                'name'     => (string)$event['name'],
                'regional' => (string)($event['name_regional'] ?? ''),
                'venue'    => (string)($event['venue'] ?? ''),
                'image'    => (string)($event['image'] ?? ''),
                'dates'    => trim(formatDate($event['start_date']) . ' – ' . formatDate($event['end_date']), ' –'),
                'url'      => \Services\ResultSnapshot::urlPath($code) . '/',
                'updated'  => is_array($meta) ? (string)($meta['updated'] ?? '') : '',
            ];
        }

        $this->renderWith('public', 'public/results', [
            'pageTitle' => 'Live Results — SportsMIS® Regatta',
            'bodyClass' => 'sms-body',
            'events'    => $events,
        ]);
    }
}
