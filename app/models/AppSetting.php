<?php
namespace Models;

use Core\Model;

/** Global key/value settings owned by the platform admin. */
class AppSetting extends Model
{
    /** Keys the settings screen exposes, with their labels. */
    public const EDITABLE = [
        'platform_name'     => 'Platform display name',
        'support_email'     => 'Support email shown to event organisers',
        'default_lanes'     => 'Default lane count for a new event',
        'default_chroma'    => 'Default chroma-key colour for the stream overlay',
        'programme_footer'  => 'Footer line printed on programmes and reports',
    ];

    public static function all(): array
    {
        $rows = static::rows("SELECT setting_key, setting_value FROM app_settings");
        return array_column($rows, 'setting_value', 'setting_key');
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $v = static::value("SELECT setting_value FROM app_settings WHERE setting_key = ?", [$key]);
        return $v === null ? $default : (string)$v;
    }

    public static function set(string $key, string $value): void
    {
        static::query(
            "INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
            [$key, $value]
        );
    }
}
