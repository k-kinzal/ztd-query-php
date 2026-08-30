<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation\Table;

use RuntimeException;
use ZtdQuery\Connection\ResultColumn;
use ZtdQuery\Connection\ResultSet;
use ZtdQuery\Schema\ColumnDeclaration;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\Mutation\Row\ResultSetMutation;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Applies CREATE TABLE ... AS SELECT ... operation to the virtual schema.
 * This mutation creates a new table with structure and data from SELECT.
 */
final class CreateTableAsSelectMutation implements ResultSetMutation
{
    private string $tableName;

    /** @var list<string> */
    private array $columnNames;

    private TableDefinitionRegistry $registry;
    private ColumnDeclaration $fallbackColumnType;
    private bool $ifNotExists;

    /**
     * @param string $tableName The name of the new table to create.
     * @param list<string> $columnNames Column names extracted from SELECT.
     * @param TableDefinitionRegistry $registry The registry.
     * @param ColumnDeclaration $fallbackColumnType The dialect-provided type used when result metadata is unavailable.
     * @param bool $ifNotExists Whether to skip if table exists.
     */
    public function __construct(
        string $tableName,
        array $columnNames,
        TableDefinitionRegistry $registry,
        ColumnDeclaration $fallbackColumnType,
        bool $ifNotExists = false
    ) {
        $this->tableName = $tableName;
        $this->columnNames = $columnNames;
        $this->registry = $registry;
        $this->fallbackColumnType = $fallbackColumnType;
        $this->ifNotExists = $ifNotExists;
    }

    /**
     * {@inheritDoc}
     */
    public function apply(ShadowStore $store, array $rows): void
    {
        $this->applyResultSet($store, new ResultSet($rows, []));
    }

    /**
     * @throws RuntimeException When the result set does not describe a table that could be created
     */
    public function applyResultSet(ShadowStore $store, ResultSet $result): void
    {
        if ($this->registry->has($this->tableName)) {
            if ($this->ifNotExists) {
                return;
            }
            throw new RuntimeException("Table '{$this->tableName}' already exists.");
        }

        $resultColumns = $result->columns;
        $columns = $this->columnNames;
        if ($columns === []) {
            $columns = array_map(static fn (ResultColumn $column): string => $column->name, $resultColumns);
        }
        if ($columns === [] && $result->rows !== []) {
            $columns = array_keys($result->rows[0]);
        }
        if ($columns === []) {
            throw new RuntimeException("Cannot determine result columns for virtual table '{$this->tableName}'.");
        }

        $columnTypes = [];
        /** @var array<string, ColumnDeclaration> $typedColumns */
        $typedColumns = [];
        foreach ($columns as $index => $column) {
            $type = $resultColumns[$index]->type ?? $this->fallbackColumnType;
            $columnTypes[$column] = $type->nativeType;
            $typedColumns[$column] = $type;
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
        $store->set($this->tableName, $result->rows);
    }

    /**
     * {@inheritDoc}
     */
    public function tableName(): string
    {
        return $this->tableName;
    }
}
