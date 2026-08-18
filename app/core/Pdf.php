<?php
namespace Core;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Thin Dompdf wrapper for the downloadable reports (order-of-events
 * programme, heat sheets, rank lists).
 *
 * Remote fetching stays OFF — any logo must be inlined as a data: URI first
 * with Pdf::imageDataUri(), otherwise the renderer silently drops it.
 */
class Pdf
{
    /**
     * Render an HTML string to raw PDF bytes. With $pageNumbers a
     * "Page N of M" footer is stamped through the Dompdf canvas — CSS
     * counter(pages) returns 0 under Dompdf, so the canvas is the only
     * reliable way to get an accurate total.
     */
    public static function render(string $html, string $paper = 'A4',
                                  string $orientation = 'portrait', bool $pageNumbers = false): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $tmp = self::tempDir();
        if ($tmp !== '') {
            $options->set('tempDir', $tmp);
            $options->set('fontCache', $tmp);
        }
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper($paper, $orientation);
        $dompdf->render();
        if ($pageNumbers) {
            try {
                $canvas = $dompdf->getCanvas();
                $font   = $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
                $w = $canvas->get_width();
                $h = $canvas->get_height();
                $canvas->page_text($w - 130, $h - 24, 'Page {PAGE_NUM} of {PAGE_COUNT}',
                    $font, 8, [0.4, 0.4, 0.4]);
            } catch (\Throwable $e) { /* page numbering is best-effort */ }
        }
        return (string)$dompdf->output();
    }

    /** Render + stream inline with no-store headers, then exit. */
    public static function stream(string $html, string $downloadName,
                                  string $paper = 'A4', string $orientation = 'portrait',
                                  bool $pageNumbers = false): void
    {
        if (!class_exists(Dompdf::class)) {
            // Composer dependencies aren't installed — fail loudly but
            // usefully rather than with a blank 500.
            while (ob_get_level() > 0) { ob_end_clean(); }
            http_response_code(500);
            echo '<!doctype html><meta charset="utf-8">'
               . '<div style="font-family:Inter,system-ui,sans-serif;max-width:620px;margin:60px auto">'
               . '<h2>PDF renderer unavailable</h2>'
               . '<p>Dompdf is not installed. Run <code>composer install</code> on the server, '
               . 'or use the printable view and “Save as PDF” from your browser.</p></div>';
            exit;
        }
        $pdf = self::render($html, $paper, $orientation, $pageNumbers);
        while (ob_get_level() > 0) { ob_end_clean(); }
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . str_replace('"', '', $downloadName) . '"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        echo $pdf;
        exit;
    }

    /**
     * Resolve an uploaded image URL to an inline data: URI. Returns '' when
     * the file can't be found or read, so the caller can just omit the image.
     */
    public static function imageDataUri(?string $url): string
    {
        $url = trim((string)$url);
        if ($url === '') return '';
        if (str_starts_with($url, 'data:')) return $url;

        $path  = parse_url($url, PHP_URL_PATH) ?: $url;
        $local = '';
        if (str_starts_with($path, '/')) {
            $docRoot = (string)($_SERVER['DOCUMENT_ROOT'] ?? '');
            $candidates = array_filter([
                PUBLIC_ROOT . $path,
                APP_ROOT . '/public' . $path,
                dirname(APP_ROOT) . '/public' . $path,
                $docRoot !== '' ? rtrim($docRoot, '/') . $path : null,
            ]);
            foreach ($candidates as $c) {
                if (is_file($c) && is_readable($c)) { $local = $c; break; }
            }
        } elseif (is_file($url) && is_readable($url)) {
            $local = $url;
        }
        if ($local === '') return '';

        $data = @file_get_contents($local);
        if ($data === false || $data === '') return '';

        $mime = 'image/png';
        if (function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            if ($fi) { $mime = finfo_file($fi, $local) ?: $mime; finfo_close($fi); }
        } else {
            $ext = strtolower(pathinfo($local, PATHINFO_EXTENSION));
            $mime = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
                     'webp' => 'image/webp', 'gif' => 'image/gif'][$ext] ?? 'image/png';
        }
        return 'data:' . $mime . ';base64,' . base64_encode($data);
    }

    /** A writable scratch directory for Dompdf's font cache / temp files. */
    private static function tempDir(): string
    {
        $candidates = [
            dirname(APP_ROOT) . '/storage/dompdf',
            APP_ROOT . '/storage/dompdf',
            sys_get_temp_dir() . '/dompdf',
        ];
        foreach ($candidates as $c) {
            if (!is_dir($c)) { @mkdir($c, 0775, true); }
            if (is_dir($c) && is_writable($c)) return $c;
        }
        return sys_get_temp_dir();
    }
}
