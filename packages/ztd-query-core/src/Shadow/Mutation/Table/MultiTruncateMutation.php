<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation\Table;

use ZtdQuery\Shadow\Mutation\DataMutation;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Applies a single TRUNCATE statement to every target shadow table.
 */
final class MultiTruncateMutation implements DataMutation
{
    /**
     * @param list<string> $tableNames
     */
    public function __construct(
        private readonly array $tableNames,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function apply(ShadowStore $store, array $rows): void
    {
        foreach ($this->tableNames as $tableName) {
            $store->set($tableName, []);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function tableName(): string
    {
        return $this->tableNames[0] ?? '';
    }

    /**
     * @return list<string>
     */
    public function tableNames(): array
    {
        return $this->tableNames;
    }
}
