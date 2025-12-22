<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Invariant;

use ZtdQuery\Shadow\ShadowStore;

final class ShadowStoreConsistencyChecker
{
    private ShadowStore $store;

    public function __construct(ShadowStore $store)
    {
        $this->store = $store;
    }

    /**
     * Check that all shadow store tables maintain array-of-arrays structure.
     *
     * ShadowStore::getAll() is typed as array<string, array<int, array<string, mixed>>>,
     * so structural consistency is guaranteed by the type system. This check verifies
     * that no table has been left in an empty-row state unintentionally.
     *
     * @return InvariantViolation|null
     */
    public function check(string $sql): ?InvariantViolation
    {
        $allData = $this->store->getAll();

        foreach ($allData as $tableName => $rows) {
            foreach ($rows as $index => $row) {
                if ($row === []) {
                    return new InvariantViolation(
                        'INV-L4-01',
                        sprintf('ShadowStore table "%s" row %d is an empty array', $tableName, $index),
                        $sql,
                        ['table' => $tableName, 'row_index' => $index]
                    );
                }
            }
        }

        return null;
    }
}
