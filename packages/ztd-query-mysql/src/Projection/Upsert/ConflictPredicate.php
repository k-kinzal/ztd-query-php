<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Projection\Upsert;

use ZtdQuery\Platform\MySql\MySqlIdentifierQuoter;

/**
 * Conflict Predicate.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class ConflictPredicate
{
    /**
     * Configure the dependencies used by this operation.
     */
    public function __construct(private readonly MySqlIdentifierQuoter $quoter)
    {
    }
    /**
     * @param array<string, array<int, string>> $candidateKeys
     */
    public function conflictPredicate(array $candidateKeys, string $existingAlias, string $incomingAlias): string
    {
        $keys = [];
        foreach ($candidateKeys as $columns) {
            if ($columns === []) {
                continue;
            }
            $comparisons = [];
            foreach ($columns as $column) {
                $quoted = $this->quoter->quote($column);
                $comparisons[] = "$existingAlias.$quoted = $incomingAlias.$quoted";
            }
            $keys[] = '(' . implode(' AND ', $comparisons) . ')';
        }

        return $keys === [] ? 'FALSE' : '(' . implode(' OR ', $keys) . ')';
    }
}
