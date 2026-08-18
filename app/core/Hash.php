<?php
namespace Core;

/**
 * Tiny URL-ID obfuscator. Wraps an integer ID in an HMAC-signed,
 * URL-safe base64 token so links can't be guessed by incrementing
 * a number in the address bar (defence against sequential IDOR
 * snooping on resources scoped per-context, e.g. "event").
 *
 * The output is short (≤ 12 chars for any reasonable ID) and
 * deterministic — encoding the same id under the same context
 * always yields the same token, so links are stable.
 */
class Hash
{
    private const SIG_BYTES = 4;

    public static function encode(int $id, string $context = ''): string
    {
        $packed = pack('N', $id);
        $sig    = substr(self::hmac($packed, $context), 0, self::SIG_BYTES);
        return rtrim(strtr(base64_encode($packed . $sig), '+/', '-_'), '=');
    }

    public static function decode(string $token, string $context = ''): ?int
    {
        $token = trim($token);
        if ($token === '') return null;
        $bin = base64_decode(strtr($token, '-_', '+/'), true);
        if ($bin === false || strlen($bin) !== 4 + self::SIG_BYTES) return null;
        $packed = substr($bin, 0, 4);
        $sig    = substr($bin, 4);
        $expect = substr(self::hmac($packed, $context), 0, self::SIG_BYTES);
        if (!hash_equals($expect, $sig)) return null;
        $u = unpack('Nid', $packed);
        return (int)$u['id'];
    }

    /**
     * Convenience: try decoding the value as a hash for the given
     * context; if that fails, fall back to a plain integer cast so
     * existing numeric routes / bookmarks keep working during the
     * migration.
     */
    public static function decodeOrInt($value, string $context = ''): int
    {
        if (is_int($value)) return $value;
        $value = (string)$value;
        if (ctype_digit($value)) return (int)$value;
        $id = self::decode($value, $context);
        return $id ?? 0;
    }

    private static function hmac(string $packed, string $context): string
    {
        $secret = self::secret();
        return hash_hmac('sha256', $context . '|' . $packed, $secret, true);
    }

    private static function secret(): string
    {
        $cfg = require CONFIG_ROOT . '/app.php';
        return (string)($cfg['secret'] ?? 'change-this-secret-key-in-production');
    }
}
