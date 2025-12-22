<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

use ZtdQuery\Exception\TableAlreadyExistsException;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Applies CREATE TABLE operation to the virtual schema.
 * This mutation registers a new table in the TableDefinitionRegistry.
 */
final class CreateTableMutation implements ShadowMutation
{
    private string $tableName;
    private ?TableDefinition $definition;
    private TableDefinitionRegistry $registry;
    private bool $ifNotExists;

    /**
     * @param string $tableName The name of the table to create.
     * @param TableDefinition|null $definition The parsed table definition.
     * @param TableDefinitionRegistry $registry The registry to register the table.
     * @param bool $ifNotExists Whether to skip if table exists.
     */
    public function __construct(
        string $tableName,
        ?TableDefinition $definition,
        TableDefinitionRegistry $registry,
        bool $ifNotExists = false
    ) {
        $this->tableName = $tableName;
        $this->definition = $definition;
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
            throw new TableAlreadyExistsException("CREATE TABLE `{$this->tableName}`", $this->tableName);
        }

        if ($this->definition !== null) {
            $this->registry->register($this->tableName, $this->definition);
        }

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
