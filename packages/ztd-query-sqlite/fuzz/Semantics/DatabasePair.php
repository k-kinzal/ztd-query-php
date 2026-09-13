<?php

declare(strict_types=1);

namespace Fuzz\Semantics;

use Error;
use PDO;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Owns disposable native and physical databases for exactly one command sequence.
 */
final class DatabasePair
{
    /**
     * Native SQLite reference with the logical fixture rows.
     */
    public readonly PDO $native;

    /**
     * Physical database whose sentinel must survive every simulated write.
     */
    public readonly PDO $physical;

    /**
     * Creates independent SQLite fixtures and an observable physical sentinel.
     */
    public function __construct(string $schema)
    {
        $this->native = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->physical = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->native->exec($schema);
        $this->physical->exec($schema);
        $this->native->exec("INSERT INTO users VALUES (1, 'Alice', 10), (2, 'Bob', 20)");
        $this->physical->exec("INSERT INTO users VALUES (9000, 'physical', 777)");
    }

    /**
     * Executes a query while treating connection failures as campaign failures.
     *
     * @return list<array<string, mixed>>
     * @throws Error
     */
    public static function query(PDO $connection, string $sql): array
    {
        $statement = $connection->query($sql);
        if ($statement === false) {
            throw new Error('SQLite query failed: ' . $sql);
        }

        $rows = [];
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            if (!is_array($row)) {
                throw new Error('SQLite returned a non-associative row');
            }
            $columns = [];
            foreach ($row as $column => $value) {
                if (!is_string($column)) {
                    throw new Error('SQLite returned an unnamed result column');
                }
                $columns[$column] = $value;
            }
            $rows[] = $columns;
        }

        return $rows;
    }

    /**
     * Checks row values and physical isolation after every command, including no-op writes.
     *
     * @throws Error
     */
    public function compareState(ShadowStore $store): void
    {
        $native = self::query($this->native, 'SELECT id, name, score FROM users ORDER BY id');
        $shadow = $store->get('users');
        array_multisort(array_column($shadow, 'id'), SORT_ASC, SORT_NUMERIC, $shadow);
        $native = array_map(static function (array $row): array {
            ksort($row);
            return $row;
        }, $native);
        $shadow = array_map(static function (array $row): array {
            ksort($row);
            return $row;
        }, $shadow);
        if ($native !== $shadow) {
            throw new Error('Native/shadow state mismatch: ' . var_export([$native, $shadow], true));
        }
        $physical = self::query($this->physical, 'SELECT id, name, score FROM users');
        if ($physical !== [['id' => 9000, 'name' => 'physical', 'score' => 777]]) {
            throw new Error('Physical database was modified: ' . var_export($physical, true));
        }
    }
}
