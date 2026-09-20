<?php

declare(strict_types=1);

namespace Fuzz\Shared\Database;

use PDO;
use PDOException;

/**
 * Owns a private database/schema; never clears a caller's existing tables.
 */
final class Sandbox
{
    /**
     * Native connection restricted to this instance namespace.
     */
    public readonly PDO $pdo;
    /**
     * Independent catalog reader for snapshots and row comparisons.
     */
    public readonly Catalog $catalog;
    private readonly string $name;
    private bool $closed = false;

    /**
     * Allocate a private namespace for one side of the comparison.
     * @param 'mysql'|'pgsql'|'sqlite' $driver
     * @throws PDOException
     */
    public function __construct(public readonly string $driver)
    {
        $this->name = 'ztd_fuzz_' . bin2hex(random_bytes(8));
        $host = Environment::read($driver === 'mysql' ? 'MYSQL_HOST' : 'PG_HOST', '127.0.0.1');
        $port = Environment::read($driver === 'mysql' ? 'MYSQL_PORT' : 'PG_PORT', ($driver === 'mysql' ? '3306' : '5432'));
        $dsn = match ($driver) {
            'mysql' => "mysql:host=$host;port=$port;charset=utf8mb4",
            'pgsql' => "pgsql:host=$host;port=$port;dbname=" . (Environment::read('PG_DATABASE', 'test')),
            'sqlite' => 'sqlite::memory:',
        };
        $user = $driver === 'mysql' ? (Environment::read('MYSQL_USER', 'root')) : (Environment::read('PG_USER', 'test'));
        $password = $driver === 'mysql' ? (Environment::read('MYSQL_PASSWORD', 'root')) : (Environment::read('PG_PASSWORD', 'test'));
        $this->pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->catalog = new Catalog($this->pdo, $driver);
        if ($driver === 'mysql') {
            $this->pdo->exec('CREATE DATABASE `' . $this->name . '` CHARACTER SET utf8mb4');
            $this->pdo->exec('USE `' . $this->name . '`');
        } elseif ($driver === 'pgsql') {
            $this->pdo->exec('CREATE SCHEMA "' . $this->name . '"');
            $this->pdo->exec('SET search_path TO "' . $this->name . '"');
        } else {
            $this->pdo->exec('PRAGMA foreign_keys = ON');
        }
    }

    /**
     * Roll back any transaction and clear only this owned namespace.
     * @throws \Fuzz\Shared\Oracle\Finding
     * @throws PDOException
     */
    public function reset(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        foreach (array_reverse($this->catalog->tables()) as $table) {
            $this->pdo->exec('DROP TABLE ' . $this->catalog->quote($table));
        }
    }

    /**
     * Remove the namespace allocated by this instance.
     * @throws PDOException
     */
    public function close(): void
    {
        if ($this->closed) {
            return;
        }
        $this->closed = true;
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        if ($this->driver === 'mysql') {
            $this->pdo->exec('DROP DATABASE `' . $this->name . '`');
        } elseif ($this->driver === 'pgsql') {
            $this->pdo->exec('DROP SCHEMA "' . $this->name . '" CASCADE');
        }
    }
}
