<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Grammar;

/**
 * Represents a non-terminal symbol in a formal grammar.
 *
 * Non-terminal symbols can be expanded via production rules.
 * They correspond to grammar rules and are replaced during derivation.
 *
 * Examples: select_stmt, expr, table_ref
 */
final class NonTerminal implements Symbol
{
    public function __construct(
        public readonly string $value,
    ) {
    }

    public function value(): string
    {
        return $this->value;
    }
}
