<?php

declare(strict_types=1);

namespace Fuzz\Shared\Database;

use Fuzz\Shared\Oracle\Finding;
use PDO;
use PDOException;

/**
 * Reads the independent database catalog, including tables a faulty rewrite might create.
 */
final class Catalog
{
    /**
     * Read the catalog of one owned database or schema.
     * @param 'mysql'|'pgsql'|'sqlite' $driver
     */
    public function __construct(private readonly PDO $pdo, private readonly string $driver)
    {
    }

    /**
     * @return list<string>
      * @throws Finding
     * @throws PDOException
     */
    public function tables(): array
    {
        $sql = match ($this->driver) {
            'mysql' => 'SHOW TABLES',
            'pgsql' => 'SELECT tablename FROM pg_tables WHERE schemaname = current_schema() ORDER BY tablename',
            'sqlite' => "SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name",
        };
        $tables = [];
        foreach ($this->query($sql) as $row) {
            $name = array_values($row)[0];
            if (!is_string($name)) {
                throw new Finding('Invalid table name in native catalog.');
            }
            $tables[] = $name;
        }
        sort($tables);
        return $tables;
    }

    /**
     * Quote a catalog identifier for the native engine.
     */
    public function quote(string $identifier): string
    {
        $quote = $this->driver === 'mysql' ? '`' : '"';
        return $quote . str_replace($quote, $quote . $quote, $identifier) . $quote;
    }

    /**
     * @return list<array<string, mixed>>
      * @throws Finding
     * @throws PDOException
     */
    public function query(string $sql): array
    {
        return array_values((new Connection($this->pdo))->query($sql)->fetchAll());
    }

    /**
     * Includes all rows, table definitions, indexes and constraints in the owned catalog.
      * @throws Finding
     * @throws PDOException
     */
    public function snapshot(): string
    {
        $state = [];
        foreach ($this->tables() as $table) {
            $quoted = $this->quote($table);
            $state[$table] = $this->query('SELECT * FROM ' . $quoted . ' ORDER BY id');
            $state[$table . ':definition'] = match ($this->driver) {
                'mysql' => $this->query('SHOW CREATE TABLE ' . $quoted),
                'pgsql' => $this->query('SELECT column_name, data_type, udt_name, is_nullable, column_default, character_maximum_length FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ' . $this->pdo->quote($table) . ' ORDER BY ordinal_position'),
                'sqlite' => $this->query('SELECT type, name, sql FROM sqlite_master WHERE tbl_name = ' . $this->pdo->quote($table) . ' ORDER BY type, name'),
            };
        }
        if ($this->driver === 'pgsql') {
            $state[':indexes'] = $this->query('SELECT tablename, indexname, indexdef FROM pg_indexes WHERE schemaname = current_schema() ORDER BY tablename, indexname');
            $state[':constraints'] = $this->query('SELECT conname, pg_get_constraintdef(oid) AS definition FROM pg_constraint WHERE connamespace = current_schema()::regnamespace ORDER BY conname');
        }
        return serialize($state);
    }
}
