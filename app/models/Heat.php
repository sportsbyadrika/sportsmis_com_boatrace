<?php
namespace Models;

use Core\Model;

/**
 * A heat is one running of a round. Every heat exposes exactly its round's
 * lane count, so the lane board and the printed heat sheet always agree on
 * how many boats can start.
 */
class Heat extends Model
{
    public static function findById(int $id): ?array
    {
        return static::row("SELECT * FROM heats WHERE id = ?", [$id]);
    }

    public static function findForEvent(int $eventId, int $id): ?array
    {
        return static::row("SELECT * FROM heats WHERE id = ? AND event_id = ?", [$id, $eventId]);
    }

    public static function forRound(int $roundId): array
    {
        return static::rows(
            "SELECT h.*,
                    (SELECT COUNT(*) FROM lane_allocations la WHERE la.heat_id = h.id) AS allocated_count,
                    (SELECT COUNT(*) FROM results re WHERE re.heat_id = h.id AND re.position IS NOT NULL) AS result_count
               FROM heats h
              WHERE h.round_id = ?
              ORDER BY h.heat_no",
            [$roundId]
        );
    }

    public static function countForRound(int $roundId): int
    {
        return (int)static::value("SELECT COUNT(*) FROM heats WHERE round_id = ?", [$roundId], 0);
    }

    public static function create(array $data): int
    {
        return static::insert('heats', $data);
    }

    public static function updateById(int $id, array $data): int
    {
        return static::update('heats', $data, ['id' => $id]);
    }

    public static function deleteById(int $id): int
    {
        return static::delete('heats', ['id' => $id]);
    }

    public static function nextHeatNo(int $roundId): int
    {
        return (int)static::value(
            "SELECT COALESCE(MAX(heat_no), 0) + 1 FROM heats WHERE round_id = ?", [$roundId], 1);
    }

    /**
     * Make the round hold exactly $count heats, numbered 1..$count. Heats
     * beyond the target are removed only when empty, so a shrink can never
     * silently discard a lane draw — the count of survivors is returned.
     */
    public static function syncCount(int $eventId, int $roundId, int $count): int
    {
        $count    = max(1, min(40, $count));
        $existing = static::forRound($roundId);
        $byNo     = [];
        foreach ($existing as $h) $byNo[(int)$h['heat_no']] = $h;

        for ($n = 1; $n <= $count; $n++) {
            if (isset($byNo[$n])) continue;
            static::create([
                'event_id' => $eventId,
                'round_id' => $roundId,
                'heat_no'  => $n,
                'name'     => 'Heat ' . $n,
            ]);
        }
        foreach ($byNo as $no => $heat) {
            if ($no <= $count) continue;
            if ((int)$heat['allocated_count'] > 0) continue;   // keep drawn heats
            static::deleteById((int)$heat['id']);
        }
        return static::countForRound($roundId);
    }

    /** "Heat 2" — falls back to the number when no name was given. */
    public static function label(array $heat): string
    {
        $name = trim((string)($heat['name'] ?? ''));
        return $name !== '' ? $name : 'Heat ' . (int)($heat['heat_no'] ?? 0);
    }
}
