<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

use ZtdQuery\Exception\SchemaNotFoundException;
use ZtdQuery\Schema\SchemaRegistry;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Applies DROP TABLE operation to the virtual schema.
 * This mutation removes a table from the SchemaRegistry and ShadowStore.
 */
final class DropTableMutation implements ShadowMutation
{
    /**
     * Table name to drop.
     *
     * @var string
     */
    private string $tableName;

    /**
     * Schema registry to unregister the table.
     *
     * @var SchemaRegistry
     */
    private SchemaRegistry $schemaRegistry;

    /**
     * Whether to skip if table doesn't exist (IF EXISTS).
     *
     * @var bool
     */
    private bool $ifExists;

    /**
     * @param string $tableName The name of the table to drop.
     * @param SchemaRegistry $schemaRegistry The schema registry to unregister the table.
     * @param bool $ifExists Whether to skip if table doesn't exist.
     */
    public function __construct(
        string $tableName,
        SchemaRegistry $schemaRegistry,
        bool $ifExists = false
    ) {
        $this->tableName = $tableName;
        $this->schemaRegistry = $schemaRegistry;
        $this->ifExists = $ifExists;
    }

    /**
     * {@inheritDoc}
     */
    public function apply(ShadowStore $store, array $rows): void
    {
        // Check if table exists
        $existingColumns = $this->schemaRegistry->getColumns($this->tableName);
        if ($existingColumns === null) {
            if ($this->ifExists) {
                // Skip if table doesn't exist and IF EXISTS was specified
                return;
            }
            throw new SchemaNotFoundException("DROP TABLE `{$this->tableName}`", $this->tableName);
        }

        // Unregister the table from the schema registry
        $this->schemaRegistry->unregister($this->tableName);

        // Clear the table data from the shadow store
        $store->set($this->tableName, []);
    }

    /**
     * {@inheritDoc}
     */
    public function tableName(): string
    {
        return $this->tableName;
    }
}
