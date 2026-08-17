<?php

declare(strict_types=1);

namespace ZtdQuery\Schema;

final class TablePartitionRelation
{
    public readonly string $parentTable;
    public readonly ?string $predicate;

    public function __construct(string $parentTable, ?string $predicate)
    {
        $parentTable = trim($parentTable);
        if ($parentTable === '') {
            throw new \InvalidArgumentException('Partition parent table must not be empty.');
        }
        if ($predicate !== null && trim($predicate) === '') {
            throw new \InvalidArgumentException('Partition predicate must not be empty.');
        }

        $this->parentTable = $parentTable;
        $this->predicate = $predicate;
    }

    /** @param list<string> $siblingPredicates */
    public function selectionPredicate(array $siblingPredicates): string
    {
        if ($this->predicate !== null) {
            return $this->predicate;
        }
        if ($siblingPredicates === []) {
            return 'TRUE';
        }

        $predicates = array_map(
            static fn (string $predicate): string => "($predicate)",
            $siblingPredicates,
        );

        return 'COALESCE(NOT (' . implode(' OR ', $predicates) . '), TRUE)';
    }
}
