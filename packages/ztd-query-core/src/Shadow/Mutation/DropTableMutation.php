<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

use ZtdQuery\Exception\SchemaNotFoundException;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Applies DROP TABLE operation to the virtual schema.
 * This mutation removes a table from the TableDefinitionRegistry and ShadowStore.
 */
final class DropTableMutation implements ShadowMutation
{
    private string $tableName;
    private TableDefinitionRegistry $registry;
    private bool $ifExists;

    public function __construct(
        string $tableName,
        TableDefinitionRegistry $registry,
        bool $ifExists = false
    ) {
        $this->tableName = $tableName;
        $this->registry = $registry;
        $this->ifExists = $ifExists;
    }

    /**
     * {@inheritDoc}
     */
    public function apply(ShadowStore $store, array $rows): void
    {
        if (!$this->registry->has($this->tableName)) {
            if ($this->ifExists) {
                return;
            }
            throw new SchemaNotFoundException("DROP TABLE `{$this->tableName}`", $this->tableName);
        }

        $this->registry->unregister($this->tableName);
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
