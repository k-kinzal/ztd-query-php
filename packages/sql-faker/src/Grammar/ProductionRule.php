<?php

declare(strict_types=1);

namespace SqlFaker\Grammar;

/**
 * Represents a production rule in a formal grammar.
 *
 * A production rule defines how a non-terminal symbol (left-hand side) can be
 * expanded into a sequence of symbols (right-hand side alternatives).
 */
final class ProductionRule
{
    /**
     * @param string $lhs Left-hand side: non-terminal symbol name
     * @param list<Production> $alternatives Right-hand side alternatives
     */
    public function __construct(
        public readonly string $lhs,
        public readonly array $alternatives,
    ) {
    }
}
