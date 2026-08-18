<?php
namespace Controllers;

/**
 * Race office -> Displays (privilege: displays).
 *
 * A launcher, not a screen: it hands the operator the two public URLs, the
 * PIN they will be asked for, and a quick way to open each in a new window.
 * The screens themselves live in DisplayController and need no app session,
 * so a venue machine can be pointed at a URL once and left alone.
 */
class EventUserDisplayController extends EventUserBase
{
    public function index(): void
    {
        $this->boot();
        $this->requirePrivilege('displays');

        $hash = hid_event($this->eventId());
        $code = (string)$this->event['code'];
        $dir  = \Services\ResultSnapshot::directory($code);

        $manifest = null;
        if (is_file($dir . '/manifest.json')) {
            $manifest = json_decode((string)@file_get_contents($dir . '/manifest.json'), true);
        }

        $this->view('event-user/displays/index', [
            'pageTitle' => 'Display Screens',
            'wallUrl'   => '/display/' . $hash . '/wall',
            'streamUrl' => '/display/' . $hash . '/stream',
            'publicUrl' => \Services\ResultSnapshot::urlPath($code) . '/',
            'manifest'  => is_array($manifest) ? $manifest : null,
            'published' => \Models\Result::publishedRounds($this->eventId(), 20),
        ]);
    }

    /**
     * Report exactly what the public results directory holds and what the web
     * server actually returns for it.
     *
     * From a browser, "no results" looks the same whether the snapshot was
     * never republished, a file is missing, or a server rule is denying the
     * payload. This distinguishes them instead of leaving it to guesswork.
     */
    public function diagnose(): void
    {
        $this->boot();
        $this->requirePrivilege('displays');

        $code = (string)$this->event['code'];
        $dir  = \Services\ResultSnapshot::directory($code);
        $base = \Services\ResultSnapshot::urlPath($code) . '/';

        $files = [];
        foreach (['manifest.json', 'index.html', '.htaccess'] as $name) {
            $path = $dir . '/' . $name;
            $files[$name] = is_file($path)
                ? ['exists' => true, 'size' => filesize($path), 'mtime' => filemtime($path),
                   'perms' => substr(sprintf('%o', fileperms($path)), -4)]
                : ['exists' => false];
        }
        foreach (glob($dir . '/results-*.json') ?: [] as $path) {
            $files[basename($path)] = ['exists' => true, 'size' => filesize($path),
                'mtime' => filemtime($path), 'perms' => substr(sprintf('%o', fileperms($path)), -4)];
        }

        $manifest = is_file($dir . '/manifest.json')
            ? json_decode((string)@file_get_contents($dir . '/manifest.json'), true)
            : null;

        // Is the published page the one currently in the repository? A stale
        // one means the code was deployed but never republished.
        $pageVersion = null;
        if (is_file($dir . '/index.html')) {
            $page = (string)@file_get_contents($dir . '/index.html');
            $pageVersion = preg_match('/name="rg-template" content="(\d+)"/', $page, $m)
                ? (int)$m[1] : 0;
        }

        // What the web server actually returns — the only thing that settles
        // a 403 from a rule we cannot see from here.
        $probes = [];
        foreach (['manifest.json', 'index.html'] as $name) {
            $probes[$name] = $this->probe(url($base . $name));
        }

        $this->view('event-user/displays/diagnose', [
            'pageTitle'   => 'Public Results — Diagnostics',
            'dir'         => $dir,
            'dirExists'   => is_dir($dir),
            'dirWritable' => is_dir($dir) && is_writable($dir),
            'baseUrl'     => $base,
            'files'       => $files,
            'manifest'    => is_array($manifest) ? $manifest : null,
            'pageVersion' => $pageVersion,
            'wantVersion' => \Services\ResultSnapshot::TEMPLATE_VERSION,
            'probes'      => $probes,
        ]);
    }

    /** Fetch a URL from the server and report what came back. */
    private function probe(string $url): array
    {
        $out = ['url' => $url, 'status' => null, 'error' => '', 'snippet' => ''];

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 6,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,   // may be a self-signed staging cert
            ]);
            $body = curl_exec($ch);
            $out['status'] = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: null;
            if ($body === false) $out['error'] = (string)curl_error($ch);
            curl_close($ch);
            $out['snippet'] = mb_substr((string)$body, 0, 220);
            return $out;
        }

        $ctx  = stream_context_create(['http' => ['timeout' => 6, 'ignore_errors' => true]]);
        $body = @file_get_contents($url, false, $ctx);
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $out['status'] = (int)$m[1];
        }
        if ($body === false) $out['error'] = 'Request failed.';
        $out['snippet'] = mb_substr((string)$body, 0, 220);
        return $out;
    }

    /**
     * Rebuild the public results snapshot by hand.
     *
     * It is also rebuilt automatically whenever a round is published, so this
     * is for the cases automation cannot see — a race image swapped, a team
     * renamed, or a snapshot that needs forcing after a failure.
     */
    public function publishSnapshot(): void
    {
        $this->boot();
        $this->requirePrivilege('displays');
        $this->verifyCsrf();

        try {
            $result = \Services\ResultSnapshot::publish($this->eventId());
        } catch (\Throwable $e) {
            $this->redirect('/event-user/displays',
                'Could not publish the public results: ' . $e->getMessage(), 'error');
        }

        $this->redirect('/event-user/displays', sprintf(
            'Public results published — version %d, %d race%s, %s.',
            $result['version'], $result['races'], $result['races'] === 1 ? '' : 's',
            $result['bytes'] > 1024 ? round($result['bytes'] / 1024) . ' KB' : $result['bytes'] . ' bytes'
        ));
    }
}
