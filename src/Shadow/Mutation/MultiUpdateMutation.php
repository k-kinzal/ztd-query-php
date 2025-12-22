<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

use ZtdQuery\Shadow\ShadowStore;

/**
 * Applies multi-table UPDATE operation to the shadow store.
 * This mutation handles UPDATE statements that target multiple tables.
 */
final class MultiUpdateMutation implements ShadowMutation
{
    /**
     * Target tables with their primary keys.
     *
     * @var array<string, array<int, string>>
     */
    private array $tables;

    /**
     * Primary table name (first target table).
     *
     * @var string
     */
    private string $primaryTable;

    /**
     * @param array<string, array<int, string>> $tables Map of table names to their primary keys.
     */
    public function __construct(array $tables)
    {
        $this->tables = $tables;
        $tableNames = array_keys($tables);
        $this->primaryTable = $tableNames[0] ?? '';
    }

    /**
     * {@inheritDoc}
     */
    public function apply(ShadowStore $store, array $rows): void
    {
        // For multi-table UPDATE, the rows contain updated data from all tables
        // We update matching rows in each target table
        foreach ($this->tables as $tableName => $primaryKeys) {
            $store->update($tableName, $rows, $primaryKeys);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function tableName(): string
    {
        return $this->primaryTable;
    }

    /**
     * Get all target table names.
     *
     * @return array<int, string>
     */
    public function tableNames(): array
    {
        return array_keys($this->tables);
    }
}
