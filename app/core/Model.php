<?php
namespace Core;

/**
 * PDO wrapper every model extends. Models own all SQL — controllers never
 * build a query. The connection is lazily opened once per request.
 */
class Model
{
    protected static ?\PDO $pdo = null;

    protected static function db(): \PDO
    {
        if (static::$pdo === null) {
            $cfg = require CONFIG_ROOT . '/database.php';
            $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['database']};charset={$cfg['charset']}";
            try {
                static::$pdo = new \PDO($dsn, $cfg['username'], $cfg['password'], $cfg['options']);
            } catch (\PDOException $e) {
                throw new \RuntimeException(
                    "Database connection failed. Check your .env DB_* settings. ({$e->getMessage()})",
                    (int)$e->getCode(), $e
                );
            }
        }
        return static::$pdo;
    }

    protected static function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = static::db()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    protected static function row(string $sql, array $params = []): ?array
    {
        return static::query($sql, $params)->fetch() ?: null;
    }

    protected static function rows(string $sql, array $params = []): array
    {
        return static::query($sql, $params)->fetchAll();
    }

    /** Single scalar value from the first column of the first row. */
    protected static function value(string $sql, array $params = [], mixed $default = null): mixed
    {
        $v = static::query($sql, $params)->fetchColumn();
        return $v === false ? $default : $v;
    }

    protected static function insert(string $table, array $data): int
    {
        $cols = implode(',', array_keys($data));
        $plc  = implode(',', array_fill(0, count($data), '?'));
        static::query("INSERT INTO {$table} ({$cols}) VALUES ({$plc})", array_values($data));
        return (int) static::db()->lastInsertId();
    }

    protected static function update(string $table, array $data, array $where): int
    {
        if (!$data) return 0;
        $set  = implode(',', array_map(fn($k) => "{$k}=?", array_keys($data)));
        $cond = implode(' AND ', array_map(fn($k) => "{$k}=?", array_keys($where)));
        $stmt = static::query(
            "UPDATE {$table} SET {$set} WHERE {$cond}",
            [...array_values($data), ...array_values($where)]
        );
        return $stmt->rowCount();
    }

    protected static function delete(string $table, array $where): int
    {
        $cond = implode(' AND ', array_map(fn($k) => "{$k}=?", array_keys($where)));
        return static::query("DELETE FROM {$table} WHERE {$cond}", array_values($where))->rowCount();
    }

    /** Run a callback inside a transaction, rolling back on any throwable. */
    protected static function transaction(callable $fn): mixed
    {
        $pdo = static::db();
        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
