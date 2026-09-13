<?php

declare (strict_types=1);

namespace Fuzz\Robustness\Invariant;

use ZtdQuery\Shadow\ShadowStore;

/**
 * Shadow store consistency checker for PostgreSQL queries.
 */
final class ShadowStoreConsistencyChecker
{
    private ShadowStore $store;
    /**
     * Initializes the collaborators and state used by this shadow store consistency checker.
     */
    public function __construct(ShadowStore $store)
    {
        $this->store = $store;
    }
    /**
     * Check that every shadow store entry has a usable table key.
     *
     * Empty row sets are valid after CREATE, DELETE, and TRUNCATE.
     */
    public function check(string $sql): ?InvariantViolation
    {
        foreach (array_keys($this->store->getAll()) as $tableName) {
            if ($tableName === '') {
                return new InvariantViolation('SHADOW_EMPTY_KEY', 'ShadowStore contains an empty table name key', $sql);
            }
        }
        return null;
    }
}
