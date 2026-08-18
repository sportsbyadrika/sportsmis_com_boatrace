<?php
/**
 * Rebuild the public results snapshot for one event, or for every active one.
 *
 * The snapshot is refreshed automatically whenever a round is published, so
 * this exists for the times that is not enough: a scheduled safety net during
 * a race day, or a forced rebuild after a failed write.
 *
 *   php tools/publish-snapshots.php            # every active / completed event
 *   php tools/publish-snapshots.php RG1A2B3C   # one event, by Event Code
 *
 * It is also safe to run from cron every few minutes during racing — see the
 * example under the docblock, which cannot live inside it because a cron
 * step value contains the characters that end a PHP block comment.
 */
// Cron, every five minutes:
//   */5 * * * * /usr/local/bin/php /home/olympicd/olympicday.in/tools/publish-snapshots.php >/dev/null 2>&1
declare(strict_types=1);

$root = dirname(__DIR__);
define('APP_ROOT',    $root . '/app');
define('CONFIG_ROOT', APP_ROOT . '/config');
define('PUBLIC_ROOT', APP_ROOT . '/public');

// Same .env loader as the front controller, so cron sees the same database.
$envFile = APP_ROOT . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        if ($k !== '') putenv("{$k}=" . trim($v, "\"'"));
    }
}

spl_autoload_register(function (string $class) {
    foreach ([
        'Core\\'     => APP_ROOT . '/core/',
        'Models\\'   => APP_ROOT . '/models/',
        'Services\\' => APP_ROOT . '/services/',
    ] as $prefix => $base) {
        if (!str_starts_with($class, $prefix)) continue;
        $file = $base . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (file_exists($file)) { require $file; return; }
    }
});
require APP_ROOT . '/core/helpers.php';
date_default_timezone_set((string)(require CONFIG_ROOT . '/app.php')['timezone']);

$only = isset($argv[1]) ? strtoupper(trim((string)$argv[1])) : '';
$exit = 0;

try {
    \Models\Schema::ensureAll();
    $events = $only !== ''
        ? array_filter([\Models\Event::findByCode($only)])
        : \Models\Event::publicListing();
} catch (\Throwable $e) {
    fwrite(STDERR, "Cannot read events: {$e->getMessage()}\n");
    exit(1);
}

if (!$events) {
    fwrite(STDERR, $only !== "" ? "No event with code {$only}.\n" : "No active events.\n");
    exit(1);
}

foreach ($events as $event) {
    try {
        $r = \Services\ResultSnapshot::publish((int)$event['id']);
        printf("%-12s v%-4d %2d races  %6.1f KB  %s\n",
            $event['code'] ?: '(no code)', $r['version'], $r['races'], $r['bytes'] / 1024, $r['path']);
    } catch (\Throwable $e) {
        fwrite(STDERR, sprintf("%-12s FAILED: %s\n", $event['code'] ?: '?', $e->getMessage()));
        $exit = 1;
    }
}

exit($exit);
