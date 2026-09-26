<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Model;

use SqlParser\Parser\Node;

/**
 * A bound SELECT statement: sources, row conditions, ordered outputs, and result modifiers.
 *
 * @example Accept this semantic value in a database-independent consumer
 *     $consume = static fn (\SqlSemantics\Core\Model\BoundSelect $value): string => $value::class;
 *     $consume instanceof \Closure // => true
 *
 * @visibility public
 */
final class BoundSelect
{
    /**
     * @param string $scopeId Stable scope identity within this bound statement
     * @param TableUse|Join|null $from Logical input, absent for a constant SELECT
     * @param list<TableUse> $relations Table occurrences in binding order
     * @param list<OutputColumn> $outputs Ordered result columns
     * @param Expression|null $where Predicate applied after joins; only TRUE retains a row
     * @param bool $distinct Whether duplicate output tuples are removed
     * @param list<Ordering> $orderBy Ordered sort keys
     * @param Expression|null $limit Maximum row count expression
     * @param Expression|null $offset Number of rows to skip
     * @param Node $source Original SELECT tree
     */
    public function __construct(
        public readonly string $scopeId,
        public readonly TableUse|Join|null $from,
        public readonly array $relations,
        public readonly array $outputs,
        public readonly ?Expression $where,
        public readonly bool $distinct,
        public readonly array $orderBy,
        public readonly ?Expression $limit,
        public readonly ?Expression $offset,
        public readonly Node $source,
    ) {
    }
}
