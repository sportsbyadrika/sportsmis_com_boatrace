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

// ═══════════════════════════════════════════════════════ PUBLIC (stub)
$router->get ('/public',                           'PublicController@index');

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
