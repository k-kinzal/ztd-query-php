<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

use ZtdQuery\Schema\SchemaRegistry;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Applies CREATE TABLE ... LIKE ... operation to the virtual schema.
 * This mutation copies the schema from an existing table to a new table.
 */
final class CreateTableLikeMutation implements ShadowMutation
{
    /**
     * New table name to create.
     *
     * @var string
     */
    private string $tableName;

    /**
     * Source table name to copy schema from.
     *
     * @var string
     */
    private string $sourceTableName;

    /**
     * Schema registry to register the table.
     *
     * @var SchemaRegistry
     */
    private SchemaRegistry $schemaRegistry;

    /**
     * Whether to skip if table already exists (IF NOT EXISTS).
     *
     * @var bool
     */
    private bool $ifNotExists;

    /**
     * @param string $tableName The name of the new table to create.
     * @param string $sourceTableName The source table to copy schema from.
     * @param SchemaRegistry $schemaRegistry The schema registry.
     * @param bool $ifNotExists Whether to skip if table exists.
     */
    public function __construct(
        string $tableName,
        string $sourceTableName,
        SchemaRegistry $schemaRegistry,
        bool $ifNotExists = false
    ) {
        $this->tableName = $tableName;
        $this->sourceTableName = $sourceTableName;
        $this->schemaRegistry = $schemaRegistry;
        $this->ifNotExists = $ifNotExists;
    }

    /**
     * {@inheritDoc}
     */
    public function apply(ShadowStore $store, array $rows): void
    {
        // Check if target table already exists in virtual schema
        if ($this->schemaRegistry->hasVirtualTableDefinition($this->tableName)) {
            if ($this->ifNotExists) {
                return;
            }
            throw new \RuntimeException("Table '{$this->tableName}' already exists.");
        }

        // Get source table schema
        $sourceSql = $this->schemaRegistry->get($this->sourceTableName);
        if ($sourceSql === null) {
            throw new \RuntimeException("Source table '{$this->sourceTableName}' does not exist.");
        }

        // Replace source table name with new table name in CREATE TABLE SQL
        $newSql = preg_replace(
            '/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?[`"\']?' . preg_quote($this->sourceTableName, '/') . '[`"\']?/i',
            'CREATE TABLE `' . $this->tableName . '`',
            $sourceSql
        );

        if ($newSql === null) {
            throw new \RuntimeException("Failed to generate CREATE TABLE SQL for '{$this->tableName}'.");
        }

        // Register the new table
        $this->schemaRegistry->register($this->tableName, $newSql);

        // Ensure the table exists in shadow store (empty)
        $store->ensure($this->tableName);
    }

    /**
     * {@inheritDoc}
     */
    public function tableName(): string
    {
        return $this->tableName;
    }
}
