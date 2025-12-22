<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

use ZtdQuery\Exception\TableAlreadyExistsException;
use ZtdQuery\Schema\SchemaRegistry;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Applies CREATE TABLE operation to the virtual schema.
 * This mutation registers a new table in the SchemaRegistry.
 */
final class CreateTableMutation implements ShadowMutation
{
    /**
     * Table name to create.
     *
     * @var string
     */
    private string $tableName;

    /**
     * CREATE TABLE SQL statement.
     *
     * @var string
     */
    private string $createSql;

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
     * @param string $tableName The name of the table to create.
     * @param string $createSql The CREATE TABLE SQL statement.
     * @param SchemaRegistry $schemaRegistry The schema registry to register the table.
     * @param bool $ifNotExists Whether to skip if table exists.
     */
    public function __construct(
        string $tableName,
        string $createSql,
        SchemaRegistry $schemaRegistry,
        bool $ifNotExists = false
    ) {
        $this->tableName = $tableName;
        $this->createSql = $createSql;
        $this->schemaRegistry = $schemaRegistry;
        $this->ifNotExists = $ifNotExists;
    }

    /**
     * {@inheritDoc}
     */
    public function apply(ShadowStore $store, array $rows): void
    {
        // Check if table already exists (in virtual schema or real DB)
        $existingSchema = $this->schemaRegistry->get($this->tableName);
        if ($existingSchema !== null) {
            if ($this->ifNotExists) {
                // Skip if table exists and IF NOT EXISTS was specified
                return;
            }
            throw new TableAlreadyExistsException($this->createSql, $this->tableName);
        }

        // Register the table in the schema registry
        $this->schemaRegistry->register($this->tableName, $this->createSql);

        // Also ensure the table exists in the shadow store (empty)
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
