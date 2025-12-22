<?php

declare(strict_types=1);

namespace SqlFaker\Grammar;

/**
 * Represents a single production (right-hand side) in a grammar rule.
 *
 * A production is a sequence of symbols that a non-terminal can expand to.
 */
final class Production
{
    /**
     * @param list<Symbol> $symbols Sequence of terminal and non-terminal symbols
     */
    public function __construct(
        public readonly array $symbols,
    ) {
    }
}
