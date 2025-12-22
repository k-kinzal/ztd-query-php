<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

use ZtdQuery\Shadow\ShadowStore;

/**
 * Applies DELETE result rows to the shadow store.
 */
final class DeleteMutation implements ShadowMutation
{
    /**
     * Target table to delete from.
     *
     * @var string
     */
    private string $tableName;

    /**
     * Primary key columns used to match rows.
     *
     * @var array<int, string>
     */
    private array $primaryKeys;

    /**
     * @param array<int, string> $primaryKeys
     */
    public function __construct(
        string $tableName,
        array $primaryKeys
    ) {
        $this->tableName = $tableName;
        $this->primaryKeys = $primaryKeys;
    }

    /**
     * {@inheritDoc}
     */
    public function apply(ShadowStore $store, array $rows): void
    {
        $store->delete($this->tableName, $rows, $this->primaryKeys);
    }

    /**
     * {@inheritDoc}
     */
    public function tableName(): string
    {
        return $this->tableName;
    }
}
