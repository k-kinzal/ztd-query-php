<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

use ZtdQuery\Shadow\ShadowStore;

/**
 * Applies TRUNCATE operation to the shadow store by clearing all rows.
 */
final class TruncateMutation implements ShadowMutation
{
    /**
     * Target table to truncate.
     *
     * @var string
     */
    private string $tableName;

    public function __construct(string $tableName)
    {
        $this->tableName = $tableName;
    }

    /**
     * {@inheritDoc}
     */
    public function apply(ShadowStore $store, array $rows): void
    {
        // TRUNCATE clears all rows from the table
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
