<?php
/**
 * Static integrity check — runs without a database.
 *
 *  1. php -l every PHP file.
 *  2. Every route in app/public/index.php resolves to a real controller method.
 *  3. Every render()/renderWith() view path in a controller resolves to a file.
 *  4. Every layout referenced by renderWith() exists.
 *
 * Usage: php tools/verify.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
define('APP_ROOT',    $root . '/app');
define('CONFIG_ROOT', APP_ROOT . '/config');
define('PUBLIC_ROOT', APP_ROOT . '/public');

$errors = [];
$checked = ['lint' => 0, 'routes' => 0, 'views' => 0];

// ── 1. Lint ─────────────────────────────────────────────────────────────────
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app'));
foreach ($rii as $file) {
    if ($file->isDir() || $file->getExtension() !== 'php') continue;
    $out = [];
    exec('php -l ' . escapeshellarg($file->getPathname()) . ' 2>&1', $out, $code);
    $checked['lint']++;
    if ($code !== 0) $errors[] = 'LINT  ' . implode(' ', $out);
}

// ── 2. Routes -> controller methods ─────────────────────────────────────────
$index = file_get_contents(APP_ROOT . '/public/index.php');
preg_match_all('/\$router->(get|post)\s*\(\s*\'([^\']+)\'\s*,\s*\'([^\']+)\'\s*\)/', $index, $m, PREG_SET_ORDER);
foreach ($m as $route) {
    [$all, $verb, $path, $handler] = $route;
    $checked['routes']++;
    [$controller, $action] = array_pad(explode('@', $handler, 2), 2, '');
    $file = APP_ROOT . "/controllers/{$controller}.php";
    if (!file_exists($file)) {
        $errors[] = "ROUTE {$verb} {$path} -> missing controller {$controller}";
        continue;
    }
    $src = file_get_contents($file);
    if (!preg_match('/public\s+function\s+' . preg_quote($action, '/') . '\s*\(/', $src)) {
        $errors[] = "ROUTE {$verb} {$path} -> {$controller} has no public method {$action}()";
    }
    // Path params must match the method's declared arity.
    preg_match_all('/\{(\w+)\}/', $path, $params);
    $paramCount = count($params[1]);
    if (preg_match('/public\s+function\s+' . preg_quote($action, '/') . '\s*\(([^)]*)\)/', $src, $sig)) {
        $declared = trim($sig[1]) === '' ? 0 : count(explode(',', $sig[1]));
        if ($declared !== $paramCount) {
            $errors[] = "ROUTE {$verb} {$path} -> {$controller}::{$action}() takes {$declared} arg(s), route supplies {$paramCount}";
        }
    }
}

// ── 3/4. render()/renderWith() targets ──────────────────────────────────────
foreach (glob(APP_ROOT . '/controllers/*.php') as $file) {
    $src  = file_get_contents($file);
    $name = basename($file);

    preg_match_all('/renderWith\s*\(\s*\'([^\']+)\'\s*,\s*\'([^\']+)\'/', $src, $rw, PREG_SET_ORDER);
    foreach ($rw as [$_, $layout, $view]) {
        $checked['views']++;
        if (!file_exists(APP_ROOT . "/views/layouts/{$layout}.php")) {
            $errors[] = "VIEW  {$name}: layout '{$layout}' not found";
        }
        if (!file_exists(APP_ROOT . "/views/{$view}.php")) {
            $errors[] = "VIEW  {$name}: view '{$view}' not found";
        }
    }

    preg_match_all('/(?<!With\()(?<![\w])render\s*\(\s*\'([^\']+)\'/', $src, $rd, PREG_SET_ORDER);
    foreach ($rd as [$_, $view]) {
        if (str_contains($view, '/layouts/')) continue;
        $checked['views']++;
        if (!file_exists(APP_ROOT . "/views/{$view}.php")) {
            $errors[] = "VIEW  {$name}: view '{$view}' not found";
        }
    }

    // $this->view('path') — the per-area shorthand defined on the base
    // controllers, which wraps renderWith() with that area's layout.
    preg_match_all('/->view\s*\(\s*\'([^\']+)\'/', $src, $sv, PREG_SET_ORDER);
    foreach ($sv as [$_, $view]) {
        $checked['views']++;
        if (!file_exists(APP_ROOT . "/views/{$view}.php")) {
            $errors[] = "VIEW  {$name}: view '{$view}' not found";
        }
    }
}

// ── Report ──────────────────────────────────────────────────────────────────
printf("Linted %d files · checked %d routes · checked %d view targets\n",
    $checked['lint'], $checked['routes'], $checked['views']);

if ($errors) {
    echo "\n" . count($errors) . " problem(s):\n";
    foreach ($errors as $e) echo "  - {$e}\n";
    exit(1);
}
echo "All checks passed.\n";
