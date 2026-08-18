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
$checked = ['lint' => 0, 'routes' => 0, 'views' => 0, 'js' => 0];

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

// ── 5. Inline <script> blocks in views (skipped when node isn't available) ──
exec('node --version 2>/dev/null', $nodeOut, $nodeCode);
if ($nodeCode === 0) {
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APP_ROOT . '/views'));
    foreach ($rii as $file) {
        if ($file->isDir() || $file->getExtension() !== 'php') continue;
        $src = file_get_contents($file->getPathname());
        if (!preg_match_all('#<script(?![^>]*\ssrc=)[^>]*>(.*?)</script>#s', $src, $blocks)) continue;
        foreach ($blocks[1] as $i => $js) {
            if (trim($js) === '') continue;
            // A block with PHP control flow isn't standalone JavaScript, but
            // one that only interpolates values is — stub each short-echo tag
            // with a literal so the surrounding syntax can still be checked.
            // (A close tag must not appear in this comment; it would end PHP.)
            if (str_contains($js, '<?php')) continue;
            $js = preg_replace('/<\?=.*?\?>/s', '0', $js);
            $checked['js']++;
            $tmp = sys_get_temp_dir() . '/verify-' . getmypid() . "-{$i}.js";
            file_put_contents($tmp, $js);
            $out = [];
            exec('node --check ' . escapeshellarg($tmp) . ' 2>&1', $out, $code);
            @unlink($tmp);
            if ($code !== 0) {
                $errors[] = 'JS    ' . str_replace(APP_ROOT, 'app', $file->getPathname())
                          . ' block #' . ($i + 1) . ': ' . implode(' ', $out);
            }
        }
    }
}

// ── Report ──────────────────────────────────────────────────────────────────
printf("Linted %d files · checked %d routes · %d view targets · %d inline script blocks\n",
    $checked['lint'], $checked['routes'], $checked['views'], $checked['js']);

if ($errors) {
    echo "\n" . count($errors) . " problem(s):\n";
    foreach ($errors as $e) echo "  - {$e}\n";
    exit(1);
}
echo "All checks passed.\n";
