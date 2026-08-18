<?php
/**
 * SportsMIS® Regatta — front controller.
 *
 * Everything enters here: env loading, autoloading, session bootstrap and the
 * route table. Path params like {hash} are handed to the controller method as
 * arguments; there is no middleware layer, so each controller gates itself in
 * its private boot().
 */
declare(strict_types=1);

define('APP_ROOT',    dirname(__DIR__));
define('CONFIG_ROOT', APP_ROOT . '/config');
define('PUBLIC_ROOT', __DIR__);

// ── Load app/.env if present ─────────────────────────────────────────────────
$envFile = APP_ROOT . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");
        if ($key !== '') putenv("{$key}={$value}");
    }
}

// ── Global exception handler (never show a blank 500) ────────────────────────
set_exception_handler(function (Throwable $e) {
    $isDebug = (getenv('APP_ENV') === 'local');
    error_log('[Regatta] ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    if ($isDebug) {
        echo '<pre style="font:14px monospace;padding:20px;background:#1e1e1e;color:#f8f8f2">';
        echo '<b style="color:#ff6b6b">' . htmlspecialchars(get_class($e)) . '</b>: ';
        echo htmlspecialchars($e->getMessage()) . "\n\n";
        echo htmlspecialchars($e->getTraceAsString());
        echo '</pre>';
    } else {
        require APP_ROOT . '/views/errors/500.php';
    }
    exit;
});

// ── PSR-style autoloader for the project namespaces ──────────────────────────
spl_autoload_register(function (string $class) {
    $map = [
        'Core\\'        => APP_ROOT . '/core/',
        'Controllers\\' => APP_ROOT . '/controllers/',
        'Models\\'      => APP_ROOT . '/models/',
        'Services\\'    => APP_ROOT . '/services/',
    ];
    foreach ($map as $prefix => $base) {
        if (!str_starts_with($class, $prefix)) continue;
        $file = $base . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (file_exists($file)) { require $file; return; }
    }
});

// Composer autoload (Dompdf). Optional so a non-Composer dev shell still runs.
foreach ([dirname(APP_ROOT) . '/vendor/autoload.php', APP_ROOT . '/vendor/autoload.php'] as $vendor) {
    if (file_exists($vendor)) { require_once $vendor; break; }
}

require APP_ROOT . '/core/helpers.php';

// ── Bootstrap ────────────────────────────────────────────────────────────────
$appConfig = require CONFIG_ROOT . '/app.php';
date_default_timezone_set($appConfig['timezone']);
error_reporting($appConfig['debug'] ? E_ALL : 0);
ini_set('display_errors', $appConfig['debug'] ? '1' : '0');

