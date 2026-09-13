<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Invariant;

use ZtdQuery\Shadow\ShadowStore;

/**
 * Checks that mutation results preserve valid shadow-table names.
 */
final class ShadowStoreConsistencyChecker
{
    private ShadowStore $store;

    /**
     * Binds the collaborator used by this invariant check.
     */
    public function __construct(ShadowStore $store)
    {
        $this->store = $store;
    }

    /**
     * Check that all shadow store tables maintain non-empty key structure.
     */
    public function check(string $sql): ?InvariantViolation
    {
        foreach ($this->store->getAll() as $tableName => $rows) {
            if ($tableName === '') {
                return new InvariantViolation('SHADOW_EMPTY_KEY', 'ShadowStore contains an empty table name key', $sql);
            }
        }

        return null;
    }
}
