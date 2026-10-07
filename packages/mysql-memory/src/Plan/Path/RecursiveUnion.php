<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Path;

use MySqlMemory\Typing\Domain;
use Override;

/**
 * Computes a recursive common table expression: its nonrecursive part, then its recursive part over the rows of the last iteration, until an iteration adds no row.
 *
 * With UNION DISTINCT a row already produced is not added again. More iterations than
 * cte_max_recursion_depth abort the query.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/with.html#common-table-expressions-recursive.
 *
 * @visibility MySqlMemory
 */
final class RecursiveUnion implements AccessPath
{
    /**
     * @param AccessPath $anchor The nonrecursive part
     * @param AccessPath $recursive The recursive part, which reads the working table
     * @param WorkingTable $working The rows of the last iteration
     * @param bool $distinct Whether duplicate rows are removed
     * @param list<Domain> $domains The domains of the columns
     * @param int $limit The most iterations (cte_max_recursion_depth)
     */
    public function __construct(
        public readonly AccessPath $anchor,
        public readonly AccessPath $recursive,
        public readonly WorkingTable $working,
        public readonly bool $distinct,
        public readonly array $domains,
        public readonly int $limit,
    ) {
    }

    /**
     * Answers the number of columns.
     */
    #[Override]
    public function width(): int
    {
        return count($this->domains);
    }
}
