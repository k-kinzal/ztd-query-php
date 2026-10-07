<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Path;

use MySqlMemory\Typing\Domain;
use Override;

/**
 * Combines the rows of two queries: UNION, INTERSECT or EXCEPT, with or without duplicates.
 *
 * @visibility MySqlMemory
 */
final class SetOperation implements AccessPath
{
    /**
     * @param SetKind $kind The operation
     * @param bool $distinct Whether duplicate rows are removed
     * @param AccessPath $left The left query
     * @param AccessPath $right The right query
     * @param list<Domain> $domains The domains of the combined columns, to compare rows by
     */
    public function __construct(
        public readonly SetKind $kind,
        public readonly bool $distinct,
        public readonly AccessPath $left,
        public readonly AccessPath $right,
        public readonly array $domains,
    ) {
    }

    /**
     * Answers the number of combined columns.
     */
    #[Override]
    public function width(): int
    {
        return count($this->domains);
    }
}
