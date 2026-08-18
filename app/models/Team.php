<?php
namespace Models;

use Core\Model;

/**
 * A team is one competing boat crew: the club that enters it, the boat's own
 * name and its captain. Teams are event-scoped — the same club entering two
 * regattas has one row per event, so a boat can be renamed or re-crewed from
 * one year to the next without rewriting history.
 */
class Team extends Model
{
    public static function findById(int $id): ?array
    {
        return static::row("SELECT * FROM teams WHERE id = ?", [$id]);
    }

    /** A team scoped to its event — the guard every event controller uses. */
    public static function findForEvent(int $eventId, int $id): ?array
    {
        return static::row("SELECT * FROM teams WHERE id = ? AND event_id = ?", [$id, $eventId]);
    }

    /**
     * All teams of an event, each joined to its registration so the list can
     * show status without a second query.
     */
    public static function forEvent(int $eventId, string $search = '', string $status = ''): array
    {
        $sql = "SELECT t.*,
                       tr.id           AS registration_id,
                       tr.status       AS registration_status,
                       tr.remarks      AS registration_remarks,
                       tr.submitted_at AS registration_submitted_at
                  FROM teams t
             LEFT JOIN team_registrations tr ON tr.team_id = t.id AND tr.event_id = t.event_id
                 WHERE t.event_id = ?";
        $params = [$eventId];

        if ($search !== '') {
            $sql .= " AND (t.club_name LIKE ? OR t.boat_name LIKE ? OR t.captain_name LIKE ? OR t.short_code LIKE ?)";
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like);
        }
        if ($status !== '') {
            $sql .= " AND tr.status = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY t.club_name, t.boat_name";
        return static::rows($sql, $params);
    }

    /** Approved boats only — the pool the lane draw may allocate from. */
    public static function approvedForEvent(int $eventId): array
    {
        return static::rows(
            "SELECT t.*, tr.id AS registration_id
               FROM teams t
               JOIN team_registrations tr ON tr.team_id = t.id AND tr.event_id = t.event_id
              WHERE t.event_id = ? AND tr.status = 'approved' AND t.status = 'active'
              ORDER BY t.club_name, t.boat_name",
            [$eventId]
        );
    }

    public static function create(array $data): int
    {
        return static::insert('teams', $data);
    }

    public static function updateById(int $id, array $data): int
    {
        return static::update('teams', $data, ['id' => $id]);
    }

    public static function deleteById(int $id): int
    {
        return static::delete('teams', ['id' => $id]);
    }

    /** Is this boat already drawn into a lane anywhere in the event? */
    public static function isAllocated(int $teamId): bool
    {
        return (int)static::value(
            "SELECT COUNT(*)
               FROM lane_allocations la
               JOIN team_registrations tr ON tr.id = la.team_registration_id
              WHERE tr.team_id = ?",
            [$teamId], 0
        ) > 0;
    }

    /**
     * Find the team a bulk-upload row refers to, if it is already on file.
     * A short code identifies a boat outright when one is given; otherwise
     * club + boat name do, matched case-insensitively so "NBC" and "nbc"
     * are not imported twice.
     */
    public static function matchExisting(int $eventId, string $shortCode, string $club, string $boat): ?array
    {
        $shortCode = strtoupper(trim($shortCode));
        if ($shortCode !== '') {
            $row = static::row(
                "SELECT * FROM teams WHERE event_id = ? AND UPPER(short_code) = ?",
                [$eventId, $shortCode]
            );
            if ($row) return $row;
        }
        return static::row(
            "SELECT * FROM teams
              WHERE event_id = ? AND LOWER(club_name) = ? AND LOWER(boat_name) = ?",
            [$eventId, mb_strtolower(trim($club)), mb_strtolower(trim($boat))]
        );
    }

    /**
     * Commit a validated bulk upload in one transaction — either the whole
     * batch lands or none of it does, so a failure halfway through cannot
     * leave an event holding half its entries.
     *
     * $rows carries the canonical fields from Services\TeamImport. $mode is
     * 'skip' or 'update' for rows that match a team already on file, and
     * $registrationStatus is the state each team's registration opens in.
     *
     * Returns ['created' => n, 'updated' => n, 'skipped' => n].
     */
    public static function importRows(int $eventId, array $rows, string $mode, string $registrationStatus): array
    {
        $mode   = $mode === 'update' ? 'update' : 'skip';
        $status = in_array($registrationStatus, ['draft', 'submitted', 'approved'], true)
            ? $registrationStatus : 'draft';

        return static::transaction(function () use ($eventId, $rows, $mode, $status) {
            $tally = ['created' => 0, 'updated' => 0, 'skipped' => 0];

            foreach ($rows as $row) {
                $data = $row['data'] ?? [];
                if (($data['club_name'] ?? '') === '' || ($data['boat_name'] ?? '') === '') continue;

                $fields = [
                    'club_name'     => $data['club_name'],
                    'boat_name'     => $data['boat_name'],
                    'captain_name'  => $data['captain_name'],
                    'boat_class'    => $data['boat_class']    !== '' ? $data['boat_class']    : null,
                    'home_place'    => $data['home_place']    !== '' ? $data['home_place']    : null,
                    'short_code'    => $data['short_code']    !== '' ? $data['short_code']    : null,
                    'contact_name'  => $data['contact_name']  !== '' ? $data['contact_name']  : null,
                    'contact_phone' => $data['contact_phone'] !== '' ? $data['contact_phone'] : null,
                    'contact_email' => $data['contact_email'] !== '' ? $data['contact_email'] : null,
                    'status'        => $data['status'] === 'inactive' ? 'inactive' : 'active',
                ];

                $existing = static::matchExisting(
                    $eventId, (string)$data['short_code'], $data['club_name'], $data['boat_name']
                );

                if ($existing) {
                    if ($mode === 'skip') { $tally['skipped']++; continue; }
                    // The logo is never touched: a spreadsheet cannot carry
                    // one, so re-importing must not wipe an uploaded image.
                    static::update('teams', $fields, ['id' => (int)$existing['id']]);
                    $teamId = (int)$existing['id'];
                    $tally['updated']++;
                } else {
                    $fields['event_id'] = $eventId;
                    $teamId = static::insert('teams', $fields);
                    $tally['created']++;
                }

                TeamRegistration::ensureFor($eventId, $teamId, $status);
                TeamRegistration::applyImportedStatus($eventId, $teamId, $status);
            }

            return $tally;
        });
    }

    /** "Boat Name (Club)" — the label used on lane cards and result rows. */
    public static function label(array $team): string
    {
        $boat = trim((string)($team['boat_name'] ?? ''));
        $club = trim((string)($team['club_name'] ?? ''));
        if ($boat === '') return $club;
        if ($club === '') return $boat;
        return "{$boat} ({$club})";
    }
}
