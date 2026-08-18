<?php
namespace Controllers;

use Core\{Controller, Auth};
use Models\{Schema, Event, User, AppSetting};

/**
 * Super Admin (platform owner) home. Oversees every tenant event; the
 * per-entity screens live in AdminEventController / AdminAccountController.
 */
class AdminController extends Controller
{
    /** Gate + self-heal. Called first by every action in this controller. */
    private function boot(): void
    {
        try { Schema::ensureAll(); } catch (\Throwable $e) { /* reported by the handler */ }
        if (!Auth::check() || !Auth::is('super_admin')) {
            $this->redirect('/login', 'Please sign in to continue.', 'warning');
        }
    }

    public function dashboard(): void
    {
        $this->boot();

        $this->renderWith('app', 'admin/dashboard', [
            'pageTitle'      => 'Dashboard',
            'stats'          => Event::platformStats(),
            'recentEvents'   => array_slice(Event::allWithCounts(), 0, 6),
            'defaultPassword'=> User::usingDefaultPassword((int)Auth::id()),
        ]);
    }

    public function settings(): void
    {
        $this->boot();

        $this->renderWith('app', 'admin/settings', [
            'pageTitle' => 'Platform Settings',
            'settings'  => AppSetting::all(),
        ]);
    }

    public function saveSettings(): void
    {
        $this->boot();
        $this->verifyCsrf();

        foreach (AppSetting::EDITABLE as $key => $_label) {
            if (array_key_exists($key, $_POST)) {
                AppSetting::set($key, trim((string)$_POST[$key]));
            }
        }
        if ($this->isAjax()) $this->json(['success' => true, 'message' => 'Settings saved.']);
        $this->redirect('/admin/settings', 'Settings saved.');
    }
}
