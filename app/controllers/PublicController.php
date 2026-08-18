<?php
namespace Controllers;

use Core\Controller;
use Models\Schema;

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
}
