<?php
namespace Models;

use Core\Model;

/** Platform accounts. Today this is the super admin only. */
class User extends Model
{
    public static function findById(int $id): ?array
    {
        return static::row("SELECT * FROM users WHERE id = ?", [$id]);
    }

    public static function findByEmail(string $email): ?array
    {
        return static::row("SELECT * FROM users WHERE email = ?", [strtolower(trim($email))]);
    }

    public static function updateLastLogin(int $id): void
    {
        static::update('users', ['last_login_at' => date('Y-m-d H:i:s')], ['id' => $id]);
    }

    public static function updatePassword(int $id, string $hash): void
    {
        static::update('users', ['password' => $hash], ['id' => $id]);
    }

    /** True while the bootstrap account still carries its shipped password. */
    public static function usingDefaultPassword(int $id): bool
    {
        $u = static::findById($id);
        return $u ? password_verify('ChangeMe@123', (string)$u['password']) : false;
    }
}
