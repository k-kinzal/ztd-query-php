<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Applies CREATE TABLE ... LIKE ... operation to the virtual schema.
 * This mutation copies the TableDefinition from an existing table to a new table.
 */
final class CreateTableLikeMutation implements ShadowMutation
{
    private string $tableName;
    private string $sourceTableName;
    private TableDefinitionRegistry $registry;
    private bool $ifNotExists;

    public function __construct(
        string $tableName,
        string $sourceTableName,
        TableDefinitionRegistry $registry,
        bool $ifNotExists = false
    ) {
        $this->tableName = $tableName;
        $this->sourceTableName = $sourceTableName;
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

        $sourceDefinition = $this->registry->get($this->sourceTableName);
        if ($sourceDefinition === null) {
            throw new \RuntimeException("Source table '{$this->sourceTableName}' does not exist.");
        }

        $this->registry->register($this->tableName, $sourceDefinition);
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
