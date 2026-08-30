<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres;

use ZtdQuery\Platform\Postgres\Parse\PgSqlSchemaParser;
use ZtdQuery\Schema\Key\PartialUniqueIndex;
use ZtdQuery\Schema\Partition\TablePartitionKey;
use ZtdQuery\Schema\Partition\TablePartitionRelation;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Schema\ViewDefinitionSet;

/**
 * What a database declares, as ZTD reads it.
 *
 * A table is read out of the statement that would create it, which says most
 * of what the shadow needs. What it does not say -- a unique index that only
 * covers some rows, and how a table is partitioned -- PostgreSQL keeps in its
 * catalogue instead, so those are written onto the definition afterwards.
 *
 * Nothing here asks the database anything: it is handed what was read, which
 * is what lets it be about the reading and not about the connection.
 */
final class PgSqlReflectedSchema
{
    /**
     * @param PgSqlSchemaParser $schemaParser Reads a declaration into a definition
     */
    public function __construct(private readonly PgSqlSchemaParser $schemaParser = new PgSqlSchemaParser())
    {
    }

    /**
     * Answers every table the database declares.
     *
     * @param array<string, string> $declarations Table name => the statement that would create it
     * @param array<string, array<string, PartialUniqueIndex>> $partialUniqueIndexes Table name => its partial unique indexes
     * @param array{keys: array<string, TablePartitionKey>, relations: array<string, TablePartitionRelation>} $partitioning How the tables are partitioned
     *
     * @return TableDefinitionRegistry The tables, as ZTD reads them
     */
    public function tables(array $declarations, array $partialUniqueIndexes, array $partitioning): TableDefinitionRegistry
    {
        $registry = new TableDefinitionRegistry();
        foreach ($declarations as $tableName => $declaration) {
            $definition = $this->schemaParser->parse($declaration);
            if ($definition !== null) {
                $registry->register($tableName, $definition);
            }
        }
        $this->addPartialUniqueIndexes($registry, $partialUniqueIndexes);
        $this->addPartitioning($registry, $partitioning);

        return $registry;
    }

    /**
     * Writes onto each table the unique indexes that only cover some of its rows.
     *
     * @param TableDefinitionRegistry $registry The tables, as ZTD reads them
     * @param array<string, array<string, PartialUniqueIndex>> $partialUniqueIndexes Table name => its partial unique indexes
     */
    public function addPartialUniqueIndexes(TableDefinitionRegistry $registry, array $partialUniqueIndexes): void
    {
        foreach ($partialUniqueIndexes as $tableName => $indexes) {
            $definition = $registry->get($tableName);
            if ($definition === null) {
                continue;
            }
            foreach ($indexes as $index) {
                $definition = $definition->withPartialUniqueIndex($index);
            }
            $registry->register($tableName, $definition);
        }
    }

    /**
     * Writes onto each table how it is partitioned, and what it partitions.
     *
     * @param TableDefinitionRegistry $registry The tables, as ZTD reads them
     * @param array{keys: array<string, TablePartitionKey>, relations: array<string, TablePartitionRelation>} $partitioning How the tables are partitioned
     */
    public function addPartitioning(TableDefinitionRegistry $registry, array $partitioning): void
    {
        foreach ($partitioning['keys'] as $tableName => $partitionKey) {
            $definition = $registry->get($tableName);
            if ($definition !== null) {
                $registry->register($tableName, $definition->withPartitionKey($partitionKey));
            }
        }
        foreach ($partitioning['relations'] as $tableName => $partitionRelation) {
            $definition = $registry->get($tableName);
            if ($definition !== null) {
                $registry->register($tableName, $definition->withPartitionRelation($partitionRelation));
            }
        }
    }

    /**
     * Answers every view the database declares.
     *
     * @param array<string, \ZtdQuery\Schema\ViewDefinition> $definitions View name => what defines it
     *
     * @return ViewDefinitionSet The views, as ZTD reads them
     */
    public function views(array $definitions): ViewDefinitionSet
    {
        $views = new ViewDefinitionSet();
        foreach ($definitions as $viewName => $definition) {
            $views->register($viewName, $definition);
        }

        return $views;
    }
}