$sessionCfg = $appConfig['session'];
session_name($sessionCfg['name']);
session_set_cookie_params([
    'lifetime' => $sessionCfg['lifetime'],
    'path'     => '/',
    'secure'   => !$appConfig['debug'],
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

$router = new Core\Router();

// ═══════════════════════════════════════════════════════ AUTH
$router->get ('/',                     'AuthController@loginForm');
$router->get ('/login',                'AuthController@loginForm');
$router->post('/login',                'AuthController@login');
$router->get ('/logout',               'AuthController@logout');
$router->post('/account/password',     'AuthController@changePassword');

$router->get ('/event-admin/login',    'AuthController@eventAdminLoginForm');
$router->post('/event-admin/login',    'AuthController@eventAdminLogin');
$router->get ('/event-admin/logout',   'AuthController@eventAdminLogout');
$router->post('/event-admin/password', 'AuthController@eventAdminPassword');

$router->get ('/event-user/login',     'AuthController@eventUserLoginForm');
$router->post('/event-user/login',     'AuthController@eventUserLogin');
$router->get ('/event-user/logout',    'AuthController@eventUserLogout');
$router->post('/event-user/password',  'AuthController@eventUserPassword');

// ═══════════════════════════════════════════════════════ SUPER ADMIN
$router->get ('/admin/dashboard',                  'AdminController@dashboard');
$router->get ('/admin/settings',                   'AdminController@settings');
$router->post('/admin/settings',                   'AdminController@saveSettings');

$router->get ('/admin/events',                     'AdminEventController@index');
$router->get ('/admin/events/create',              'AdminEventController@createForm');
$router->post('/admin/events',                     'AdminEventController@store');
$router->get ('/admin/events/{hash}',              'AdminEventController@show');
$router->get ('/admin/events/{hash}/edit',         'AdminEventController@editForm');
$router->post('/admin/events/{hash}',              'AdminEventController@update');
$router->post('/admin/events/{hash}/delete',       'AdminEventController@destroy');

$router->get ('/admin/accounts',                   'AdminAccountController@index');
$router->get ('/admin/events/{hash}/admins',       'AdminAccountController@forEvent');
$router->post('/admin/events/{hash}/admins',       'AdminAccountController@store');
$router->post('/admin/admins/{hash}/update',       'AdminAccountController@update');
$router->post('/admin/admins/{hash}/reset',        'AdminAccountController@resetPassword');
$router->post('/admin/admins/{hash}/delete',       'AdminAccountController@destroy');

// ═══════════════════════════════════════════════════════ EVENT ADMIN
$router->get ('/event-admin/dashboard',                       'EventAdminController@dashboard');
$router->get ('/event-admin/details',                         'EventAdminController@details');
$router->post('/event-admin/details/{panel}/save',            'EventAdminController@saveSection');
$router->post('/event-admin/details/image',                   'EventAdminController@saveImage');

$router->get ('/event-admin/teams',                           'EventAdminTeamController@index');
$router->get ('/event-admin/teams/create',                    'EventAdminTeamController@createForm');
$router->post('/event-admin/teams',                           'EventAdminTeamController@store');
$router->get ('/event-admin/teams/{hash}/edit',               'EventAdminTeamController@editForm');
$router->post('/event-admin/teams/{hash}',                    'EventAdminTeamController@update');
$router->post('/event-admin/teams/{hash}/delete',             'EventAdminTeamController@destroy');

$router->get ('/event-admin/registrations',                   'EventAdminTeamController@registrations');
$router->post('/event-admin/registrations/approve-all',       'EventAdminTeamController@approveAll');
$router->post('/event-admin/registrations/{hash}/decide',     'EventAdminTeamController@decide');

$router->get ('/event-admin/order-of-events',                  'EventAdminRaceController@index');
$router->post('/event-admin/order-of-events',                  'EventAdminRaceController@store');
$router->get ('/event-admin/order-of-events/print',            'EventAdminRaceController@programmePrint');
$router->get ('/event-admin/order-of-events/pdf',              'EventAdminRaceController@programmePdf');
$router->post('/event-admin/order-of-events/resequence',       'EventAdminRaceController@resequence');
$router->get ('/event-admin/order-of-events/{hash}/entries',   'EventAdminRaceController@entries');
$router->post('/event-admin/order-of-events/{hash}/entries',   'EventAdminRaceController@saveEntries');
$router->post('/event-admin/order-of-events/{hash}/status',    'EventAdminRaceController@setStatus');
$router->post('/event-admin/order-of-events/{hash}/delete',    'EventAdminRaceController@destroy');
$router->post('/event-admin/order-of-events/{hash}',           'EventAdminRaceController@update');

$router->get ('/event-admin/users',                           'EventAdminUserController@index');
$router->post('/event-admin/users',                           'EventAdminUserController@store');
$router->post('/event-admin/users/{hash}/update',             'EventAdminUserController@update');
$router->post('/event-admin/users/{hash}/reset',              'EventAdminUserController@resetPassword');
$router->post('/event-admin/users/{hash}/delete',             'EventAdminUserController@destroy');

// ═══════════════════════════════════════════════════════ EVENT USER (race office)
$router->get ('/event-user/dashboard',                     'EventUserController@dashboard');

$router->get ('/event-user/rounds',                        'EventUserRoundController@index');
$router->get ('/event-user/rounds/{hash}',                 'EventUserRoundController@show');
$router->post('/event-user/rounds/{hash}/seed',            'EventUserRoundController@seedLadder');
$router->post('/event-user/rounds/{hash}/rounds',          'EventUserRoundController@storeRound');
$router->post('/event-user/rounds/round/{hash}',           'EventUserRoundController@updateRound');
$router->post('/event-user/rounds/round/{hash}/heats',     'EventUserRoundController@setHeats');
$router->post('/event-user/rounds/round/{hash}/delete',    'EventUserRoundController@destroyRound');
$router->post('/event-user/rounds/heat/{hash}',            'EventUserRoundController@updateHeat');

$router->get ('/event-user/lane-allocation',               'EventUserLaneController@index');
$router->post('/event-user/lane-allocation/assign',        'EventUserLaneController@assign');
$router->post('/event-user/lane-allocation/unassign',      'EventUserLaneController@unassign');
$router->post('/event-user/lane-allocation/move',          'EventUserLaneController@move');
$router->post('/event-user/lane-allocation/auto-fill',     'EventUserLaneController@autoFill');
$router->post('/event-user/lane-allocation/clear',         'EventUserLaneController@clear');

$router->get ('/event-user/results',                       'EventUserResultController@index');
$router->post('/event-user/results/heat',                  'EventUserResultController@saveHeat');
$router->post('/event-user/results/clear',                 'EventUserResultController@clearHeat');
$router->post('/event-user/results/auto-qualify',          'EventUserResultController@autoQualify');
$router->post('/event-user/results/status',                'EventUserResultController@setStatus');

$router->get ('/event-user/reports',                       'EventUserReportController@index');
$router->get ('/event-user/reports/rank-list/print',       'EventUserReportController@rankListPrint');
$router->get ('/event-user/reports/rank-list/pdf',         'EventUserReportController@rankListPdf');
$router->get ('/event-user/reports/heat-sheet/{hash}/print','EventUserReportController@heatSheetPrint');
$router->get ('/event-user/reports/heat-sheet/{hash}/pdf',  'EventUserReportController@heatSheetPdf');

// ═══════════════════════════════════════════════════════ PUBLIC (stub)
$router->get ('/public',                           'PublicController@index');

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
