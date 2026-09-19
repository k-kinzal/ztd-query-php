<?php

declare(strict_types=1);

namespace Fuzz\Correctness;

use PDO;
use RuntimeException;

/**
 * Owns a disposable backing catalog that is independent of the native oracle.
 */
final class PhysicalDatabase
{
    private PDO $connection;
    private readonly string $namespace;
    private readonly string $driver;

    /**
     * Create only an owned database/schema, or an independent SQLite memory database.
     *
     * @throws RuntimeException For an unsupported driver.
     */
    public function __construct(private readonly PDO $owner, string $dsn, string $user = '', string $password = '')
    {
        $this->namespace = 'ztd_fuzz_' . bin2hex(random_bytes(8));
        $driver = $owner->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (!is_string($driver) || !in_array($driver, ['mysql', 'pgsql', 'sqlite'], true)) {
            throw new RuntimeException('Unsupported fuzz database driver.');
        }
        $this->driver = $driver;
        if ($driver === 'mysql') {
            $owner->exec('CREATE DATABASE `' . $this->namespace . '`');
            $dsn = preg_replace('/dbname=[^;]*/', 'dbname=' . $this->namespace, $dsn) ?? $dsn;
        }
        if ($driver === 'pgsql') {
            $owner->exec('CREATE SCHEMA "' . $this->namespace . '"');
        }
        $this->connection = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        if ($driver === 'pgsql') {
            $this->connection->exec('SET search_path TO "' . $this->namespace . '"');
        }
    }

    /**
     * Expose the native backing connection for independent isolation checks.
     */
    public function connection(): PDO
    {
        return $this->connection;
    }

    /**
     * Capture every table, its columns and rows, including unexpected new tables.
     *
     * @throws RuntimeException When the backing catalog cannot be inspected.
     */
    public function snapshot(): string
    {
        $sql = match ($this->driver) {
            'mysql' => 'SHOW TABLES',
            'pgsql' => 'SELECT tablename FROM pg_tables WHERE schemaname = current_schema() ORDER BY tablename',
            'sqlite' => "SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name",
        };
        $statement = $this->connection->query($sql);
        if ($statement === false) {
            throw new RuntimeException('Could not inspect physical catalog.');
        }
        $tables = [];
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $table) {
            if (!is_string($table)) {
                throw new RuntimeException('Invalid physical table name.');
            }
            $tables[$table] = PhysicalTableSnapshot::capture($this->connection, $table);
        }
        ksort($tables);

        return serialize($tables);
    }

    /**
     * Release exactly the namespace created by this instance.
     */
    public function close(): void
    {
        if ($this->driver === 'mysql') {
            $this->owner->exec('DROP DATABASE `' . $this->namespace . '`');
        }
        if ($this->driver === 'pgsql') {
            $this->owner->exec('DROP SCHEMA "' . $this->namespace . '" CASCADE');
        }
    }
}
