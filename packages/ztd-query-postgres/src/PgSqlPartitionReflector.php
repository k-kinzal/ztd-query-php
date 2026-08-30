<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres;

use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\Platform\Postgres\Parse\PgSqlPartitionParser;
use ZtdQuery\Schema\Partition\TablePartitionKey;
use ZtdQuery\Schema\Partition\TablePartitionRelation;

/**
 * The pg sql partition reflector.
 */
final class PgSqlPartitionReflector
{
    /**
     * Binds the instance to what it will work from.
     *
     * @param ConnectionInterface $connection
     */
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    /**
     * @return array{
     *     keys: array<string, TablePartitionKey>,
     *     relations: array<string, TablePartitionRelation>
     * }
     */
    public function reflect(): array
    {
        return ['keys' => $this->keys(), 'relations' => $this->relations()];
    }

    /**
     * Asks the database how each of its partitioned tables is partitioned.
     *
     * @return array<string, TablePartitionKey> Table name => what it is partitioned by
     */
    public function keys(): array
    {
        $statement = $this->connection->query(
            'SELECT c.relname AS table_name, pg_get_partkeydef(c.oid) AS partition_key '
            . 'FROM pg_partitioned_table pt '
            . 'JOIN pg_class c ON c.oid = pt.partrelid '
            . 'JOIN pg_namespace n ON n.oid = c.relnamespace '
            . 'WHERE n.nspname = current_schema() ORDER BY c.relname',
        );
        if ($statement === false) {
            return [];
        }

        $parser = new PgSqlPartitionParser();
        $keys = [];
        foreach ($statement->fetchAll() as $row) {
            $tableName = $row['table_name'] ?? null;
            $partitionKey = $row['partition_key'] ?? null;
            if (!is_string($tableName) || $tableName === '' || !is_string($partitionKey)) {
                continue;
            }
            $key = $parser->parseKey("PARTITION BY {$partitionKey}");
            if ($key !== null) {
                $keys[$tableName] = $key;
            }
        }

        return $keys;
    }

    /**
     * Asks the database which of its tables is a partition of which.
     *
     * @return array<string, TablePartitionRelation> Table name => the table it partitions, and what it holds of it
     */
    public function relations(): array
    {
        $statement = $this->connection->query(
            'SELECT child.relname AS child_table, parent.relname AS parent_table, '
            . 'pg_get_partition_constraintdef(child.oid) AS predicate '
            . 'FROM pg_inherits i '
            . 'JOIN pg_class child ON child.oid = i.inhrelid '
            . 'JOIN pg_namespace child_ns ON child_ns.oid = child.relnamespace '
            . 'JOIN pg_class parent ON parent.oid = i.inhparent '
            . 'JOIN pg_partitioned_table pt ON pt.partrelid = parent.oid '
            . 'WHERE child_ns.nspname = current_schema() ORDER BY child.relname',
        );
        if ($statement === false) {
            return [];
        }

        $relations = [];
        foreach ($statement->fetchAll() as $row) {
            $childTable = $row['child_table'] ?? null;
            $parentTable = $row['parent_table'] ?? null;
            $predicate = $row['predicate'] ?? null;
            if (!is_string($childTable)
                || $childTable === ''
                || !is_string($parentTable)
                || $parentTable === ''
                || !is_string($predicate)
                || trim($predicate) === ''
            ) {
                continue;
            }
            $relations[$childTable] = new TablePartitionRelation($parentTable, $predicate);
        }

        return $relations;
    }
}
