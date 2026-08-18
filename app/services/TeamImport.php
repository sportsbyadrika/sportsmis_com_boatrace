<?php
namespace Services;

/**
 * CSV parsing and validation for the bulk team upload.
 *
 * Deliberately free of database and HTTP concerns: it turns a CSV string into
 * clean, validated rows plus a list of what it could not accept. The
 * controller layers duplicate detection and the actual writes on top, and
 * tools/selftest.php drives this class directly.
 *
 * Club logos are not part of the import — they are per-team image uploads,
 * which a spreadsheet cannot carry. Teams come in without one and a logo is
 * added later from the team's edit form.
 */
class TeamImport
{
    /** Row cap, to bound both the preview page and the session it is held in. */
    public const MAX_ROWS = 1000;

    /**
     * Canonical column => [label, required, maxLength]. Header matching is
     * case-insensitive and ignores spaces, underscores and hyphens, so
     * "Club Name", "club_name" and "CLUBNAME" are all the same column.
     */
    public const COLUMNS = [
        'club_name'     => ['Club Name',     true,  200],
        'boat_name'     => ['Boat Name',     true,  200],
        'captain_name'  => ['Captain Name',  true,  150],
        'boat_class'    => ['Boat Class',    false, 120],
        'home_place'    => ['Home Place',    false, 150],
        'short_code'    => ['Short Code',    false, 20],
        'contact_name'  => ['Contact Name',  false, 150],
        'contact_phone' => ['Contact Phone', false, 20],
        'contact_email' => ['Contact Email', false, 190],
        'status'        => ['Status',        false, 10],
    ];

    /**
     * Parse a CSV document.
     *
     * Returns:
     *   rows    — one entry per data line: line, data (canonical fields),
     *             errors (strings), key (dedupe key), code (short code)
     *   missing — required columns absent from the header
     *   fatal   — a reason the file could not be read at all
     *   truncated — true when the file exceeded MAX_ROWS
     */
    public static function parse(string $content): array
    {
        $out = ['rows' => [], 'missing' => [], 'fatal' => '', 'truncated' => false];

        $content = self::toUtf8($content);
        if (trim($content) === '') {
            $out['fatal'] = 'That file is empty.';
            return $out;
        }

        $delimiter = self::detectDelimiter($content);

        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $header = fgetcsv($handle, 0, $delimiter);
        if ($header === false || $header === null) {
            fclose($handle);
            $out['fatal'] = 'That file has no header row.';
            return $out;
        }

        // header position => canonical column name
        $map = [];
        foreach ($header as $i => $name) {
            $key = self::normaliseHeader((string)$name);
            if ($key !== '' && isset(self::COLUMNS[$key])) $map[$i] = $key;
        }

        foreach (self::COLUMNS as $col => [$label, $required, $_max]) {
            if ($required && !in_array($col, $map, true)) $out['missing'][] = $label;
        }
        if ($out['missing']) {
            fclose($handle);
            return $out;
        }

        $line = 1;
        $seen = [];
        while (($record = fgetcsv($handle, 0, $delimiter)) !== false) {
            $line++;
            if ($record === [null] || $record === false) continue;          // blank line
            if (count(array_filter($record, fn($v) => trim((string)$v) !== '')) === 0) continue;

            if (count($out['rows']) >= self::MAX_ROWS) {
                $out['truncated'] = true;
                break;
            }

            $out['rows'][] = self::buildRow($line, $record, $map, $seen);
        }
        fclose($handle);

        return $out;
    }

