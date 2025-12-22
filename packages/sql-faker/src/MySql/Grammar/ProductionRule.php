<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Grammar;

/**
 * Represents a production rule in a formal grammar.
 *
 * A production rule defines how a non-terminal symbol (left-hand side) can be
 * expanded into a sequence of symbols (right-hand side alternatives).
 *
 * Example: select_stmt → SELECT select_list | SELECT DISTINCT select_list
 */
final class ProductionRule
{
    /**
     * @param string $lhs Left-hand side: non-terminal symbol name (e.g., "select_stmt")
     * @param list<Production> $alternatives Right-hand side alternatives
     */
    public function __construct(
        public readonly string $lhs,
        public readonly array $alternatives,
    ) {
    }
}
