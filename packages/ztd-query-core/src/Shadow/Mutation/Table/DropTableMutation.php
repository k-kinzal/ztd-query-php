<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation\Table;

use ZtdQuery\Exception\SchemaNotFoundException;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\Mutation\ShadowMutation;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Applies DROP TABLE operation to the virtual schema.
 * This mutation marks a table as removed from the virtual schema and clears its rows.
 */
final class DropTableMutation implements ShadowMutation
{
    private string $tableName;
    private TableDefinitionRegistry $registry;
    private string $sourceSql;
    private bool $ifExists;

    /**
     * Binds the instance to what it will work from.
     *
     * @param string $tableName
     * @param TableDefinitionRegistry $registry
     * @param string $sourceSql
     * @param bool $ifExists
     */
    public function __construct(
        string $tableName,
        TableDefinitionRegistry $registry,
        string $sourceSql,
        bool $ifExists = false
    ) {
        $this->tableName = $tableName;
        $this->registry = $registry;
        $this->sourceSql = $sourceSql;
        $this->ifExists = $ifExists;
    }

    /**
     * {@inheritDoc}
     *
     * @throws SchemaNotFoundException When the table is not there and the statement did not allow for that
     */
    public function apply(ShadowStore $store, array $rows): void
    {
        if (!$this->registry->has($this->tableName)) {
            if ($this->ifExists) {
                return;
            }
            throw new SchemaNotFoundException($this->sourceSql, $this->tableName);
        }

        $this->registry->markRemoved($this->tableName);
        $store->remove($this->tableName);
    }

    /**
     * {@inheritDoc}
     */
    public function tableName(): string
    {
        return $this->tableName;
    }
}
