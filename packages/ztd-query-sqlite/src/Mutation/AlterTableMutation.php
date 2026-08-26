<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Sqlite\Mutation;

use ZtdQuery\Exception\TableAlreadyExistsException;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\Mutation\ShadowMutation;
use ZtdQuery\Shadow\ShadowStore;

/**
 * The alter table mutation, as shadow mutation.
 */
final class AlterTableMutation implements ShadowMutation
{
    /**
     * Binds the instance to what it will work from.
     *
     * @param string $sql
     * @param string $sourceTable
     * @param string $targetTable
     * @param TableDefinition $definition
     * @param TableDefinitionRegistry $registry
     * @param string $resultSelect
     */
    public function __construct(
        private readonly string $sql,
        private readonly string $sourceTable,
        private readonly string $targetTable,
        private readonly TableDefinition $definition,
        private readonly TableDefinitionRegistry $registry,
        private readonly string $resultSelect,
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @throws TableAlreadyExistsException
     */
    public function apply(ShadowStore $store, array $rows): void
    {
        if ($this->sourceTable !== $this->targetTable && $this->registry->has($this->targetTable)) {
            throw new TableAlreadyExistsException($this->sql, $this->targetTable);
        }

        $this->registry->markRemoved($this->sourceTable);
        $this->registry->register($this->targetTable, $this->definition);
        if ($this->sourceTable !== $this->targetTable) {
            $store->remove($this->sourceTable);
        }
        $store->set($this->targetTable, $rows);
    }

    /**
     * Result select.
     *
     * @return string
     */
    public function resultSelect(): string
    {
        return $this->resultSelect;
    }

    /**
     * Table name.
     *
     * @return string
     */
    public function tableName(): string
    {
        return $this->sourceTable;
    }
}
