<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Applies CREATE TABLE ... AS SELECT ... operation to the virtual schema.
 * This mutation creates a new table with structure and data from SELECT.
 */
final class CreateTableAsSelectMutation implements ShadowMutation
{
    private string $tableName;

    /** @var array<int, string> */
    private array $columnNames;

    private TableDefinitionRegistry $registry;
    private bool $ifNotExists;

    /**
     * @param string $tableName The name of the new table to create.
     * @param array<int, string> $columnNames Column names extracted from SELECT.
     * @param TableDefinitionRegistry $registry The registry.
     * @param bool $ifNotExists Whether to skip if table exists.
     */
    public function __construct(
        string $tableName,
        array $columnNames,
        TableDefinitionRegistry $registry,
        bool $ifNotExists = false
    ) {
        $this->tableName = $tableName;
        $this->columnNames = $columnNames;
        $this->registry = $registry;
        $this->ifNotExists = $ifNotExists;
    }

    /**
     * {@inheritDoc}
     */
    public function apply(ShadowStore $store, array $rows): void
    {
        if ($this->registry->has($this->tableName)) {
            if ($this->ifNotExists) {
                return;
            }
            throw new \RuntimeException("Table '{$this->tableName}' already exists.");
        }

        $columns = $this->columnNames;
        if ($columns === [] && $rows !== []) {
            $columns = array_keys($rows[0]);
        }
        if ($columns === []) {
            throw new \RuntimeException("Cannot determine columns for CREATE TABLE AS SELECT.");
        }

        $columnTypes = [];
        /** @var array<string, ColumnType> $typedColumns */
        $typedColumns = [];
        foreach ($columns as $column) {
            $columnTypes[$column] = 'TEXT';
            $typedColumns[$column] = new ColumnType(ColumnTypeFamily::TEXT, 'TEXT');
        }

        $definition = new TableDefinition(
            $columns,
            $columnTypes,
            [],
            [],
            [],
            $typedColumns,
        );

        $this->registry->register($this->tableName, $definition);
        $store->set($this->tableName, $rows);
    }

    /**
     * {@inheritDoc}
     */
    public function tableName(): string
    {
        return $this->tableName;
    }
}