    /** Validate and normalise one record into a canonical row. */
    private static function buildRow(int $line, array $record, array $map, array &$seen): array
    {
        $data = array_fill_keys(array_keys(self::COLUMNS), '');
        foreach ($map as $i => $col) {
            $data[$col] = trim((string)($record[$i] ?? ''));
        }

        $errors = [];

        foreach (self::COLUMNS as $col => [$label, $required, $max]) {
            if ($required && $data[$col] === '') {
                $errors[] = "{$label} is required";
                continue;
            }
            if ($data[$col] !== '' && mb_strlen($data[$col]) > $max) {
                $errors[] = "{$label} is longer than {$max} characters";
                $data[$col] = mb_substr($data[$col], 0, $max);
            }
        }

        $data['short_code'] = strtoupper($data['short_code']);

        if ($data['contact_email'] !== '') {
            $data['contact_email'] = strtolower($data['contact_email']);
            if (!filter_var($data['contact_email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Contact Email is not a valid address';
            }
        }

        if ($data['contact_phone'] !== '') {
            // Keep digits and the usual separators; reject anything else so a
            // stray formula or note doesn't land in a phone column.
            if (!preg_match('/^[0-9+()\-\s]{6,20}$/', $data['contact_phone'])) {
                $errors[] = 'Contact Phone contains characters that are not part of a number';
            }
        }

        $status = strtolower($data['status']);
        if ($status === '') {
            $status = 'active';
        } elseif (!in_array($status, ['active', 'inactive'], true)) {
            $errors[] = 'Status must be "active" or "inactive"';
            $status = 'active';
        }
        $data['status'] = $status;

        // Duplicate rows inside the same file: the short code wins when given,
        // otherwise club + boat identify the entry.
        $key = $data['short_code'] !== ''
            ? 'code:' . $data['short_code']
            : 'name:' . mb_strtolower($data['club_name'] . '|' . $data['boat_name']);

        if (isset($seen[$key])) {
            $errors[] = 'Duplicate of row ' . $seen[$key] . ' in this file';
        } else {
            $seen[$key] = $line;
        }

        return [
            'line'   => $line,
            'data'   => $data,
            'errors' => $errors,
            'key'    => $key,
        ];
    }

    /** "Club Name" / "club-name" / "CLUBNAME" all collapse to "club_name". */
    public static function normaliseHeader(string $header): string
    {
        $h = strtolower(trim($header));
        $h = preg_replace('/^\xEF\xBB\xBF/', '', $h) ?? $h;      // stray BOM
        $h = preg_replace('/[^a-z0-9]+/', '', $h) ?? $h;
        return match ($h) {
            'clubname', 'club'            => 'club_name',
            'boatname', 'boat'            => 'boat_name',
            'captainname', 'captain'      => 'captain_name',
            'boatclass', 'class'          => 'boat_class',
            'homeplace', 'place', 'home'  => 'home_place',
            'shortcode', 'code'           => 'short_code',
            'contactname', 'contactperson'=> 'contact_name',
            'contactphone', 'phone',
            'mobile', 'contactnumber'     => 'contact_phone',
            'contactemail', 'email'       => 'contact_email',
            'status'                      => 'status',
            default                       => '',
        };
    }

    /**
     * Excel writes CSV with whatever the machine's locale prefers. Sniff the
     * header line for the delimiter that yields the most columns.
     */
    private static function detectDelimiter(string $content): string
    {
        $firstLine = strtok($content, "\r\n") ?: $content;
        $best = ',';
        $bestCount = 0;
        foreach ([',', ';', "\t", '|'] as $candidate) {
            $count = count(str_getcsv($firstLine, $candidate));
            if ($count > $bestCount) {
                $bestCount = $count;
                $best = $candidate;
            }
        }
        return $best;
    }

    /**
     * Strip a UTF-8 BOM, and rescue the common case of a spreadsheet saved as
     * Windows-1252 — without this, regional-language names arrive as mojibake.
     */
    private static function toUtf8(string $content): string
    {
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }
        if (!mb_check_encoding($content, 'UTF-8')) {
            $converted = @mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
            if (is_string($converted) && $converted !== '') $content = $converted;
        }
        return $content;
    }

    /** The downloadable starter file: header plus two example rows. */
    public static function templateCsv(): string
    {
        $header = [];
        foreach (self::COLUMNS as [$label]) $header[] = $label;

        $rows = [
            ['Nadubhagom Boat Club', 'Nadubhagom Chundan', 'K. Menon',
             'Chundan Vallam', 'Nadubhagom', 'NBC', 'R. Kumar', '9876543210',
             'nbc@example.com', 'active'],
            ['Karichal Boat Club', 'Karichal Chundan', 'S. Pillai',
             'Chundan Vallam', 'Karichal', 'KBC', '', '', '', 'active'],
        ];

        $handle = fopen('php://memory', 'r+');
        fputcsv($handle, $header);
        foreach ($rows as $row) fputcsv($handle, $row);
        rewind($handle);
        $csv = (string)stream_get_contents($handle);
        fclose($handle);

        // Excel only honours UTF-8 in a CSV when it sees a BOM.
        return "\xEF\xBB\xBF" . $csv;
    }
}
