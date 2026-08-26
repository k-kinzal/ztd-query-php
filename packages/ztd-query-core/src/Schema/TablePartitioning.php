<?php

declare(strict_types=1);

namespace ZtdQuery\Schema;

use ZtdQuery\Exception\InvalidDefinitionException;

/**
 * Predicates that select rows belonging to named table partitions.
 */
final class TablePartitioning
{
    /** @var array<string, string> */
    private readonly array $selectionPredicates;

    /**
     * @param array<string, string> $selectionPredicates
     */
    public function __construct(array $selectionPredicates)
    {
        $normalized = [];
        foreach ($selectionPredicates as $name => $predicate) {
            if (trim($name) === '' || trim($predicate) === '') {
                throw new InvalidDefinitionException('Partition names and predicates must not be empty.');
            }
            $normalized[strtolower($name)] = $predicate;
        }
        $this->selectionPredicates = $normalized;
    }

    /**
     * @param non-empty-list<string> $names
     * @return list<string>|null
     */
    public function predicatesFor(array $names): ?array
    {
        $predicates = [];
        foreach ($names as $name) {
            $normalized = strtolower($name);
            if (!isset($this->selectionPredicates[$normalized])) {
                return null;
            }
            $predicates[$normalized] = $this->selectionPredicates[$normalized];
        }

        return array_values($predicates);
    }
}
